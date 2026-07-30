<?php
declare(strict_types=1);

namespace RickAndMorty\Http;

/** An immutable HTTP result. Only the bits this application reasons about. */
final class HttpResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        /** Seconds requested by a Retry-After header, when the server sent one. */
        public readonly ?int $retryAfter = null,
    ) {
    }

    public function isOk(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /** The API answers 404 for "no results" as well as for unknown ids. */
    public function isNotFound(): bool
    {
        return $this->status === 404;
    }

    /** Rate limiting or a server-side fault — worth trying again. */
    public function isTransient(): bool
    {
        return $this->status === 429 || $this->status >= 500;
    }
}
