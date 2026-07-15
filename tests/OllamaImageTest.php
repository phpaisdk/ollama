<?php

declare(strict_types=1);

use AiSdk\Exceptions\InvalidResponseException;
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
        ->model(Ollama::model('x/z-image-turbo'))
        ->count(2)
        ->size('1024x1024')
        ->seed(42)
        ->providerOptions('ollama', [
            'raw' => [
                'quality' => 'high',
                'style' => 'vivid',
                'user' => 'user_123',
            ],
        ])
        ->run();

    expect($result->output->bytes())->toBe('image-bytes')
        ->and($client->lastRequest?->getUri()->getPath())->toBe('/v1/images/generations')
        ->and($client->sentBody())->toBe([
            'model' => 'x/z-image-turbo',
            'prompt' => 'A tiny robot',
            'response_format' => 'b64_json',
            'size' => '1024x1024',
        ])
        ->and($client->sentBody())->not->toHaveKey('n')
        ->and($client->sentBody())->not->toHaveKey('seed')
        ->and($client->sentBody())->not->toHaveKey('quality')
        ->and($client->sentBody())->not->toHaveKey('style')
        ->and($client->sentBody())->not->toHaveKey('user');
});

it('allows arbitrary installed Ollama image models', function () {
    Ollama::create();

    expect(Ollama::model('acme/private-image-model')->modelId())->toBe('acme/private-image-model');
});

it('defaults experimental image requests to Ollamas required size', function () {
    $client = new FakeHttpClient(200, json_encode([
        'data' => [['b64_json' => base64_encode('image-bytes')]],
    ]));
    $factory = new Psr17Factory();
    Generate::configure(new Sdk(httpClient: $client, requestFactory: $factory, streamFactory: $factory));
    Ollama::create();

    Generate::image('A tiny robot')
        ->model(Ollama::model('x/z-image-turbo'))
        ->run();

    expect($client->sentBody())->toBe([
        'model' => 'x/z-image-turbo',
        'prompt' => 'A tiny robot',
        'response_format' => 'b64_json',
        'size' => '1024x1024',
    ]);
});

it('rejects malformed or empty experimental image responses', function (array $response) {
    $client = new FakeHttpClient(200, json_encode($response));
    $factory = new Psr17Factory();
    Generate::configure(new Sdk(httpClient: $client, requestFactory: $factory, streamFactory: $factory));
    Ollama::create();

    Generate::image('A tiny robot')
        ->model(Ollama::model('x/z-image-turbo'))
        ->run();
})->with([
    'empty data' => [['data' => []]],
    'malformed data' => [['data' => 'malformed']],
])->throws(InvalidResponseException::class);
