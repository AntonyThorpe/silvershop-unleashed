<?php

namespace AntonyThorpe\SilvershopUnleashed\Extension;

use AntonyThorpe\SilverShopUnleashed\Utils;
use SilverStripe\ORM\DataExtension;

class OrderItem extends DataExtension
{
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
