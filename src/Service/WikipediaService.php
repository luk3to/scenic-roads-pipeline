<?php

namespace ScenicRoads\Service;

use ScenicRoads\Http\HttpClient;
use ScenicRoads\Http\HttpClientFactory;

/**
 * Service for retrieving data and cross-references from Wikipedia.
 * 
 * This client uses the MediaWiki Action API to perform
 * full-text searches for road entities.
 */
class WikipediaService
{
    private const BASE_URI = 'https://en.wikipedia.org/w/api.php';
    private HttpClient $httpClient;

    public function __construct(HttpClientFactory $factory)
    {
        $this->httpClient = $factory->create(self::BASE_URI);
    }

    /**
     * Performs a title-based search to find the most relevant Wikipedia article for a road.
     * 
     * Key API features utilized:
     * - 'redirects' => 1: Automatically follows page moves (e.g., "Hwy 1" to "Highway 1").
     * - 'exintro' & 'explaintext': Fetches only the lead paragraph in plain text.
     * - 'pageprops': Used to extract the 'wikibase_item', which is the key to Wikidata.
     *
     * @param string $roadName The exact or near-exact name of the road.
     * @return array{description: string|null, wikidata_id: string|null, page_url: string|null}
     */
    public function search(string $roadName)
    {
        $data = $this->httpClient->get('', [
            'format' => 'json',
            'action' => 'query',
            'titles' => $roadName,
            'prop' => 'extracts|pageprops',
            'redirects' => 1,
            'exintro' => 1,
            'explaintext' => 1,
        ]);

        $pages = $data['query']['pages'] ?? [];
        $page = is_array($pages) ? array_shift($pages) : null;
        $pageId = $page['pageid'] ?? null;

        return [
            'description' => $page['extract'] ?? null,
            'wikidata_id' => $page['pageprops']['wikibase_item'] ?? null,
            'page_url' => $pageId ? "https://en.wikipedia.org/?curid={$pageId}" : null
        ];
    }
}
