<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Contracts\EmbeddingModelInterface;
use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Ollama\OllamaOptions;
use AiSdk\Ollama\OllamaProvider;

final class Ollama
{
    private static ?OllamaProvider $default = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public static function create(array $config = []): OllamaProvider
    {
        return self::$default = new OllamaProvider(OllamaOptions::fromArray($config));
    }

    public static function default(): OllamaProvider
    {
        return self::$default ??= self::create();
    }

    public static function reset(): void
    {
        self::$default = null;
    }

    public static function model(string $modelId): TextModelInterface
    {
        return self::default()->textModel($modelId);
    }

    public static function image(string $modelId): ImageModelInterface
    {
        return self::default()->imageModel($modelId);
    }

    public static function embedding(string $modelId): EmbeddingModelInterface
    {
        return self::default()->embeddingModel($modelId);
    }

    /** @return array<int, ModelDefinition> */
    public static function availableModels(): array
    {
        return self::default()->availableModels();
    }

    public static function inspectModel(string $modelId): ModelDefinition
    {
        return self::default()->inspectModel($modelId);
    }
}
