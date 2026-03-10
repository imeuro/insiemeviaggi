<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api;

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Application;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Core\Source\SourceItem;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Core\Source\SourceList;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Authentication\Authentication;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Client;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Response;
class SourceClient
{
    private $httpClient;
    private $authentication;
    public function __construct(Authentication $authentication, $config = [])
    {
        $this->authentication = $authentication;
        $this->httpClient = new Client($config);
    }
    public function createSource(SourceItem $sourceItem) : Response
    {
        return $this->httpClient->request('post', Application::BASE_URLS[$this->authentication->getEnvironment()]['api'] . Application::ENDPOINTS['sources'], ['json' => ['domain' => $sourceItem->getDomain(), 'sourceCode' => $sourceItem->getCode(), 'name' => $sourceItem->getName(), 'state' => $sourceItem->getState(), 'pathSuccess' => $sourceItem->getSuccessUrl(), 'pathFail' => $sourceItem->getFailureUrl(), 'paramCancelOrder' => $sourceItem->getParamCancelOrder()], 'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'Authorization' => $this->authentication->getHeader()]]);
    }
    public function checkSource($code) : Response
    {
        $sourcesResponse = $this->httpClient->request('get', Application::BASE_URLS[$this->authentication->getEnvironment()]['api'] . Application::ENDPOINTS['sources'], ['query' => ['sourceCode' => $code], 'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'Authorization' => $this->authentication->getHeader()]]);
        if ($sourcesResponse->isSuccessful()) {
            $source = $sourcesResponse->getBody()[0];
            $sourcesResponse->setBody(new SourceItem($source));
        }
        return $sourcesResponse;
    }
    public function getSources() : Response
    {
        $sourcesResponse = $this->httpClient->request('get', Application::BASE_URLS[$this->authentication->getEnvironment()]['api'] . Application::ENDPOINTS['sources'], ['headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'Authorization' => $this->authentication->getHeader()]]);
        if ($sourcesResponse->isSuccessful()) {
            $sourcesResponse->setBody(new SourceList($sourcesResponse->getBody()));
        }
        return $sourcesResponse;
    }
}
