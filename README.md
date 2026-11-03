# PHP AI SDK — Ollama

Run local models with the framework-agnostic [PHP AI SDK](https://phpaisdk.com). Supports text generation, streaming, tools, structured output, and embeddings.

## Installation

```bash
composer require aisdk/ollama
ollama pull qwen3.8
```

Start Ollama locally. The default endpoint is `http://localhost:11434/v1`; set `OLLAMA_BASE_URL` to use another server.

## Usage

```php
use AiSdk\Generate;
use AiSdk\Ollama;

$result = Generate::text('Explain closures in PHP in one sentence.')
    ->model(Ollama::model('qwen3.8'))
    ->run();

echo $result->text;
```

## Documentation

[Read the documentation](https://phpaisdk.com/docs/0.x/providers/ollama) for configuration, supported features, and more examples.

[Contributing](https://github.com/phpaisdk/.github/blob/main/CONTRIBUTING.md) · [Support](https://github.com/phpaisdk/.github/blob/main/SUPPORT.md)

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
