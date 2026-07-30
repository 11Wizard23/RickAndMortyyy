<?php
declare(strict_types=1);

namespace RickAndMorty\Domain;

/** A character, trimmed to the fields the interface actually draws. */
final class Character
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $image,
        public readonly string $species,
        public readonly string $status,
        public readonly string $gender,
        public readonly string $location,
        /** Species subtype ("Genetic experiment", "Human with antennae"). Often blank. */
        public readonly string $type = '',
    ) {
    }

    /** @param array<string,mixed> $raw a character object from the upstream API */
    public static function fromApi(array $raw): self
    {
        return new self(
            (int) ($raw['id'] ?? 0),
            (string) ($raw['name'] ?? ''),
            (string) ($raw['image'] ?? ''),
            (string) ($raw['species'] ?? 'unknown'),
            (string) ($raw['status'] ?? 'unknown'),
            (string) ($raw['gender'] ?? 'unknown'),
            (string) ($raw['location']['name'] ?? 'unknown'),
            trim((string) ($raw['type'] ?? '')),
        );
    }

    /**
     * Case-insensitive "contains", matching how the upstream API filters
     * (species=Human also hits "Humanoid"). An empty needle matches everything.
     */
    public function matches(string $attribute, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        // An explicit map, so a typo'd attribute fails loudly instead of
        // silently reading some unrelated property.
        $value = match ($attribute) {
            'name'     => $this->name,
            'species'  => $this->species,
            'status'   => $this->status,
            'gender'   => $this->gender,
            'location' => $this->location,
        };

        return str_contains(strtolower($value), strtolower($needle));
    }

    /**
     * Starred is per-visitor state, not an attribute of the character, so it is
     * supplied at render time rather than stored on the entity.
     *
     * @return array<string,mixed>
     */
    public function toArray(bool $starred): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'image'    => $this->image,
            'species'  => $this->species,
            'status'   => $this->status,
            'gender'   => $this->gender,
            'location' => $this->location,
            'type'     => $this->type,
            'starred'  => $starred,
        ];
    }
}
