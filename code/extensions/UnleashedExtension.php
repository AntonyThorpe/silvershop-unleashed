<?php

/**
 * Member, Product, ProductCategory, Order, OrderItem
 */
class UnleashedExtension extends DataExtension
{
    private static $db = array(
        'Guid' => 'Varchar(64)'
    );

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('Guid');
    }
}
