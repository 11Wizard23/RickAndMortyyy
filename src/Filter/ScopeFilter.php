<?php
declare(strict_types=1);

namespace RickAndMorty\Filter;

use RickAndMorty\Domain\Character;
use RickAndMorty\Domain\Scope;

/** Implements the mockup's All / Starred / Others toggle. */
final class ScopeFilter implements CharacterFilter
{
    /** @param int[] $starredIds */
    public function __construct(
        private readonly Scope $scope,
        private readonly array $starredIds,
    ) {
    }

    public function passes(Character $character): bool
    {
        $starred = in_array($character->id, $this->starredIds, true);

        return match ($this->scope) {
            Scope::All     => true,
            Scope::Starred => $starred,
            Scope::Others  => !$starred,
        };
    }
}
