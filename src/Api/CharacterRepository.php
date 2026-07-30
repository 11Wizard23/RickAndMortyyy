<?php
declare(strict_types=1);

namespace RickAndMorty\Api;

use RickAndMorty\Domain\Character;
use RickAndMorty\Domain\CharacterQuery;

/**
 * Where characters come from. The service layer depends on this rather than on
 * the Rick and Morty API, which is what lets tests swap in a fake.
 */
interface CharacterRepository
{
    public function search(CharacterQuery $query, int $page): SearchResult;

    /**
     * @param  int[] $ids
     * @return Character[]
     */
    public function findByIds(array $ids): array;
}
