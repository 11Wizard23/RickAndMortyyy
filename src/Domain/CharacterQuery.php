<?php
declare(strict_types=1);

namespace RickAndMorty\Domain;

/** An immutable description of what the user is searching for. */
final class CharacterQuery
{
    public function __construct(
        public readonly string $name = '',
        public readonly string $status = '',
        public readonly string $species = '',
        public readonly string $gender = '',
        public readonly Scope $scope = Scope::All,
    ) {
    }

    /**
     * The non-empty attribute filters, keyed by the Character property they
     * test. These are exactly the ones the upstream API understands, so the
     * same array serves as query string and as local re-filter.
     *
     * @return array<string,string>
     */
    public function attributeFilters(): array
    {
        return array_filter([
            'name'    => $this->name,
            'status'  => $this->status,
            'species' => $this->species,
            'gender'  => $this->gender,
        ], static fn (string $value): bool => $value !== '');
    }
}
