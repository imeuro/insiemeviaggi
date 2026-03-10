<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Tests;

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Log\DiagnosticsLogger;
class DiagnosticsLogTest extends BearerAuthenticationTest
{
    private $logs = [["eventId" => "1001", "error" => "Test error", "source" => "Plugins SDK", "channel" => "development", "message" => "Test Message", "created" => "2021-07-28T10:40:00Z", "correlationId" => "84-191-F79568ER", "applicationKey" => "com.vivawallet.plugins", "userHostAddress" => ""]];
    public function __construct()
    {
        parent::__construct();
        $this->logs[0]['eventData'] = (object) [];
    }
    public function testDiagnosticsLogTest()
    {
        $demoBearerAuthentication = self::testGetBackScopeDemoBearerAuthentication();
        $demoDiagnosticsLogger = new DiagnosticsLogger($demoBearerAuthentication);
        $demoDiagnosticsResponse = $demoDiagnosticsLogger->log($this->logs);
        $this->assertTrue($demoDiagnosticsResponse->isSuccessful());
        $this->assertNotEmpty($demoDiagnosticsResponse->getBody());
        $this->assertIsInt($demoDiagnosticsResponse->getBody());
        $this->assertGreaterThan(0, $demoDiagnosticsResponse->getBody());
    }
}
