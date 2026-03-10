<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Controller;

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api\SourceClient;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Application;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Core\Source\SourceItem;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Core\Source\SourceList;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Authentication\Authentication;
class SourceController
{
    public static function getSourceList(string $domain, Authentication $authentication, ?array $urls = null, &$information = null) : SourceList
    {
        // if merchant is using localhost then we create a fake source
        $cmsAbbreviation = Application::getInformation()['cms']['abbreviation'] ?? 'Unknown_cms';
        $domain = \false !== \strpos($domain, 'localhost') ? "{$cmsAbbreviation}.example.com" : $domain;
        $client = new SourceClient($authentication);
        $sourcesResponse = $information['getResponse'] = $client->getSources();
        if ($sourcesResponse->isSuccessful()) {
            $sourceList = $sourcesResponse->getBody() instanceof SourceList ? $sourcesResponse->getBody() : new SourceList();
            $filteredSourceList = $sourceList->filterByDomain($domain)->filterByState(1)->filterByUrls($urls);
            if (!\count($filteredSourceList)) {
                $createdSourceItem = $information['createdSourceItem'] = new SourceItem((object) \array_filter(['code' => $sourceList->getFreeSourceCode("{$cmsAbbreviation}-"), 'name' => \sprintf(Application::SOURCE_NAME_FORMAT, $cmsAbbreviation, $domain), 'domain' => $domain, 'successUrl' => $urls['success'] ?? null, 'failureUrl' => $urls['failure'] ?? null, 'paramCancelOrder' => Application::PARAM_CANCEL_ORDER], function ($v) {
                    return !\is_null($v);
                }));
                $createSourceResponse = $information['createResponse'] = $client->createSource($createdSourceItem);
                if ($createSourceResponse->isSuccessful()) {
                    $filteredSourceList = new SourceList([$createdSourceItem]);
                }
            }
        }
        return $filteredSourceList ?? new SourceList();
    }
}
