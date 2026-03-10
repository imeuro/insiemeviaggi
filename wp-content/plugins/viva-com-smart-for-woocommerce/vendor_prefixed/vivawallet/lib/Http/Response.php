<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http;

class Response
{
    private $httpStatusCode;
    private $body;
    private $headers;
    private $error;
    private $success;
    /**
     * Response constructor.
     *
     * @param bool $success
     * @param int $httpStatusCode
     * @param object|array|null $body
     * @param array|null $headers
     * @param Error|null $error
     */
    public function __construct(bool $success = \true, int $httpStatusCode = 0, $body = null, ?array $headers = null, ?Error $error = null)
    {
        $this->httpStatusCode = $httpStatusCode;
        $this->body = $body;
        $this->headers = $headers;
        $this->success = $success;
        $this->error = $error;
    }
    /**
     * @return array|null
     */
    public function getHeaders() : ?array
    {
        return $this->headers;
    }
    /**
     * @param array|null $headers
     */
    public function setHeaders(?array $headers) : void
    {
        $this->headers = $headers;
    }
    public function getError() : ?Error
    {
        return $this->error;
    }
    public function setError(?Error $error) : void
    {
        $this->error = $error;
    }
    public function getHttpCode() : int
    {
        return $this->httpStatusCode;
    }
    public function setHttpCode(int $httpStatusCode) : void
    {
        $this->httpStatusCode = $httpStatusCode;
    }
    public function setSuccess(bool $success) : void
    {
        $this->success = $success;
    }
    public function getBody()
    {
        return $this->body;
    }
    public function setBody($body) : void
    {
        $this->body = $body;
    }
    public function isSuccessful() : bool
    {
        return $this->success;
    }
    public function hasError() : bool
    {
        return !\is_null($this->error);
    }
    public function all() : array
    {
        return ['body' => $this->body, 'httpStatusCode' => $this->httpStatusCode, 'headers' => $this->headers, 'errorMessage' => !\is_null($this->error) ? $this->error->getMessage() : ''];
    }
}
