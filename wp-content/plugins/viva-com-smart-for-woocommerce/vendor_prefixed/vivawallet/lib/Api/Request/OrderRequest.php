<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api\Request;

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Application;
class OrderRequest
{
    public static function getCreateOrder(int $amount, string $currencyCode, array $options = []) : array
    {
        return \array_filter(['amount' => $amount, 'currencyCode' => Application::getCurrencyCode($currencyCode, \false), 'sourceCode' => $options['sourceCode'] ?? null, 'customer' => \array_filter(['email' => $options['customer']['email'] ?? null, 'fullName' => $options['customer']['fullName'] ?? null, 'phone' => $options['customer']['phone'] ?? null, 'requestLang' => $options['customer']['requestLang'] ?? null, 'countryCode' => $options['customer']['countryCode'] ?? null]), 'maxInstallments' => $options['payment']['maxInstallments'] ?? null, 'allowRecurring' => $options['payment']['allowRecurring'] ?? null, 'preauth' => $options['payment']['preauth'] ?? null, 'paymentNotification' => $options['payment']['paymentNotification'] ?? null, 'tipAmount' => $options['payment']['tipAmount'] ?? null, 'disableExactAmount' => $options['payment']['disableExactAmount'] ?? null, 'disableCash' => $options['payment']['disableCash'] ?? null, 'disableWallet' => $options['payment']['disableWallet'] ?? null, 'paymentTimeOut' => $options['payment']['paymentTimeOut'] ?? null, 'customerTrns' => isset($options['messages']['customer']) ? \substr($options['messages']['customer'], 0, 255) : null, 'merchantTrns' => isset($options['messages']['merchant']) ? \substr($options['messages']['merchant'], 0, 50) : null, 'tags' => $options['messages']['tags'] ?? null, 'dynamicDescriptor' => $options['payment']['dynamicDescriptor'] ?? null], function ($value) {
            return !\is_null($value);
        });
    }
}
