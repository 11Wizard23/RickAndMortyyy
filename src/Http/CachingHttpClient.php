<?php
declare(strict_types=1);

namespace RickAndMorty\Http;

use RickAndMorty\Cache\Cache;

/**
 * Serves successful responses from a cache before hitting the inner client.
 * Only 2xx bodies are stored — an error must never become sticky.
 */
final class CachingHttpClient implements HttpClient
{
    public function __construct(
        private readonly HttpClient $inner,
        private readonly Cache $cache,
    ) {
    }

    public function get(string $url): HttpResponse
    {
        $hit = $this->cache->get($url);
        if ($hit !== null) {
            return new HttpResponse(200, $hit);
        }

        $response = $this->inner->get($url);
        if ($response->isOk()) {
            $this->cache->set($url, $response->body);
        }

        return $response;
    }
}
