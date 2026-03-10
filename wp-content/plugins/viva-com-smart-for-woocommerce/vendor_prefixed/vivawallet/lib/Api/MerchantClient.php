<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api;

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Application;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Authentication\Authentication;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Client;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Response;
class MerchantClient
{
    private $httpClient;
    private $authentication;
    public function __construct(Authentication $authentication, $config = [])
    {
        $this->authentication = $authentication;
        $this->httpClient = new Client($config);
    }
    public function getInfo() : Response
    {
        return $this->httpClient->request('get', Application::BASE_URLS[$this->authentication->getEnvironment()]['api'] . Application::ENDPOINTS['merchants'], ['headers' => ['Content-Type' => 'application/json', 'Authorization' => $this->authentication->getHeader()]]);
    }
}
