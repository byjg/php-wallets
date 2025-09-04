<?php

namespace Tests;

use ByJG\AccountStatements\DTO\StatementDTO;
use ByJG\AccountStatements\Entity\StatementEntity;
use ByJG\AccountStatements\Exception\AmountException;
use ByJG\AccountStatements\Exception\StatementException;
use PHPUnit\Framework\TestCase;

class ReserveFundsWithdrawTest extends TestCase
{
    use BaseDALTrait;


    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
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
    protected function tearDown(): void
    {
        $this->dbClear();
    }

    public function testReserveForWithdrawFunds()
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statementId = $this->statementBLL->reserveFundsForWithdraw(
            StatementDTO::create($accountId, 350)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('350.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test Withdraw');
        $statement->setGrossBalance('1000.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($statementId);
        $statement->setTypeId('WB');
        $statement->setNetBalance('650.00');
        $statement->setPrice('1.00');
        $statement->setUnCleared('350.00');
        $statement->setReferenceId('Referencia Withdraw');
        $statement->setReferenceSource('Source Withdraw');
        $statement->setAccountTypeId('USDTEST');

        $actual = $this->statementBLL->getById($statementId);
        $statement->setDate($actual->getDate());

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function testReserveForWithdrawFunds_Invalid()
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

    public function testReserveForWithdrawFunds_InvalidRound()
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Amount needs to have two decimal places');

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->statementBLL->reserveFundsForWithdraw(
            StatementDTO::create($accountId, 45.009)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw'));
    }

    public function testReserveForWithdrawFunds_Allow_Negative()
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('NEGTEST', "___TESTUSER-1", 1000, 1, -400);
        $statementId = $this->statementBLL->reserveFundsForWithdraw(
            StatementDTO::create($accountId, 1150)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('1150.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test Withdraw');
        $statement->setGrossBalance('1000.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($statementId);
        $statement->setTypeId('WB');
        $statement->setNetBalance('-150.00');
        $statement->setPrice('1.00');
        $statement->setUnCleared('1150.00');
        $statement->setReferenceId('Referencia Withdraw');
        $statement->setReferenceSource('Source Withdraw');
        $statement->setAccountTypeId('NEGTEST');

        $actual = $this->statementBLL->getById($statementId);
        $statement->setDate($actual->getDate());

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function testReserveForWithdrawFunds_NegativeInvalid()
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

    public function testAcceptFundsById_InvalidId()
    {
        $this->expectException(StatementException::class);

        // Populate Data!
        $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $this->statementBLL->acceptFundsById(2);
    }

    public function testAcceptFundsById_InvalidType()
    {
        $this->expectException(StatementException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statementId = $this->statementBLL->withdrawFunds(
            StatementDTO::create($accountId, 200)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );

        $this->statementBLL->acceptFundsById($statementId);
    }

    public function testAcceptFundsById_HasParentTransation()
    {
        $this->expectException(StatementException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->statementBLL->withdrawFunds(StatementDTO::create($accountId, 150)->setDescription('Test Withdraw')->setReferenceId('Referencia Withdraw'));
        $statementId = $this->statementBLL->reserveFundsForWithdraw(StatementDTO::create($accountId, 350)->setDescription('Test Withdraw')->setReferenceId('Referencia Withdraw'));

        // Executar ação
        $this->statementBLL->acceptFundsById($statementId);

        // Provar o erro:
        $this->statementBLL->acceptFundsById($statementId);    
    }

    public function testAcceptFundsById_OK()
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->statementBLL->withdrawFunds(
            StatementDTO::create($accountId, 150)
                ->setDescription( 'Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );
        $statementId = $this->statementBLL->reserveFundsForWithdraw(
            StatementDTO::create($accountId, 350)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );

        // Executar ação
        $actualId = $this->statementBLL->acceptFundsById($statementId);
        $actual = $this->statementBLL->getById($actualId);

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('350.00');
        $statement->setDescription('Test Withdraw');
        $statement->setGrossBalance('500.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($actualId);
        $statement->setStatementParentId($statementId);
        $statement->setTypeId('W');
        $statement->setNetBalance('500.00');
        $statement->setPrice('1.00');
        $statement->setUnCleared('0.00');
        $statement->setReferenceId('Referencia Withdraw');
        $statement->setReferenceSource('Source Withdraw');
        $statement->setDate($actual->getDate());
        $statement->setAccountTypeId('USDTEST');

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function testRejectFundsById_InvalidId()
    {
        $this->expectException(StatementException::class);

        // Populate Data!
        $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $this->statementBLL->rejectFundsById(5);
    }

    public function testRejectFundsById_InvalidType()
    {
        $this->expectException(StatementException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statementId = $this->statementBLL->withdrawFunds(StatementDTO::create($accountId, 300));

        $this->statementBLL->rejectFundsById($statementId);
    }

    public function testRejectFundsById_HasParentTransation()
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
        $statementId = $this->statementBLL->reserveFundsForWithdraw(
            StatementDTO::create($accountId, 350)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );

        // Executar ação
        $this->statementBLL->rejectFundsById($statementId);

        // Provocar o erro:
        $this->statementBLL->rejectFundsById($statementId);
    }

    public function testRejectFundsById_OK()
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->statementBLL->withdrawFunds(
            StatementDTO::create($accountId, 150)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );
        $statementId = $this->statementBLL->reserveFundsForWithdraw(
            StatementDTO::create($accountId, 350)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );

        // Executar ação
        $actualId = $this->statementBLL->rejectFundsById($statementId);
        $actual = $this->statementBLL->getById($actualId);

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('350.00');
        $statement->setDescription('Test Withdraw');
        $statement->setGrossBalance('850.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($actualId);
        $statement->setStatementParentId($statementId);
        $statement->setTypeId('R');
        $statement->setNetBalance('850.00');
        $statement->setPrice('1.00');
        $statement->setUnCleared('0.00');
        $statement->setReferenceId('Referencia Withdraw');
        $statement->setReferenceSource('Source Withdraw');
        $statement->setDate($actual->getDate());
        $statement->setAccountTypeId('USDTEST');

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

}
