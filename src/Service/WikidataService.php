<?php

namespace ScenicRoads\Service;

use ScenicRoads\Http\HttpClient;
use ScenicRoads\Http\HttpClientFactory;

/**
 * Service for retrieving rich metadata from Wikidata and Wikimedia.
 */
class WikidataService
{
    private const BASE_URI = 'https://www.wikidata.org/w/api.php';
    private HttpClient $httpClient;

    public function __construct(HttpClientFactory $factory)
    {
        $this->httpClient = $factory->create(self::BASE_URI);
    }

    /**
     * Retrieves and parses core road properties from a Wikidata ID.
     * 
     * @param string $wikidataId The unique entity ID
     * @return array{length_km: float|null, image: array|null}
     */
    public function getData(string $wikidataId)
    {
        $data = $this->httpClient->get('', [
            'format' => 'json',
            'action' => 'wbgetentities',
            'props' => 'claims',
            'ids' => $wikidataId
        ]);
        $claims = $data['entities'][$wikidataId]['claims'];

        // P2043 = length
        $length = $claims['P2043'][0]['mainsnak']['datavalue']['value'] ?? null;

        // P18 = image (returns filename, not URL)
        $filename = $claims['P18'][0]['mainsnak']['datavalue']['value'] ?? null;

        return [
            'length_km' => $length ? $this->normalizeLength($length) : null,
            'image' => $filename ? $this->fetchImageData($filename) : null
        ];
    }

    /**
     * Resolves a Wikimedia filename into a full URL and attribution metadata.
     * 
     * Necessary because Wikidata only stores the filename string (e.g., "Natchez_Trace_Parkway.jpg").
     * This method fetches the actual URL and legal credits (License, Author).
     * @param string $filename The image filename from Wikidata.
     * @return array Metadata including 'url', 'license', 'author', and 'usageTerms'.
     */
    private function fetchImageData(string $filename)
    {
        $data = $this->httpClient->get('', [
            'action' => 'query',
            'titles' => 'File:' . $filename,
            'format' => 'json',
            'prop' => 'imageinfo',
            'iiprop' => 'url|extmetadata',
        ]);

        $page = array_shift($data['query']['pages']);

        if (!isset($page['imageinfo'][0])) {
            return [
                'filename' => $filename,
            ];
        }

        $imageInfo = $page['imageinfo'][0];

        return [
            'filename' => $filename,
            'url' => $imageInfo['url'] ?? null,
            'license' => $imageInfo['extmetadata']['LicenseShortName']['value'] ?? null,
            'licenseUrl' => $imageInfo['extmetadata']['LicenseUrl']['value'] ?? null,
            'author' => strip_tags($imageInfo['extmetadata']['Artist']['value'] ?? ''),
            'credit' => strip_tags($imageInfo['extmetadata']['Credit']['value'] ?? ''),
            'usageTerms' => $imageInfo['extmetadata']['UsageTerms']['value'] ?? null,
        ];
    }

    /**
     * Converts various Wikidata measurement units into a standardized Kilometer value.
     * 
     * @param array $length The 'value' array from Wikidata mainsnak.
     * @return float|null The distance in kilometers.
     */
    private function normalizeLength(array $length)
    {
        if (!isset($length['amount'], $length['unit'])) {
            return null;
        }

        $value = floatval($length['amount']);
        $unitId = basename($length['unit']);

        return match ($unitId) {
            'Q253276' => $value * 1.609344,   // Miles to KM
            'Q828224' => $value / 1000,       // Meters to KM
            'Q11573'  => $value,              // Already KM
            'Q3710'   => $value * 0.0003048,  // Feet to KM
            'Q174728' => $value * 0.0009144,  // Yards to KM
            default   => null
        };
    }
}
