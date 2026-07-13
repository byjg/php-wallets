<?php

namespace Tests;

use ByJG\Wallets\DTO\TransactionDTO;
use ByJG\Wallets\Entity\TransactionEntity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Classes\TransactionExtended;

class TransactionDTOTest extends TestCase
{
    public function testSetToTransactionCopiesFieldsAndProperties(): void
    {
        $dto = TransactionDTO::create(42, 100)
            ->setDescription('Test Description')
            ->setCode('CODE')
            ->setReferenceId('REF-1')
            ->setReferenceSource('REF-SRC')
            ->setProperty('extraProperty', 'hello');

        $transaction = new TransactionExtended();
        $dto->setToTransaction($transaction);

        $this->assertEquals(42, $transaction->getWalletId());
        $this->assertEquals(100, $transaction->getAmount());
        $this->assertEquals('Test Description', $transaction->getDescription());
        $this->assertEquals('CODE', $transaction->getCode());
        $this->assertEquals('REF-1', $transaction->getReferenceId());
        $this->assertEquals('REF-SRC', $transaction->getReferenceSource());
        $this->assertEquals('hello', $transaction->getExtraProperty());
    }

    public function testSetToTransactionUnknownPropertyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Property unknownProperty not found in TransactionEntity');

        $dto = TransactionDTO::create(1, 1)->setProperty('unknownProperty', 'x');
        $dto->setToTransaction(new TransactionEntity());
    }
}
