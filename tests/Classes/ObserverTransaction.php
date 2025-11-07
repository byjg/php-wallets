<?php

namespace Tests\Classes;

use ByJG\MicroOrm\Interface\ObserverProcessorInterface;
use ByJG\MicroOrm\ObserverData;
use Throwable;

class ObserverTransaction implements ObserverProcessorInterface
{
    public function __construct(public TransactionRepositoryExtended $repository)
    {

    }

    #[\Override]
    public function process(ObserverData $observerData): void
    {
        // This is tied to the test AccountTransactionTest::testTransactionObserver()
        $this->repository->setReach($observerData->getData()->getTransactionId() == 3 && $observerData->getData()->getAmount() == 250);
    }

    #[\Override]
    public function getObservedTable(): string
    {
        return "transaction";
    }

    #[\Override]
    public function onError(Throwable $exception, ObserverData $observerData): void
    {
        throw $exception;
    }
}