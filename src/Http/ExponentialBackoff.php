<?php
declare(strict_types=1);

namespace RickAndMorty\Http;

/**
 * How long to wait before retrying attempt #$attempt (0-based): 1s, 2s, 4s, …
 * A server-sent Retry-After wins when present.
 *
 * Both paths are capped: a hostile or broken Retry-After must not be able to
 * park a PHP worker for minutes.
 */
final class ExponentialBackoff
{
    public function __construct(private readonly int $maxSeconds = 8)
    {
    }

    public function delayFor(int $attempt, ?int $retryAfter): int
    {
        if ($retryAfter !== null && $retryAfter > 0) {
            return min($retryAfter, $this->maxSeconds);
        }

        return (int) min(2 ** max(0, $attempt), $this->maxSeconds);
    }
}
