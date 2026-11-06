<?php

declare(strict_types=1);

namespace App;

use Psr\Log\LoggerInterface;

/** The pretend model endpoint shared by both assistant entry points. */
final class AssistantModel
{
    public const UNAVAILABLE = 'The assistant is not available. Try again in a moment.';

    public static function url(): string
    {
        return rtrim((string) (config('shop.model_url') ?? config('app.url')), '/') . '/api/model/chat';
    }

    public static function unavailable(LoggerInterface $log, string $url, string $reason): void
    {
        $log->warning(
            'The assistant model at {url} is unavailable ({reason}). Set MODEL_URL to the model base URL, or set APP_URL to the address the app is served on.',
            ['url' => $url, 'reason' => $reason],
        );
    }
}
