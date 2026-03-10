<?php

namespace VivaComSmartCheckout\Vivawallet\VivawalletPhp\Core\Source;

class SourceList extends \ArrayObject
{
    public function __construct(array $sources = [])
    {
        parent::__construct(\array_map(function ($source) {
            return $source instanceof SourceItem ? $source : new SourceItem($source);
        }, $sources));
    }
    public function filterByDomain(string $domain) : SourceList
    {
        return new self(\array_filter((array) $this, function (SourceItem $source) use($domain) {
            return $source->getDomain() === $domain;
        }));
    }
    public function filterByState(int $state) : SourceList
    {
        return new self(\array_filter((array) $this, function (SourceItem $source) use($state) {
            return $source->getState() === $state;
        }));
    }
    public function filterByUrls(array $urls) : SourceList
    {
        return new self(\array_filter((array) $this, function (SourceItem $source) use($urls) {
            return (!isset($urls['success']) || $source->getSuccessUrl() === $urls['success']) && (!isset($urls['failure']) || $source->getFailureUrl() === $urls['failure']);
        }));
    }
    public function getFreeSourceCode(string $prefix, int $step = 1) : string
    {
        $maxExistingSourceIds = \array_map(function (SourceItem $source) use($prefix) {
            return (int) \ltrim(\substr($source->getCode(), \strlen($prefix)), '0');
            // get only the number.
        }, \array_filter((array) $this, function (SourceItem $source) use($prefix) {
            return \substr($source->getCode(), 0, \strlen($prefix)) === $prefix;
        }));
        $maxExistingSourceId = empty($maxExistingSourceIds) ? 0 : \max($maxExistingSourceIds);
        $freeSourceId = $maxExistingSourceId + $step;
        return $prefix . \str_pad((string) $freeSourceId, 4, '0', \STR_PAD_LEFT);
    }
}
