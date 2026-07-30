<?php
declare(strict_types=1);

namespace RickAndMorty\Web;

use RickAndMorty\Domain\CharacterQuery;
use RickAndMorty\Domain\Scope;
use RickAndMorty\Exception\BadRequestException;

/** The trust boundary: nothing past this class touches a superglobal. */
final class Request
{
    /** @param array<string,mixed> $query */
    public function __construct(private readonly array $query)
    {
    }

    public static function fromGlobals(): self
    {
        return new self($_GET);
    }

    public function action(): string
    {
        return $this->string('action', 'list');
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->query[$key] ?? $default;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function page(): int
    {
        return max(1, (int) $this->string('page', '1'));
    }

    /** @throws BadRequestException */
    public function id(): int
    {
        $id = filter_var($this->string('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new BadRequestException('A positive integer "id" is required.');
        }

        return $id;
    }

    public function toCharacterQuery(): CharacterQuery
    {
        return new CharacterQuery(
            $this->string('name'),
            $this->string('status'),
            $this->string('species'),
            $this->string('gender'),
            Scope::fromString($this->string('scope')),
        );
    }
}
