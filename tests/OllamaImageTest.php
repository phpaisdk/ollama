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

it('generates images through Ollamas experimental OpenAI-compatible endpoint', function () {
    $client = new FakeHttpClient(200, json_encode([
        'data' => [['b64_json' => base64_encode('image-bytes')]],
    ]));
    $factory = new Psr17Factory();
    Generate::configure(new Sdk(httpClient: $client, requestFactory: $factory, streamFactory: $factory));
    Ollama::create();

    $result = Generate::image('A tiny robot')
        ->model(Ollama::image('x/z-image-turbo'))
        ->size('1024x1024')
        ->run();

    expect($result->output->bytes())->toBe('image-bytes')
        ->and($client->lastRequest?->getUri()->getPath())->toBe('/v1/images/generations')
        ->and($client->sentBody()['model'])->toBe('x/z-image-turbo');
});

it('allows arbitrary installed Ollama image models', function () {
    Ollama::create();

    expect(Ollama::image('acme/private-image-model')->modelId())->toBe('acme/private-image-model');
});
