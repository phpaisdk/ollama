<?php

declare(strict_types=1);

namespace AiSdk\Ollama;

use AiSdk\Exceptions\InvalidArgumentException;

enum OllamaApi: string
{
    case ChatCompletions = 'chat_completions';
    case Responses = 'responses';

    public static function resolve(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return self::ChatCompletions;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            foreach (self::cases() as $case) {
                if ($case->value === $normalized) {
                    return $case;
                }
            }
        }

        throw new InvalidArgumentException('Invalid Ollama API surface. Expected chat_completions or responses.', [
            'api' => $value,
        ]);
    }
}
