<?php

namespace AntonyThorpe\SilvershopUnleashed;

use SilverStripe\Core\Config\Configurable;

/**
 * Member, Product, ProductCategory,
 * Order, OrderItem
 */
class Defaults
{
    use Configurable;

    /**
     * Enable sending of Sales Orders to Unleashed
     * @var boolean
     */
    private static $send_sales_orders_to_unleashed = "";

    /**
     * Declare the tax modifier used in Silvershop
     *
     * @example  'SilverShop\Model\Modifiers\Tax\FlatTax'
     * @var string
     */
    private static $tax_modifier_class_name = '';

    /**
     * Days following payment that delivery is expected
     * @var int
     */
    private static $expected_days_to_deliver = 0;

    /**
     * Default CreatedBy
     * @var string
     */
    private static $created_by = '';

    /**
     * Default payment term
     * @var string
     */
    private static $payment_term = 'Same Day';

    /**
     * Default Customer Type
     * @var string
     */
    private static $customer_type = '';

    /**
     * Default Sales Order Group
     * @var string
     */
    private static $sales_order_group = '';

    /**
     * Default Source Id
     * @var string
     */
    private static $source_id = '';
}
