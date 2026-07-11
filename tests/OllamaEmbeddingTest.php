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

it('generates embeddings through the native Ollama endpoint', function () {
    $client = new FakeHttpClient(200, json_encode([
        'model' => 'embeddinggemma',
        'embeddings' => [[0.1, 0.2], [0.3, 0.4]],
        'total_duration' => 14143917,
        'load_duration' => 1019500,
        'prompt_eval_count' => 8,
    ]));
    $factory = new Psr17Factory();
    Generate::configure(new Sdk($client, $factory, $factory));
    Ollama::create(['baseUrl' => 'http://localhost:11434/v1']);

    $result = Generate::embedding(['First document', 'Second document'])
        ->model(Ollama::embedding('embeddinggemma'))
        ->dimensions(256)
        ->providerOptions('ollama', ['truncate' => false, 'keep_alive' => '10m'])
        ->run();

    expect($result->output->vector)->toBe([0.1, 0.2])
        ->and($result->embeddings[1]->vector)->toBe([0.3, 0.4])
        ->and($result->usage->inputTokens)->toBe(8)
        ->and($result->providerMetadata['ollama']['total_duration'])->toBe(14143917)
        ->and((string) $client->lastRequest?->getUri())->toBe('http://localhost:11434/api/embed')
        ->and($client->sentBody())->toMatchArray([
            'model' => 'embeddinggemma',
            'input' => ['First document', 'Second document'],
            'dimensions' => 256,
            'truncate' => false,
            'keep_alive' => '10m',
        ]);
});

it('rejects incomplete Ollama embedding batches', function () {
    $client = new FakeHttpClient(200, json_encode([
        'embeddings' => [[0.1, 0.2]],
    ]));
    $factory = new Psr17Factory();
    Generate::configure(new Sdk($client, $factory, $factory));
    Ollama::create(['baseUrl' => 'http://localhost:11434/v1']);

    Generate::embedding(['First document', 'Second document'])
        ->model(Ollama::embedding('embeddinggemma'))
        ->run();
})->throws(\AiSdk\Exceptions\InvalidResponseException::class, 'unexpected number of embeddings');
