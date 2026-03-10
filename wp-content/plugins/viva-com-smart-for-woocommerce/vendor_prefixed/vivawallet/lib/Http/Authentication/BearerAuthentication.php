<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Authentication;

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api\AuthenticationClient;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Response;
class BearerAuthentication implements Authentication
{
    private const TYPE = 'Bearer';
    private $token = null;
    private $ttl = null;
    private $scope;
    private $grantType;
    private $response;
    private $environment;
    public function __construct(string $environment, string $username, string $password, string $grantType, ?string $scope = null)
    {
        $this->environment = $environment;
        $this->scope = $scope;
        $this->grantType = $grantType;
        if (!empty($username) && !empty($password)) {
            $this->setNewToken($username, $password);
        }
    }
    private function setNewToken($username, $password)
    {
        $authenticationClient = new AuthenticationClient();
        $this->response = $authenticationClient->getBearerToken(new BasicAuthentication($username, $password, $this->environment), $this->grantType, $this->scope);
        if ($this->response->isSuccessful() && isset($this->response->getBody()->access_token)) {
            $this->token = $this->response->getBody()->access_token;
            $this->ttl = $this->response->getBody()->expires_in;
        }
    }
    public function getToken() : ?string
    {
        return $this->token;
    }
    public function getTtl() : ?string
    {
        return $this->ttl;
    }
    public function getScope() : string
    {
        return $this->scope;
    }
    public function getGrantType() : string
    {
        return $this->grantType;
    }
    public function getEnvironment() : string
    {
        return $this->environment;
    }
    public function getResponse() : ?Response
    {
        return $this->response;
    }
    public function getHeader() : string
    {
        return \implode(' ', [self::TYPE, $this->token]);
    }
    public function hasValidToken() : bool
    {
        return !empty($this->token);
    }
    public function __serialize() : array
    {
        return ['token' => $this->token, 'ttl' => $this->ttl, 'scope' => $this->scope, 'grantType' => $this->grantType, 'environment' => $this->environment];
    }
    public function __unserialize(array $data) : void
    {
        $this->token = $data['token'];
        $this->ttl = $data['ttl'];
        $this->scope = $data['scope'];
        $this->grantType = $data['grantType'];
        $this->environment = $data['environment'];
    }
}
