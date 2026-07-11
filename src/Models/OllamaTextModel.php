<?php

declare(strict_types=1);

namespace AiSdk\Ollama\Models;

use AiSdk\Capability;
use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Ollama\OllamaOptions;
use AiSdk\OpenAICompatible\ChatRequestBuilder;
use AiSdk\OpenAICompatible\ChatResponseParser;
use AiSdk\OpenAICompatible\ChatStreamParser;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Utils\Support\Url;
use Generator;

final class OllamaTextModel extends BaseModel implements TextModelInterface
{
    private const array ADAPTER_CAPABILITIES = [
        Capability::TextGeneration,
        Capability::Streaming,
        Capability::TextInput,
    ];

    private const array ADAPTED_CAPABILITIES = [
        'structured_output' => 'JSON schema passed through the response format',
    ];

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

    public function generate(TextModelRequest $request): TextModelResponse
    {
        $this->ensureTextRequestSupported($request, self::ADAPTER_CAPABILITIES, self::ADAPTED_CAPABILITIES);

        $body = ChatRequestBuilder::build($this->modelId, $this->provider(), $request, stream: false);
        $url = Url::joinPath($this->options->baseUrl, '/chat/completions');

        $payload = $this->runner($this->options->sdk)
            ->postJson($url, $body, $this->options->authHeaders(), $this->provider());

        return ChatResponseParser::parse($payload, $this->provider());
    }

    public function stream(TextModelRequest $request): Generator
    {
        $this->ensureTextRequestSupported($request, self::ADAPTER_CAPABILITIES, self::ADAPTED_CAPABILITIES, streaming: true);

        $body = ChatRequestBuilder::build($this->modelId, $this->provider(), $request, stream: true);
        $url = Url::joinPath($this->options->baseUrl, '/chat/completions');

        $events = $this->runner($this->options->sdk)
            ->postStream($url, $body, $this->options->authHeaders(), $this->provider());

        yield from ChatStreamParser::parse($events, $this->provider());
    }
}
