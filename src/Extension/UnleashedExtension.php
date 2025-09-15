<?php

namespace AntonyThorpe\SilverShopUnleashed\Extension;

use SilverStripe\Core\Extension;
use SilverShop\Model\Modifiers\OrderModifier;
use SilverShop\Model\Order;
use SilverShop\Model\OrderItem;
use SilverShop\Page\Product;
use SilverShop\Page\ProductCategory;
use SilverStripe\Security\Member;
use SilverStripe\Forms\FieldList;

/**
 * Member, Product, ProductCategory, Order, OrderItem
 * @property ?string $Guid
 * @extends Extension<((OrderModifier & static) | (Order & static) | (OrderItem & static) | (Product & static) | (ProductCategory & static) | (Member & static))>
 */
class UnleashedExtension extends Extension
{
    /**
     * @config
     */
    private static array $db = [
        'Guid' => 'Varchar(64)'
    ];

    public function updateCMSFields(FieldList $fieldList): void
    {
        $fieldList->removeByName('Guid');
    }
}
