<?php

declare(strict_types=1);

namespace App;

/**
 * A pretend mail server: each message becomes a file in a folder. It can
 * be told to refuse some of them, as a real one does on a bad day, so
 * that the queue's retries have something to do.
 */
final readonly class FileMailer implements Mailer
{
    /**
     * @param float $failRate How often a message is refused, from 0 to 1.
     */
    public function __construct(private string $directory, private float $failRate = 0.0)
    {
    }

    public function send(string $to, string $subject, string $body): void
    {
        if ($this->failRate > 0 && mt_rand() / mt_getrandmax() < $this->failRate) {
            throw new \RuntimeException("The mail server refused the message to {$to}: 451 try again later");
        }
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException("The mail folder {$this->directory} cannot be created.");
        }
        $file = $this->directory . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.txt';
        file_put_contents($file, "To: {$to}\nSubject: {$subject}\n\n{$body}\n");
    }
}
