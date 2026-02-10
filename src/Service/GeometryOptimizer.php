<?php

namespace ScenicRoads\Service;

class GeometryOptimizer
{
    /**
     * Organizes raw geometry into ordered, continuous chains.
     * returns a MultiLineString.
     */
    public function optimize(array $geometry): array
    {
        // 1. Normalize input to a flat list of segments (array of arrays of points)
        $pool = $this->extractSegments($geometry);

        // 2. The container for our sorted chains
        // Each "Chain" is a valid, continuous LineString
        $chains = [];

        while (!empty($pool)) {
            // Start a new chain with the first available segment
            $currentChain = array_shift($pool);
            $extended = true;

            // Keep trying to grow this chain until we hit a dead end or a branch
            while ($extended) {
                $extended = false;

                // Look through the remaining pool for a connection
                foreach ($pool as $index => $segment) {
                    $mergeResult = $this->tryMerge($currentChain, $segment);

                    if ($mergeResult) {
                        $currentChain = $mergeResult;
                        unset($pool[$index]);
                        $extended = true;
                        // Break loop to restart search with the new, longer chain
                        break;
                    }
                }
            }

            // Chain is finished (hit a gap or branch), save it.
            $chains[] = $currentChain;
        }

        return [
            'type' => 'MultiLineString',
            'coordinates' => $chains
        ];
    }

    /**
     * Tries to attach a segment to the start or end of the current chain.
     */
    private function tryMerge(array $chain, array $segment): ?array
    {
        $chainStart = $chain[0];
        $chainEnd   = end($chain);
        $segStart   = $segment[0];
        $segEnd     = end($segment);

        // Tolerance (approx 10-20 meters)
        // Using squared Euclidean distance for speed
        $tolerance = 0.00005;

        // 1. Check: End of Chain -> Start of Segment (Normal flow)
        if ($this->distSq($chainEnd, $segStart) < $tolerance) {
            array_shift($segment); // Remove duplicate join point
            return array_merge($chain, $segment);
        }

        // 2. Check: End of Chain -> End of Segment (Segment is reversed)
        if ($this->distSq($chainEnd, $segEnd) < $tolerance) {
            $segment = array_reverse($segment);
            array_shift($segment);
            return array_merge($chain, $segment);
        }

        // 3. Check: Start of Chain -> End of Segment (Prepend normal)
        if ($this->distSq($chainStart, $segEnd) < $tolerance) {
            array_pop($segment); // Remove duplicate join point
            return array_merge($segment, $chain);
        }

        // 4. Check: Start of Chain -> Start of Segment (Prepend reversed)
        if ($this->distSq($chainStart, $segStart) < $tolerance) {
            $segment = array_reverse($segment);
            array_pop($segment);
            return array_merge($segment, $chain);
        }

        // No connection found
        return null;
    }

    private function extractSegments(array $geometry): array
    {
        if ($geometry['type'] === 'LineString') {
            return [$geometry['coordinates']];
        }
        return $geometry['coordinates'];
    }

    private function distSq(array $p1, array $p2): float
    {
        return pow($p1[0] - $p2[0], 2) + pow($p1[1] - $p2[1], 2);
    }
}
