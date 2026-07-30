<?php
declare(strict_types=1);

namespace RickAndMorty\Exception;

use RuntimeException;

/** No endpoint by that name. Maps to HTTP 404. */
final class UnknownActionException extends RuntimeException
{
    public static function named(string $action): self
    {
        return new self(sprintf('Unknown action "%s".', $action));
    }
}
