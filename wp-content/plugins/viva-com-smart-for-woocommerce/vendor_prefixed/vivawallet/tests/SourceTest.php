<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Tests;

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Core\Source\SourceItem;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api\SourceClient;
class SourceTest extends BearerAuthenticationTest
{
    private const RANDOM_STRING_LENGTH = 10;
    public function testGetDemoSources()
    {
        $demoBearerAuthentication = self::testGetBackScopeDemoBearerAuthentication();
        $demoSource = new SourceClient($demoBearerAuthentication);
        $sourcesResponse = $demoSource->getSources();
        $this->assertTrue($sourcesResponse->isSuccessful());
        $this->assertNotEmpty($sourcesResponse->getBody());
        $this->assertIsObject($sourcesResponse->getBody());
    }
    public function testCreateSource() : string
    {
        $demoBearerAuthentication = self::testGetBackScopeDemoBearerAuthentication();
        $code = \strval(\rand(1000, 9999));
        $demoSource = new SourceClient($demoBearerAuthentication);
        $sourcesResponse = $demoSource->createSource(new SourceItem((object) ['sourceCode' => $code, 'name' => 'PHPUnit test source ' . $code, 'domain' => 'www.' . $this->generateRandomString(self::RANDOM_STRING_LENGTH) . '.com']));
        $this->assertTrue($sourcesResponse->isSuccessful());
        return $code;
    }
    private function generateRandomString(int $length = 10) : string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = \strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[\rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    /**
     * @depends testCreateSource
     */
    public function testCheckDemoSource(string $sourceCode)
    {
        $demoBearerAuthentication = self::testGetBackScopeDemoBearerAuthentication();
        $demoSource = new SourceClient($demoBearerAuthentication);
        $sourceCode = 'WC-0001';
        $sourcesResponse = $demoSource->checkSource($sourceCode);
        $this->assertTrue($sourcesResponse->isSuccessful());
        $this->assertNotEmpty($sourcesResponse->getBody());
        $this->assertIsObject($sourcesResponse->getBody());
    }
}
