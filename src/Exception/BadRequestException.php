<?php
declare(strict_types=1);

namespace RickAndMorty\Exception;

use InvalidArgumentException;

/** The client sent something we refuse to guess at. Maps to HTTP 400. */
final class BadRequestException extends InvalidArgumentException
{
}
