<?php

namespace ScenicRoads\Source;

use ScenicRoads\Source\SourceInterface;
use ScenicRoads\Model\RawRoadData;

abstract class AbstractSource implements SourceInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $name
    ) {}

    /**
     * @return RawRoadData[] 
     */
    public function getTargetData(string $target): array
    {
        $rawData = $this->fetchRawData($target);
        $dtos = [];

        foreach ($rawData as $item) {
            // Validation check
            if (!isset($item['name'], $item['geometry'])) {
                throw new \RuntimeException("Source [{$this->name}] provided an item missing 'name' or 'geometry'.");
            }

            // Conversion to DTO
            $dtos[] = new RawRoadData(
                name: $item['name'],
                geometry: $item['geometry'],
            );
        }

        return $dtos;
    }

    /**
     * Child classes should implement this
     */
    abstract protected function fetchRawData(string $target): array;
}
