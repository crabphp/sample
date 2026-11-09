<?php

declare(strict_types=1);

namespace App;

use Crab\Contracts\HttpClient\Event;
use Psr\Log\LoggerInterface;

/** The model endpoint shared by both assistant entry points. */
final class AssistantModel
{
    public const UNAVAILABLE = 'The assistant is not available. Try again in a moment.';

    public static function url(): string
    {
        $base = config('shop.model_url');
        if (!is_string($base) || $base === '') {
            return rtrim((string) config('app.url'), '/') . '/api/model/chat';
        }
        return rtrim($base, '/') . '/' . ltrim((string) config('shop.model_endpoint'), '/');
    }

    /**
     * @return array<string, string>
     */
    public static function headers(): array
    {
        $headers = ['Accept' => 'text/event-stream'];
        $key = config('shop.model_key');
        if (is_string($key) && $key !== '') {
            $headers['Authorization'] = 'Bearer ' . $key;
        }
        return $headers;
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(string $prompt): array
    {
        $base = config('shop.model_url');
        if (!is_string($base) || $base === '') {
            return ['prompt' => $prompt];
        }
        $endpoint = strtolower((string) config('shop.model_endpoint'));
        return (
            str_ends_with($endpoint, 'chat/completions')
                ? [
                    'model' => (string) config('shop.model_name'),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'stream' => true,
                ]
                : [
                    'model' => (string) config('shop.model_name'),
                    'input' => [['role' => 'user', 'content' => $prompt]],
                    'stream' => true,
                ]
        );
    }

    public static function done(Event $event): bool
    {
        if ($event->data === '[DONE]') {
            return true;
        }
        $data = $event->json();
        $type = is_array($data) && is_string($data['type'] ?? null) ? $data['type'] : $event->name;
        return (
            in_array($type, ['response.completed', 'response.incomplete', 'response.cancelled'], true)
            || is_array($data)
            && ($data['choices'][0]['finish_reason'] ?? null) !== null
        );
    }

    public static function delta(Event $event): string
    {
        if ($event->data === '[DONE]') {
            return '';
        }
        $data = $event->json();
        if (!is_array($data)) {
            return '';
        }
        if (($data['type'] ?? $event->name) === 'response.output_text.delta') {
            return is_string($data['delta'] ?? null) ? $data['delta'] : '';
        }
        $content = $data['choices'][0]['delta']['content'] ?? null;
        if (is_string($content)) {
            return $content;
        }
        return is_string($data['delta'] ?? null) ? $data['delta'] : '';
    }

    public static function unavailable(LoggerInterface $log, string $url, string $reason): void
    {
        $log->warning(
            'The assistant model at {url} is unavailable ({reason}). Set MODEL_URL and MODEL_API_KEY for a real model, or leave MODEL_URL unset to use the built-in demo model.',
            ['url' => $url, 'reason' => $reason],
        );
    }
}
