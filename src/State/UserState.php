<?php
declare(strict_types=1);

namespace RickAndMorty\State;

/** Per-visitor state: which characters are starred and which are soft-deleted. */
interface UserState
{
    /** @return int[] */
    public function starredIds(): array;

    /** @return int[] */
    public function deletedIds(): array;

    public function isStarred(int $id): bool;

    /** @return bool the state after toggling */
    public function toggleStar(int $id): bool;

    public function delete(int $id): void;

    public function restoreAll(): void;
}
