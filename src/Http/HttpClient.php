<?php
declare(strict_types=1);

namespace RickAndMorty\Http;

use RickAndMorty\Exception\TransportException;

/**
 * Anything that can perform a GET. Kept deliberately narrow so retrying and
 * caching can be layered as decorators over the same contract.
 */
interface HttpClient
{
    /** @throws TransportException when no response could be obtained at all. */
    public function get(string $url): HttpResponse;
}
