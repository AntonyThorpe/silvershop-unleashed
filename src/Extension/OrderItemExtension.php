<?php

namespace AntonyThorpe\SilverShopUnleashed\Extension;

use SilverShop\Model\OrderItem;
use SilverStripe\Core\Extension;
use AntonyThorpe\SilverShopUnleashed\Utils;

/**
 * @extends Extension<OrderItem&static>
 */
class OrderItemExtension extends Extension
{
    /**
     * Apply Guid if absent
     */
    public function onBeforeWrite(): void
    {
        if (!$this->getOwner()->getField("Guid")) {
            $this->getOwner()->Guid = Utils::createGuid();
        }
    }
}
