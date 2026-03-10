<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Authentication;

class BasicAuthentication implements Authentication
{
    const TYPE = 'Basic';
    private $username;
    private $password;
    public $environment;
    /**
     * Basic constructor.
     * @param string $username
     * @param string $password
     * @param string $environment
     */
    public function __construct(string $username, string $password, string $environment)
    {
        $this->username = $username;
        $this->password = $password;
        $this->environment = $environment;
    }
    public function getEnvironment() : string
    {
        return $this->environment;
    }
    public function getHeader() : string
    {
        return \implode(' ', [self::TYPE, \base64_encode(\implode(':', [$this->username, $this->password]))]);
    }
}
