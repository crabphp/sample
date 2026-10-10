<?php

declare(strict_types=1);

namespace App;

/**
 * Sends a message to someone. The sample has no mail server, so the one
 * bound to this writes files; an application of your own would bind SMTP.
 */
interface Mailer
{
    /**
     * @throws \RuntimeException When the message could not be handed over; sending it again is safe.
     */
    public function send(string $to, string $subject, string $body): void;
}
