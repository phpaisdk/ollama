<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Contracts\Model;
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

    public static function model(string $modelId): Model
    {
        return self::default()->model($modelId);
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
