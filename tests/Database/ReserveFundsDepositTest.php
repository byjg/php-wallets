<?php

namespace Tests\Database;

use ByJG\AccountStatements\DTO\StatementDTO;
use ByJG\AccountStatements\Entity\StatementEntity;
use ByJG\AccountStatements\Exception\AmountException;
use ByJG\AccountStatements\Exception\StatementException;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use PHPUnit\Framework\TestCase;
use Tests\BaseDALTrait;

class ReserveFundsDepositTest extends TestCase
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

    public function testReserveForDepositFunds(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $dto = StatementDTO::create($accountId, 350)
            ->setDescription('Test Deposit')
            ->setReferenceId('Referencia Deposit')
            ->setReferenceSource('Source Deposit');
        $actual = $this->statementBLL->reserveFundsForDeposit($dto);

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('350.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test Deposit');
        $statement->setGrossBalance('1000.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($actual->getStatementId());
        $statement->setTypeId('DB');
        $statement->setNetBalance('1350.00');
        $statement->setPrice('1.00');
        $statement->setReserved('-350.00');
        $statement->setReferenceId('Referencia Deposit');
        $statement->setReferenceSource('Source Deposit');
        $statement->setAccountTypeId('USDTEST');
        $statement->setStatementParentId(null);
        $statement->setDate($actual->getDate());
        $statement->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function testReserveForDepositFunds_Invalid(): void
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Amount needs to be greater than zero');

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->statementBLL->reserveFundsForDeposit(StatementDTO::create($accountId, -50)->setDescription('Test Withdraw')->setReferenceId('Referencia Withdraw'));
    }

    public function testReserveForDepositFunds_Allow_Negative(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('NEGTEST', "___TESTUSER-1", -200, 1, -400);
        $dto = StatementDTO::create($accountId, 300)
            ->setDescription('Test Deposit')
            ->setReferenceId('Referencia Deposit')
            ->setReferenceSource('Source Deposit');
        $actual = $this->statementBLL->reserveFundsForDeposit($dto);

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('300.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test Deposit');
        $statement->setGrossBalance('-200.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($actual->getStatementId());
        $statement->setTypeId('DB');
        $statement->setNetBalance('100.00');
        $statement->setPrice('1.00');
        $statement->setReserved('-300.00');
        $statement->setReferenceId('Referencia Deposit');
        $statement->setReferenceSource('Source Deposit');
        $statement->setAccountTypeId('NEGTEST');
        $statement->setDate($actual->getDate());
        $statement->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }
//
//    /**
//     * @expectedException OutOfRangeException
//     */
//    public function testAcceptFundsById_InvalidId()
//    {
//        // Populate Data!
//        $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
//
//        $this->statementBLL->acceptFundsById(2);
//    }
//

public function testAcceptFundsById_InvalidType(): void
    {
        $this->expectException(StatementException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statement = $this->statementBLL->addFunds(
            StatementDTO::create($accountId, 200)
                ->setDescription('Test Deposit')
                ->setReferenceId('Referencia Deposit')
                ->setReferenceSource('Source Deposit')
            );

        $this->statementBLL->acceptFundsById($statement->getStatementId());;
    }

    public function testAcceptFundsById_HasParentTransation(): void
    {
        $this->expectException(StatementException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->statementBLL->addFunds(StatementDTO::create($accountId, 150)->setDescription('Test Deposit')->setReferenceId('Referencia Deposit'));
        $statement = $this->statementBLL->reserveFundsForDeposit(StatementDTO::create($accountId, 350)->setDescription('Test Deposit')->setReferenceId('Referencia Deposit'));

        // Executar ação
        $this->statementBLL->acceptFundsById($statement->getStatementId());;

        // Provar o erro: try to accept the same statement again
        $this->statementBLL->acceptFundsById($statement->getStatementId());;
    }

    public function testAcceptFundsById_OK(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->statementBLL->addFunds(
            StatementDTO::create($accountId, 150)
                ->setDescription('Test Deposit')
                ->setReferenceId('Referencia Deposit')
                ->setReferenceSource('Source Deposit')
            );
        $reserveDto = StatementDTO::create($accountId, 350)
            ->setDescription('Test Deposit')
            ->setReferenceId('Referencia Deposit')
            ->setReferenceSource('Source Deposit');
        $reserveStatement = $this->statementBLL->reserveFundsForDeposit($reserveDto);

        // Executar ação
        $actualId = $this->statementBLL->acceptFundsById($reserveStatement->getStatementId());
        $actual = $this->statementBLL->getById($actualId);

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('350.00');
        $statement->setDescription('Test Deposit');
        $statement->setGrossBalance('1500.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($actualId);
        $statement->setStatementParentId($reserveStatement->getStatementId());
        $statement->setTypeId('D');
        $statement->setNetBalance('1500.00');
        $statement->setPrice('1.00');
        $statement->setReserved('0.00');
        $statement->setReferenceId('Referencia Deposit');
        $statement->setReferenceSource('Source Deposit');
        $statement->setDate($actual->getDate());
        $statement->setAccountTypeId('USDTEST');
        $statement->setUuid($actual->getUuid());

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function testAcceptPartialFundsById_PartialAmountZero(): void
    {
        $this->expectException(AmountException::class);

        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $reserveStatement = $this->statementBLL->reserveFundsForWithdraw(
            StatementDTO::create($accountId, 100)
        );

        $statementRefundDto = StatementDTO::createEmpty()
            ->setDescription("Refund")
            ->setReferenceSource("test-source");

        $statementDTO = StatementDTO::createEmpty()->setAmount(0);
        $this->statementBLL->acceptPartialFundsById($reserveStatement->getStatementId(), $statementDTO, $statementRefundDto);
    }

    public function testAcceptPartialFundsById_AmountMoreThanWithdrawBlocked(): void
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Partial amount must be greater than zero and less than the original reserved amount.');

        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $reserveStatement = $this->statementBLL->reserveFundsForWithdraw(
            StatementDTO::create($accountId, 100)
        );

        $statementRefundDto = StatementDTO::createEmpty()
            ->setDescription("Refund")
            ->setReferenceSource("test-source");

        $statementDTO = StatementDTO::createEmpty()->setAmount(101);
        $this->statementBLL->acceptPartialFundsById($reserveStatement->getStatementId(), $statementDTO, $statementRefundDto);
    }

    public function testAcceptPartialFundsById_OK(): void
    {
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $reserveStatement = $this->statementBLL->reserveFundsForWithdraw(
            StatementDTO::create($accountId, 100)->setDescription('Reserva para Aposta')
        );

        $statementWithdrawDto = StatementDTO::createEmpty()
            ->setAmount(80.00)
            ->setDescription("Deposit")
            ->setReferenceSource("test-source");

        $statementRefundDto = StatementDTO::createEmpty()
            ->setDescription("Refund")
            ->setReferenceSource("test-source");

        $finalDebitStatement = $this->statementBLL->acceptPartialFundsById(
            $reserveStatement->getStatementId(),
            $statementWithdrawDto,
            $statementRefundDto
        );

        $accountAfter = $this->accountBLL->getById($accountId);
        $this->assertEquals('920.00', $accountAfter->getGrossBalance());
        $this->assertEquals('920.00', $accountAfter->getNetBalance());
        $this->assertEquals('0.00', $accountAfter->getReserved());

        $rejectedStatement = $this->statementBLL->getRepository()->getByParentId($reserveStatement->getStatementId());
        $this->assertNotNull($rejectedStatement);
        $this->assertEquals(StatementEntity::REJECT, $rejectedStatement->getTypeId());
        $this->assertEquals('100.00', $rejectedStatement->getAmount());

        /** @var StatementEntity $finalDebitStatement */
        $finalDebitStatement = $this->statementBLL->getById($finalDebitStatement->getStatementId());
        $this->assertEquals('80.00', $finalDebitStatement->getAmount());
        $this->assertEquals(StatementEntity::WITHDRAW, $finalDebitStatement->getTypeId());
        $this->assertEquals("Deposit", $finalDebitStatement->getDescription());
    }

    public function testRejectFundsById_InvalidType(): void
    {
        $this->expectException(StatementException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statement = $this->statementBLL->addFunds(StatementDTO::create($accountId, 300));

        $this->statementBLL->rejectFundsById($statement->getStatementId());
    }

    public function testRejectFundsById_HasParentTransation(): void
    {
        $this->expectException(StatementException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->statementBLL->addFunds(StatementDTO::create($accountId, 150)->setDescription('Test Deposit')->setReferenceId('Referencia Deposit'));
        $reserveStatement = $this->statementBLL->reserveFundsForDeposit(StatementDTO::create($accountId, 350)->setDescription('Test Deposit')->setReferenceId('Referencia Deposit'));

        // Executar ação
        $this->statementBLL->rejectFundsById($reserveStatement->getStatementId());

        // Provocar o erro: try to reject the same statement again
        $this->statementBLL->rejectFundsById($reserveStatement->getStatementId());
    }

    public function testRejectFundsById_OK(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $addDto = StatementDTO::create($accountId, 150)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add')
            ->setReferenceSource('Source Add');
        $this->statementBLL->addFunds($addDto);
        
        $reserveDto = StatementDTO::create($accountId, 350)
            ->setDescription('Test Reserve Deposit')
            ->setReferenceId('Referencia Reserve')
            ->setReferenceSource('Source Reserve');
        $reserveStatement = $this->statementBLL->reserveFundsForDeposit($reserveDto);

        // Executar ação
        $actualId = $this->statementBLL->rejectFundsById($reserveStatement->getStatementId());
        $actual = $this->statementBLL->getById($actualId);

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('350.00');
        $statement->setDescription('Test Reserve Deposit');
        $statement->setGrossBalance('1150.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($actualId);
        $statement->setStatementParentId($reserveStatement->getStatementId());
        $statement->setTypeId('R');
        $statement->setNetBalance('1150.00');
        $statement->setPrice('1.00');
        $statement->setReserved('0.00');
        $statement->setReferenceId('Referencia Reserve');
        $statement->setReferenceSource('Source Reserve');
        $statement->setDate($actual->getDate());
        $statement->setAccountTypeId('USDTEST');
        $statement->setUuid($actual->getUuid());

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

}
