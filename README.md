# aisdk/ollama

Official Ollama provider for the framework-agnostic PHP AI SDK. It supports text, streaming, native embeddings, and Ollama's experimental OpenAI-compatible image endpoint.

## Installation

```bash
composer require aisdk/ollama
```

## Basic Usage

```php
use AiSdk\Generate;
use AiSdk\Ollama;

$result = Generate::text()
    ->model(Ollama::model('your-installed-model'))
    ->prompt('Explain closures in PHP.')
    ->run();

echo $result->text;
```

Any model installed on the configured Ollama server can be selected. Like every AI SDK provider package, this package treats model IDs as opaque provider values and does not ship a model inventory.

Ollama's OpenAI-compatible Chat Completions endpoint is the default. Select the Responses endpoint globally or per request when needed:

```php
Ollama::create(['api' => 'responses']);

$result = Generate::text('Explain this code.')
    ->model(Ollama::model('your-installed-model'))
    ->providerOptions('ollama', ['api' => 'responses'])
    ->run();
```

Supported values are `chat_completions` and `responses`. Chat Completions supports Ollama's documented vision, function tools, reasoning effort, and structured-output fields. Ollama Responses supports streaming and function tools, but its documented request fields do not currently expose reasoning or structured-output controls. The selected installed model remains responsible for accepting model-dependent features.

## Available models

Read the current server registry instead of relying on package data:

```php
$models = Ollama::availableModels();

foreach ($models as $model) {
    echo $model->id;
}
```

`availableModels()` calls Ollama's native `/api/tags` endpoint. To load a model's optional capabilities—such as embeddings, vision, tools, audio input, or thinking—inspect it explicitly:

```php
$definition = Ollama::inspectModel('your-installed-model');

if (in_array('image_input', $definition->capabilityNames(), true)) {
    // The installed model advertises vision support.
}
```

Inspection calls `/api/show` and returns informational details reported by the current Ollama server. It does not change adapter behavior, and ordinary generation does not perform hidden discovery requests.

## Embeddings

Ollama embeddings use the native `/api/embed` endpoint and accept one text or a batch:

```php
use AiSdk\Generate;
use AiSdk\Ollama;

$result = Generate::embedding(['Search query', 'Document text'])
    ->model(Ollama::embedding('embeddinggemma'))
    ->dimensions(256)
    ->providerOptions('ollama', [
        'truncate' => false,
        'keep_alive' => '10m',
    ])
    ->run();

$queryVector = $result->embeddings[0]->vector;
$documentVector = $result->embeddings[1]->vector;
```

`truncate` and `keep_alive` are passed to Ollama unchanged. The selected installed model remains the authority on supported output dimensions.

## Image generation

```php
$image = Generate::image('A small robot')
    ->model(Ollama::image('your-installed-image-model'))
    ->size('1024x1024')
    ->run();
```

Image generation is experimental in Ollama. Ollama does not currently expose an OpenAI-compatible speech endpoint, so this package intentionally does not implement `Generate::speech()`.

## Streaming

```php
use AiSdk\Generate;
use AiSdk\Ollama;

foreach (Generate::text('Tell me a story.')->model(Ollama::model('your-installed-model'))->stream()->chunks() as $chunk) {
    echo $chunk;
}
```

## Configuration

No API key is required for a local Ollama server. Provide one only if your server is protected.

| Variable | Description | Default |
|---|---|---|
| `OLLAMA_BASE_URL` | Base URL for the OpenAI-compatible API | `http://localhost:11434/v1` |
| `OLLAMA_NATIVE_BASE_URL` | Base URL for native API endpoints | Derived by removing `/v1` from `OLLAMA_BASE_URL` |
| `OLLAMA_API_KEY` | Optional bearer token | — |

```php
Ollama::create([
    'baseUrl' => 'http://localhost:11434/v1',
    'nativeBaseUrl' => 'http://localhost:11434',
]);
```

## Testing

```bash
composer test
```

## Links

- [Ollama Embed API](https://docs.ollama.com/api/embed)
- [Ollama OpenAI compatibility](https://docs.ollama.com/api/openai-compatibility)
- [Core Package](https://github.com/phpaisdk/core)
