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
     * Default CreatedBy
     * @var string
     */
    private static $created_by = '';

    /**
     * Default Customer Type
     * @var string
     */
    private static $customer_type = '';

    /**
     * Days following payment that delivery is expected
     * @var int
     */
    private static $expected_days_to_deliver = 0;

    /**
     * Default Order Status
     * @var string
     */
    private static $order_status = 'Parked';

    /**
     * Default payment term
     * @var string
     */
    private static $payment_term = 'Same Day';

    /**
     * Default PrintPackingSlipInsteadOfInvoice
     * @var boolean
     */
    private static $print_packingslip_instead_of_invoice = true;

    /**
     * Default Sales Order Group
     * @var string
     */
    private static $sales_order_group = '';

    /**
     * Enable sending of Sales Orders to Unleashed
     * @var boolean
     */
    private static $send_sales_orders_to_unleashed = false;

    /**
     * Declare the Shipping modifier used in Silvershop
     *
     * @example  'SilverShop\Model\Modifiers\Shipping\Simple'
     * @var string
     */
    private static $shipping_modifier_class_name = '';

    /**
     * Default Source Id
     * @var string
     */
    private static $source_id = '';

    /**
     * Declare the tax modifier used in Silvershop
     *
     * @example  'SilverShop\Model\Modifiers\Tax\FlatTax'
     * @var string
     */
    private static $tax_modifier_class_name = '';
}
