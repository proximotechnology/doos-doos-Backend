<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ProcessOpenRouterRequest;
use Illuminate\Support\Str;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;

class OpenRouterService
{
    protected Client $client;
    protected string $apiKey;
    protected string $baseUrl = 'https://openrouter.ai/api/v1';
    protected array $defaultOptions = [
        'max_tokens' => 100,
        'temperature' => 0.7,
    ];
    protected bool $enableCache = true;
    protected int $cacheTtl = 3600; // 1 hour
    protected bool $useQueue = false;
    protected string $queueName = 'default';
    protected CacheRepository $cacheStore;
    protected bool $enableRateLimiting = true;
    protected int $rateLimit = 10; // requests per minute
    protected bool $logRequests = false;

    public function __construct(
        ?Client $client = null,
        ?string $apiKey = null,
        ?CacheRepository $cacheStore = null
    ) {
        $this->client = $client ?? new Client([
            'timeout' => 15,
            'connect_timeout' => 3,
            'http_errors' => false,
        ]);

        $this->apiKey = $apiKey ?? env('OPENROUTER_API_KEY');
        $this->cacheStore = $cacheStore ?? Cache::store();
    }

    /**
     * Send chat request with caching and queue support
     */
    public function chat(array $messages, string $model = 'anthropic/claude-2', array $options = []): array
    {
        $this->validateInput($messages, $model, $options);

        if ($this->enableRateLimiting) {
            $this->checkRateLimit();
        }

        $cacheKey = $this->generateCacheKey($messages, $model, $options);

        if ($this->enableCache && $this->cacheStore->has($cacheKey)) {
            return $this->cacheStore->get($cacheKey);
        }

        $payload = $this->buildPayload($messages, $model, $options);

        if ($this->useQueue) {
            return $this->dispatchToQueue($cacheKey, $payload);
        }

        return $this->makeCachedRequest($cacheKey, 'chat/completions', $payload);
    }

    /**
     * Validate input parameters
     */
    protected function validateInput(array $messages, string $model, array $options): void
    {
        if (empty($messages)) {
            throw new \InvalidArgumentException('Messages array cannot be empty');
        }

        if (!is_string($model) || empty($model)) {
            throw new \InvalidArgumentException('Model must be a non-empty string');
        }

        foreach ($messages as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                throw new \InvalidArgumentException('Each message must have role and content');
            }
        }
    }

    /**
     * Check and enforce rate limiting
     */
    protected function checkRateLimit(): void
    {
        $rateLimitKey = 'openrouter_rate_limit_' . md5($this->apiKey);
        $current = $this->cacheStore->get($rateLimitKey, 0);

        if ($current >= $this->rateLimit) {
            throw new \Exception('Rate limit exceeded. Please try again later.');
        }

        $this->cacheStore->put($rateLimitKey, $current + 1, now()->addMinutes(1));
    }

    /**
     * Dispatch request to queue
     */
    protected function dispatchToQueue(string $cacheKey, array $payload): array
    {
        $job = new ProcessOpenRouterRequest(
            $this->apiKey,
            $cacheKey,
            $payload,
            $this->enableCache,
            $this->cacheTtl,
            $this->getHeaders()
        );

        Queue::pushOn($this->queueName, $job);

        if ($this->logRequests) {
            Log::info('OpenRouter request queued', [
                'cache_key' => $cacheKey,
                'queue' => $this->queueName
            ]);
        }

        return [
            'status' => 'queued',
            'message' => 'Request has been added to the queue',
            'cache_key' => $cacheKey,
            'queue' => $this->queueName
        ];
    }

    /**
     * Make request with caching support
     */
    protected function makeCachedRequest(string $cacheKey, string $endpoint, array $payload): array
    {
        try {
            $startTime = microtime(true);

            $response = $this->makeAsyncRequest($endpoint, $payload);
            $result = $this->processResponse($response);

            if ($this->enableCache) {
                $this->cacheStore->put($cacheKey, $result, $this->cacheTtl);
            }

            if ($this->logRequests) {
                Log::info('OpenRouter API request completed', [
                    'endpoint' => $endpoint,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'cache_key' => $cacheKey
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            if ($this->logRequests) {
                Log::error('OpenRouter API request failed', [
                    'error' => $e->getMessage(),
                    'payload' => $payload
                ]);
            }
            throw $e;
        }
    }

    /**
     * Make asynchronous request for better performance
     */
    protected function makeAsyncRequest(string $endpoint, array $payload): ResponseInterface
    {
        $promise = $this->client->postAsync("{$this->baseUrl}/{$endpoint}", [
            'headers' => $this->getHeaders(),
            'json' => $payload,
        ]);

        return $promise->wait();
    }

    /**
     * Generate unique cache key based on request parameters
     */
    protected function generateCacheKey(array $messages, string $model, array $options): string
    {
        return 'openrouter_'.md5(json_encode([
            'messages' => $messages,
            'model' => $model,
            'options' => array_merge($this->defaultOptions, $options),
        ]));
    }

    /**
     * Build the request payload
     */
    protected function buildPayload(array $messages, string $model, array $options): array
    {
        return array_merge($this->defaultOptions, $options, [
            'model' => $model,
            'messages' => $messages,
        ]);
    }

    /**
     * Get optimized request headers
     */
    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Accept-Encoding' => 'gzip',
            'HTTP-Referer' => config('app.url'),
            'X-Title' => config('app.name'),
        ];
    }

    /**
     * Process response with performance optimizations
     */
    protected function processResponse(ResponseInterface $response): array
    {
        $content = $response->getBody()->getContents();

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \Exception('Failed to decode JSON response: ' . $e->getMessage());
        }

        if ($response->getStatusCode() !== 200) {
            $errorMessage = $data['error']['message'] ?? $content;
            throw new \Exception("API Error: {$errorMessage}", $response->getStatusCode());
        }

        return $data;
    }

    /**
     * Enable/disable caching
     */
    public function setCacheEnabled(bool $enabled): self
    {
        $this->enableCache = $enabled;
        return $this;
    }

    /**
     * Set cache TTL
     */
    public function setCacheTtl(int $seconds): self
    {
        $this->cacheTtl = $seconds;
        return $this;
    }

    /**
     * Set default options
     */
    public function setDefaultOptions(array $options): self
    {
        $this->defaultOptions = array_merge($this->defaultOptions, $options);
        return $this;
    }

    /**
     * Enable/disable queue usage
     */
    public function setQueueEnabled(bool $enabled, string $queueName = 'default'): self
    {
        $this->useQueue = $enabled;
        $this->queueName = $queueName;
        return $this;
    }

    /**
     * Enable/disable rate limiting
     */
    public function setRateLimitEnabled(bool $enabled, int $limit = 10): self
    {
        $this->enableRateLimiting = $enabled;
        $this->rateLimit = $limit;
        return $this;
    }

    /**
     * Enable/disable request logging
     */
    public function setRequestLogging(bool $enabled): self
    {
        $this->logRequests = $enabled;
        return $this;
    }

    /**
     * Flush cache for specific parameters
     */
    public function flushCache(array $messages, string $model, array $options = []): bool
    {
        $cacheKey = $this->generateCacheKey($messages, $model, $options);
        return $this->cacheStore->forget($cacheKey);
    }

    /**
     * Get current cache store
     */
    public function getCacheStore(): CacheRepository
    {
        return $this->cacheStore;
    }
}
