<?php

namespace ScenicRoads\Pipeline;

use ScenicRoads\Service\WikipediaService;
use ScenicRoads\Service\WikidataService;
use ScenicRoads\Service\AIService;
use ScenicRoads\Model\RoadDTO;
use ScenicRoads\Model\RawRoadData;
use ScenicRoads\Service\GeometryOptimizer;
use ScenicRoads\Service\MediaService;
use ScenicRoads\Service\OpenElevationService;
use ScenicRoads\Source\SourceInterface;
use ScenicRoads\Utils\JsonHandler;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * RoadEnricher orchestrates the transformation of raw road data into a 
 * rich dataset by fetching information from Wikipedia and AI services.
 */
class RoadEnricher
{
    private ?\Closure $logger = null;

    public function __construct(
        private WikipediaService $wikipedia,
        private WikidataService $wikidata,
        private MediaService $media,
        private AIService $aiService,
        private OpenElevationService $elevationService,
        private GeometryOptimizer $geometryOptimizer,
        private JsonHandler $jsonHandler,
        private string $outputPath,
        private bool $aiEnabled,
        private bool $elevationEnabled
    ) {}

    /**
     * Injects a logging callback
     */
    public function setLogger(callable $callback): void
    {
        $this->logger = $callback;
    }

    /**
     * Internal helper to pass messages to the attached logger.
     */
    private function log(string $message): void
    {
        if ($this->logger) {
            ($this->logger)($message);
        }
    }

    /**
     * Executes the enrichment pipeline for an target (e.g., a State or File).
     *
     * @param SourceInterface $source The data source strategy (Local, API, etc.)
     * @param string $targetId The specific identifier within that source.
     * @return array Summary of the operation results.
     */
    public function process(SourceInterface $source, string $targetId, SymfonyStyle $io)
    {
        try {
            $location = $source->getTargetLocation($targetId);

            $this->log("Fetching {$targetId} data...");

            // Fetch raw list of roads from the source
            $rawDataItems = $source->getTargetData($targetId);

            // Iterate and enrich each individual road
            $outputData = [];
            foreach ($rawDataItems as $index => $rawRoad) {
                $outputData[] = $this->processRoad($rawRoad, $source->id, $location, $index + 1, count($rawDataItems));
            }

            $filePath = $this->createOutputFilePath($source, $targetId);

            // Persistence
            $this->jsonHandler->save($outputData, $filePath);

            return [
                'target' => $targetId,
                'count' => count($outputData),
                'path' => $filePath,
                'status' => '<fg=green>Success</>'
            ];
        } catch (\Exception $e) {
            $io->error("Failed to process {$targetId}: " . $e->getMessage());

            return [
                'target' => $targetId,
                'count' => 0,
                'path' => null,
                'status' => '<fg=red>Failed</>',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Coordinates the enrichment of a single road using strict DTOs.
     *
     * @param RawRoadData $roadData Standardized output from any Source.
     * @param string $sourceId
     * @param array $location
     */
    public function processRoad(RawRoadData $roadData, string $sourceId, array $location, int $index, int $total): RoadDTO
    {
        $stateName = $location['stateName'] ?? '';
        $countryIso2 = $location['countryIso2'] ?? '';
        $roadName = $roadData->name;

        // AI Bridge: If the name looks like a messy GIS string, clean it first
        if ($this->aiEnabled) {
            $this->log("<comment>{$roadName}</comment>: ({$index}/{$total}) Normalizing name via AI...");
            $roadName = $this->aiService->getCleanName($roadData, $stateName, $countryIso2);
        }

        // Normalize geometry (Sort & Stitch)
        $this->log("<comment>{$roadName}</comment>: ({$index}/{$total}) Sorting and stitching geometry segments...");
        $geometry = $this->geometryOptimizer->optimize($roadData->geometry);

        // Inject elevation data into road geometry
        if ($this->elevationEnabled) {
            $this->log("<comment>{$roadName}</comment>: ({$index}/{$total}) Fetching elevation data...");
            $geometry = $this->elevationService->enrichGeometry($geometry);
        }

        // Initialize DTO with core data
        $road = RoadDTO::fromArray([
            ...$location,
            'name' => $roadName,
            'geom' => $geometry,
            'source' => $sourceId,
            'sourceUrl' =>  ''
        ]);

        // Step 1: Wikipedia Search
        $wikiResult = $this->wikipedia->search($roadName);
        $wikidataId = $wikiResult['wikidata_id'] ?? null;

        $this->log("<comment>{$roadName}</comment>: ({$index}/{$total}) Fetching Wikipedia...");

        // Step 2: Handle Description (Wiki Fallback to AI)
        if ($wikiResult['description']) {
            $road->description = $wikiResult['description'];
            $road->descriptionSource = 'wiki';
            $road->descriptionSourceUrl = $wikiResult['page_url'];
        } elseif ($this->aiEnabled) {
            // Create description using AI
            $this->log("<comment>{$roadName}</comment>: ({$index}/{$total}) Generating AI description...");
            $road->description = $this->aiService->createDescription($road);
        }

        // Step 3: Wikidata Enrichment
        if ($wikidataId) {
            $this->log("<comment>{$roadName}</comment>: ({$index}/{$total}) Fetching Wikidata...");

            $wikidataResult = $this->wikidata->getData($wikidataId);

            $road->lengthKm = $wikidataResult['length_km'];
            $road->image = $wikidataResult['image'];
        }

        // Step 4: Asset Management (Download images locally)
        if (isset($road->image['url'])) {
            $road->image['filename'] = $this->createImageFileName($road->image['url'], $roadName);

            $this->log("<comment>{$roadName}</comment>: ({$index}/{$total}) Downloading image...");
            $this->media->downloadImage($road->image['url'], $sourceId . '/' . $road->image['filename']);
        }

        return $road;
    }

    /**
     * Generates a standardized path for the resulting JSON file.
     */
    private function createOutputFilePath(SourceInterface $source, string $targetId): string
    {
        $fileName = $source->getTargetOutFilename($targetId);

        return "{$this->outputPath}/data/{$source->id}/{$fileName}.json";
    }

    /**
     * Creates a filesystem-safe filename for road images.
     */
    private function createImageFileName(string $imageUrl, string $roadName): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $roadName);
        $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';

        return $safeName . '.' . $extension;
    }
}
