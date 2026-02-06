<?php

namespace ScenicRoads\Model;

readonly class RawRoadData
{
    public function __construct(
        public string $name,
        public array $geometry
    ) {}
}
