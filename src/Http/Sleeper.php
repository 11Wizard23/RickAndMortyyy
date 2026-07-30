<?php
declare(strict_types=1);

namespace RickAndMorty\Http;

/** Waiting, behind an interface purely so tests don't have to actually wait. */
interface Sleeper
{
    public function sleep(int $seconds): void;
}
