<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Tests;

use VivaComSmartCheckout\PHPUnit\Framework\TestCase;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Authentication\BearerAuthentication;
class BearerAuthenticationTest extends TestCase
{
    private const ENVIRONMENT = 'demo';
    private const CLIENT_ID = 'XXX';
    private const CLIENT_SECRET = 'XXX';
    private const DEFAULT_GRANT_TYPE = 'client_credentials';
    // must include an extra scope: urn:viva:payments:core:api:plugins:{plugin_scope}
    private const SCOPES = ['back' => ['urn:viva:payments:core:api:plugins', 'urn:viva:payments:core:api:redirectcheckout', 'urn:viva:payments:core:api:acquiring:transactions', 'urn:viva:payments:core:api:acquiring'], 'front' => ['urn:viva:payments:core:api:nativecheckoutv2']];
    public function testGetBackScopeDemoBearerAuthentication() : BearerAuthentication
    {
        $bearerAuthentication = new BearerAuthentication(self::ENVIRONMENT, self::CLIENT_ID, self::CLIENT_SECRET, self::DEFAULT_GRANT_TYPE, \implode(' ', self::SCOPES['back']));
        $this->assertTrue($bearerAuthentication->hasValidToken());
        return $bearerAuthentication;
    }
    public function testGetFrontScopeDemoBearerAuthentication() : BearerAuthentication
    {
        $bearerAuthentication = new BearerAuthentication(self::ENVIRONMENT, self::CLIENT_ID, self::CLIENT_SECRET, self::DEFAULT_GRANT_TYPE, \implode(' ', self::SCOPES['front']));
        $this->assertTrue($bearerAuthentication->hasValidToken());
        return $bearerAuthentication;
    }
}
