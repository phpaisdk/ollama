<?php

declare(strict_types=1);

namespace AiSdk\Ollama\Models;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Ollama\OllamaOptions;
use AiSdk\OpenAICompatible\ImageRequestBuilder;
use AiSdk\OpenAICompatible\ImageResponseParser;
use AiSdk\Requests\ImageRequest;
use AiSdk\Responses\ImageResponse;
use AiSdk\Utils\Support\Url;

final class OllamaImageModel extends BaseModel implements ImageModelInterface
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

    public function generate(ImageRequest $request): ImageResponse
    {
        $body = ImageRequestBuilder::build($this->modelId, $this->provider(), $request);
        $payload = $this->runner($this->options->sdk)->postJson(
            Url::joinPath($this->options->baseUrl, '/images/generations'),
            $body,
            $this->options->authHeaders(),
            $this->provider(),
        );

        return ImageResponseParser::parse($payload, $this->provider());
    }
}
