<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api;

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api\Request\TransactionRequest;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Application;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Authentication\Authentication;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Client;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Response;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Utils\Utils;
class TransactionClient
{
    private $httpClient;
    private $authentication;
    public function __construct(Authentication $authentication, $config = [])
    {
        $this->authentication = $authentication;
        $this->httpClient = new Client($config);
    }
    public function getChargeToken(int $amount, array $options, ?Authentication $authentication = null) : Response
    {
        $authentication = \is_null($authentication) ? $this->authentication : $authentication;
        return $this->httpClient->request('post', Application::BASE_URLS[$authentication->getEnvironment()]['api'] . Application::ENDPOINTS['nativeChargeToken'], ['json' => TransactionRequest::getChargeToken($amount, $options), 'headers' => ['Accept' => 'application/json', 'Authorization' => $authentication->getHeader()]]);
    }
    public function getDigitalChargeToken(string $sourceCode, string $providerId, string $validationUrl, ?Authentication $authentication = null) : Response
    {
        return $this->httpClient->request('post', Application::BASE_URLS[$authentication->getEnvironment()]['api'] . Application::ENDPOINTS['nativeDigitalChargeToken'], ['json' => TransactionRequest::getDigitalChargeToken($sourceCode, $providerId, $validationUrl), 'headers' => ['Accept' => 'application/json', 'Authorization' => $authentication->getHeader()]]);
    }
    public function createRecurringTransaction(string $transactionId, int $amount, array $options = [], ?Authentication $authentication = null) : Response
    {
        $authentication = \is_null($authentication) ? $this->authentication : $authentication;
        return $this->httpClient->request('post', Application::BASE_URLS[$this->authentication->getEnvironment()]['api'] . Application::ENDPOINTS['acquiring'] . "/{$transactionId}:charge", ['json' => TransactionRequest::getCreateRecurringTransaction($amount, $options), 'headers' => ['Accept' => 'application/json', 'Authorization' => $authentication->getHeader(), 'User-Agent' => Utils::getCustomUserAgent()]]);
    }
    public function createTransaction(int $amount, string $currencyCode, string $chargeToken, array $options = [], ?Authentication $authentication = null) : Response
    {
        $authentication = \is_null($authentication) ? $this->authentication : $authentication;
        return $this->httpClient->request('post', Application::BASE_URLS[$this->authentication->getEnvironment()]['api'] . Application::ENDPOINTS['nativeTransactions'], ['json' => TransactionRequest::getCreateTransaction($amount, $currencyCode, $chargeToken, $options), 'headers' => ['Accept' => 'application/json', 'Authorization' => $authentication->getHeader(), 'User-Agent' => Utils::getCustomUserAgent()]]);
    }
    public function refundTransaction(string $transactionId, int $amount, string $currencyCode, string $sourceCode, ?string $idempotencyKey = null, ?Authentication $authentication = null) : Response
    {
        $authentication = \is_null($authentication) ? $this->authentication : $authentication;
        return $this->httpClient->request('delete', Application::BASE_URLS[$this->authentication->getEnvironment()]['api'] . Application::ENDPOINTS['acquiring'] . "/{$transactionId}", ['query' => TransactionRequest::getRefundTransaction($amount, $currencyCode, $sourceCode, $idempotencyKey), 'headers' => ['Accept' => 'application/json', 'Authorization' => $authentication->getHeader()]]);
    }
    public function retrieveTransactionById(string $transactionId, ?Authentication $authentication = null) : Response
    {
        $authentication = \is_null($authentication) ? $this->authentication : $authentication;
        return $this->retrieveTransaction(['transactionId' => $transactionId], $authentication);
    }
    private function retrieveTransaction(array $options, ?Authentication $authentication = null) : Response
    {
        $authentication = \is_null($authentication) ? $this->authentication : $authentication;
        return $this->httpClient->request('get', Application::BASE_URLS[$this->authentication->getEnvironment()]['api'] . Application::ENDPOINTS['transactions'] . (!empty($options['transactionId']) ? "/{$options['transactionId']}" : ''), ['headers' => ['Accept' => 'application/json', 'Authorization' => $authentication->getHeader()]]);
    }
    public function captureTransaction(string $transactionId, int $amount, string $currencyCode, string $sourceCode, ?Authentication $authentication = null) : Response
    {
        $authentication = \is_null($authentication) ? $this->authentication : $authentication;
        return $this->httpClient->request('post', Application::BASE_URLS[$this->authentication->getEnvironment()]['api'] . Application::ENDPOINTS['nativeTransactions'] . "/{$transactionId}", ['json' => TransactionRequest::getCaptureTransaction($amount, $currencyCode, $sourceCode), 'headers' => ['Accept' => 'application/json', 'Authorization' => $authentication->getHeader(), 'User-Agent' => Utils::getCustomUserAgent()]]);
    }
    public function captureAuthorizedTransaction(string $transactionId, int $amount, array $options = [], ?Authentication $authentication = null) : Response
    {
        $authentication = \is_null($authentication) ? $this->authentication : $authentication;
        return $this->httpClient->request('post', Application::BASE_URLS[$this->authentication->getEnvironment()]['api'] . Application::ENDPOINTS['acquiring'] . "/{$transactionId}:charge", ['json' => TransactionRequest::getCaptureAuthorizedTransaction($amount, $options), 'headers' => ['Accept' => 'application/json', 'Authorization' => $authentication->getHeader(), 'User-Agent' => Utils::getCustomUserAgent()]]);
    }
    public function voidAuthorizedTransaction(string $transactionId, int $amount, string $currencyCode, string $sourceCode, ?string $idempotencyKey = null, ?Authentication $authentication = null) : Response
    {
        $authentication = \is_null($authentication) ? $this->authentication : $authentication;
        return $this->httpClient->request('delete', Application::BASE_URLS[$this->authentication->getEnvironment()]['api'] . Application::ENDPOINTS['acquiring'] . "/{$transactionId}", ['query' => TransactionRequest::getVoidAuthorizedTransaction($amount, $currencyCode, $sourceCode, $idempotencyKey), 'headers' => ['Accept' => 'application/json', 'Authorization' => $authentication->getHeader()]]);
    }
}
