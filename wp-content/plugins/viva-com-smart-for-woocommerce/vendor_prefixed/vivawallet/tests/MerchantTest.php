<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Tests;

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api\MerchantClient;
class MerchantTest extends BearerAuthenticationTest
{
    public function testMerchantInfo()
    {
        $demoBearerAuthentication = self::testGetBackScopeDemoBearerAuthentication();
        $client = new MerchantClient($demoBearerAuthentication);
        $response = $client->getInfo();
        $this->assertTrue($response->isSuccessful());
    }
}
