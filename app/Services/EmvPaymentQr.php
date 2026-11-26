<?php

namespace App\Services;

use InvalidArgumentException;

class EmvPaymentQr
{
    public function generate(string $base, int $centavos): string
    {
        $fields = $this->parse(trim($base));
        $amount = sprintf('%d.%02d', intdiv($centavos, 100), $centavos % 100);
        if ($centavos <= 0 || strlen($amount) > 13) {
            throw new InvalidArgumentException('The payment amount must be a positive PHP amount.');
        }

        $output = '';
        foreach ($fields as $id => $value) {
            if ($id === 63) {
                continue;
            }
            $output .= $this->field((string) $id, $id === 54 ? $amount : $value);
            if ($id === 53 && ! isset($fields[54])) {
                $output .= $this->field('54', $amount);
            }
        }

        if (strlen($output) + 8 > 512) {
            throw new InvalidArgumentException('This payload is too long for a booking payment QR.');
        }

        $output .= '6304';

        return $output.$this->crc($output);
    }

    private function parse(string $payload): array
    {
        if (strlen($payload) > 512 || ! mb_check_encoding($payload, 'UTF-8')
            || preg_match('/[\x00-\x1f\x7f]/', $payload)
            || ! str_starts_with($payload, '000201')
            || ! preg_match('/6304([0-9a-fA-F]{4})$/D', $payload, $match)
            || ! hash_equals($this->crc(substr($payload, 0, -4)), strtoupper($match[1]))) {
            throw new InvalidArgumentException('Enter a valid base EMV payload with a matching checksum.');
        }

        $fields = [];
        $position = 0;
        $size = mb_strlen($payload, 'UTF-8');
        while ($position < $size) {
            $header = mb_substr($payload, $position, 4, 'UTF-8');
            if (! preg_match('/^[0-9]{4}$/D', $header)) {
                throw new InvalidArgumentException('The EMV payload contains an invalid field.');
            }

            $id = substr($header, 0, 2);
            $length = (int) substr($header, 2);
            $position += 4;
            if ($length === 0 || $position + $length > $size || isset($fields[$id])) {
                throw new InvalidArgumentException('The EMV payload contains an invalid field length or duplicate ID.');
            }
            $fields[$id] = mb_substr($payload, $position, $length, 'UTF-8');
            $position += $length;
        }

        $hasAccount = array_filter(array_keys($fields), fn ($id) => (int) $id >= 2 && (int) $id <= 51);
        if (! $hasAccount || count($fields) < 8 || array_key_last($fields) !== 63
            || ($fields[63] ?? null) !== $match[1] || ($fields[53] ?? null) !== '608'
            || ($fields[58] ?? null) !== 'PH' || ! preg_match('/^[0-9]{4}$/D', $fields[52] ?? '')
            || ! isset($fields[59], $fields[60])) {
            throw new InvalidArgumentException('Use a Philippine peso merchant QR.');
        }
        if (! in_array($fields['01'] ?? '11', ['11', '12'], true)) {
            throw new InvalidArgumentException('The EMV payload has an invalid point-of-initiation marker.');
        }
        if (isset($fields[55]) || isset($fields[56]) || isset($fields[57])) {
            throw new InvalidArgumentException('Use a merchant QR without a tip prompt or convenience fee.');
        }
        if (isset($fields[54]) && (strlen($fields[54]) > 13
            || ! preg_match('/^[0-9]+(?:\.[0-9]{0,2})?$/D', $fields[54])
            || strpbrk($fields[54], '123456789') === false)) {
            throw new InvalidArgumentException('The base QR contains an invalid payment amount.');
        }

        return $fields;
    }

    private function field(string $id, string $value): string
    {
        return $id.sprintf('%02d', mb_strlen($value, 'UTF-8')).$value;
    }

    private function crc(string $data): string
    {
        $crc = 0xFFFF;
        foreach (str_split($data) as $byte) {
            $crc ^= ord($byte) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = (($crc << 1) ^ (($crc & 0x8000) ? 0x1021 : 0)) & 0xFFFF;
            }
        }

        return sprintf('%04X', $crc);
    }
}
