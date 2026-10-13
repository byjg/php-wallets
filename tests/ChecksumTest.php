<?php

namespace Tests;

use ByJG\Wallets\Checksum\ChecksumFactory;
use ByJG\Wallets\Checksum\ChecksumV1;
use ByJG\Wallets\Checksum\ChecksumV2;
use ByJG\Wallets\Entity\TransactionEntity;
use ByJG\Wallets\Exception\TransactionException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests (no database) for the checksum algorithms:
 * coverage of all business fields, chaining, secret, legacy (v1) validation
 * and version dispatching through the factory.
 */
class ChecksumTest extends TestCase
{
    private function buildEntity(): TransactionEntity
    {
        $transaction = new TransactionEntity();
        $transaction->setWalletId(1);
        $transaction->setWalletTypeId('USD');
        $transaction->setTypeId(TransactionEntity::DEPOSIT);
        $transaction->setAmount(100);
        $transaction->setScale(2);
        $transaction->setBalance(1100);
        $transaction->setReserved(0);
        $transaction->setAvailable(1100);
        $transaction->setCode('COD');
        $transaction->setDescription('A deposit');
        $transaction->setReferenceId('order-1');
        $transaction->setReferenceSource('shop');
        $transaction->setUuid('F47AC10B-58CC-4372-A567-0E02B2C3D479');
        $transaction->setPreviousUuid('F47AC10B-58CC-4372-A567-0E02B2C3D470');
        $transaction->setPreviousChecksum(str_repeat('a', 64));
        $transaction->setChecksumVersion(ChecksumV2::VERSION);
        return $transaction;
    }

    public function testFactoryResolvesAlgorithmsByVersion(): void
    {
        $this->assertInstanceOf(ChecksumV2::class, ChecksumFactory::current());
        $this->assertInstanceOf(ChecksumV1::class, ChecksumFactory::get(1));
        $this->assertInstanceOf(ChecksumV2::class, ChecksumFactory::get(2));
        // Rows without a version predate the versioning and validate as v1
        $this->assertInstanceOf(ChecksumV1::class, ChecksumFactory::get(null));

        $this->expectException(TransactionException::class);
        $this->expectExceptionMessage('Unknown checksum version 99');
        ChecksumFactory::get(99);
    }

    public function testChecksumCoversEveryBusinessField(): void
    {
        $checksumV2 = new ChecksumV2();
        $base = $checksumV2->calculate($this->buildEntity());

        $mutations = [
            'walletId' => fn (TransactionEntity $t) => $t->setWalletId(2),
            'walletTypeId' => fn (TransactionEntity $t) => $t->setWalletTypeId('BRL'),
            'typeId' => fn (TransactionEntity $t) => $t->setTypeId(TransactionEntity::WITHDRAW),
            'amount' => fn (TransactionEntity $t) => $t->setAmount(999),
            'scale' => fn (TransactionEntity $t) => $t->setScale(0),
            'balance' => fn (TransactionEntity $t) => $t->setBalance(999),
            'reserved' => fn (TransactionEntity $t) => $t->setReserved(999),
            'available' => fn (TransactionEntity $t) => $t->setAvailable(999),
            'code' => fn (TransactionEntity $t) => $t->setCode('XXX'),
            'description' => fn (TransactionEntity $t) => $t->setDescription('tampered'),
            'referenceId' => fn (TransactionEntity $t) => $t->setReferenceId('order-2'),
            'referenceSource' => fn (TransactionEntity $t) => $t->setReferenceSource('other'),
            'transactionParentId' => fn (TransactionEntity $t) => $t->setTransactionParentId(10),
            'uuid' => fn (TransactionEntity $t) => $t->setUuid('F47AC10B-58CC-4372-A567-0E02B2C3D471'),
            'previousUuid' => fn (TransactionEntity $t) => $t->setPreviousUuid('F47AC10B-58CC-4372-A567-0E02B2C3D472'),
            'previousChecksum' => fn (TransactionEntity $t) => $t->setPreviousChecksum(str_repeat('b', 64)),
        ];

        foreach ($mutations as $field => $mutate) {
            $entity = $this->buildEntity();
            $mutate($entity);
            $this->assertNotEquals(
                $base,
                $checksumV2->calculate($entity),
                "Changing '$field' must change the checksum"
            );
        }
    }

    public function testChecksumWithSecret(): void
    {
        $checksumV2 = new ChecksumV2();
        $entity = $this->buildEntity();

        $withoutSecret = $checksumV2->calculate($entity);
        $withSecret = $checksumV2->calculate($entity, 'my-secret');

        $this->assertNotEquals($withoutSecret, $withSecret);
        $this->assertEquals($withoutSecret, $checksumV2->calculate($entity, ''));

        $this->assertTrue($checksumV2->validate($entity, $withSecret, 'my-secret'));
        $this->assertFalse($checksumV2->validate($entity, $withSecret, 'wrong-secret'));
        $this->assertFalse($checksumV2->validate($entity, $withSecret));
    }

    public function testLegacyChecksumValidation(): void
    {
        $entity = $this->buildEntity();
        $entity->setPreviousChecksum(null);
        $entity->setChecksumVersion(ChecksumV1::VERSION);
        $legacyChecksum = (new ChecksumV1())->calculate($entity);

        // A row recorded as version 1 validates with the legacy algorithm, even when
        // the verifier is configured with a secret (v1 predates the secret)
        $algorithm = ChecksumFactory::get($entity->getChecksumVersion());
        $this->assertTrue($algorithm->validate($entity, $legacyChecksum));
        $this->assertTrue($algorithm->validate($entity, $legacyChecksum, 'my-secret'));

        // The same checksum is not acceptable for the current algorithm
        $this->assertFalse(ChecksumFactory::current()->validate($entity, $legacyChecksum));

        // The legacy algorithm ignores the business fields (that is why v2 exists)
        $tampered = $this->buildEntity();
        $tampered->setPreviousChecksum(null);
        $tampered->setChecksumVersion(ChecksumV1::VERSION);
        $tampered->setDescription('tampered');
        $this->assertTrue($algorithm->validate($tampered, $legacyChecksum));
    }
}
