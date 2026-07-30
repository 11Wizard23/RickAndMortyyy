<?php
declare(strict_types=1);

namespace RickAndMorty\Filter;

use RickAndMorty\Domain\Character;

/** One rule about whether a character belongs in the result. */
interface CharacterFilter
{
    public function passes(Character $character): bool;
}
