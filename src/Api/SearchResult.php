<?php
declare(strict_types=1);

namespace RickAndMorty\Api;

use RickAndMorty\Domain\Character;

/** One page of results plus the paging information the UI needs. */
final class SearchResult
{
    /** @param Character[] $characters */
    public function __construct(
        public readonly array $characters,
        public readonly int $pages,
        public readonly int $total,
    ) {
    }

    /** The API answers 404 for a search that matched nothing. */
    public static function empty(): self
    {
        return new self([], 0, 0);
    }

    public function hasPageAfter(int $page): bool
    {
        return $page < $this->pages;
    }
}
