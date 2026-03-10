<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api;

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api\Request\OrderRequest;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Application;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Authentication\Authentication;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Client;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Response;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Utils\Utils;
/**
 * Api connection for Order
 *
 * @package VivaWallet\Api
 */
class OrderClient
{
    private $httpClient;
    private $authentication;
    /**
     * Order constructor.
     *
     * @param Authentication $authentication authentication for api connection
     */
    public function __construct(Authentication $authentication, $config = [])
    {
        $this->authentication = $authentication;
        $this->httpClient = new Client($config);
    }
    /**
     * Create a payment order.
     *
     * @param int $amount amount of the order.
     * @param string $currencyCode currency of the amount.
     * @param array $options array containing options for creating order. Default empty array. Optional.
     * @param Authentication|null $authentication
     *
     * @return Response
     */
    public function createOrder(int $amount, string $currencyCode, array $options = [], ?Authentication $authentication = null) : Response
    {
        $authentication = \is_null($authentication) ? $this->authentication : $authentication;
        return $this->httpClient->request('post', Application::BASE_URLS[$authentication->getEnvironment()]['api'] . Application::ENDPOINTS['pluginOrders'], ['json' => OrderRequest::getCreateOrder($amount, $currencyCode, $options), 'headers' => ['Accept' => 'application/json', 'User-Agent' => Utils::getCustomUserAgent(), 'Authorization' => $authentication->getHeader()]]);
    }
    /**
     * Retrieve payment Order.
     *
     * @param array $options options
     * @param Authentication|null $authentication
     *
     * @return Response
     */
    public function retrieveOrder(array $options, ?Authentication $authentication = null) : Response
    {
        $authentication = \is_null($authentication) ? $this->authentication : $authentication;
        return $this->httpClient->request('get', Application::BASE_URLS[$this->authentication->getEnvironment()]['api'] . Application::ENDPOINTS['order'] . (!empty($options['orderCode']) ? "/{$options['orderCode']}" : ''), ['headers' => ['Accept' => 'application/json', 'Authorization' => $authentication->getHeader()]]);
    }
}
