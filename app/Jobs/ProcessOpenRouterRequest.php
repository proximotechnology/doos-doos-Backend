<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Support\Facades\Log;

class ProcessOpenRouterRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [5, 30, 60];

    public function __construct(
        protected string $apiKey,
        protected string $cacheKey,
        protected array $payload,
        protected bool $enableCache,
        protected int $cacheTtl,
        protected array $headers
    ) {}

    public function handle()
    {
        $client = new Client([
            'timeout' => 15,
            'connect_timeout' => 3,
        ]);

        try {
            $response = $client->post('https://openrouter.ai/api/v1/chat/completions', [
                'headers' => $this->headers,
                'json' => $this->payload,
            ]);

            $data = $this->processResponse($response);

            if ($this->enableCache) {
                Cache::put($this->cacheKey, $data, $this->cacheTtl);
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('OpenRouter Job Failed: ' . $e->getMessage(), [
                'payload' => $this->payload
            ]);
            throw $e;
        }
    }

    protected function processResponse(ResponseInterface $response): array
    {
        $content = $response->getBody()->getContents();
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception($data['error']['message'] ?? 'API request failed');
        }

        return $data;
    }

    public function failed(\Throwable $exception)
    {
        Log::critical('OpenRouter Job Failed After Retries: ' . $exception->getMessage());
    }
}
