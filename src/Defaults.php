<?php

namespace AntonyThorpe\SilverShopUnleashed;

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
     * @config
     */
    private static string $created_by = '';

    /**
     * Default Customer Type
     * @config
     */
    private static string $customer_type = '';

    /**
     * Days following payment that delivery is expected
     * @config
     */
    private static int $expected_days_to_deliver = 0;

    /**
     * Default Order Status
     * @config
     */
    private static string $order_status = 'Parked';

    /**
     * Default payment term
     * @config
     */
    private static string $payment_term = 'Same Day';

    /**
     * Default PrintPackingSlipInsteadOfInvoice
     * @config
     */
    private static bool $print_packingslip_instead_of_invoice = true;

    /**
     * Default Sales Order Group
     * @config
     */
    private static string $sales_order_group = '';

    /**
     * Enable sending of Sales Orders to Unleashed
     * @config
     */
    private static bool $send_sales_orders_to_unleashed = false;

    /**
     * Declare the Shipping modifier used in Silvershop
     * @config
     * @example  'SilverShop\Model\Modifiers\Shipping\Simple'
     */
    private static string $shipping_modifier_class_name = '';

    /**
     * Default Source Id
     * @config
     */
    private static string $source_id = '';

    /**
     * Declare the tax modifier used in Silvershop
     * @config
     * @example  'SilverShop\Model\Modifiers\Tax\FlatTax'
     */
    private static string $tax_modifier_class_name = '';
}
