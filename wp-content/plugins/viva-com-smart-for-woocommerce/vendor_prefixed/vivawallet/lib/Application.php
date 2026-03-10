<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp;

class Application
{
    public const VIVAWALLET_ABBREVIATION = 'VW';
    public const SDK_VERSION = '2.2.4';
    public const SOURCE_NAME_FORMAT = 'Viva Wallet For %s - %s';
    public const PARAM_CANCEL_ORDER = 'cancel';
    public const BASE_URLS = ['demo' => ['accounts' => 'https://demo-accounts.vivapayments.com', 'api' => 'https://demo-api.vivapayments.com', 'default' => 'https://demo.vivapayments.com'], 'live' => ['accounts' => 'https://accounts.vivapayments.com', 'api' => 'https://api.vivapayments.com', 'default' => 'https://www.vivapayments.com']];
    public const ENDPOINTS = ['accountsToken' => '/connect/token', 'sources' => '/plugins/v1/sources', 'webhook' => '/plugins/v1/webhooks', 'webhookToken' => '/plugins/v1/webhooks/token', 'order' => '/plugins/v1/orders', 'pluginOrders' => '/checkout/v2/orders', 'merchants' => '/plugins/v1/merchants', 'orders' => '/api/orders', 'transactions' => '/checkout/v2/transactions', 'smartCheckout' => '/web/checkout', 'acquiring' => '/acquiring/v1/transactions', 'diagnostics' => '/diagnostics/v1/log', 'nativeTransactions' => '/nativecheckout/v2/transactions', 'nativeChargeToken' => '/nativecheckout/v2/chargetokens', 'nativeDigitalChargeToken' => '/nativecheckout/v2/chargetokens:digitize'];
    public const CURRENCIES = ['AED' => '784', 'AFN' => '971', 'ALL' => '008', 'AMD' => '051', 'ANG' => '532', 'AOA' => '973', 'ARS' => '032', 'AUD' => '036', 'AWG' => '533', 'AZN' => '944', 'BAM' => '977', 'BBD' => '052', 'BDT' => '050', 'BGN' => '975', 'BHD' => '048', 'BIF' => '108', 'BMD' => '060', 'BND' => '096', 'BOB' => '068', 'BOV' => '984', 'BRL' => '986', 'BSD' => '044', 'BTN' => '064', 'BWP' => '072', 'BYN' => '933', 'BZD' => '084', 'CAD' => '124', 'CDF' => '976', 'CHE' => '947', 'CHF' => '756', 'CHW' => '948', 'CLF' => '990', 'CLP' => '152', 'CNY' => '156', 'COP' => '170', 'COU' => '970', 'CRC' => '188', 'CUC' => '931', 'CUP' => '192', 'CVE' => '132', 'CZK' => '203', 'DJF' => '262', 'DKK' => '208', 'DOP' => '214', 'DZD' => '012', 'EGP' => '818', 'ERN' => '232', 'ETB' => '230', 'EUR' => '978', 'FJD' => '242', 'FKP' => '238', 'GBP' => '826', 'GEL' => '981', 'GHS' => '936', 'GIP' => '292', 'GMD' => '270', 'GNF' => '324', 'GTQ' => '320', 'GYD' => '328', 'HKD' => '344', 'HNL' => '340', 'HRK' => '191', 'HTG' => '332', 'HUF' => '348', 'IDR' => '360', 'ILS' => '376', 'INR' => '356', 'IQD' => '368', 'IRR' => '364', 'ISK' => '352', 'JMD' => '388', 'JOD' => '400', 'JPY' => '392', 'KES' => '404', 'KGS' => '417', 'KHR' => '116', 'KMF' => '174', 'KPW' => '408', 'KRW' => '410', 'KWD' => '414', 'KYD' => '136', 'KZT' => '398', 'LAK' => '418', 'LBP' => '422', 'LKR' => '144', 'LRD' => '430', 'LSL' => '426', 'LYD' => '434', 'MAD' => '504', 'MDL' => '498', 'MGA' => '969', 'MKD' => '807', 'MMK' => '104', 'MNT' => '496', 'MOP' => '446', 'MRU' => '929', 'MUR' => '480', 'MVR' => '462', 'MWK' => '454', 'MXN' => '484', 'MXV' => '979', 'MYR' => '458', 'MZN' => '943', 'NAD' => '516', 'NGN' => '566', 'NIO' => '558', 'NOK' => '578', 'NPR' => '524', 'NZD' => '554', 'OMR' => '512', 'PAB' => '590', 'PEN' => '604', 'PGK' => '598', 'PHP' => '608', 'PKR' => '586', 'PLN' => '985', 'PYG' => '600', 'QAR' => '634', 'RON' => '946', 'RSD' => '941', 'RUB' => '643', 'RWF' => '646', 'SAR' => '682', 'SBD' => '090', 'SCR' => '690', 'SDG' => '938', 'SEK' => '752', 'SGD' => '702', 'SHP' => '654', 'SLL' => '694', 'SOS' => '706', 'SRD' => '968', 'SSP' => '728', 'STN' => '930', 'SVC' => '222', 'SYP' => '760', 'SZL' => '748', 'THB' => '764', 'TJS' => '972', 'TMT' => '934', 'TND' => '788', 'TOP' => '776', 'TRY' => '949', 'TTD' => '780', 'TWD' => '901', 'TZS' => '834', 'UAH' => '980', 'UGX' => '800', 'USD' => '840', 'USN' => '997', 'UYI' => '940', 'UYU' => '858', 'UYW' => '927', 'UZS' => '860', 'VED' => '926', 'VES' => '928', 'VND' => '704', 'VUV' => '548', 'WST' => '882', 'XAF' => '950', 'XAG' => '961', 'XAU' => '959', 'XBA' => '955', 'XBB' => '956', 'XBC' => '957', 'XBD' => '958', 'XCD' => '951', 'XDR' => '960', 'XOF' => '952', 'XPD' => '964', 'XPF' => '953', 'XPT' => '962', 'XSU' => '994', 'XTS' => '963', 'XUA' => '965', 'XXX' => '999', 'YER' => '886', 'ZAR' => '710', 'ZMW' => '967', 'ZWL' => '932'];
    public const SUPPORTED_LANGUAGES = ['bg' => 'bg-BG', 'hr' => 'hr-HR', 'cs' => 'cs-CZ', 'da' => 'da-DK', 'nl' => 'nl-NL', 'en' => 'en-GB', 'fi' => 'fi-FI', 'fr' => 'fr-FR', 'de' => 'de-DE', 'el' => 'el-GR', 'hu' => 'hu-HU', 'it' => 'it-IT', 'pl' => 'pl-PL', 'pt' => 'pt-PT', 'ro' => 'ro-RO', 'es' => 'es-ES'];
    private static $information = [];
    public static function getCurrencyCode(string $code, bool $isNumericCode)
    {
        $currencyList = $isNumericCode === \false ? self::CURRENCIES : \array_flip(self::CURRENCIES);
        return $currencyList[$code] ?? null;
    }
    public static function getInformation() : array
    {
        return self::$information;
    }
    public static function setInformation(array $information) : void
    {
        self::$information['cms']['name'] = $information['cms']['name'];
        self::$information['cms']['abbreviation'] = $information['cms']['abbreviation'];
        self::$information['cms']['version'] = $information['cms']['version'] ?? null;
        self::$information['vivaWallet']['abbreviation'] = $information['vivaWallet']['abbreviation'] ?? self::VIVAWALLET_ABBREVIATION;
        self::$information['vivaWallet']['version'] = $information['vivaWallet']['version'];
        if (isset($information['ecommercePlatform']['abbreviation'])) {
            self::$information['ecommercePlatform']['abbreviation'] = $information['ecommercePlatform']['abbreviation'];
        }
        if (isset($information['ecommercePlatform']['version'])) {
            self::$information['ecommercePlatform']['version'] = $information['ecommercePlatform']['version'];
        }
    }
    public static function getSmartCheckoutUrl(array $params, string $environment) : string
    {
        $baseUrl = self::BASE_URLS[$environment]['default'];
        $endpoint = self::ENDPOINTS['smartCheckout'];
        $filteredParams = \array_filter($params, function ($param) {
            return !empty($param);
        });
        return \implode([$baseUrl, $endpoint, '?', \http_build_query($filteredParams)]);
    }
}
