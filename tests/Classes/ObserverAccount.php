<?php

namespace Tests\Classes;

use ByJG\MicroOrm\Enum\ObserverEvent;
use ByJG\MicroOrm\Interface\ObserverProcessorInterface;
use ByJG\MicroOrm\ObserverData;
use Throwable;

class ObserverAccount implements ObserverProcessorInterface
{
    public function __construct(public AccountRepositoryExtended $repository)
    {

    }

    #[\Override]
    public function process(ObserverData $observerData): void
    {
        // This is tied to the test AccountTransactionTest::testTransactionObserver()
        $this->repository->setReach($observerData->getEvent() == ObserverEvent::Update && $observerData->getData()->getAvailable() == 1250);
    }

    #[\Override]
    public function getObservedTable(): string
    {
        return "account";
    }

    #[\Override]
    public function onError(Throwable $exception, ObserverData $observerData): void
    {
        throw $exception;
    }
}