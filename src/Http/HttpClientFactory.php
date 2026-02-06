<?php

namespace ScenicRoads\Http;

class HttpClientFactory
{
    public function __construct(
        private string $userAgent,
        private string $projectDir
    ) {}

    public function create(string $baseUri, int $limit = 1): HttpClient
    {
        return new HttpClient(
            $baseUri,
            $this->userAgent,
            $this->projectDir,
            $limit
        );
    }
}
