<?php

namespace ScenicRoads\Source;

use ScenicRoads\Utils\StateMapper;
use Symfony\Component\Console\Style\SymfonyStyle;
use ScenicRoads\Http\HttpClient;
use ScenicRoads\Http\HttpClientFactory;

class ArcGisSource extends AbstractSource
{
    private const BASE_URI = 'https://services7.arcgis.com';
    private HttpClient $httpClient;

    public function __construct(
        HttpClientFactory $factory,
        private array $activeLayers,
        private StateMapper $stateMapper,
        string $id = 'arcgis',
        string $name = 'ArcGIS (services7.arcgis.com)'
    ) {
        $this->httpClient = $factory->create(self::BASE_URI, 1);
        parent::__construct($id, $name);
    }

    /**
     * Allows the user to select specific states for processing.
     *
     * @param SymfonyStyle $io The IO utility for interactive selection.
     * @return string[] A list of states iso2 to be processed as targets.
     */
    public function getTargets(SymfonyStyle $io): array
    {
        $availableStates = array_keys($this->stateMapper->getAllStates());

        $selection = $io->choice(
            'Step 2: Select US States (comma separated)',
            array_merge(['all'], $availableStates),
            'all',
            true
        );

        return in_array('all', (array)$selection) ? $availableStates : (array)$selection;
    }

    /**
     * Normalizes the target identifier into a structured location array.
     *
     * This ensures the pipeline receives a consistent schema regardless of 
     * whether the source provides countries, states, or local regions.
     *
     * @param string $targetID The target identifier (state ISO2).
     * @return array{countryIso2: string, stateIso2: string|null, stateName: string|null}
     */
    public function getTargetLocation(string $targetId): array
    {
        return [
            'countryIso2' => 'US',
            'stateIso2' => $targetId,
            'stateName' => $this->stateMapper->getNameByCode($targetId)
        ];
    }

    public function getTargetOutFilename(string $targetId): string
    {
        return $targetId;
    }

    /**
     * Fetches and returns the raw road data for a specific file target.
     * 
     * @param string $targetId The identifier (state ISO2).
     * @return array{name: string, geometry: array}[]
     */
    protected function fetchRawData(string $targetId): array
    {
        $stateName = $this->stateMapper->getNameByCode($targetId);
        $params = [
            'where' => "State='{$stateName}'",
            'outFields' => '*',
            'returnGeometry' => 'true',
            'f' => 'pgeojson',
        ];

        $allFeatures = [];

        foreach ($this->activeLayers as $layerId) {
            $endpoint = "/yiuFazTjHE8F5gzQ/ArcGIS/rest/services/America_s_Scenic_Highways_and_Byways_WFL1/FeatureServer/{$layerId}/query";
            $features = ($this->httpClient->get($endpoint, $params))['features'] ?? [];

            array_push($allFeatures, ...$features);
        }

        return $this->mergeFeaturesByName($allFeatures);
    }

    /**
     * Deduplicates and merges raw GIS features based on their common name.
     * 
     * ArcGIS often stores roads as hundreds of tiny line segments. This method
     * groups them into a single 'MultiLineString' so that a road like the 
     * "Route 66" is treated as one entity rather than 50 separate entries.
     *
     * @param array $features Raw GeoJSON-style features.
     * @return array Unified road data.
     */
    public function mergeFeaturesByName(array $features): array
    {
        $merged = [];

        foreach ($features as $feature) {
            $props = $feature['properties'] ?? [];

            $rawName = $props['Name'] ?? $props['name'] ?? $props['Byway_Name'] ?? '';
            $name = trim($rawName);

            if (empty($name)) {
                continue;
            }

            $coordinates = $feature['geometry']['coordinates'] ?? null;
            $type = $feature['geometry']['type'] ?? 'LineString';

            if (!$coordinates) {
                continue;
            }

            if (!isset($merged[$name])) {
                $merged[$name] = [
                    'name' => $name,
                    'geometry' => [
                        'type' => 'MultiLineString',
                        'coordinates' => []
                    ],
                ];
            }

            if ($type === 'LineString') {
                // A single line: [[lng, lat], ...]
                $merged[$name]['geometry']['coordinates'][] = $coordinates;
            } elseif ($type === 'MultiLineString') {
                // Multiple lines: [[[lng, lat], ...], [[lng, lat], ...]]
                foreach ($coordinates as $line) {
                    $merged[$name]['geometry']['coordinates'][] = $line;
                }
            }
        }

        return array_values($merged);
    }
}
