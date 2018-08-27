<?php

namespace AntonyThorpe\SilvershopUnleashed;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

/**
 * Member, Product, ProductCategory, Order, OrderItem
 */
class UnleashedExtension extends DataExtension
{
    private static $db = [
        'Guid' => 'Varchar(64)'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('Guid');
    }
}
