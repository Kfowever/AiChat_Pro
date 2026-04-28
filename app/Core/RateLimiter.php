<?php

namespace App\Core;

class RateLimiter
{
    private $storageDir;

    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?: dirname(__DIR__, 2) . '/storage/rate_limits';
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }

    public function hit(string $key, int $maxAttempts, int $windowSeconds): array
    {
        $maxAttempts = max(1, $maxAttempts);
        $windowSeconds = max(1, $windowSeconds);
        $now = time();
        $file = $this->storageDir . '/' . hash('sha256', $key) . '.json';
        $handle = @fopen($file, 'c+');

        if (!$handle) {
            return ['allowed' => true, 'remaining' => $maxAttempts - 1, 'retry_after' => 0];
        }

        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $timestamps = $raw ? json_decode($raw, true) : [];
            if (!is_array($timestamps)) {
                $timestamps = [];
            }

            $cutoff = $now - $windowSeconds;
            $timestamps = array_values(array_filter($timestamps, function ($ts) use ($cutoff) {
                return is_int($ts) && $ts > $cutoff;
            }));

            if (count($timestamps) >= $maxAttempts) {
                $oldest = min($timestamps);
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'retry_after' => max(1, ($oldest + $windowSeconds) - $now),
                ];
            }

            $timestamps[] = $now;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($timestamps));

            return [
                'allowed' => true,
                'remaining' => max(0, $maxAttempts - count($timestamps)),
                'retry_after' => 0,
            ];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
