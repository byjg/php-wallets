<?php

namespace Tests\Database;

use ByJG\AccountStatements\DTO\StatementDTO;
use ByJG\AccountStatements\Entity\StatementEntity;
use ByJG\AccountStatements\Exception\AmountException;
use ByJG\AccountStatements\Exception\StatementException;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use PHPUnit\Framework\TestCase;
use Tests\BaseDALTrait;

class ReserveFundsWithdrawTest extends TestCase
{
    use BaseDALTrait;


    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    #[\Override]
    protected function setUp(): void
    {
        $this->dbSetUp();
        $this->prepareObjects();
        $this->createDummyData();
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    #[\Override]
    protected function tearDown(): void
    {
        $this->dbClear();
    }

    public function testReserveForWithdrawFunds(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $dto = StatementDTO::create($accountId, 350)
            ->setDescription('Test Withdraw')
            ->setReferenceId('Referencia Withdraw')
            ->setReferenceSource('Source Withdraw');
        $actual = $this->statementBLL->reserveFundsForWithdraw($dto);

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('350.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test Withdraw');
        $statement->setBalance('1000.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($actual->getStatementId());;
        $statement->setTypeId('WB');
        $statement->setAvailable('650.00');
        $statement->setPrice('1.00');
        $statement->setReserved('350.00');
        $statement->setReferenceId('Referencia Withdraw');
        $statement->setReferenceSource('Source Withdraw');
        $statement->setAccountTypeId('USDTEST');
        $statement->setDate($actual->getDate());
        $statement->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function testReserveForWithdrawFunds_Invalid(): void
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Amount needs to be greater than zero');

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->statementBLL->reserveFundsForWithdraw(
            StatementDTO::create($accountId, -50)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw'));
    }

    public function testReserveForWithdrawFunds_Allow_Negative(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('NEGTEST', "___TESTUSER-1", 1000, 1, -400);
        $dto = StatementDTO::create($accountId, 1150)
            ->setDescription('Test Withdraw')
            ->setReferenceId('Referencia Withdraw')
            ->setReferenceSource('Source Withdraw');
        $actual = $this->statementBLL->reserveFundsForWithdraw($dto);

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('1150.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test Withdraw');
        $statement->setBalance('1000.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($actual->getStatementId());
        $statement->setTypeId('WB');
        $statement->setAvailable('-150.00');
        $statement->setPrice('1.00');
        $statement->setReserved('1150.00');
        $statement->setReferenceId('Referencia Withdraw');
        $statement->setReferenceSource('Source Withdraw');
        $statement->setAccountTypeId('NEGTEST');
        $statement->setDate($actual->getDate());
        $statement->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function testReserveForWithdrawFunds_NegativeInvalid(): void
    {
        $this->expectException(AmountException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000, 1, -400);
        $this->statementBLL->reserveFundsForWithdraw(
            StatementDTO::create($accountId, 1401)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );
    }

    public function testAcceptFundsById_InvalidId(): void
    {
        $this->expectException(StatementException::class);

        // Populate Data!
        $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $this->statementBLL->acceptFundsById(2);
    }

    public function testAcceptFundsById_InvalidType(): void
    {
        $this->expectException(StatementException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statement = $this->statementBLL->withdrawFunds(
            StatementDTO::create($accountId, 200)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );

        $this->statementBLL->acceptFundsById($statement->getStatementId());
    }

    public function testAcceptFundsById_HasParentTransation(): void
    {
        $this->expectException(StatementException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->statementBLL->withdrawFunds(StatementDTO::create($accountId, 150)->setDescription('Test Withdraw')->setReferenceId('Referencia Withdraw'));
        $statement = $this->statementBLL->reserveFundsForWithdraw(StatementDTO::create($accountId, 350)->setDescription('Test Withdraw')->setReferenceId('Referencia Withdraw'));

        // Executar ação
        $this->statementBLL->acceptFundsById($statement->getStatementId());

        // Provar o erro:
        $this->statementBLL->acceptFundsById($statement->getStatementId());
    }

    public function testAcceptFundsById_OK(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->statementBLL->withdrawFunds(
            StatementDTO::create($accountId, 150)
                ->setDescription( 'Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );
        $reserveStatement = $this->statementBLL->reserveFundsForWithdraw(
            StatementDTO::create($accountId, 350)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );

        // Executar ação
        $actualId = $this->statementBLL->acceptFundsById($reserveStatement->getStatementId());
        $actual = $this->statementBLL->getById($actualId);

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('350.00');
        $statement->setDescription('Test Withdraw');
        $statement->setBalance('500.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($actualId);
        $statement->setStatementParentId($reserveStatement->getStatementId());
        $statement->setTypeId('W');
        $statement->setAvailable('500.00');
        $statement->setPrice('1.00');
        $statement->setReserved('0.00');
        $statement->setReferenceId('Referencia Withdraw');
        $statement->setReferenceSource('Source Withdraw');
        $statement->setDate($actual->getDate());
        $statement->setAccountTypeId('USDTEST');
        $statement->setUuid($actual->getUuid());

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function testRejectFundsById_InvalidId(): void
    {
        $this->expectException(StatementException::class);

        // Populate Data!
        $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $this->statementBLL->rejectFundsById(5);
    }

    public function testRejectFundsById_InvalidType(): void
    {
        $this->expectException(StatementException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statement = $this->statementBLL->withdrawFunds(StatementDTO::create($accountId, 300));

        $this->statementBLL->rejectFundsById($statement->getStatementId());
    }

    public function testRejectFundsById_HasParentTransation(): void
    {
        $this->expectException(StatementException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->statementBLL->withdrawFunds(
            StatementDTO::create($accountId, 150)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );
        $statement = $this->statementBLL->reserveFundsForWithdraw(
            StatementDTO::create($accountId, 350)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );

        // Executar ação
        $this->statementBLL->rejectFundsById($statement->getStatementId());

        // Provocar o erro:
        $this->statementBLL->rejectFundsById($statement->getStatementId());
    }

    public function testRejectFundsById_OK(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->statementBLL->withdrawFunds(
            StatementDTO::create($accountId, 150)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );
        $reserveStatement = $this->statementBLL->reserveFundsForWithdraw(
            StatementDTO::create($accountId, 350)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );

        // Executar ação
        $actualId = $this->statementBLL->rejectFundsById($reserveStatement->getStatementId());
        $actual = $this->statementBLL->getById($actualId);

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('350.00');
        $statement->setDescription('Test Withdraw');
        $statement->setBalance('850.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($actualId);
        $statement->setStatementParentId($reserveStatement->getStatementId());
        $statement->setTypeId('R');
        $statement->setAvailable('850.00');
        $statement->setPrice('1.00');
        $statement->setReserved('0.00');
        $statement->setReferenceId('Referencia Withdraw');
        $statement->setReferenceSource('Source Withdraw');
        $statement->setDate($actual->getDate());
        $statement->setAccountTypeId('USDTEST');
        $statement->setUuid($actual->getUuid());

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

}
