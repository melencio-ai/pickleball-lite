<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Venue;
use App\Services\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MySqlBookingConcurrencyTest extends TestCase
{
    public function test_two_simultaneous_attempts_confirm_exactly_one_booking(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requires a disposable MySQL database and the PHP pcntl extension.');
        }

        $this->artisan('migrate:fresh', ['--seed' => true])->assertSuccessful();
        try {
            $venue = Venue::firstOrFail();
            $data = [
                'court_id' => $venue->courts()->firstOrFail()->id,
                'date' => now($venue->timezone)->addDay()->toDateString(),
                'start' => '10:00',
                'duration' => 60,
                'customer_name' => 'Concurrent Player',
                'customer_phone' => '09171234567',
            ];

            // Each child opens a fresh connection and waits for the same start signal.
            DB::disconnect();
            $workers = [];
            for ($attempt = 0; $attempt < 2; $attempt++) {
                [$parent, $child] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
                $pid = pcntl_fork();
                if ($pid === 0) {
                    fclose($parent);
                    stream_set_timeout($child, 10);
                    if (fread($child, 1) !== 'G') {
                        exit(2);
                    }

                    try {
                        app(Schedule::class)->reserve($venue, $data, 'staff');
                        exit(0);
                    } catch (ValidationException $exception) {
                        exit(isset($exception->errors()['start']) ? 1 : 2);
                    }
                }
                fclose($child);
                $this->assertGreaterThan(0, $pid);
                $workers[] = [$pid, $parent];
            }

            foreach ($workers as [$pid, $socket]) {
                fwrite($socket, 'G');
                fclose($socket);
            }

            $results = [];
            foreach ($workers as [$pid]) {
                $this->assertSame($pid, pcntl_waitpid($pid, $status));
                $this->assertTrue(pcntl_wifexited($status));
                $results[] = pcntl_wexitstatus($status);
            }

            $this->assertEqualsCanonicalizing([0, 1], $results);
            $this->assertSame(1, Booking::count());
        } finally {
            $this->artisan('migrate:rollback')->assertSuccessful();
            RefreshDatabaseState::$migrated = false;
        }
    }
}
