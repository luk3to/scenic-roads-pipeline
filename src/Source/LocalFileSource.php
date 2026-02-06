<?php

namespace ScenicRoads\Source;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use ScenicRoads\Utils\JsonHandler;

class LocalFileSource extends AbstractSource
{
    public function __construct(
        private JsonHandler $jsonHandler,
        private string $inputPath,
        string $id = 'local',
        string $name = 'Local Files'
    ) {
        parent::__construct($id, $name);
    }

    /**
     * Scans the input directory for JSON files and allows the user to select
     * specific files for processing.
     *
     * @param SymfonyStyle $io The IO utility for interactive selection.
     * @return string[] A list of file names (without extensions) to be processed as targets.
     */
    public function getTargets(SymfonyStyle $io): array
    {
        $finder = (new Finder())->files()->in($this->inputPath)->sortByName()->name('*.json');

        if (!$finder->hasResults()) {
            $io->error("No files found in: {$this->inputPath}");
            return [];
        }

        $files = array_map(fn($f) => $f->getFilenameWithoutExtension(), iterator_to_array($finder));

        $selection = $io->choice(
            'Step 2: Select local files to process (comma separated)',
            array_merge(['all'], array_values($files)),
            'all',
            true
        );

        return in_array('all', (array)$selection) ? array_values($files) : (array)$selection;
    }

    /**
     * Normalizes the target identifier into a structured location array.
     *
     * This ensures the pipeline receives a consistent schema regardless of 
     * whether the source provides countries, states, or local regions.
     *
     * @param string $targetId The target identifier (e.g., ISO country code).
     * @return array{countryIso2: string, stateIso2: string|null, stateName: string|null}
     */
    public function getTargetLocation(string $targetId): array
    {
        return [
            'countryIso2' => $targetId,
            'stateIso2' => null,
            'stateName' => null
        ];
    }

    public function getTargetOutFilename(string $targetId): string
    {
        return $targetId;
    }

    /**
     * Loads and returns the raw road data for a specific file target.
     * 
     * @param string $targetId The identifier (filename without extension).
     * @return array<string, mixed> The decoded JSON data structure.
     */
    public function fetchRawData(string $targetId): array
    {
        $path = $this->inputPath . '/' . $targetId . '.json';
        return $this->jsonHandler->load($path);
    }
}
