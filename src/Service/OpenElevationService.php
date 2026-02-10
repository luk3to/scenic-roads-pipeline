<?php

namespace ScenicRoads\Service;

use ScenicRoads\Http\HttpClient;
use ScenicRoads\Http\HttpClientFactory;

/**
 * Service for retrieving and processing elevation data.
 */
class OpenElevationService
{
    private HttpClient $httpClient;

    public function __construct(
        HttpClientFactory $factory,
        string $baseUri,
    ) {
        $this->httpClient = $factory->create($baseUri);
    }

    /**
     * Injects elevation into a Geometry array and applies smoothing
     * to prevent noise and extreme grade spikes.
     * 
     * @param array $geometry The GeoJSON geometry array (LineString or MultiLineString).
     * @return array The enriched geometry.
     */
    public function enrichGeometry(array $geometry): array
    {
        $type = $geometry['type'] ?? '';
        $coords = $geometry['coordinates'] ?? [];

        // Fetch and inject raw elevation data for points that miss it.
        if ($type === 'LineString') {
            $coords = $this->processPoints($coords);
        } elseif ($type === 'MultiLineString') {
            foreach ($coords as $index => $line) {
                $coords[$index] = $this->processPoints($line);
            }
        }

        // Smooth the elevation data using a Moving Average.
        if ($type === 'LineString') {
            $geometry['coordinates'] = $this->applySmoothing($coords);
        } elseif ($type === 'MultiLineString') {
            foreach ($coords as $index => $line) {
                $geometry['coordinates'][$index] = $this->applySmoothing($line);
            }
        }

        return $geometry;
    }

    /**
     * Applies a Moving Average filter to the Z-coordinate.
     *
     * We use a window size of 10 (looking 10 points back and 10 forward).
     * @param array $points Array of [lon, lat, ele] points.
     * @return array The points with smoothed elevation values.
     */
    private function applySmoothing(array $points): array
    {
        $count = count($points);
        if ($count < 3) {
            return $points;
        }

        $smoothed = $points;

        // Window size: 10 neighbors on each side (21 points total).
        $window = 10;

        for ($i = 0; $i < $count; $i++) {
            $sumZ = 0;
            $sampleCount = 0;

            // Gather neighbors within the window range
            for ($j = $i - $window; $j <= $i + $window; $j++) {
                // Check bounds and ensure the Z-coordinate exists
                if ($j >= 0 && $j < $count && isset($points[$j][2])) {
                    $sumZ += $points[$j][2];
                    $sampleCount++;
                }
            }

            // Calculate the average elevation for this window
            if ($sampleCount > 0) {
                // We keep the original Lat/Lng, only smooth the Elevation
                $smoothed[$i][2] = round($sumZ / $sampleCount, 1);
            }
        }

        return $smoothed;
    }

    /**
     * Identifies points missing elevation data and fetches it.
     *
     * @param array $points Array of coordinates.
     * @return array The coordinates with injected elevation data.
     */
    private function processPoints(array $points): array
    {
        // 1. Identify points that need elevation (missing index 2)
        // We preserve keys ($index) so we can put data back in the right spot
        $pointsToFetch = [];
        foreach ($points as $index => $point) {
            if (!isset($point[2])) {
                $pointsToFetch[$index] = $point;
            }
        }

        // If all points already have elevation, we are done
        if (empty($pointsToFetch)) {
            return $points;
        }

        // 2. Fetch elevation data from the API
        // array_values() is used to send a clean 0-indexed list to the API helper
        $elevations = $this->getElevationData(array_values($pointsToFetch));

        // 3. Inject the fetched data back into the original array
        $i = 0;
        foreach ($pointsToFetch as $index => $point) {
            // Use the fetched elevation, or 0 if something went wrong
            $points[$index][2] = $elevations[$i] ?? 0;
            $i++;
        }

        return $points;
    }

    /**
     * Batches requests to the API to avoid payload limits and handles results.
     *
     * @param array $coords Array of coordinates to look up.
     * @return array Array of elevation values (floats) corresponding to the input.
     */
    private function getElevationData(array $coords): array
    {
        $total = count($coords);
        // Pre-fill results with 0 so index alignment is guaranteed even if a chunk fails
        $finalElevations = array_fill(0, $total, 0);

        // Chunk size: 1000 points
        $chunks = array_chunk($coords, 1000, true);

        foreach ($chunks as $chunk) {
            $locations = [];
            $mapIndex = [];

            // Prepare payload for the API
            foreach ($chunk as $originalIndex => $p) {
                $lat = (float)$p[1];
                $lon = (float)$p[0];

                // Sanitization: Skip NaN or Infinite values
                if (!is_finite($lat) || !is_finite($lon)) {
                    continue;
                }

                $locations[] = ['latitude' => $lat, 'longitude' => $lon];
                // Remember which result goes where
                $mapIndex[] = $originalIndex;
            }

            if (empty($locations)) {
                continue;
            }

            try {
                $response = $this->httpClient->post('/api/v1/lookup', [
                    'json' => ['locations' => $locations]
                ]);

                $results = $response['results'] ?? [];

                // Map results back to the correct index in $finalElevations
                foreach ($results as $apiIndex => $res) {
                    if (isset($mapIndex[$apiIndex])) {
                        $originalIdx = $mapIndex[$apiIndex];
                        $finalElevations[$originalIdx] = $res['elevation'] ?? 0;
                    }
                }
            } catch (\Exception $e) {
                // Silent fail: points remain 0
            }
        }

        return $finalElevations;
    }
}
