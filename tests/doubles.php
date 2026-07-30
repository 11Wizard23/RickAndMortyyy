<?php
declare(strict_types=1);

/**
 * Test doubles. Every collaborator sits behind an interface, so none of these
 * needs a mocking framework — or a network, a session, or a real clock.
 */

use RickAndMorty\Api\CharacterRepository;
use RickAndMorty\Api\SearchResult;
use RickAndMorty\Cache\Cache;
use RickAndMorty\Domain\Character;
use RickAndMorty\Domain\CharacterQuery;
use RickAndMorty\Http\HttpClient;
use RickAndMorty\Http\HttpResponse;
use RickAndMorty\Http\Sleeper;
use RickAndMorty\State\UserState;

/** Replays a scripted list of responses; a Throwable entry is thrown instead. */
final class FakeHttpClient implements HttpClient
{
    public int $calls = 0;

    /** @var list<string> */
    public array $urls = [];

    /** @param list<HttpResponse|Throwable> $script */
    public function __construct(private array $script)
    {
    }

    public function get(string $url): HttpResponse
    {
        $this->calls++;
        $this->urls[] = $url;

        $next = array_shift($this->script) ?? new HttpResponse(200, '{}');
        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }
}

/** Records what it was asked to wait for, without waiting. */
final class RecordingSleeper implements Sleeper
{
    /** @var list<int> */
    public array $slept = [];

    public function sleep(int $seconds): void
    {
        $this->slept[] = $seconds;
    }
}

final class ArrayCache implements Cache
{
    /** @var array<string,string> */
    private array $entries = [];

    public function get(string $key): ?string
    {
        return $this->entries[$key] ?? null;
    }

    public function set(string $key, string $value): void
    {
        $this->entries[$key] = $value;
    }
}

/** Returns the same characters for every page, so paging is easy to assert on. */
final class FakeRepository implements CharacterRepository
{
    /** @param Character[] $characters */
    public function __construct(
        private readonly array $characters,
        private readonly int $pages = 1,
        private readonly int $total = 0,
    ) {
    }

    public function search(CharacterQuery $query, int $page): SearchResult
    {
        return new SearchResult($this->characters, $this->pages, $this->total);
    }

    public function findByIds(array $ids): array
    {
        return array_values(array_filter(
            $this->characters,
            static fn (Character $c): bool => in_array($c->id, $ids, true)
        ));
    }
}

/** UserState without a session, so tests can run in one process. */
final class ArrayUserState implements UserState
{
    /**
     * @param int[] $starred
     * @param int[] $deleted
     */
    public function __construct(
        private array $starred = [],
        private array $deleted = [],
    ) {
    }

    public function starredIds(): array
    {
        return $this->starred;
    }

    public function deletedIds(): array
    {
        return $this->deleted;
    }

    public function isStarred(int $id): bool
    {
        return in_array($id, $this->starred, true);
    }

    public function toggleStar(int $id): bool
    {
        if ($this->isStarred($id)) {
            $this->starred = array_values(array_diff($this->starred, [$id]));

            return false;
        }
        $this->starred[] = $id;

        return true;
    }

    public function delete(int $id): void
    {
        if (!in_array($id, $this->deleted, true)) {
            $this->deleted[] = $id;
        }
        $this->starred = array_values(array_diff($this->starred, [$id]));
    }

    public function restoreAll(): void
    {
        $this->deleted = [];
    }
}
