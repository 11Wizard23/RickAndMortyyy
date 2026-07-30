<?php
declare(strict_types=1);

namespace RickAndMorty\Exception;

use RuntimeException;

/** The upstream API answered, but with a status or body we can't use. */
final class UpstreamException extends RuntimeException
{
    public static function status(int $status): self
    {
        return new self("Upstream returned HTTP $status");
    }

    public static function malformed(): self
    {
        return new self('Upstream returned malformed JSON');
    }
}
