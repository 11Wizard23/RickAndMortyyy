<?php
declare(strict_types=1);

namespace RickAndMorty\Domain;

/** The mockup's "Character" filter: everyone, only starred, or only the rest. */
enum Scope: string
{
    case All     = 'all';
    case Starred = 'starred';
    case Others  = 'others';

    public static function fromString(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::All;
    }
}
