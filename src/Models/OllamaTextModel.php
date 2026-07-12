<?php

declare(strict_types=1);

namespace AiSdk\Ollama\Models;

use AiSdk\Capability;
use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Ollama\OllamaApi;
use AiSdk\Ollama\OllamaOptions;
use AiSdk\OpenAICompatible\ChatRequestBuilder;
use AiSdk\OpenAICompatible\ChatResponseParser;
use AiSdk\OpenAICompatible\ChatStreamParser;
use AiSdk\OpenAICompatible\ResponsesRequestBuilder;
use AiSdk\OpenAICompatible\ResponsesResponseParser;
use AiSdk\OpenAICompatible\ResponsesStreamParser;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Utils\Support\Url;
use Generator;

final class OllamaTextModel extends BaseModel implements TextModelInterface
{
    private const array ADAPTER_CAPABILITIES = [
        Capability::TextGeneration,
        Capability::Streaming,
        Capability::ToolCalling,
        Capability::StructuredOutput,
        Capability::Reasoning,
        Capability::TextInput,
        Capability::ImageInput,
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
        $this->ensureTextRequestSupported($request, self::ADAPTER_CAPABILITIES);

        $api = $this->resolveApi($request);
        $this->ensureApiRequestSupported($api, $request);
        $body = $api === OllamaApi::Responses
            ? ResponsesRequestBuilder::build($this->modelId, $this->provider(), $request, stream: false)
            : ChatRequestBuilder::build($this->modelId, $this->provider(), $request, stream: false);
        unset($body['api']);
        $url = Url::joinPath($this->options->baseUrl, $api === OllamaApi::Responses ? '/responses' : '/chat/completions');

        $payload = $this->runner($this->options->sdk)
            ->postJson($url, $body, $this->options->authHeaders(), $this->provider());

        return $api === OllamaApi::Responses
            ? ResponsesResponseParser::parse($payload, $this->provider())
            : ChatResponseParser::parse($payload, $this->provider());
    }

    public function stream(TextModelRequest $request): Generator
    {
        $this->ensureTextRequestSupported($request, self::ADAPTER_CAPABILITIES, streaming: true);

        $api = $this->resolveApi($request);
        $this->ensureApiRequestSupported($api, $request);
        $body = $api === OllamaApi::Responses
            ? ResponsesRequestBuilder::build($this->modelId, $this->provider(), $request, stream: true)
            : ChatRequestBuilder::build($this->modelId, $this->provider(), $request, stream: true);
        unset($body['api']);
        $url = Url::joinPath($this->options->baseUrl, $api === OllamaApi::Responses ? '/responses' : '/chat/completions');

        $events = $this->runner($this->options->sdk)
            ->postStream($url, $body, $this->options->authHeaders(), $this->provider());

        yield from $api === OllamaApi::Responses
            ? ResponsesStreamParser::parse($events, $this->provider())
            : ChatStreamParser::parse($events, $this->provider());
    }

    private function resolveApi(TextModelRequest $request): OllamaApi
    {
        $override = $request->providerOptionsFor($this->provider())['api'] ?? null;

        return $override === null ? $this->options->api : OllamaApi::resolve($override);
    }

    private function ensureApiRequestSupported(OllamaApi $api, TextModelRequest $request): void
    {
        if ($api === OllamaApi::Responses && $request->reasoning !== null) {
            throw new InvalidArgumentException('Ollama Responses does not expose reasoning controls. Use chat_completions or let the selected thinking model reason automatically.');
        }

        if ($api === OllamaApi::Responses && $request->output !== null) {
            throw new InvalidArgumentException('Ollama Responses does not expose structured-output controls. Use chat_completions for portable structured output.');
        }

        if ($request->reasoning?->effort === 'minimal') {
            throw new InvalidArgumentException('Ollama Chat Completions reasoning effort must be low, medium, or high.');
        }
    }
}
