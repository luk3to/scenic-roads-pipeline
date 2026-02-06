<?php

namespace ScenicRoads\Model;

class RoadDTO
{
    public function __construct(
        public string $countryIso2,
        public ?string $stateIso2 = null,
        public ?string $stateName = null,
        public string $name = 'Unnamed Route',
        public ?string $description = null,
        public ?array $image = null,
        public array $geom = [],
        public array $tags = [],
        public ?float $lengthKm = null,
        public ?string $source = null,
        public ?string $sourceUrl = null,
        public ?string $descriptionSource = null,
        public ?string $descriptionSourceUrl = null,
    ) {}

    /**
     * Static Factory to create DTO from raw data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            countryIso2: $data['countryIso2'],
            stateIso2: $data['stateIso2'] ?? null,
            stateName: $data['stateName'] ?? null,
            name: $data['name'] ?? 'Unnamed Route',
            description: $data['description'] ?? null,
            image: $data['image'] ?? null,
            geom: $data['geom'] ?? [],
            tags: $data['tags'] ?? [],
            lengthKm: isset($data['lengthKm']) ? (float)$data['lengthKm'] : null,
            source: $data['source'] ?? null,
            sourceUrl: $data['sourceUrl'] ?? null,
            descriptionSource: $data['descriptionSource'] ?? null,
            descriptionSourceUrl: $data['descriptionSourceUrl'] ?? null,
        );
    }
}
