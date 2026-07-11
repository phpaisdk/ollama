<?php

declare(strict_types=1);

namespace AiSdk\Ollama\Models;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\EmbeddingModelInterface;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Ollama\OllamaOptions;
use AiSdk\Requests\EmbeddingRequest;
use AiSdk\Responses\EmbeddingResponse;
use AiSdk\Results\EmbeddingData;
use AiSdk\Support\Usage;
use AiSdk\Utils\Support\Url;

final class OllamaEmbeddingModel extends BaseModel implements EmbeddingModelInterface
{
    public function __construct(
        private readonly string $modelId,
        private readonly OllamaOptions $options,
    ) {}

    public function provider(): string
    {
        return OllamaOptions::PROVIDER_NAME;
    }

    public function modelId(): string
    {
        return $this->modelId;
    }

    public function generate(EmbeddingRequest $request): EmbeddingResponse
    {
        $body = array_filter([
            'model' => $this->modelId,
            'input' => $request->inputs,
            'dimensions' => $request->dimensions,
        ], static fn(mixed $value): bool => $value !== null);

        $providerOptions = $request->providerOptionsFor($this->provider());
        $raw = $providerOptions['raw'] ?? null;
        unset($providerOptions['raw']);
        $body = array_replace_recursive($body, $providerOptions);
        if (is_array($raw)) {
            $body = array_replace_recursive($body, $raw);
        }

        $payload = $this->runner($this->options->sdk)->postJson(
            Url::joinPath($this->options->nativeBaseUrl, '/api/embed'),
            $body,
            $this->options->authHeaders(),
            $this->provider(),
        );

        $embeddings = [];
        $valuesByInput = $payload['embeddings'] ?? null;
        if (! is_array($valuesByInput) || count($valuesByInput) !== count($request->inputs)) {
            throw InvalidResponseException::forProvider(
                $this->provider(),
                'Ollama returned an unexpected number of embeddings.',
                ['body' => $payload],
            );
        }

        foreach ($valuesByInput as $index => $values) {
            $vector = $this->vector($values);
            if ($vector === []) {
                throw InvalidResponseException::forProvider(
                    $this->provider(),
                    'Ollama returned an invalid embedding.',
                    ['body' => $payload, 'inputIndex' => $index],
                );
            }

            $embeddings[] = new EmbeddingData($vector, (int) $index);
        }

        $inputTokens = is_numeric($payload['prompt_eval_count'] ?? null)
            ? (int) $payload['prompt_eval_count']
            : 0;

        return new EmbeddingResponse(
            embeddings: $embeddings,
            usage: new Usage(inputTokens: $inputTokens),
            rawResponse: $payload,
            providerMetadata: [
                $this->provider() => array_filter([
                    'model' => is_string($payload['model'] ?? null) ? $payload['model'] : $this->modelId,
                    'total_duration' => is_numeric($payload['total_duration'] ?? null) ? (int) $payload['total_duration'] : null,
                    'load_duration' => is_numeric($payload['load_duration'] ?? null) ? (int) $payload['load_duration'] : null,
                ], static fn(mixed $value): bool => $value !== null),
            ],
        );
    }

    /**
     * @return array<int, float>
     */
    private function vector(mixed $values): array
    {
        if (! is_array($values) || $values === []) {
            return [];
        }

        $vector = [];
        foreach ($values as $value) {
            if (! is_int($value) && ! is_float($value)) {
                return [];
            }

            $vector[] = (float) $value;
        }

        return $vector;
    }
}
