<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Core\Order;

class OrderItem
{
    private $code;
    private $sourceCode;
    private $tags;
    private $tipAmount;
    private $languageCode;
    private $messages;
    private $maxInstallments;
    private $amount;
    private $expirationDate;
    private $stateId;
    /**
     * OrderItem constructor.
     *
     * @param object|null $orderItem
     */
    public function __construct(object $orderItem = null)
    {
        if (!\is_null($orderItem)) {
            $this->code = $orderItem->OrderCode ?? null;
            $this->sourceCode = $orderItem->SourceCode ?? null;
            $this->tags = $orderItem->Tags ?? [];
            $this->tipAmount = $orderItem->TipAmount ?? 0;
            $this->languageCode = $orderItem->RequestLang ?? null;
            $this->messages = (object) ['customer' => $orderItem->MerchantTrns ?? null, 'merchant' => $orderItem->CustomerTrns ?? null];
            $this->maxInstallments = $orderItem->MaxInstallments ?? 0;
            $this->amount = $orderItem->RequestAmount ?? 0;
            $this->expirationDate = $orderItem->ExpirationDate ?? null;
            $this->stateId = $orderItem->StateId ?? 0;
        }
    }
    public function getCode()
    {
        return $this->code;
    }
    public function getSourceCode()
    {
        return $this->sourceCode;
    }
    public function getTags()
    {
        return $this->tags;
    }
    public function getLanguageCode()
    {
        return $this->languageCode;
    }
    public function getAmount()
    {
        return $this->amount;
    }
    public function getExpirationDate()
    {
        return $this->expirationDate;
    }
}
