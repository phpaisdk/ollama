<?php

declare(strict_types=1);

use AiSdk\Generate;
use AiSdk\Ollama;
use AiSdk\Ollama\Tests\Fakes\FakeHttpClient;
use AiSdk\Support\Sdk;
use Nyholm\Psr7\Factory\Psr17Factory;

afterEach(function () {
    Generate::reset();
    Ollama::reset();
});

function configureOllamaWith(FakeHttpClient $client): void
{
    $factory = new Psr17Factory();
    Generate::configure(new Sdk(
        httpClient: $client,
        requestFactory: $factory,
        streamFactory: $factory,
    ));
}

it('generates text end to end through the Ollama vertical', function () {
    $client = new FakeHttpClient(200, json_encode([
        'id' => 'chatcmpl_ollama',
        'model' => 'llama3.2',
        'choices' => [['index' => 0, 'message' => ['content' => 'Hello from Ollama'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3],
    ]));
    configureOllamaWith($client);

    Ollama::create();

    $result = Generate::text('Hi')->model(Ollama::model('llama3.2'))->run();

    expect($result->text)->toBe('Hello from Ollama')
        ->and($result->usage->inputTokens)->toBe(5);

    $body = $client->sentBody();
    expect($body['model'])->toBe('llama3.2')
        ->and($body['stream'])->toBeFalse();

    expect($client->lastRequest->getUri()->getPath())->toBe('/v1/chat/completions')
        ->and($client->lastRequest->getHeaderLine('Authorization'))->toBe('');
});

it('sends a bearer token when configured', function () {
    $client = new FakeHttpClient(200, json_encode([
        'choices' => [['message' => ['content' => 'Done'], 'finish_reason' => 'stop']],
    ]));
    configureOllamaWith($client);

    Ollama::create(['apiKey' => 'secret-token']);

    Generate::text('Hi')->model(Ollama::model('llama3.2'))->run();

    expect($client->lastRequest->getHeaderLine('Authorization'))->toBe('Bearer secret-token');
});

it('accepts arbitrary installed Ollama model identifiers', function () {
    Ollama::create();

    expect(Ollama::model('acme/private-model:latest')->modelId())->toBe('acme/private-model:latest');
});

it('lists the models installed on the configured Ollama server', function () {
    $client = new FakeHttpClient(200, json_encode([
        'models' => [[
            'name' => 'private/model:latest',
            'model' => 'private/model:latest',
            'modified_at' => '2026-07-10T10:00:00Z',
            'size' => 123456,
            'digest' => 'sha256:abc',
            'details' => ['family' => 'custom', 'parameter_size' => '7B'],
        ]],
    ]));
    configureOllamaWith($client);
    Ollama::create();

    $models = Ollama::availableModels();

    expect($models)->toHaveCount(1)
        ->and($models[0]->id)->toBe('private/model:latest')
        ->and($models[0]->metadata['details']['family'])->toBe('custom')
        ->and($client->lastRequest?->getMethod())->toBe('GET')
        ->and($client->lastRequest?->getUri()->getPath())->toBe('/api/tags');
});

it('inspects optional capabilities without changing adapter behavior', function () {
    $client = new FakeHttpClient(200, json_encode([
        'capabilities' => ['completion', 'vision', 'tools', 'thinking'],
        'modified_at' => '2026-07-10T10:00:00Z',
        'details' => ['family' => 'custom'],
        'model_info' => ['custom.context_length' => 131072],
    ]));
    configureOllamaWith($client);
    Ollama::create();
    $model = Ollama::model('private/model:latest');

    $definition = Ollama::inspectModel('private/model:latest');

    expect($definition->capabilityNames())->toBe([
        'text_generation',
        'streaming',
        'text_input',
        'image_input',
        'tool_calling',
        'reasoning',
        'structured_output',
    ])->and($definition->metadata['ollama_capabilities'])->toBe(['completion', 'vision', 'tools', 'thinking'])
        ->and($model->modelId())->toBe('private/model:latest')
        ->and($client->lastRequest?->getUri()->getPath())->toBe('/api/show')
        ->and($client->sentBody())->toBe(['model' => 'private/model:latest', 'verbose' => false]);
});
