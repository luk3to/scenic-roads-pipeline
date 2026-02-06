<?php

namespace ScenicRoads\Utils;

class JsonHandler
{
    /**
     * Save data to JSON file
     * 
     * @param array $data Array of RoadDTO objects
     * @param string $path Full path to the output file
     */
    public function save(array $data, string $path): void
    {
        $dir = dirname($path);

        // Ensure the directory exists
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Load JSON file into array
     */
    public function load(string $path): array
    {
        if (!file_exists($path)) {
            throw new \Exception("Local source file missing: $path");
            return [];
        }

        $content = file_get_contents($path);
        return json_decode($content, true) ?? [];
    }
}
