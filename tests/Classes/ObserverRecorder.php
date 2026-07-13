<?php

namespace Tests\Classes;

use ByJG\MicroOrm\Interface\ObserverProcessorInterface;
use ByJG\MicroOrm\ObserverData;
use Closure;
use Throwable;

/**
 * Generic observer for tests: records every ObserverData received by process()
 * and every exception routed to onError(). An optional callback allows a test
 * to make process() throw and exercise the onError path.
 */
class ObserverRecorder implements ObserverProcessorInterface
{
    /** @var ObserverData[] */
    public array $processed = [];

    /** @var array<array{exception: Throwable, observerData: ObserverData}> */
    public array $errors = [];

    public function __construct(
        private readonly string $table,
        private readonly ?Closure $processCallback = null
    ) {
    }

    #[\Override]
    public function process(ObserverData $observerData): void
    {
        $this->processed[] = $observerData;
        if ($this->processCallback !== null) {
            ($this->processCallback)($observerData);
        }
    }

    #[\Override]
    public function getObservedTable(): string
    {
        return $this->table;
    }

    #[\Override]
    public function onError(Throwable $exception, ObserverData $observerData): void
    {
        $this->errors[] = ['exception' => $exception, 'observerData' => $observerData];
    }
}