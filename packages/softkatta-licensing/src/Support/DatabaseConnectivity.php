<?php

namespace SoftKatta\Licensing\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Shared-hosting MySQL often flakes briefly (max connections / cold start).
 * Retry before treating the outage as a hard DATABASE_UNAVAILABLE gate.
 */
final class DatabaseConnectivity
{
    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public static function retry(callable $callback, int $attempts = 6, int $sleepMs = 250): mixed
    {
        $attempts = max(1, $attempts);
        $last = null;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                if ($i > 0) {
                    self::reconnect();
                }

                return $callback();
            } catch (Throwable $e) {
                $last = $e;
                if ($i < $attempts - 1) {
                    // Back off so Hostinger MySQL has time to accept the next connection.
                    usleep(($sleepMs + ($i * 120)) * 1000);
                }
            }
        }

        throw $last ?? new \RuntimeException('Database unavailable.');
    }

    public static function reconnect(): void
    {
        try {
            DB::purge();
            DB::reconnect();
        } catch (Throwable) {
            // ignore — next query will surface the real error
        }
    }
}
