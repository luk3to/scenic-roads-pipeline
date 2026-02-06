<?php

namespace ScenicRoads\Service;

use ScenicRoads\Http\HttpClient;
use ScenicRoads\Http\HttpClientFactory;
use ScenicRoads\Model\RoadDTO;
use ScenicRoads\Model\RawRoadData;

class AIService
{
    private HttpClient $httpClient;

    public function __construct(
        HttpClientFactory $factory,
        private string $baseUri,
        private string $model,
        private string $maxTokens,
        private string $temperature
    ) {
        $this->httpClient = $factory->create($baseUri);
    }

    /**
     * Sends a prompt to the AI model and sanitizes the output.
     *
     * @param string $prompt The user instruction or data to process.
     * @param float $temperature Controls randomness (0 for deterministic, 1+ for creative).
     * @return string The cleaned, plain-text response from the AI.
     */
    private function ask(string $prompt, float $temperature)
    {
        $payload = [
            'json' => [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => $this->maxTokens,
                'temperature' => $temperature ?? $this->temperature,
                'stream' => false,
                // Force direct response, no reasoning
                'stop' => ["<|im_start|>", "<|im_end|>"]
            ]
        ];

        $rawResponse = $this->httpClient->post('', $payload)['choices'][0]['message']['content'];

        // Remove <think>...</think> blocks if present
        $cleanResponse = preg_replace([
            '/<think>.*?<\/think>/is',
            '/<think>.*$/is'
        ], '', $rawResponse);

        return trim($cleanResponse) ?? '';
    }

    /**
     * Normalizes a messy GIS description into a clean, official road name.
     * eg. "SUNCOAST SCENIC PKWY" -> "Suncoast Scenic Parkway"
     */
    public function getCleanName(RawRoadData $road, string $stateName, string $countryIso2): string
    {
        $prompt = <<<PROMPT
            Identify the official, primary human-readable name for the following geographic entity.

            INPUT DATA:
            - Raw GIS Name: "{$road->name}"
            - State: "{$stateName}"
            - Country ISO: "{$countryIso2}"

            TASK:
            Normalize the "Raw GIS Name" into its full, official title used by Wikipedia and official maps.

            CONSTRAINTS:
            1. Expand all abbreviations (e.g., "PKWY" to "Parkway", "CR" to "County Road").
            2. Do not include specific county names unless they are part of the official road title.
            3. If it is a State Road or Route, use the format "[State] State Road [Number]" or the most common local naming convention.
            4. Return ONLY the plain text string. No quotes, no markdown, and no internal reasoning.
            PROMPT;

        return $this->ask($prompt, 0);
    }

    /**
     * Generates description for a specific road.
     * 
     * Uses a structured prompt based on the RoadDTO properties.
     *
     * @param RoadDTO $road The road data object.
     * @return string The AI-generated description.
     */
    public function createDescription(RoadDTO $road): string
    {
        $prompt = <<<PROMPT
            Act as a travel writer for a high-end automotive magazine. 
            Write a vivid, 2-3 sentence description for the '{$road->name}' scenic route in {$road->stateIso2}, {$road->countryIso2}.

            STYLE GUIDELINES:
            1. FOCUS: Highlight specific driving appeal—winding curves, elevation changes, or iconic roadside vistas.
            2. TONE: Engaging, aspirational, and adventurous.
            3. LANDMARKS: If the road is known for a specific bridge, mountain pass, or coastal view, include it.
            4. CONSTRAINT: Do not start with "The [Road Name] is..." or "Located in...". Jump straight into the experience.
            5. LENGTH: Strictly 2 to 3 sentences. No more than 60 words.

            Output only the description text. No quotes or introductory filler.
            PROMPT;

        return $this->ask($prompt, 0.7);
    }
}
