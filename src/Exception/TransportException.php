<?php
declare(strict_types=1);

namespace RickAndMorty\Exception;

use RuntimeException;

/** The request never produced an HTTP response: DNS, TLS, timeout, reset. */
final class TransportException extends RuntimeException
{
}
