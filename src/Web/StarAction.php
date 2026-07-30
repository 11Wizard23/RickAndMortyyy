<?php
declare(strict_types=1);

namespace RickAndMorty\Web;

use RickAndMorty\State\UserState;

final class StarAction implements Action
{
    public function __construct(private readonly UserState $state)
    {
    }

    public function handle(Request $request): array
    {
        return ['starred' => $this->state->toggleStar($request->id())];
    }
}
