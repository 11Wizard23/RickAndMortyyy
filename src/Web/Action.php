<?php
declare(strict_types=1);

namespace RickAndMorty\Web;

/** One endpoint. Returns the payload; serialising is the front controller's job. */
interface Action
{
    /** @return array<string,mixed> */
    public function handle(Request $request): array;
}
