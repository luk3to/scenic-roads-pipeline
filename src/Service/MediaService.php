<?php

namespace ScenicRoads\Service;

use ScenicRoads\Http\HttpClient;
use ScenicRoads\Http\HttpClientFactory;

class MediaService
{
    private const BASE_URI = '';
    private HttpClient $httpClient;

    public function __construct(
        HttpClientFactory $factory,
        private string $outputPath,
    ) {
        $this->httpClient = $factory->create(self::BASE_URI);
    }

    /**
     * Downloads an image and saves it with a specific filename.
     * 
     * This method leverages the parent download logic which:
     * 1. Checks if the file already exists locally to prevent duplicate downloads.
     * 2. Creates the directory structure if it's missing.
     *
     * @param string $url The full remote URL of the image.
     * @param string $fileName The desired local filename.
     * @return string The absolute path to the saved file.
     */
    public function downloadImage(string $url, string $fileName)
    {
        return $this->httpClient->downloadFile($url, $this->outputPath . '/images/' . $fileName);
    }
}
