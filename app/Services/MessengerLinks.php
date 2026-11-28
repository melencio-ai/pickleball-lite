<?php
namespace App\Services;

final class MessengerLinks
{
    public const PLATFORM = 'https://www.facebook.com/messages/t/pickleliteapp';

    // Rewrite only unambiguous Messenger recipient URLs; preserve other saved
    // contact links and never derive a destination from a Facebook profile URL.
    public static function variants(?string $url): array
    {
        if (!is_string($url) || !preg_match('~^https://(?:m\\.me/|(?:www\\.)?facebook\\.com/messages/t/|(?:www\\.)?messenger\\.com/t/)([a-zA-Z0-9._-]+)/?$~iD', $url, $m)) {
            return ['desktop'=>$url,'mobile'=>$url];
        }
        return ['desktop'=>'https://www.facebook.com/messages/t/'.$m[1],'mobile'=>'https://m.me/'.$m[1]];
    }

    public static function mobileRequest(): bool
    {
        return request()->header('Sec-CH-UA-Mobile') === '?1' ||
            preg_match('/Android|iPhone|iPad|iPod|Mobile|Windows Phone/i', (string) request()->userAgent()) === 1;
    }
}
