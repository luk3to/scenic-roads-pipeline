<?php

namespace ScenicRoads\Source;

use Symfony\Component\Console\Style\SymfonyStyle;
use ScenicRoads\Model\RawRoadData;

interface SourceInterface
{
    /** @return string[] */
    public function getTargets(SymfonyStyle $io): array;

    /**
     * @return array{countryIso2: string, stateIso2: string|null, stateName: string|null}
     */
    public function getTargetLocation(string $targetId): array;

    /**
     * @return string
     */
    public function getTargetOutFilename(string $targetId): string;

    /** @return RawRoadData[] */
    public function getTargetData(string $targetId): array;
}
