<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Tests;

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api\TransactionClient;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Application;
class TransactionTest extends BearerAuthenticationTest
{
    private const AMOUNT = 3495;
    private const SOURCE_CODE = 'SF-0005';
    private const CURRENCY_CODE = 'EUR';
    private const MESSAGES = ['merchant' => 'Merchant Message: Transaction from PHPUnit test.', 'customer' => 'Customer Message: Transaction from PHPUnit test.'];
    private const CUSTOMER = ['email' => 'test_account@vivawallet.com', 'fullName' => 'TestName TestSurname', 'phone' => '302111111111', 'requestLang' => 'en-GB', 'countryCode' => 'GR'];
    private const CARD_OPTIONS = ['holderName' => 'John Papadopoulos', 'number' => '5239290700000101', 'expirationMonth' => '10', 'expirationYear' => '2025', 'cvc' => '111', 'sessionRedirectUrl' => 'https://www.example.com'];
    private const PREAUTH = \true;
    public function __construct()
    {
        parent::__construct();
        Application::setInformation(['vivaWallet' => ['version' => '1.0.1'], 'cms' => ['version' => '1.7.1', 'abbreviation' => 'PU', 'name' => 'PhpUnit']]);
    }
    public function testCreateGetChargeToken()
    {
        $demoBearerAuthentication = self::testGetFrontScopeDemoBearerAuthentication();
        $demoTransaction = new TransactionClient($demoBearerAuthentication);
        $transactionResponse = $demoTransaction->getChargeToken(self::AMOUNT, self::CARD_OPTIONS);
        $this->assertTrue($transactionResponse->isSuccessful());
        $this->assertTrue(isset($transactionResponse->getBody()->chargeToken));
        return $transactionResponse->getBody()->chargeToken;
    }
    /**
     * @depends testCreateGetChargeToken
     */
    public function testCreateTransaction(string $chargeToken) : string
    {
        $demoBearerAuthentication = self::testGetBackScopeDemoBearerAuthentication();
        $demoTransaction = new TransactionClient($demoBearerAuthentication);
        $transactionResponse = $demoTransaction->createTransaction(self::AMOUNT, self::CURRENCY_CODE, $chargeToken, ['sourceCode' => self::SOURCE_CODE, 'allowsRecurring' => \true, 'messages' => self::MESSAGES, 'customer' => self::CUSTOMER, 'preauth' => self::PREAUTH]);
        $this->assertTrue($transactionResponse->isSuccessful());
        $this->assertTrue(isset($transactionResponse->getBody()->transactionId));
        return $transactionResponse->getBody()->transactionId;
    }
    /**
     * @depends testCreateTransaction
     */
    public function testRetrieveTransactionByTransactionId(string $transactionId)
    {
        $demoBearerAuthentication = self::testGetBackScopeDemoBearerAuthentication();
        $demoTransaction = new TransactionClient($demoBearerAuthentication);
        $transactionResponse = $demoTransaction->retrieveTransactionById($transactionId);
        $this->assertTrue($transactionResponse->isSuccessful());
    }
    /**
     * @depends testCreateTransaction
     */
    public function testCaptureTransaction(string $transactionId)
    {
        $demoBearerAuthentication = self::testGetBackScopeDemoBearerAuthentication();
        $demoTransaction = new TransactionClient($demoBearerAuthentication);
        $transactionResponse = $demoTransaction->captureTransaction($transactionId, self::AMOUNT, self::SOURCE_CODE);
        $this->assertTrue($transactionResponse->isSuccessful());
        $this->assertTrue(isset($transactionResponse->getBody()->transactionId));
        return $transactionResponse->getBody()->transactionId;
    }
    /**
     * @depends testCreateTransaction
     */
    public function testCreateRecurringTransaction(string $transactionId) : string
    {
        $demoBearerAuthentication = self::testGetBackScopeDemoBearerAuthentication();
        $demoTransaction = new TransactionClient($demoBearerAuthentication);
        $transactionResponse = $demoTransaction->createRecurringTransaction($transactionId, self::AMOUNT, ['sourceCode' => self::SOURCE_CODE, 'messages' => self::MESSAGES]);
        $this->assertTrue($transactionResponse->isSuccessful());
        $this->assertTrue(isset($transactionResponse->getBody()->transactionId));
        return $transactionResponse->getBody()->transactionId;
    }
    /**
     * @depends testCaptureTransaction
     */
    public function testFullRefundTransaction(string $transactionId)
    {
        $demoBearerAuthentication = self::testGetBackScopeDemoBearerAuthentication();
        $demoTransaction = new TransactionClient($demoBearerAuthentication);
        $transactionResponse = $demoTransaction->refundTransaction($transactionId, self::AMOUNT, self::SOURCE_CODE);
        $this->assertTrue($transactionResponse->isSuccessful());
        $this->assertTrue(isset($transactionResponse->getBody()->transactionId));
    }
    /**
     * @depends testCreateRecurringTransaction
     */
    public function testPartialRefundTransaction(string $transactionId)
    {
        $demoBearerAuthentication = self::testGetBackScopeDemoBearerAuthentication();
        $demoTransaction = new TransactionClient($demoBearerAuthentication);
        $transactionResponse = $demoTransaction->refundTransaction($transactionId, self::AMOUNT / 2, self::SOURCE_CODE);
        $this->assertTrue($transactionResponse->isSuccessful());
        $this->assertTrue(isset($transactionResponse->getBody()->transactionId));
    }
    /**
     * @depends testCreateTransaction
     */
    public function testCaptureAuthorizedTransaction(string $transactionId) : string
    {
        $demoBearerAuthentication = self::testGetBackScopeDemoBearerAuthentication();
        $demoTransaction = new TransactionClient($demoBearerAuthentication);
        $transactionResponse = $demoTransaction->captureAuthorizedTransaction($transactionId, self::AMOUNT, ['sourceCode' => self::SOURCE_CODE, 'messages' => self::MESSAGES]);
        $this->assertTrue($transactionResponse->isSuccessful());
        $this->assertTrue(isset($transactionResponse->getBody()->transactionId));
        return $transactionResponse->getBody()->transactionId;
    }
    /**
     * @depends testCaptureAuthorizedTransaction
     */
    public function testFullVoidTransaction(string $transactionId)
    {
        $demoBearerAuthentication = self::testGetBackScopeDemoBearerAuthentication();
        $demoTransaction = new TransactionClient($demoBearerAuthentication);
        $transactionResponse = $demoTransaction->voidAuthorizedTransaction($transactionId, self::AMOUNT, self::SOURCE_CODE);
        $this->assertTrue($transactionResponse->isSuccessful());
        $this->assertTrue(isset($transactionResponse->getBody()->transactionId));
    }
    /**
     * @depends testCaptureAuthorizedTransaction
     */
    public function testPartialVoidTransaction(string $transactionId)
    {
        $demoBearerAuthentication = self::testGetBackScopeDemoBearerAuthentication();
        $demoTransaction = new TransactionClient($demoBearerAuthentication);
        $transactionResponse = $demoTransaction->voidAuthorizedTransaction($transactionId, self::AMOUNT / 2, self::SOURCE_CODE);
        $this->assertTrue($transactionResponse->isSuccessful());
        $this->assertTrue(isset($transactionResponse->getBody()->transactionId));
    }
}
