<?php
declare(strict_types=1);

namespace RickAndMorty\State;

/**
 * Session-backed state. Nothing is written upstream — the soft delete only
 * hides a character from this visitor.
 */
final class SessionUserState implements UserState
{
    private const STARRED = 'starred';
    private const DELETED = 'deleted';

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[self::STARRED] ??= [];
        $_SESSION[self::DELETED] ??= [];
    }

    public function starredIds(): array
    {
        return array_values($_SESSION[self::STARRED]);
    }

    public function deletedIds(): array
    {
        return array_values($_SESSION[self::DELETED]);
    }

    public function isStarred(int $id): bool
    {
        return in_array($id, $this->starredIds(), true);
    }

    public function toggleStar(int $id): bool
    {
        if ($this->isStarred($id)) {
            $this->remove(self::STARRED, $id);

            return false;
        }

        $_SESSION[self::STARRED][] = $id;

        return true;
    }

    public function delete(int $id): void
    {
        if (!in_array($id, $this->deletedIds(), true)) {
            $_SESSION[self::DELETED][] = $id;
        }
        // A hidden character has no business sitting in the starred block.
        $this->remove(self::STARRED, $id);
    }

    public function restoreAll(): void
    {
        $_SESSION[self::DELETED] = [];
    }

    private function remove(string $bucket, int $id): void
    {
        $_SESSION[$bucket] = array_values(
            array_filter($_SESSION[$bucket], static fn (int $stored): bool => $stored !== $id)
        );
    }
}
