<?php

namespace AntonyThorpe\SilverShopUnleashed\Extension;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

/**
 * Member, Product, ProductCategory, Order, OrderItem
 */
class UnleashedExtension extends DataExtension
{
    /**
     * @config
     */
    private static array $db = [
        'Guid' => 'Varchar(64)'
    ];

    public function updateCMSFields(FieldList $fields): void
    {
        $fields->removeByName('Guid');
    }
}
