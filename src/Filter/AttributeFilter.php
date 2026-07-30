<?php
declare(strict_types=1);

namespace RickAndMorty\Filter;

use RickAndMorty\Domain\Character;
use RickAndMorty\Domain\CharacterQuery;

/**
 * Re-applies name/status/species/gender locally.
 *
 * Only the starred block needs this: it is fetched by id, so the upstream API
 * never had a chance to filter it.
 */
final class AttributeFilter implements CharacterFilter
{
    public function __construct(private readonly CharacterQuery $query)
    {
    }

    public function passes(Character $character): bool
    {
        foreach ($this->query->attributeFilters() as $attribute => $needle) {
            if (!$character->matches($attribute, $needle)) {
                return false;
            }
        }

        return true;
    }
}
