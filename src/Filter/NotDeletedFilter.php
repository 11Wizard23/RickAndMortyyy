<?php
declare(strict_types=1);

namespace RickAndMorty\Filter;

use RickAndMorty\Domain\Character;

/** Enforces the soft delete: deleted characters never reach the client. */
final class NotDeletedFilter implements CharacterFilter
{
    /** @param int[] $deletedIds */
    public function __construct(private readonly array $deletedIds)
    {
    }

    public function passes(Character $character): bool
    {
        return !in_array($character->id, $this->deletedIds, true);
    }
}
