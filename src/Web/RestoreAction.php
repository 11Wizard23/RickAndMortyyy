<?php
declare(strict_types=1);

namespace RickAndMorty\Web;

use RickAndMorty\State\UserState;

final class RestoreAction implements Action
{
    public function __construct(private readonly UserState $state)
    {
    }

    public function handle(Request $request): array
    {
        $this->state->restoreAll();

        return ['deleted' => 0];
    }
}
