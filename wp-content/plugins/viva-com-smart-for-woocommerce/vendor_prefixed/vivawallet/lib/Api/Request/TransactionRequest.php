<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api\Request;

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Application;
class TransactionRequest
{
    public static function getChargeToken(int $amount, array $options) : array
    {
        return \array_filter(['amount' => $amount, 'holderName' => $options['holderName'] ?? null, 'number' => $options['number'] ?? null, 'expirationMonth' => $options['expirationMonth'] ?? null, 'expirationYear' => $options['expirationYear'] ?? null, 'cvc' => $options['cvc'] ?? null, 'sessionRedirectUrl' => $options['sessionRedirectUrl'] ?? null, 'installments' => $options['installments'] ?? null], function ($value) {
            return !\is_null($value);
        });
    }
    public static function getDigitalChargeToken(string $sourceCode, string $providerId, string $validationUrl) : array
    {
        return ['sourceCode' => $sourceCode, 'providerId' => $providerId, 'validationUrl' => $validationUrl];
    }
    public static function getCreateRecurringTransaction(int $amount, array $options = []) : array
    {
        return \array_filter(['amount' => $amount, 'currencyCode' => Application::getCurrencyCode($options['currencyCode'], \false) ?? null, 'sourceCode' => $options['sourceCode'] ?? null, 'merchantTrns' => $options['messages']['merchant'] ?? null, 'customerTrns' => $options['messages']['customer'] ?? null], function ($value) {
            return !\is_null($value);
        });
    }
    public static function getCreateTransaction(int $amount, string $currencyCode, string $chargeToken, array $options = []) : array
    {
        return \array_filter(['amount' => $amount, 'currencyCode' => Application::getCurrencyCode($currencyCode, \false), 'chargeToken' => $chargeToken, 'preauth' => $options['preauth'] ?? null, 'tipAmount' => $options['tipAmount'] ?? null, 'sourceCode' => $options['sourceCode'] ?? null, 'installments' => $options['installments'] ?? null, 'merchantTrns' => $options['messages']['merchant'] ?? null, 'customerTrns' => $options['messages']['customer'] ?? null, 'allowsRecurring' => $options['allowsRecurring'] ?? null, 'paymentMethodId' => $options['paymentMethodId'] ?? null, 'customer' => ['email' => $options['customer']['email'] ?? null, 'phone' => $options['customer']['phone'] ?? null, 'fullName' => $options['customer']['fullName'] ?? null, 'requestLang' => $options['customer']['requestLang'] ?? null, 'countryCode' => $options['customer']['countryCode'] ?? null]], function ($value) {
            return !\is_null($value);
        });
    }
    public static function getRefundTransaction(int $amount, string $currencyCode, string $sourceCode, ?string $idempotencyKey = null) : array
    {
        return \array_filter(['amount' => $amount, 'currencyCode' => Application::getCurrencyCode($currencyCode, \false), 'sourceCode' => $sourceCode, 'idempotencyKey' => $idempotencyKey], function ($v) {
            return !\is_null($v);
        });
    }
    public static function getCaptureTransaction(int $amount, string $currencyCode, string $sourceCode) : array
    {
        return ['amount' => $amount, 'currencyCode' => Application::getCurrencyCode($currencyCode, \false), 'sourceCode' => $sourceCode];
    }
    public static function getCaptureAuthorizedTransaction(int $amount, array $options = []) : array
    {
        return \array_filter(['amount' => $amount, 'currencyCode' => Application::getCurrencyCode($options['currencyCode'], \false) ?? null, 'sourceCode' => $options['sourceCode'] ?? null, 'merchantTrns' => $options['messages']['merchant'] ?? null, 'customerTrns' => $options['messages']['customer'] ?? null], function ($value) {
            return !\is_null($value);
        });
    }
    public static function getVoidAuthorizedTransaction(int $amount, string $currencyCode, string $sourceCode, ?string $idempotencyKey = null) : array
    {
        return \array_filter(['amount' => $amount, 'currencyCode' => Application::getCurrencyCode($currencyCode, \false), 'sourceCode' => $sourceCode, 'idempotencyKey' => $idempotencyKey], function ($v) {
            return !\is_null($v);
        });
    }
}
