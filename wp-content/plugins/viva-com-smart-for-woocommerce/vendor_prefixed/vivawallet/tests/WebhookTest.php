<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Tests;

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api\WebhookClient;
class WebhookTest extends BearerAuthenticationTest
{
    private const URL = '';
    public function testCreateWebhook()
    {
        $demoBearerAuthentication = self::testGetBackScopeDemoBearerAuthentication();
        $demoWebhook = new WebhookClient($demoBearerAuthentication);
        $webhookResponse = $demoWebhook->createWebhook(self::URL);
        $this->assertTrue($webhookResponse->isSuccessful());
        $this->assertNotEmpty($webhookResponse->getBody());
    }
    public function testGetVerificationProcess()
    {
        $demoBearerAuthentication = self::testGetBackScopeDemoBearerAuthentication();
        $demoWebhook = new WebhookClient($demoBearerAuthentication);
        $webhookResponse = $demoWebhook->getVerificationToken();
        $this->assertTrue($webhookResponse->isSuccessful());
        $this->assertNotEmpty($webhookResponse->getBody());
    }
}
