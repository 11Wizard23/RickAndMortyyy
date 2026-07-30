<?php
declare(strict_types=1);

namespace RickAndMorty\Service;

use RickAndMorty\Api\CharacterRepository;
use RickAndMorty\Api\SearchResult;
use RickAndMorty\Domain\Character;
use RickAndMorty\Domain\CharacterQuery;
use RickAndMorty\Domain\Scope;
use RickAndMorty\Filter\AttributeFilter;
use RickAndMorty\Filter\CharacterFilter;
use RickAndMorty\Filter\FilterChain;
use RickAndMorty\Filter\NotDeletedFilter;
use RickAndMorty\Filter\ScopeFilter;
use RickAndMorty\State\UserState;

/**
 * Builds the payload the sidebar renders: a starred block on top, the rest
 * below, with soft-deletes and the scope toggle applied.
 */
final class CharacterListService
{
    public function __construct(
        private readonly CharacterRepository $repository,
        private readonly UserState $state,
    ) {
    }

    /** @return array<string,mixed> */
    public function list(CharacterQuery $query, int $page): array
    {
        // "Starred" is answered entirely from session state, so it never pages
        // through the upstream API.
        $searchesUpstream = $query->scope !== Scope::Starred;

        $starred = $this->starredBlock($query, $page);
        $result  = $searchesUpstream
            ? $this->repository->search($query, $page)
            : SearchResult::empty();

        $characters = $this->chain(new ScopeFilter($query->scope, $this->state->starredIds()))
            ->apply($result->characters);

        // Whatever the starred block already shows must not appear again below it.
        $characters = $this->reject($characters, array_map(
            static fn (Character $c): int => $c->id,
            $starred
        ));

        return [
            'starred'    => array_map($this->present(...), $starred),
            'characters' => array_map($this->present(...), $characters),
            'page'       => $page,
            'hasMore'    => $result->hasPageAfter($page),
            'total'      => $searchesUpstream ? $result->total : count($starred),
            'deleted'    => count($this->state->deletedIds()),
        ];
    }

    /**
     * Starred characters are fetched by id, so they only belong on the first
     * page — and never when the user asked for "others".
     *
     * @return Character[]
     */
    private function starredBlock(CharacterQuery $query, int $page): array
    {
        if ($page !== 1 || $query->scope === Scope::Others) {
            return [];
        }

        $ids = array_values(array_diff($this->state->starredIds(), $this->state->deletedIds()));

        // Fetched by id, so nothing upstream filtered it: re-apply the attributes here.
        return $this->chain(new AttributeFilter($query))->apply($this->repository->findByIds($ids));
    }

    /** Soft-deletes apply everywhere; the caller adds whatever else is needed. */
    private function chain(CharacterFilter ...$extra): FilterChain
    {
        return new FilterChain(new NotDeletedFilter($this->state->deletedIds()), ...$extra);
    }

    /**
     * @param  Character[] $characters
     * @param  int[]       $ids
     * @return Character[]
     */
    private function reject(array $characters, array $ids): array
    {
        return array_values(array_filter(
            $characters,
            static fn (Character $c): bool => !in_array($c->id, $ids, true)
        ));
    }

    /** @return array<string,mixed> */
    private function present(Character $character): array
    {
        return $character->toArray($this->state->isStarred($character->id));
    }
}
