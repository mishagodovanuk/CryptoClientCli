<?php

namespace App\Domain\Crypto\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class BaseHttpClient
{
    public const EXCHANGE_CODE = '';
    public const EXCHANGE_NAME = '';

    /**
     * @return string
     */
    public function code(): string
    {
        return static::EXCHANGE_CODE;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return static::EXCHANGE_NAME;
    }

    /**
     * @return string
     */
    abstract protected function baseUrl(): string;

    /**
     * @param string $baseUrl
     * @return PendingRequest
     */
    protected function http(string $baseUrl): PendingRequest
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
     * @param Response $response
     * @param string $logKey
     * @param array $context
     * @return bool
     */
    protected function guardOk(Response $response, string $logKey, array $context = []): bool
    {
        if ($response->ok()) {
            return true;
        }

        \Log::warning($logKey, $context + [
                'exchange' => $this->code(),
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

        return false;
    }

    /**
     * @param $json
     * @param string $logKey
     * @param array $context
     * @return bool
     */
    protected function guardArrayJson($json, string $logKey, array $context = []): bool
    {
        if (is_array($json)) {
            return true;
        }

        \Log::warning($logKey, $context + [
                'exchange' => $this->code(),
            ]);

        return false;
    }

    /**
     * @param string $failLogKey
     * @param callable $fn
     * @return array
     */
    protected function safeArray(string $failLogKey, callable $fn): array
    {
        try {
            $response = $fn();

            return is_array($response) ? $response : [];
        } catch (\Throwable $e) {
            \Log::warning($failLogKey, [
                'exchange' => $this->code(),
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param array $pairs
     * @return array
     */
    protected function finalizePairs(array $pairs): array
    {
        $pairs = array_values(array_unique(array_filter($pairs)));
        sort($pairs);

        return $pairs;
    }

    /**
     * @param string $path
     * @param array $query
     * @param string $failLogKey
     * @param string $invalidJsonLogKey
     * @param array $context
     * @return array
     */
    protected function getArrayJson(
        string $path,
        array $query,
        string $failLogKey,
        string $invalidJsonLogKey,
        array $context = []
    ): array {
        return $this->safeArray($failLogKey, function () use ($path, $query, $failLogKey, $invalidJsonLogKey, $context) {
            $response = $this->http($this->baseUrl())->get($path, $query);

            if (!$this->guardOk($response, $failLogKey, $context)) {
                return [];
            }

            $json = $response->json();

            if (!$this->guardArrayJson($json, $invalidJsonLogKey, $context)) {
                return [];
            }

            return $json;
        });
    }

    /**
     * @param string $path
     * @param array $query
     * @param string $failLogKey
     * @param string $invalidJsonLogKey
     * @param string $dotPath
     * @param array $context
     * @return array
     */
    protected function getArrayJsonPath(
        string $path,
        array $query,
        string $failLogKey,
        string $invalidJsonLogKey,
        string $dotPath,
        array $context = []
    ): array {
        return $this->safeArray($failLogKey, function () use ($path, $query, $failLogKey, $invalidJsonLogKey, $dotPath, $context) {
            $response = $this->http($this->baseUrl())->get($path, $query);

            if (!$this->guardOk($response, $failLogKey, $context)) {
                return [];
            }

            $json = $response->json();

            if (!is_array($json)) {
                $this->guardArrayJson($json, $invalidJsonLogKey, $context);

                return [];
            }

            $value = $this->arrayGetDot($json, $dotPath);

            if (!$this->guardArrayJson($value, $invalidJsonLogKey, $context)) {
                return [];
            }

            return $value;
        });
    }

    /**
     * @param array $data
     * @param string $path
     * @return array|mixed|null
     */
    protected function arrayGetDot(array $data, string $path)
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
