<?php namespace SilvershopUnleashed\Model;

use DataExtension;
use Utils;
use SS_Log;

class OrderModifier extends DataExtension
{
    /**
     * Map OrderModifier
     * @var string
     */
    private static $product_code;

    /**
     * Apply Guid if absent
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->owner->getField("Guid")) {
            $this->owner->Guid = (string) Utils::createGuid();
        }
    }
}
