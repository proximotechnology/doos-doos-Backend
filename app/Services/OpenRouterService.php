<?php

namespace App\Services;

use GuzzleHttp\Client;

class OpenRouterService
{
    protected $client;
    protected $apiKey;
    protected $baseUrl = 'https://openrouter.ai/api/v1';

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = env('OPENROUTER_API_KEY');
    }

public function chat(array $messages, string $model = 'openai/gpt-3.5-turbo')
{
    try {
        $response = $this->client->post("{$this->baseUrl}/chat/completions", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'messages' => $messages,
            ],
        ]);

        return json_decode($response->getBody(), true);
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        $errorResponse = json_decode($e->getResponse()->getBody(), true);
        throw new \Exception('API Error: ' . ($errorResponse['error']['message'] ?? 'Unknown error'));
    }
}
}
