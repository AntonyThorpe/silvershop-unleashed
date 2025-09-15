<?php

namespace AntonyThorpe\SilverShopUnleashed\Extension;

use SilverShop\Model\Modifiers\OrderModifier;
use SilverStripe\Core\Extension;
use AntonyThorpe\SilverShopUnleashed\Utils;

/**
 * @extends Extension<OrderModifier&static>
 */
class OrderModifierExtension extends Extension
{
    /**
     * Map OrderModifier
     * @config
     */
    private static string $product_code = '';

    /**
     * Apply Guid if absent
     */
    public function onBeforeWrite(): void
    {
        if (!$this->getOwner()->getField('Guid')) {
            $this->getOwner()->Guid = Utils::createGuid();
        }
    }
}
