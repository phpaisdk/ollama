<?php

declare(strict_types=1);

namespace AiSdk\Ollama;

use AiSdk\Capability;
use AiSdk\Contracts\AvailableModelsProviderInterface;
use AiSdk\Contracts\BaseProvider;
use AiSdk\Contracts\EmbeddingModelInterface;
use AiSdk\Contracts\EmbeddingProviderInterface;
use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Contracts\ImageProviderInterface;
use AiSdk\Contracts\ModelInspectionProviderInterface;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Contracts\TextProviderInterface;
use AiSdk\Generate;
use AiSdk\ModelDefinition;
use AiSdk\Ollama\Models\OllamaEmbeddingModel;
use AiSdk\Ollama\Models\OllamaImageModel;
use AiSdk\Ollama\Models\OllamaTextModel;
use AiSdk\Utils\Http\HttpRunner;
use AiSdk\Utils\Support\Url;

final class OllamaProvider extends BaseProvider implements AvailableModelsProviderInterface, EmbeddingProviderInterface, ImageProviderInterface, ModelInspectionProviderInterface, TextProviderInterface
{
    public function __construct(public readonly OllamaOptions $options) {}

    public function name(): string
    {
        return OllamaOptions::PROVIDER_NAME;
    }

    protected function textModel(string $modelId): TextModelInterface
    {
        return new OllamaTextModel($modelId, $this->options);
    }

    protected function imageModel(string $modelId): ImageModelInterface
    {
        return new OllamaImageModel($modelId, $this->options);
    }

    protected function embeddingModel(string $modelId): EmbeddingModelInterface
    {
        return new OllamaEmbeddingModel($modelId, $this->options);
    }

    public function availableModels(): array
    {
        $payload = $this->runner()->getJson(
            Url::joinPath($this->options->nativeBaseUrl, '/api/tags'),
            $this->options->authHeaders(),
            $this->name(),
        );
        $models = [];

        foreach (($payload['models'] ?? []) as $model) {
            if (! is_array($model)) {
                continue;
            }

            $id = $model['model'] ?? $model['name'] ?? null;
            if (! is_string($id) || $id === '') {
                continue;
            }

            $models[] = new ModelDefinition(
                id: $id,
                metadata: array_filter([
                    'modified_at' => $model['modified_at'] ?? null,
                    'size' => $model['size'] ?? null,
                    'digest' => $model['digest'] ?? null,
                    'details' => is_array($model['details'] ?? null) ? $model['details'] : null,
                ], static fn(mixed $value): bool => $value !== null),
            );
        }

        return $models;
    }

    public function inspectModel(string $modelId): ModelDefinition
    {
        $payload = $this->runner()->postJson(
            Url::joinPath($this->options->nativeBaseUrl, '/api/show'),
            ['model' => $modelId, 'verbose' => false],
            $this->options->authHeaders(),
            $this->name(),
        );
        $nativeCapabilities = array_values(array_filter(
            is_array($payload['capabilities'] ?? null) ? $payload['capabilities'] : [],
            is_string(...),
        ));
        $model = new ModelDefinition(
            id: $modelId,
            capabilities: $this->mapCapabilities($nativeCapabilities),
            adaptedCapabilities: in_array('completion', $nativeCapabilities, true)
                ? ['structured_output' => ['strategy' => 'JSON schema passed through the response format']]
                : [],
            metadata: array_filter([
                'modified_at' => $payload['modified_at'] ?? null,
                'details' => is_array($payload['details'] ?? null) ? $payload['details'] : null,
                'model_info' => is_array($payload['model_info'] ?? null) ? $payload['model_info'] : null,
                'ollama_capabilities' => $nativeCapabilities,
            ], static fn(mixed $value): bool => $value !== null),
        );

        return $model;
    }

    private function runner(): HttpRunner
    {
        return HttpRunner::fromSdk($this->options->sdk ?? Generate::sdk());
    }

    /**
     * @param  array<int, string>  $capabilities
     * @return array<int, Capability>
     */
    private function mapCapabilities(array $capabilities): array
    {
        $mapped = [];

        if (in_array('completion', $capabilities, true)) {
            $mapped = [Capability::TextGeneration, Capability::Streaming, Capability::TextInput];
        }

        $optional = [
            'vision' => Capability::ImageInput,
            'audio' => Capability::AudioInput,
            'tools' => Capability::ToolCalling,
            'thinking' => Capability::Reasoning,
            'image' => Capability::ImageGeneration,
            'image_generation' => Capability::ImageGeneration,
            'embedding' => Capability::Embedding,
        ];

        foreach ($optional as $native => $capability) {
            if (in_array($native, $capabilities, true)) {
                $mapped[$capability->name()] = $capability;
            }
        }

        return array_values($mapped);
    }
}
