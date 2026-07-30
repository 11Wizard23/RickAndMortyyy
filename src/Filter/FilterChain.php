<?php
declare(strict_types=1);

namespace RickAndMorty\Filter;

use RickAndMorty\Domain\Character;

/** Composite: passes only when every member filter passes. */
final class FilterChain implements CharacterFilter
{
    /** @var CharacterFilter[] */
    private array $filters;

    public function __construct(CharacterFilter ...$filters)
    {
        $this->filters = $filters;
    }

    public function passes(Character $character): bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter->passes($character)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Character[] $characters
     * @return Character[] re-indexed, so it encodes as a JSON array
     */
    public function apply(array $characters): array
    {
        return array_values(array_filter($characters, $this->passes(...)));
    }
}
