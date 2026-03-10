<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http;

use VivaComSmartCheckout\GuzzleHttp\Exception\TransferException;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Utils\Utils;
class Client
{
    private const TIMEOUT = 30;
    private $client;
    public function __construct($config = [])
    {
        $this->client = new \VivaComSmartCheckout\GuzzleHttp\Client(\array_merge(['timeout' => self::TIMEOUT], $config));
    }
    public function request($method, $url, $options = []) : Response
    {
        $success = \true;
        $statusCode = 500;
        $body = $error = $headers = null;
        try {
            $response = $this->response($method, $url, $options);
            $statusCode = $response->getStatusCode();
            $headers = $response->getHeaders();
            $contents = $response->getBody()->getContents();
            $body = \json_decode($contents, \false, 512, \JSON_BIGINT_AS_STRING);
            if (\is_null($body)) {
                $body = $contents;
            }
            if (!Utils::isHttpStatusCodeValid($statusCode)) {
                $success = \false;
                $error = new Error($response->getReasonPhrase(), ['method' => $method, 'url' => $url, 'options' => $options], ['headers' => $headers, 'body' => $body, 'exception' => null]);
            }
        } catch (TransferException $exception) {
            $success = \false;
            if (\method_exists($exception, 'hasResponse') && \method_exists($exception, 'getResponse') && $exception->hasResponse()) {
                $statusCode = (int) $exception->getResponse()->getStatusCode();
                $headers = $exception->getResponse()->getHeaders();
                $body = \json_decode($exception->getResponse()->getBody()->getContents(), \false, 512, \JSON_BIGINT_AS_STRING);
                $body = !\is_object($body) && !\is_array($body) ? null : $body;
            }
            $error = new Error($exception->getCode() . '-' . $exception->getMessage(), ['method' => $method, 'url' => $url, 'options' => $options], ['headers' => $headers, 'body' => $body, 'exception' => $exception]);
        }
        return new Response($success, $statusCode, $body, $headers, $error);
    }
    public function response($method, $url, $options = [])
    {
        if (isset($options['form_params'])) {
            $options['body'] = \http_build_query($options['form_params'], '', '&');
            unset($options['form_params']);
        }
        return $this->client->{\strtolower($method)}($url, $options);
    }
}
