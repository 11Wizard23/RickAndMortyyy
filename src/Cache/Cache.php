<?php
declare(strict_types=1);

namespace RickAndMorty\Cache;

interface Cache
{
    /** Null when absent or expired. */
    public function get(string $key): ?string;

    public function set(string $key, string $value): void;
}
