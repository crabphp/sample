<?php

declare(strict_types=1);

namespace Tests;

use Crab\Engine\Async\Task;
use Crab\Engine\EventLoop;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Real workers call services in fibers. Do the same when tests use Redis
 * while a test socket stays open; a main-context Redis call drains the
 * loop, which cannot end while that socket is still listening.
 */
final readonly class WorkerHandler implements RequestHandlerInterface
{
    public function __construct(
        private RequestHandlerInterface $app,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return self::run(fn(): ResponseInterface => $this->app->handle($request));
    }

    public static function run(\Closure $work): mixed
    {
        if (\Fiber::getCurrent() !== null) {
            return $work();
        }
        $loop = EventLoop::current();
        $task = Task::spawn($loop, $work);
        $task->onComplete(static fn() => $loop->stop());
        $deadline = $loop->delay(10, $loop->stop(...));
        try {
            $loop->run();
            if (!$task->isComplete()) {
                $task->cancel();
                throw new \RuntimeException('The shop test work did not finish within ten seconds.');
            }
            // Failures leave the task here, so none can be swallowed by the loop.
            return $task->result();
        } finally {
            $loop->cancel($deadline);
        }
    }
}
