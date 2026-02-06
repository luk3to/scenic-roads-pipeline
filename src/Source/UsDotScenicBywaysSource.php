<?php

namespace ScenicRoads\Source;

use Symfony\Component\Console\Style\SymfonyStyle;
use ScenicRoads\Http\HttpClient;
use ScenicRoads\Http\HttpClientFactory;

class UsDotScenicBywaysSource extends AbstractSource
{
    private const BASE_URI = 'https://geo.dot.gov';
    private const LAYER_MAP = [
        3   => ['iso2' => 'AL', 'state' => 'Alabama',        'layer' => 'AL_ScenicByways'],
        5   => ['iso2' => 'AK', 'state' => 'Alaska',         'layer' => 'AK_ScenicByways'],
        7   => ['iso2' => 'AZ', 'state' => 'Arizona',        'layer' => 'AZ_ScenicByways'],
        9   => ['iso2' => 'AR', 'state' => 'Arkansas',       'layer' => 'AR_ScenicByways'],
        11  => ['iso2' => 'CA', 'state' => 'California',     'layer' => 'CA_Scenic_Byways'],
        13  => ['iso2' => 'CO', 'state' => 'Colorado',       'layer' => 'Grand_Mesa_Scenic_Byway'],
        14  => ['iso2' => 'CO', 'state' => 'Colorado',       'layer' => 'Gold_Belt_Tour_Scenic_Byway'],
        15  => ['iso2' => 'CO', 'state' => 'Colorado',       'layer' => 'Frontier_Pathways_Scenic_Byway'],
        16  => ['iso2' => 'CO', 'state' => 'Colorado',       'layer' => 'Flat_Tops_Trail_Scenic_Byway'],
        17  => ['iso2' => 'CO', 'state' => 'Colorado',       'layer' => 'Dinosaur_Diamond_Scenic_Byway'],
        18  => ['iso2' => 'CO', 'state' => 'Colorado',       'layer' => 'Colorado_River_Headwaters_Scenic_Byway'],
        19  => ['iso2' => 'CO', 'state' => 'Colorado',       'layer' => 'Colligiate_Peaks_Scenic_Byway'],
        20  => ['iso2' => 'CO', 'state' => 'Colorado',       'layer' => 'Cache_la_Poudre_NP_Scenic_Byway'],
        21  => ['iso2' => 'CO', 'state' => 'Colorado',       'layer' => 'Alpine_Loop_Scenic_Byway'],
        23  => ['iso2' => 'CT', 'state' => 'Connecticut',    'layer' => 'CT_ScenicByways'],
        25  => ['iso2' => 'FL', 'state' => 'Florida',        'layer' => 'scenic_highways'],
        27  => ['iso2' => 'GA', 'state' => 'Georgia',        'layer' => 'GA_ScenicByways'],
        29  => ['iso2' => 'ID', 'state' => 'Idaho',          'layer' => 'Scenic_Byway'],
        31  => ['iso2' => 'IL', 'state' => 'Illinois',       'layer' => 'IL_ScenicByways'],
        33  => ['iso2' => 'IN', 'state' => 'Indiana',        'layer' => 'IN_ScenicByways'],
        35  => ['iso2' => 'IA', 'state' => 'Iowa',           'layer' => 'IA_ScenicByways'],
        37  => ['iso2' => 'KS', 'state' => 'Kansas',         'layer' => 'KS_ScenicByways'],
        39  => ['iso2' => 'KY', 'state' => 'Kentucky',       'layer' => 'KY_ScenicByways'],
        41  => ['iso2' => 'LA', 'state' => 'Louisiana',      'layer' => 'LA_ScenicByways'],
        43  => ['iso2' => 'ME', 'state' => 'Maine',          'layer' => 'MaineDOT_Scenic_Byways'],
        45  => ['iso2' => 'MA', 'state' => 'Massachusetts',  'layer' => 'MA_ScenicByways'],
        47  => ['iso2' => 'MD', 'state' => 'Maryland',       'layer' => 'MD_ScenicByways'],
        49  => ['iso2' => 'MI', 'state' => 'Michigan',       'layer' => 'MI_ScenicByways'],
        51  => ['iso2' => 'MN', 'state' => 'Minnesota',      'layer' => 'MN_ScenicByways'],
        53  => ['iso2' => 'MS', 'state' => 'Mississippi',    'layer' => 'MS_ScenicByways'],
        55  => ['iso2' => 'MO', 'state' => 'Missouri',       'layer' => 'MO_ScenicByways'],
        57  => ['iso2' => 'MT', 'state' => 'Montana',        'layer' => 'MT_ScenicByways'],
        59  => ['iso2' => 'NE', 'state' => 'Nebraska',       'layer' => 'NE_ScenicByways'],
        61  => ['iso2' => 'NV', 'state' => 'Nevada',         'layer' => 'NV_ScenicByways'],
        63  => ['iso2' => 'NH', 'state' => 'New Hampshire',  'layer' => 'NH_ScenicByways'],
        65  => ['iso2' => 'NJ', 'state' => 'New Jersey',     'layer' => 'NJ_ScenicByways'],
        67  => ['iso2' => 'NY', 'state' => 'New York',       'layer' => 'NY_ScenicByways'],
        69  => ['iso2' => 'NM', 'state' => 'New Mexico',     'layer' => 'NM_ScenicByways'],
        71  => ['iso2' => 'NC', 'state' => 'North Carolina', 'layer' => 'NC_ScenicByways'],
        73  => ['iso2' => 'ND', 'state' => 'North Dakota',   'layer' => 'ND_ScenicByways'],
        75  => ['iso2' => 'OH', 'state' => 'Ohio',           'layer' => 'OH_ScenicByways'],
        77  => ['iso2' => 'OR', 'state' => 'Oregon',         'layer' => 'oregon_scenic_byways'],
        79  => ['iso2' => 'OK', 'state' => 'Oklahoma',       'layer' => 'OK_ScenicByways'],
        81  => ['iso2' => 'PA', 'state' => 'Pennsylvania',   'layer' => 'PA_ScenicByways'],
        83  => ['iso2' => 'RI', 'state' => 'Rhode Island',   'layer' => 'RI_ScenicByways'],
        85  => ['iso2' => 'SC', 'state' => 'South Carolina', 'layer' => 'SC_ScenicByways'],
        87  => ['iso2' => 'SD', 'state' => 'South Dakota',   'layer' => 'SD_ScenicByways'],
        89  => ['iso2' => 'TN', 'state' => 'Tennessee',      'layer' => 'TN_ScenicByways'],
        91  => ['iso2' => 'TX', 'state' => 'Texas',          'layer' => 'TX_ScenicByways'],
        93  => ['iso2' => 'UT', 'state' => 'Utah',           'layer' => 'Utah_Scenic_Byways'],
        95  => ['iso2' => 'VT', 'state' => 'Vermont',        'layer' => 'VT_ScenicByways'],
        97  => ['iso2' => 'VA', 'state' => 'Virginia',       'layer' => 'VA_ScenicByways'],
        99  => ['iso2' => 'WA', 'state' => 'Washington',     'layer' => 'WSDOT_-_Scenic_Byways'],
        101 => ['iso2' => 'WV', 'state' => 'West Virginia',  'layer' => 'WV_ScenicByways'],
        103 => ['iso2' => 'WI', 'state' => 'Wisconsin',      'layer' => 'WI_ScenicByways'],
        105 => ['iso2' => 'WY', 'state' => 'Wyoming',        'layer' => 'WY_ScenicByways'],
        //107 => ['iso2' => 'US', 'state' => 'National',       'layer' => 'National_Scenic_Byways'],
    ];

    private HttpClient $httpClient;

    public function __construct(
        HttpClientFactory $factory,
        string $id = 'us-dot',
        string $name = 'U.S. DOT Scenic Byways'
    ) {
        $this->httpClient = $factory->create(self::BASE_URI, 1);
        parent::__construct($id, $name);
    }


    /**
     * Allows the user to select specific layers for processing.
     *
     * @param SymfonyStyle $io The IO utility for interactive selection.
     * @return string[] A list of layer IDs to be processed as targets.
     */
    public function getTargets(SymfonyStyle $io): array
    {
        $choices = [];
        foreach (self::LAYER_MAP as $id => $info) {
            // Creates a label like: "Colorado: Grand_Mesa_Scenic_Byway (13)"
            $label = sprintf('%s: %s (%s)', $info['state'], $info['layer'], $id);
            $choices[$label] = $id;
        }

        $selectedLabels = $io->choice(
            'Step 2: Select layers',
            array_keys($choices),
            multiSelect: true
        );

        // Return the IDs
        return array_map(fn($label) => (string) $choices[$label], $selectedLabels);
    }

    /**
     * Normalizes the target identifier into a structured location array.
     *
     * This ensures the pipeline receives a consistent schema regardless of 
     * whether the source provides countries, states, or local regions.
     *
     * @param string $targetId The target identifier (layer ID).
     * @return array{countryIso2: string, stateIso2: string|null, stateName: string|null}
     */
    public function getTargetLocation(string $targetId): array
    {
        return [
            'countryIso2' => 'US',
            'stateIso2' => self::LAYER_MAP[$targetId]['iso2'],
            'stateName' => self::LAYER_MAP[$targetId]['state']
        ];
    }

    public function getTargetOutFilename(string $targetId): string
    {
        return self::LAYER_MAP[$targetId]['iso2'];
    }

    /**
     * Fetches and returns the raw road data for a specific target (layer).
     * 
     * @param string $targetId The identifier (layer ID).
     * @return array<string, mixed> The decoded JSON data structure.
     */
    public function fetchRawData(string $targetId): array
    {
        $params = [
            'where' => "1=1",
            'outFields' => '*',
            'returnGeometry' => 'true',
            'f' => 'geojson',
        ];

        $endpoint = "/server/rest/services/US_Scenic_Byways/MapServer/{$targetId}/query";
        $features = ($this->httpClient->get($endpoint, $params))['features'] ?? [];

        return $this->mergeFeaturesByName($features);
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

            // Exclude Ferry routes
            if (isset($props['RouteType']) && $props['RouteType'] === 'Ferry Route') {
                continue;
            }

            $name = $this->normalizeRoadName($props);

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

    /**
     * Extracts and cleans the road name from raw GIS attributes.
     * 
     * @param array<string, mixed> $props The raw key-value pairs from the source feature.
     * @return string A human-readable road name.
     */
    private function normalizeRoadName(array $props): string
    {
        // Pick the best available raw field
        $rawName = $props['NAME'] ?? $props['Name'] ?? $props['name'] ?? $props['LOCATION'] ?? $props['DESCRIPT'] ?? $props['Byway_Name'] ?? '';
        $rawName = trim($rawName);

        if (empty($rawName)) {
            return '';
        }

        // Expand common abbreviations used in GIS data
        $expansions = [
            ' PKWY' => ' Parkway',
            ' HWY'  => ' Highway',
            ' EXPY' => ' Expressway',
            ' TRCE' => ' Trace',
            ' RD'   => ' Road',
            ' BLVD' => ' Boulevard',
            ' NP'   => ' National Park',
            ' TRL'  => ' Trail',
            ' RTE'  => ' Route',
            ' ST'   => ' Street',
            ' AVE'  => ' Avenue',
            ' DR'   => ' Drive',
            ' LN'   => ' Lane',
            ' CIR'  => ' Circle',
            ' WAY'  => ' Way',
            ' BYP'  => ' Bypass',
            ' TPKE' => ' Turnpike',
        ];

        $cleanName = str_ireplace(array_keys($expansions), array_values($expansions), $rawName);

        // Convert "ALL CAPS" to "Title Case"
        return mb_convert_case(strtolower($cleanName), MB_CASE_TITLE, "UTF-8");
    }
}
