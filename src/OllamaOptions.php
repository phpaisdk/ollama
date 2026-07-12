<?php

declare(strict_types=1);

namespace AiSdk\Ollama;

use AiSdk\Support\Sdk;
use AiSdk\Utils\Support\Env;
use AiSdk\Utils\Support\Url;

final class OllamaOptions
{
    public const string DEFAULT_BASE_URL = 'http://localhost:11434/v1';

    public const string PROVIDER_NAME = 'ollama';

    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly ?string $apiKey,
        public readonly OllamaApi $api = OllamaApi::ChatCompletions,
        public readonly string $baseUrl = self::DEFAULT_BASE_URL,
        public readonly string $nativeBaseUrl = 'http://localhost:11434',
        public readonly array $headers = [],
        public readonly ?Sdk $sdk = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config = []): self
    {
        $apiKey = Env::loadOptionalSetting(isset($config['apiKey']) ? (string) $config['apiKey'] : null, 'OLLAMA_API_KEY');
        $api = OllamaApi::resolve($config['api'] ?? null);

        $baseUrl = Url::withoutTrailingSlash(
            Env::loadOptionalSetting(isset($config['baseUrl']) ? (string) $config['baseUrl'] : null, 'OLLAMA_BASE_URL')
                ?? self::DEFAULT_BASE_URL,
        );

        $nativeBaseUrl = Url::withoutTrailingSlash(
            Env::loadOptionalSetting(isset($config['nativeBaseUrl']) ? (string) $config['nativeBaseUrl'] : null, 'OLLAMA_NATIVE_BASE_URL')
                ?? (string) preg_replace('#/v1$#', '', $baseUrl),
        );

        /** @var array<string, string> $headers */
        $headers = isset($config['headers']) && is_array($config['headers']) ? $config['headers'] : [];
        $sdk = $config['sdk'] ?? null;

        return new self(
            apiKey: ($apiKey !== null && $apiKey !== '') ? $apiKey : null,
            api: $api,
            baseUrl: $baseUrl,
            nativeBaseUrl: $nativeBaseUrl,
            headers: $headers,
            sdk: $sdk instanceof Sdk ? $sdk : null,
        );
    }

    /**
     * @return array<string, string>
     */
    public function authHeaders(): array
    {
        $headers = $this->headers;
        if ($this->apiKey !== null) {
            $headers = array_merge(['Authorization' => 'Bearer ' . $this->apiKey], $headers);
        }

        return $headers;
    }
}
