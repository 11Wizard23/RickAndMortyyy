<?php
declare(strict_types=1);

namespace RickAndMorty\Http;

use RickAndMorty\Exception\TransportException;

/** The one place that actually talks to the network. */
final class CurlHttpClient implements HttpClient
{
    public function __construct(
        private readonly int $timeout = 10,
        private readonly string $userAgent = 'rick-and-morty-list/1.0',
    ) {
    }

    public function get(string $url): HttpResponse
    {
        $retryAfter = null;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_USERAGENT      => $this->userAgent,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$retryAfter): int {
                if (stripos($line, 'retry-after:') === 0) {
                    $retryAfter = (int) trim(substr($line, strlen('retry-after:')));
                }

                return strlen($line);
            },
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new TransportException('Upstream request failed: ' . $error);
        }

        return new HttpResponse($status, (string) $body, $retryAfter);
    }
}
