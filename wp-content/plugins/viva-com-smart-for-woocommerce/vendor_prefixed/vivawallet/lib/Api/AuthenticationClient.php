<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api;

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Application;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Authentication\BasicAuthentication;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Client;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Response;
class AuthenticationClient
{
    private $httpClient;
    public function __construct($config = [])
    {
        $this->httpClient = new Client($config);
    }
    public function getBearerToken(BasicAuthentication $basicAuthentication, $grantType, $scope) : Response
    {
        return $this->httpClient->request('post', Application::BASE_URLS[$basicAuthentication->getEnvironment()]['accounts'] . Application::ENDPOINTS['accountsToken'], ['form_params' => ['grant_type' => $grantType, 'scope' => $scope], 'headers' => ['Content-type' => 'application/x-www-form-urlencoded', 'Authorization' => $basicAuthentication->getHeader()]]);
    }
}
