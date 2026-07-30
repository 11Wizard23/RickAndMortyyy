<?php
declare(strict_types=1);

namespace RickAndMorty\Web;

use RickAndMorty\Service\CharacterListService;

final class ListAction implements Action
{
    public function __construct(private readonly CharacterListService $service)
    {
    }

    public function handle(Request $request): array
    {
        return $this->service->list($request->toCharacterQuery(), $request->page());
    }
}
