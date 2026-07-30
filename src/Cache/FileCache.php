<?php
declare(strict_types=1);

namespace RickAndMorty\Cache;

/** Flat-file cache with a fixed TTL. Enough for read-only upstream responses. */
final class FileCache implements Cache
{
    public function __construct(
        private readonly string $directory,
        private readonly int $ttl = 300,
        private readonly string $prefix = 'rm-cache-',
    ) {
    }

    public function get(string $key): ?string
    {
        $path = $this->path($key);
        if (!is_file($path) || time() - (int) filemtime($path) >= $this->ttl) {
            return null;
        }

        $body = @file_get_contents($path);

        return $body === false ? null : $body;
    }

    public function set(string $key, string $value): void
    {
        // Write-then-rename so a concurrent reader never sees a half-written file.
        $path = $this->path($key);
        $temp = $path . '.' . getmypid() . '.tmp';

        if (@file_put_contents($temp, $value) !== false) {
            @rename($temp, $path);
        }
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, '/') . '/' . $this->prefix . md5($key) . '.cache';
    }
}
