<?php

namespace Tests\Classes;

use ByJG\MicroOrm\Interface\ObserverProcessorInterface;
use ByJG\MicroOrm\ObserverData;
use Throwable;

class ObserverStatement implements ObserverProcessorInterface
{
    public function __construct(public StatementRepositoryExtended $repository)
    {

    }

    #[\Override]
    public function process(ObserverData $observerData): void
    {
        // This is tied to the test AccountStatementTest::testStatementObserver()
        $this->repository->setReach($observerData->getData()->getStatementId() == 3 && $observerData->getData()->getAmount() == 250);
    }

    #[\Override]
    public function getObservedTable(): string
    {
        return "statement";
    }

    #[\Override]
    public function onError(Throwable $exception, ObserverData $observerData): void
    {
        throw $exception;
    }
}