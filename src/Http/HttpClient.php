<?php

namespace ScenicRoads\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use Spatie\GuzzleRateLimiterMiddleware\RateLimiterMiddleware;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class HttpClient
{
    protected Client $client;
    protected CacheInterface $cache;

    public function __construct(
        protected string $baseUri,
        protected string $userAgent,
        protected string $projectDir,
        protected int $limitPerSecond = 1,
    ) {
        $this->cache = new FilesystemAdapter('', 0, $projectDir . '/var/cache');

        $stack = HandlerStack::create();
        $stack->push(RateLimiterMiddleware::perSecond($this->limitPerSecond));

        $this->client = new Client([
            'handler' => $stack,
            'base_uri' => $this->baseUri,
            'headers' => [
                'User-Agent' => $userAgent,
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Executes a standardized GET request with integrated caching and rate limiting.
     * 
     * This method first checks the local cache for a matching request hash.
     * - If found (Cache Hit): Returns the data immediately.
     * - If not found (Cache Miss): Performs a live HTTP request, populates the 
     * cache for 1 week, and flags the event as a network operation.
     *
     * @param string $endpoint    The API route (relative to baseUri).
     * @param array  $queryParams Associative array of GET parameters.
     * @return array The decoded JSON response as an associative array.
     * @throws \Exception If the network request fails or the response is invalid.
     */
    public function get(string $endpoint, array $queryParams = [])
    {
        $cacheKey = $this->getCacheKey('get', $endpoint, $queryParams);

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($endpoint, $queryParams) {
                $item->expiresAfter(604800);
                $response = $this->client->request('GET', $endpoint, [
                    'query' => $queryParams
                ]);
                return json_decode((string)$response->getBody(), true);
            });
        } catch (RequestException $e) {
            throw new \Exception("API Error in " . get_class($this) . ": " . $e->getMessage());
        }
    }

    /**
     * Executes a standardized POST request with integrated caching and rate limiting.
     * 
     * Even though POST requests are typically non-idempotent, this method caches 
     * responses to optimize data retrieval during the pipeline execution. If the 
     * request is not cached, it triggers a live network call.
     * This is primarily used for AI generation requests. Caching here is critical
     * to avoid redundant token costs and to ensure data consistency across multiple
     * pipeline runs for the same prompt.
     *
     * @param string $endpoint The API endpoint (relative to baseUri).
     * @param array  $params   The request options
     * @return array The decoded JSON response.
     * @throws \Exception If the API request fails or returns a client/server error.
     */
    public function post(string $endpoint, array $params = [])
    {
        $cacheKey = $this->getCacheKey('post', $endpoint, $params);

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($endpoint, $params) {
                $item->expiresAfter(604800);
                $response = $this->client->request('POST', $endpoint, $params);
                return json_decode($response->getBody(), true);
            });
        } catch (RequestException $e) {
            throw new \Exception("API Error in " . get_class($this) . ": " . $e->getMessage());
        }
    }

    /**
     * Downloads a file if it does not already exist at the specified path.
     *
     * @param string $url      The absolute URL of the file.
     * @param string $savePath The local destination path.
     * @return string The local path to the file
     * @throws \Exception If the download fails or directory is unwritable.
     */
    public function downloadFile(string $url, string $savePath)
    {
        // Check if the file already exists locally
        if (file_exists($savePath)) {
            return file_get_contents($savePath);
        }

        try {
            // Ensure the directory exists
            $directory = dirname($savePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Perform the download
            $response = $this->client->request('GET', $url, [
                'stream' => true,
                'headers' => [
                    'Accept' => 'image/*, application/octet-stream',
                ],
            ]);

            $data = (string)$response->getBody();

            // Save to disk
            if (file_put_contents($savePath, $data) === false) {
                throw new \RuntimeException("Failed to write to: $savePath");
            }

            return $savePath;
        } catch (RequestException $e) {
            throw new \Exception("Download Error in " . get_class($this) . ": " . $e->getMessage());
        }
    }

    /**
     * Generates a unique MD5 hash for a given request.
     * 
     * This ensures that different API endpoints, HTTP methods, and query 
     * parameters are cached independently.
     *
     * @param string $method   The HTTP verb (GET, POST, etc.)
     * @param string $endpoint The API route/path
     * @param array  $params   The query parameters or POST body data
     * @return string A unique 32-character hexadecimal string prefixed with 'http_'
     */
    private function getCacheKey(string $method, string $endpoint, array $params): string
    {
        return 'http_' . md5($method . $this->baseUri . $endpoint . serialize($params));
    }
}
