<?php
declare(strict_types=1);

namespace RickAndMorty\Web;

use RickAndMorty\State\UserState;

final class DeleteAction implements Action
{
    public function __construct(private readonly UserState $state)
    {
    }

    public function handle(Request $request): array
    {
        $this->state->delete($request->id());

        return ['deleted' => count($this->state->deletedIds())];
    }
}
