<?php

namespace AntonyThorpe\SilverShopUnleashed\Extension;

use AntonyThorpe\SilverShopUnleashed\Utils;
use SilverStripe\ORM\DataExtension;

class OrderItem extends DataExtension
{
    /**
     * Apply Guid if absent
     */
    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();
        if (!$this->getOwner()->getField("Guid")) {
            $this->getOwner()->Guid = Utils::createGuid();
        }
    }
}
