<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Utils;

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Application;
class Utils
{
    public static function getCustomUserAgent() : string
    {
        $elements = self::getUserAgentElements();
        \array_walk($elements, function (&$value, $key) {
            $value = "{$key}/{$value}";
        });
        $userAgent = !empty($_SERVER['HTTP_USER_AGENT']) ? \htmlspecialchars(\strip_tags($_SERVER['HTTP_USER_AGENT']), \ENT_QUOTES) : '';
        return $userAgent . ' ' . \implode(' ', $elements);
    }
    private static function getUserAgentElements() : array
    {
        $applicationInfo = Application::getInformation();
        $abbreviationKey = $applicationInfo['vivaWallet']['abbreviation'] . $applicationInfo['cms']['abbreviation'];
        $userAgentElements = [($applicationInfo['vivaWallet']['abbreviation'] ?? '') . 'SDK' => Application::SDK_VERSION, $applicationInfo['cms']['abbreviation'] => $applicationInfo['cms']['version'], $abbreviationKey => $applicationInfo['vivaWallet']['version']];
        if (!empty($applicationInfo['ecommercePlatform']['abbreviation'])) {
            $abbreviationKey = $applicationInfo['ecommercePlatform']['abbreviation'];
            $userAgentElements[$abbreviationKey] = $applicationInfo['ecommercePlatform']['version'];
        }
        $userAgentElements['IP'] = self::getIpAddress();
        return $userAgentElements;
    }
    public static function getIpAddress() : string
    {
        foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'] as $key) {
            if (\array_key_exists($key, $_SERVER) === \true) {
                $address = \htmlspecialchars(\strip_tags($_SERVER[$key]), \ENT_QUOTES);
                $ips = \explode(',', $address);
                foreach ($ips as $ip) {
                    $ip = \trim($ip);
                    if (\filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE) !== \false) {
                        return $ip;
                    }
                }
            }
        }
        return '';
    }
    public static function isHttpStatusCodeValid($status) : bool
    {
        return $status >= 200 || $status < 300;
    }
}
