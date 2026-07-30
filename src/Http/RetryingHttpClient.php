<?php
declare(strict_types=1);

namespace RickAndMorty\Http;

use RickAndMorty\Exception\TransportException;

/**
 * Retries rate-limited (429) and transient (5xx) responses, plus outright
 * transport failures, backing off between attempts.
 *
 * Waiting here rather than in the browser is deliberate: the caller's HTTP
 * request stays open, so the frontend's loading state survives the whole
 * retry window without needing to know retries exist.
 */
final class RetryingHttpClient implements HttpClient
{
    public function __construct(
        private readonly HttpClient $inner,
        private readonly ExponentialBackoff $backoff,
        private readonly Sleeper $sleeper,
        private readonly int $maxRetries = 3,
    ) {
    }

    public function get(string $url): HttpResponse
    {
        for ($attempt = 0; ; $attempt++) {
            $exhausted = $attempt >= $this->maxRetries;

            try {
                $response = $this->inner->get($url);
                if (!$response->isTransient() || $exhausted) {
                    return $response;
                }
                $retryAfter = $response->retryAfter;
            } catch (TransportException $e) {
                if ($exhausted) {
                    throw $e;
                }
                $retryAfter = null;
            }

            $this->sleeper->sleep($this->backoff->delayFor($attempt, $retryAfter));
        }
    }
}
