<?php
declare(strict_types=1);

namespace RickAndMorty\Api;

use RickAndMorty\Domain\Character;
use RickAndMorty\Domain\CharacterQuery;
use RickAndMorty\Exception\UpstreamException;
use RickAndMorty\Http\HttpClient;

/** Translates https://rickandmortyapi.com into domain objects. */
final class RickAndMortyApi implements CharacterRepository
{
    public function __construct(
        private readonly HttpClient $client,
        private readonly string $baseUrl = 'https://rickandmortyapi.com/api',
    ) {
    }

    public function search(CharacterQuery $query, int $page): SearchResult
    {
        $params = ['page' => max(1, $page)] + $query->attributeFilters();
        $data   = $this->fetch('/character/?' . http_build_query($params));

        if ($data === null) {
            return SearchResult::empty();
        }

        return new SearchResult(
            array_map(Character::fromApi(...), $data['results'] ?? []),
            (int) ($data['info']['pages'] ?? 0),
            (int) ($data['info']['count'] ?? 0),
        );
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $data = $this->fetch('/character/' . implode(',', $ids));
        if ($data === null) {
            return [];
        }

        // A single id yields a bare object; several yield a list.
        $raw = isset($data['id']) ? [$data] : $data;

        return array_map(Character::fromApi(...), $raw);
    }

    /**
     * @return array<mixed>|null null when the API answers 404, which it uses
     *                           both for unknown ids and for "no results".
     */
    private function fetch(string $path): ?array
    {
        $response = $this->client->get($this->baseUrl . $path);

        if ($response->isNotFound()) {
            return null;
        }
        if (!$response->isOk()) {
            throw UpstreamException::status($response->status);
        }

        $data = json_decode($response->body, true);
        if (!is_array($data)) {
            throw UpstreamException::malformed();
        }

        return $data;
    }
}
