<?php

// namespace Test;

// use ByJG\AccountStatements\BaseDALTrait;
// use ByJG\Swagger\SwaggerRequester;
// use ByJG\Swagger\SwaggerSchema;
// use ByJG\Swagger\SwaggerTestCase;

// require_once(__DIR__ . '/../BaseDALTrait.php');

// /**
//  * Create a TestCase inherited from SwaggerTestCase
//  */
// class RestCallTestA extends SwaggerTestCase
// {
//     use BaseDALTrait;

//     protected $filePath = __DIR__ . '/../../../web/docs/swagger.json';

//     /**
//      * Sets up the fixture, for example, opens a network connection.
//      * This method is called before a test is executed.
//      */
//     protected function setUp()
//     {
//         $this->prepareObjects();
//         // $this->dbSetUp();
//         $this->createDummyData();

//         if (empty($this->filePath)) {
//             throw new \Exception('You have to define the property $filePath');
//         }

//         $this->swaggerSchema = new SwaggerSchema(file_get_contents($this->filePath), true);
//     }

//     protected function tearDown()/* The :void return type declaration that should be here would cause a BC issue */
//     {
//         $this->dbClear();
//         parent::tearDown();
//     }

//     /**
//      * test account
//      *
//      * @throws \ByJG\Swagger\Exception\HttpMethodNotFoundException
//      * @throws \ByJG\Swagger\Exception\InvalidDefinitionException
//      * @throws \ByJG\Swagger\Exception\NotMatchedException
//      * @throws \ByJG\Swagger\Exception\PathNotFoundException
//      * @throws \ByJG\Swagger\Exception\RequiredArgumentNotFound
//      * @throws \Exception
//      */
//     public function testAccount()
//     {
//         // -------------------------------------
//         // Create an Account
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('POST')
//             ->withRequestBody([
//                 "accounttypeid" => "USDTEST",
//                 "userid" => "___TESTUSER-10",
//                 "balance" => 1000,
//                 "price" => 1,
//                 "extra" => "Extra Information",
//                 "minvalue" => 0
//             ])
//             ->withPath("/account")
//         ;
//         $result = $this->assertRequest($request);

//         // Check if was inserted
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/account/{$result['accountid']}")
//         ;
//         $account = $this->assertRequest($request);
//         $expectedAccount = [
//             "accountid" => $result['accountid'],
//             "accounttypeid" => "USDTEST",
//             "userid" => "___TESTUSER-10",
//             "balance" => 1000,
//             "reserved" => 0,
//             "available" => 1000,
//             "price" => 1,
//             "extra" => "Extra Information",
//             "minvalue" => 0
//         ];
//         unset($account['entrydate']);
//         $this->assertEquals($expectedAccount, $account);

//         // ----------------------------------------
//         // Partial Balance
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('POST')
//             ->withRequestBody([
//                 "accountid" => $result['accountid'],
//                 "balance" => 300,
//                 "description" => "Adjust New Balance",
//             ])
//             ->withPath("/account/partialbalance")
//         ;
//         $this->assertRequest($request);

//         // Check
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/account/{$result['accountid']}")
//         ;
//         $account = $this->assertRequest($request);
//         $expectedAccount = [
//             "accountid" => $result['accountid'],
//             "accounttypeid" => "USDTEST",
//             "userid" => "___TESTUSER-10",
//             "balance" => 300,
//             "reserved" => 0,
//             "available" => 300,
//             "price" => 1,
//             "extra" => "Extra Information",
//             "minvalue" => 0
//         ];
//         unset($account['entrydate']);
//         $this->assertEquals($expectedAccount, $account);

//         // ---------------------------------------------------------
//         // Override Balance
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('POST')
//             ->withRequestBody([
//                 "accountid" => $result['accountid'],
//                 "balance" => 600,
//                 "price" => 1.1,
//                 "minvalue" => -1000,
//                 "description" => "Override Balance",
//             ])
//             ->withPath("/account/overridebalance")
//         ;
//         $this->assertRequest($request);

//         // Check
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/account/{$result['accountid']}")
//         ;
//         $account = $this->assertRequest($request);
//         $expectedAccount = [
//             "accountid" => $result['accountid'],
//             "accounttypeid" => "USDTEST",
//             "userid" => "___TESTUSER-10",
//             "balance" => 600,
//             "reserved" => 0,
//             "available" => 600,
//             "price" => 1.1,
//             "extra" => "Extra Information",
//             "minvalue" => -1000
//         ];
//         unset($account['entrydate']);
//         $this->assertEquals($expectedAccount, $account);

//         // -------------------------------------------------
//         // Close Account
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('POST')
//             ->withPath("/account/closeaccount/" . $result['accountid'])
//         ;
//         $this->assertRequest($request);

//         // Check
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/account/{$result['accountid']}")
//         ;
//         $account = $this->assertRequest($request);
//         $expectedAccount = [
//             "accountid" => $result['accountid'],
//             "accounttypeid" => "USDTEST",
//             "userid" => "___TESTUSER-10",
//             "balance" => 0,
//             "reserved" => 0,
//             "available" => 0,
//             "price" => 0,
//             "extra" => "Extra Information",
//             "minvalue" => 0
//         ];
//         unset($account['entrydate']);
//         $this->assertEquals($expectedAccount, $account);
//     }

//     /**
//      * test account
//      *
//      * @throws \ByJG\Swagger\Exception\HttpMethodNotFoundException
//      * @throws \ByJG\Swagger\Exception\InvalidDefinitionException
//      * @throws \ByJG\Swagger\Exception\NotMatchedException
//      * @throws \ByJG\Swagger\Exception\PathNotFoundException
//      * @throws \ByJG\Swagger\Exception\RequiredArgumentNotFound
//      * @throws \Exception
//      */
//     public function testStatementAddFunds()
//     {
//         // -------------------------------------
//         // Create an Account
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('POST')
//             ->withRequestBody([
//                 "accounttypeid" => "USDTEST",
//                 "userid"        => "___TESTUSER-10",
//                 "balance"       => 1000,
//                 "price"         => 1,
//                 "extra"         => "Extra Information",
//                 "minvalue"      => 0
//             ])
//             ->withPath("/account");
//         $result = $this->assertRequest($request);

//         // -------------------------------------
//         // Add Funds
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('POST')
//             ->withRequestBody([
//                 "accountid"     => $result["accountid"],
//                 "amount"        => 150,
//                 "referenceid"     => "C0CADA-DAB0A",
//                 "description"   => "Add Funds",
//             ])
//             ->withPath("/statement/addfunds");
//         $statement = $this->assertRequest($request);

//         // Check Account
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/account/{$result['accountid']}")
//         ;
//         $account = $this->assertRequest($request);
//         $expectedAccount = [
//             "accountid" => $result['accountid'],
//             "accounttypeid" => "USDTEST",
//             "userid" => "___TESTUSER-10",
//             "balance" => 1150,
//             "reserved" => 0,
//             "available" => 1150,
//             "price" => 1,
//             "extra" => "Extra Information",
//             "minvalue" => 0
//         ];
//         unset($account['entrydate']);
//         $this->assertEquals($expectedAccount, $account);

//         // Check Statement
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/statement/{$statement['statementid']}")
//         ;
//         $statementResult = $this->assertRequest($request);
//         $expectedStatement = [
//             'price' => 1,
//             'statementid' => $statement['statementid'],
//             'accountid' => $result['accountid'],
//             'typeid' => 'D',
//             'amount' => 150,
//             'balance' => 1150,
//             'reserved' => 0,
//             'available' => 1150,
//             'description' => 'Add Funds',
//             'statementparentid' => '',
//             'referenceid' => 'C0CADA-DAB0A',
//             'accounttypeid' => 'USDTEST'
//         ];
//         unset($statementResult['date']);
//         $this->assertEquals($expectedStatement, $statementResult);
//     }


//     /**
//      * test account
//      *
//      * @throws \ByJG\Swagger\Exception\HttpMethodNotFoundException
//      * @throws \ByJG\Swagger\Exception\InvalidDefinitionException
//      * @throws \ByJG\Swagger\Exception\NotMatchedException
//      * @throws \ByJG\Swagger\Exception\PathNotFoundException
//      * @throws \ByJG\Swagger\Exception\RequiredArgumentNotFound
//      * @throws \Exception
//      */
//     public function testStatementWithdrawFunds()
//     {
//         // -------------------------------------
//         // Create an Account
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('POST')
//             ->withRequestBody([
//                 "accounttypeid" => "USDTEST",
//                 "userid"        => "___TESTUSER-10",
//                 "balance"       => 1000,
//                 "price"         => 1,
//                 "extra"         => "Extra Information",
//                 "minvalue"      => 0
//             ])
//             ->withPath("/account");
//         $result = $this->assertRequest($request);

//         // -------------------------------------
//         // Withdraw Funds
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('POST')
//             ->withRequestBody([
//                 "accountid"     => $result["accountid"],
//                 "amount"        => 150,
//                 "referenceid"     => "C0CADA-DAB0A",
//                 "description"   => "Withdraw Funds",
//             ])
//             ->withPath("/statement/withdrawfunds");
//         $statement = $this->assertRequest($request);

//         // Check Account
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/account/{$result['accountid']}")
//         ;
//         $account = $this->assertRequest($request);
//         $expectedAccount = [
//             "accountid" => $result['accountid'],
//             "accounttypeid" => "USDTEST",
//             "userid" => "___TESTUSER-10",
//             "balance" => 850,
//             "reserved" => 0,
//             "available" => 850,
//             "price" => 1,
//             "extra" => "Extra Information",
//             "minvalue" => 0
//         ];
//         unset($account['entrydate']);
//         $this->assertEquals($expectedAccount, $account);

//         // Check Statement
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/statement/{$statement['statementid']}")
//         ;
//         $statementResult = $this->assertRequest($request);
//         $expectedStatement = [
//             'price' => 1,
//             'statementid' => $statement['statementid'],
//             'accountid' => $result['accountid'],
//             'typeid' => 'W',
//             'amount' => 150,
//             'balance' => 850,
//             'reserved' => 0,
//             'available' => 850,
//             'description' => 'Withdraw Funds',
//             'statementparentid' => '',
//             'referenceid' => 'C0CADA-DAB0A',
//             'accounttypeid' => 'USDTEST'
//         ];
//         unset($statementResult['date']);
//         $this->assertEquals($expectedStatement, $statementResult);
//     }


//     /**
//      * test account
//      *
//      * @throws \ByJG\Swagger\Exception\HttpMethodNotFoundException
//      * @throws \ByJG\Swagger\Exception\InvalidDefinitionException
//      * @throws \ByJG\Swagger\Exception\NotMatchedException
//      * @throws \ByJG\Swagger\Exception\PathNotFoundException
//      * @throws \ByJG\Swagger\Exception\RequiredArgumentNotFound
//      * @throws \Exception
//      */
//     public function testStatementAcceptFunds()
//     {
//         // -------------------------------------
//         // Step 1. Create an Account
//         // -------------------------------------
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('POST')
//             ->withRequestBody([
//                 "accounttypeid" => "USDTEST",
//                 "userid"        => "___TESTUSER-10",
//                 "balance"       => 1000,
//                 "price"         => 1,
//                 "extra"         => "Extra Information",
//                 "minvalue"      => 0
//             ])
//             ->withPath("/account");
//         $result = $this->assertRequest($request);

//         // -------------------------------------
//         // Step 2.1: Reserve for Withdraw Funds
//         // -------------------------------------
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('POST')
//             ->withRequestBody([
//                 "accountid"     => $result["accountid"],
//                 "amount"        => 150,
//                 "referenceid"     => "C0CADA-DAB0A-01",
//                 "description"   => "Withdraw Funds 01",
//             ])
//             ->withPath("/statement/reservefundsforwithdraw");
//         $statement = $this->assertRequest($request);

//         // -------------------------------------
//         // Step 2.2: Reserve for Withdraw Funds
//         // -------------------------------------
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('POST')
//             ->withRequestBody([
//                 "accountid"     => $result["accountid"],
//                 "amount"        => 250,
//                 "referenceid"     => "C0CADA-DAB0A-02",
//                 "description"   => "Withdraw Funds 02",
//             ])
//             ->withPath("/statement/reservefundsforwithdraw");
//         $statement2 = $this->assertRequest($request);

//         // ===================================================
//         // Step 3: Check the new Account Balance
//         // ===================================================
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/account/{$result['accountid']}")
//         ;
//         $account = $this->assertRequest($request);
//         $expectedAccount = [
//             "accountid" => $result['accountid'],
//             "accounttypeid" => "USDTEST",
//             "userid" => "___TESTUSER-10",
//             "balance" => 1000,
//             "reserved" => 400,
//             "available" => 600,
//             "price" => 1,
//             "extra" => "Extra Information",
//             "minvalue" => 0
//         ];
//         unset($account['entrydate']);
//         $this->assertEquals($expectedAccount, $account);

//         // Check Statement 1
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/statement/{$statement['statementid']}")
//         ;
//         $statementResult1 = $this->assertRequest($request);
//         $expectedStatement1 = [
//             'price' => 1,
//             'statementid' => $statement['statementid'],
//             'accountid' => $result['accountid'],
//             'typeid' => 'WB',
//             'amount' => 150,
//             'balance' => 1000,
//             'reserved' => 150,
//             'available' => 850,
//             'description' => 'Withdraw Funds 01',
//             'statementparentid' => '',
//             'referenceid' => 'C0CADA-DAB0A-01',
//             'accounttypeid' => 'USDTEST'
//         ];
//         unset($statementResult1['date']);
//         $this->assertEquals($expectedStatement1, $statementResult1);

//         // Check Statement 2
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/statement/{$statement2['statementid']}")
//         ;
//         $statementResult2 = $this->assertRequest($request);
//         $expectedStatement2 = [
//             'price' => 1,
//             'statementid' => $statement2['statementid'],
//             'accountid' => $result['accountid'],
//             'typeid' => 'WB',
//             'amount' => 250,
//             'balance' => 1000,
//             'reserved' => 400,
//             'available' => 600,
//             'description' => 'Withdraw Funds 02',
//             'statementparentid' => '',
//             'referenceid' => 'C0CADA-DAB0A-02',
//             'accounttypeid' => 'USDTEST'
//         ];
//         unset($statementResult2['date']);
//         $this->assertEquals($expectedStatement2, $statementResult2);

//         // Get Uncleread Statement.
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/statement/reservedstatements/{$result['accountid']}")
//         ;
//         $reservedStatements = $this->assertRequest($request);
//         unset($reservedStatements[0]['date']);
//         unset($reservedStatements[1]['date']);
//         $this->assertEquals(
//             [
//                 $expectedStatement2,
//                 $expectedStatement1,
//             ],
//             $reservedStatements
//         );

//         // -------------------------------------
//         // Step 4. Accept and Reject Funds
//         // -------------------------------------
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('POST')
//             ->withPath("/statement/rejectfunds/{$statement['statementid']}");
//         $st01 = $this->assertRequest($request);

//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('POST')
//             ->withPath("/statement/acceptfunds/{$statement2['statementid']}");
//         $st02 = $this->assertRequest($request);


//         // =========================================
//         // Step 5. Check the Balance with the funds accepted.
//         // =========================================
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/account/{$result['accountid']}")
//         ;
//         $account = $this->assertRequest($request);
//         $expectedAccount = [
//             "accountid" => $result['accountid'],
//             "accounttypeid" => "USDTEST",
//             "userid" => "___TESTUSER-10",
//             "balance" => 750,
//             "reserved" => 0,
//             "available" => 750,
//             "price" => 1,
//             "extra" => "Extra Information",
//             "minvalue" => 0
//         ];
//         unset($account['entrydate']);
//         $this->assertEquals($expectedAccount, $account);

//         // Check Statement
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/statement/{$st01['statementid']}")
//         ;
//         $statementResult = $this->assertRequest($request);
//         $expectedStatement = [
//             'price' => 1,
//             'statementid' => $st01['statementid'],
//             'accountid' => $result['accountid'],
//             'typeid' => 'R',
//             'amount' => 150,
//             'balance' => 1000,
//             'reserved' => 250,
//             'available' => 750,
//             'description' => 'Withdraw Funds 01',
//             'statementparentid' => $statement['statementid'],
//             'referenceid' => 'C0CADA-DAB0A-01',
//             'accounttypeid' => 'USDTEST'
//         ];
//         unset($statementResult['date']);
//         $this->assertEquals($expectedStatement, $statementResult);

//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/statement/{$st02['statementid']}")
//         ;
//         $statementResult = $this->assertRequest($request);
//         $expectedStatement = [
//             'price' => 1,
//             'statementid' => $st02['statementid'],
//             'accountid' => $result['accountid'],
//             'typeid' => 'W',
//             'amount' => 250,
//             'balance' => 750,
//             'reserved' => 0,
//             'available' => 750,
//             'description' => 'Withdraw Funds 02',
//             'statementparentid' => $statement2['statementid'],
//             'referenceid' => 'C0CADA-DAB0A-02',
//             'accounttypeid' => 'USDTEST'
//         ];
//         unset($statementResult['date']);
//         $this->assertEquals($expectedStatement, $statementResult);
//     }


//     /**
//      * test account
//      *
//      * @throws \ByJG\Swagger\Exception\HttpMethodNotFoundException
//      * @throws \ByJG\Swagger\Exception\InvalidDefinitionException
//      * @throws \ByJG\Swagger\Exception\NotMatchedException
//      * @throws \ByJG\Swagger\Exception\PathNotFoundException
//      * @throws \ByJG\Swagger\Exception\RequiredArgumentNotFound
//      * @throws \Exception
//      */
//     public function testAccountByUserId()
//     {
//         // -------------------------------------
//         // Create an Account
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('POST')
//             ->withRequestBody([
//                 "accounttypeid" => "USDTEST",
//                 "userid"        => "___TESTUSER-10",
//                 "balance"       => 1000,
//                 "price"         => 1,
//                 "extra"         => "Extra Information",
//                 "minvalue"      => 0
//             ])
//             ->withPath("/account");
//         $account = $this->assertRequest($request);

//         // Check
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/account/userid/-10");
//         $result = $this->assertRequest($request);
//         unset($result[0]['entrydate']);

//         $this->assertEquals([
//             [
//                 "accountid" => $account["accountid"],
//                 "accounttypeid" => "USDTEST",
//                 "userid" => "___TESTUSER-10",
//                 "balance" => 1000,
//                 "reserved" => 0,
//                 "available" => 1000,
//                 "price" => 1,
//                 "extra" => "Extra Information",
//                 "minvalue" => "0.00"
//             ]
//         ], $result);
//     }

//     /**
//      * test account
//      *
//      * @throws \ByJG\Swagger\Exception\HttpMethodNotFoundException
//      * @throws \ByJG\Swagger\Exception\InvalidDefinitionException
//      * @throws \ByJG\Swagger\Exception\NotMatchedException
//      * @throws \ByJG\Swagger\Exception\PathNotFoundException
//      * @throws \ByJG\Swagger\Exception\RequiredArgumentNotFound
//      * @throws \Exception
//      */
//     public function testAccountByType()
//     {
//         // -------------------------------------
//         // Create an Account
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('POST')
//             ->withRequestBody([
//                 "accounttypeid" => "ABCTEST",
//                 "userid"        => "___TESTUSER-10",
//                 "balance"       => 1000,
//                 "price"         => 1,
//                 "extra"         => "Extra Information",
//                 "minvalue"      => 0
//             ])
//             ->withPath("/account");
//         $account = $this->assertRequest($request);

//         // Check
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/account/type/ABCTEST");
//         $result = $this->assertRequest($request);
//         unset($result[0]['entrydate']);

//         $this->assertEquals([
//             [
//                 "accountid" => $account["accountid"],
//                 "accounttypeid" => "ABCTEST",
//                 "userid" => "___TESTUSER-10",
//                 "balance" => 1000,
//                 "reserved" => 0,
//                 "available" => 1000,
//                 "price" => 1,
//                 "extra" => "Extra Information",
//                 "minvalue" => "0.00"
//             ]
//         ], $result);
//     }

//     public function testAccountType()
//     {
//         // Create a new AccountTypeId
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('POST')
//             ->withRequestBody([
//                 "accounttypeid" => "XXXTEST",
//                 "name" => "Test XXX 1"
//             ])
//             ->withPath("/accounttype")
//         ;
//         $result = $this->assertRequest($request);

//         // Update an existing account type
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('POST')
//             ->withRequestBody([
//                 "accounttypeid" => $result["accounttypeid"],
//                 "name" => "Test XXX 2"
//             ])
//             ->withPath("/accounttype")
//         ;
//         $this->assertRequest($request);

//         // Get an Account Type
//         $request = new SwaggerRequester();
//         $request
//             ->withMethod('GET')
//             ->withPath("/accounttype/XXXTEST")
//         ;
//         $this->assertRequest($request);
//     }

// }
