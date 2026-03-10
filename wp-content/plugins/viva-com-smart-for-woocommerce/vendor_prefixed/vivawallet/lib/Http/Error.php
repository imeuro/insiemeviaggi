<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http;

class Error
{
    private $message;
    private $request;
    private $response;
    /**
     * Error constructor.
     *
     * @param string $message
     * @param array $request
     * @param array $response
     */
    public function __construct(string $message, array $request = [], array $response = [])
    {
        $this->message = $message;
        $this->request = $request;
        $this->response = $response;
    }
    /**
     * @return string
     */
    public function getMessage() : string
    {
        return $this->message;
    }
    public function setMessage(string $message) : void
    {
        $this->message = $message;
    }
    /**
     * @return array
     */
    public function getRequest() : array
    {
        return $this->request;
    }
    public function setRequest(array $request) : void
    {
        $this->request = $request;
    }
    /**
     * @return array
     */
    public function getResponse() : array
    {
        return $this->response;
    }
    public function setResponse(array $response) : void
    {
        $this->response = $response;
    }
}
