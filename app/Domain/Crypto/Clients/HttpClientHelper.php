<?php

namespace App\Domain\Crypto\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client helper for exchange clients.
 */
final class HttpClientHelper
{
    /**
     * Create a configured HTTP client.
     */
    public function client(string $baseUrl): PendingRequest
    {
        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('crypto.http.timeout'))
            ->connectTimeout((int) config('crypto.http.connect_timeout'))
            ->retry(
                (int) config('crypto.http.retry_times'),
                (int) config('crypto.http.retry_sleep_ms')
            );
    }

    /**
     * Check if response is OK.
     *
     * @param Response $response
     * @param string $logKey Log key for warnings
     * @param string $exchangeCode Exchange code for logging context
     * @param array $context Additional context for logging
     * @return bool
     */
    public function guardOk(Response $response, string $logKey, string $exchangeCode, array $context = []): bool
    {
        if ($response->ok()) {
            return true;
        }

        Log::warning($logKey, $context + [
            'exchange' => $exchangeCode,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    }

    /**
     * Validate that JSON response is an array.
     */
    public function guardArrayJson($json, string $logKey, string $exchangeCode, array $context = []): bool
    {
        if (is_array($json)) {
            return true;
        }

        Log::warning($logKey, $context + [
            'exchange' => $exchangeCode,
        ]);

        return false;
    }

    /**
     * Safely execute a callable and return array
     *
     * @param callable $fn Function that returns array
     * @param string $failLogKey Log key for errors
     * @param string $exchangeCode Exchange code for logging
     */
    public function safeArray(callable $fn, string $failLogKey, string $exchangeCode): array
    {
        try {
            $response = $fn();

            return is_array($response) ? $response : [];
        } catch (\Throwable $e) {
            Log::warning($failLogKey, [
                'exchange' => $exchangeCode,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Finalize pairs array.
     */
    public function finalizePairs(array $pairs): array
    {
        $pairs = array_values(array_unique(array_filter($pairs)));
        sort($pairs);

        return $pairs;
    }

    /**
     * Get JSON array from API endpoint with error handling.
     *
     * @param string $baseUrl Base URL for the exchange
     * @param string $path API path
     * @param array $query Query parameters
     * @param string $failLogKey Log key for HTTP failures
     * @param string $invalidJsonLogKey Log key for invalid JSON
     * @param string $exchangeCode Exchange code for logging
     * @param array $context Additional context
     */
    public function getArrayJson(
        string $baseUrl,
        string $path,
        array $query,
        string $failLogKey,
        string $invalidJsonLogKey,
        string $exchangeCode,
        array $context = []
    ): array {
        return $this->safeArray(
            function () use ($baseUrl, $path, $query, $failLogKey, $invalidJsonLogKey, $exchangeCode, $context) {
                $response = $this->client($baseUrl)->get($path, $query);

                if (!$this->guardOk($response, $failLogKey, $exchangeCode, $context)) {
                    return [];
                }

                $json = $response->json();

                if (!$this->guardArrayJson($json, $invalidJsonLogKey, $exchangeCode, $context)) {
                    return [];
                }

                return $json;
            },
            $failLogKey,
            $exchangeCode
        );
    }

    /**
     * Get JSON array from API endpoint.
     *
     * @param string $baseUrl Base URL for the exchange
     * @param string $path API path
     * @param array $query Query parameters
     * @param string $failLogKey Log key for HTTP failures
     * @param string $invalidJsonLogKey Log key for invalid JSON
     * @param string $dotPath Dot notation path to extract (e.g., 'result.list')
     * @param string $exchangeCode Exchange code for logging
     * @param array $context Additional context
     */
    public function getArrayJsonPath(
        string $baseUrl,
        string $path,
        array $query,
        string $failLogKey,
        string $invalidJsonLogKey,
        string $dotPath,
        string $exchangeCode,
        array $context = []
    ): array {
        return $this->safeArray(
            function () use ($baseUrl, $path, $query, $failLogKey, $invalidJsonLogKey, $dotPath, $exchangeCode, $context) {
                $response = $this->client($baseUrl)->get($path, $query);

                if (!$this->guardOk($response, $failLogKey, $exchangeCode, $context)) {
                    return [];
                }

                $json = $response->json();

                if (!is_array($json)) {
                    $this->guardArrayJson($json, $invalidJsonLogKey, $exchangeCode, $context);

                    return [];
                }

                $value = $this->arrayGetDot($json, $dotPath);

                if (!$this->guardArrayJson($value, $invalidJsonLogKey, $exchangeCode, $context)) {
                    return [];
                }

                return $value;
            },
            $failLogKey,
            $exchangeCode
        );
    }

    /**
     * Get value from array.
     *
     * @param array $data
     * @param string $path Dot notation path (e.g., 'result.list')
     * @return mixed|null
     */
    public function arrayGetDot(array $data, string $path): mixed
    {
        $segments = explode('.', $path);
        $value = $data;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}

