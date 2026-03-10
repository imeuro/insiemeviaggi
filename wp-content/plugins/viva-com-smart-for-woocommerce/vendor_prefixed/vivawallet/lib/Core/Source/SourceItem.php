<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Core\Source;

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Application;
class SourceItem
{
    private $code;
    private $name;
    private $domain;
    private $state;
    private $successUrl;
    private $failureUrl;
    private $paramCancelOrder;
    /**
     * SourceItem constructor.
     * @param $sourceItem
     */
    public function __construct($sourceItem = null)
    {
        if (!\is_null($sourceItem)) {
            $this->code = $sourceItem->sourceCode ?? $sourceItem->code ?? null;
            $this->name = $sourceItem->name ?? null;
            $this->domain = $sourceItem->domain ?? null;
            $this->state = $sourceItem->state ?? 0;
            $this->successUrl = $sourceItem->pathSuccess ?? $sourceItem->successUrl ?? null;
            $this->failureUrl = $sourceItem->pathFail ?? $sourceItem->failureUrl ?? null;
            $this->paramCancelOrder = $sourceItem->paramCancelOrder ?? Application::PARAM_CANCEL_ORDER;
        }
    }
    public function getSuccessUrl()
    {
        return $this->successUrl;
    }
    public function getFailureUrl()
    {
        return $this->failureUrl;
    }
    public function getName() : string
    {
        return $this->name;
    }
    public function getDomain() : string
    {
        return $this->domain;
    }
    public function getCode() : string
    {
        return $this->code;
    }
    public function getState() : int
    {
        return $this->state;
    }
    public function getSourceNameToDisplay() : string
    {
        return \implode(' - ', \array_filter([$this->code, $this->name, \strpos($this->name, $this->domain) === \false ? $this->domain : '']));
    }
    public function getParamCancelOrder() : string
    {
        return $this->paramCancelOrder;
    }
}
