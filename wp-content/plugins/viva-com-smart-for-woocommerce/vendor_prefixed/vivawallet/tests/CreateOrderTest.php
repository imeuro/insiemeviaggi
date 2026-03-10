<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Tests;

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api\OrderClient;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Application;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Authentication\BearerAuthentication;
class CreateOrderTest extends BearerAuthenticationTest
{
    private const AMOUNT = 200;
    private const CURRENCY = 'EUR';
    private const OPTIONS = ['sourceCode' => 'SF-0005', 'customer' => ['email' => 'test_account@vivawallet.com', 'fullName' => 'TestName TestSurname', 'phone' => '302111111111', 'requestLang' => 'en-GB', 'countryCode' => 'GR'], 'payment' => ['maxInstallments' => 4, 'allowRecurring' => \false, 'preauth' => \false, 'paymentNotification' => \false, 'tipAmount' => 0, 'disableExactAmount' => \false, 'disableCash' => \true, 'disableWallet' => \false, 'paymentTimeOut' => 1800, 'dynamicDescriptor' => 'Test descriptor'], 'messages' => ['merchant' => 'Merchant Message: Create order from PHPUnit test.', 'customer' => 'Customer Message: Create order from PHPUnit test.'], 'tags' => ['unit test']];
    public function __construct()
    {
        parent::__construct();
        Application::setInformation(['vivaWallet' => ['version' => '1.0.1'], 'cms' => ['version' => '1.7.1', 'abbreviation' => 'PU', 'name' => 'phpUnit']]);
    }
    public function testCreateOrder() : string
    {
        $demoBearerAuthentication = self::testGetBackScopeDemoBearerAuthentication();
        $demoOrder = new OrderClient($demoBearerAuthentication);
        $orderResponse = $demoOrder->createOrder(self::AMOUNT, self::CURRENCY, self::OPTIONS);
        $this->assertTrue($orderResponse->isSuccessful());
        $this->assertNotEmpty($orderResponse->getBody());
        $this->assertIsObject($orderResponse->getBody());
        $this->assertNotEmpty($orderResponse->getBody()->orderCode);
        return $orderResponse->getBody()->orderCode;
    }
}
