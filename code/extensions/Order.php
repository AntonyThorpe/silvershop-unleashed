<?php namespace SilvershopUnleashed\Model;

use DataExtension;
use ShopConfig;
use Member;
use HasGroupPricing;
use DateTime;
use UnleashedAPI;
use Utils;
use SS_Log;

class Order extends DataExtension
{
    /**
     * Enable sending of Sales Orders to Unleashed
     * @var boolean
     */
    private static $send_sales_orders_to_unleashed = false;

    /**
     * Declare the tax modifier used in Silvershop
     *
     * @example  'FlatTaxModifier'
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
    private static $default_created_by = '';

    /**
     * Default payment term
     * @var string
     */
    private static $default_payment_term = 'Same Day';

    /**
     * Default Customer Type
     * @var string
     */
    private static $default_customer_type = '';

    /**
     * Default Sales Order Group
     * @var string
     */
    private static $default_sales_order_group = '';

    /**
     * Default Sales Person
     * @var string
     */
    private static $default_sales_person = '';

    /**
     * Default Source Id
     * @var string
     */
    private static $default_source_id = '';

    /**
     * Apply Guid if absent
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->owner->getField("Guid")) {
            $this->owner->Guid = (string) Utils::createGuid();
        }
    }

    /**
     * Send a sales order to Unleashed upon paid status
     *
     * Note: create Customer first
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();
        $config = $this->owner->config();

        if ($config->send_sales_orders_to_unleashed && $this->owner->Status == "Paid") {
            // Definitions
            $order = $this->owner;
            $billing_address = $order->BillingAddress();
            $shipping_address = $order->ShippingAddress();
            $member = $order->Member();
            $countries = ShopConfig::config()->iso_3166_country_codes;
            $subtotal = $order->Total();
            $sell_price_tier = ShopConfig::current()->CustomerGroup()->Title;
            $taxable = false;
            $tax_code = '';
            $tax_total = 0;
            $tax_class_name = $config->tax_modifier_class_name;
            $modifiers = $order->Modifiers();
            $tax_modifier = $order->getModifier($config->tax_modifier_class_name);
            $shipping_method = '';
            $sales_order_lines = [];
            $line_number = 0;


            // Customer
            if (!$member->exists()) {  // Create Member for Guests
                $member = Member::create();
                $member->FirstName = $order->FirstName;
                $member->Surname = $order->Surname;
                $member->Email = $order->getLatestEmail();
            }

            // Selling Price Tier if Customer set with a different pricecard using the Extended Pricing Module
            if (class_exists('HasGroupPricing') && $member->Groups()->exists()) {
                $levels = HasGroupPricing::get_levels();
                foreach ($member->Groups() as $group) {
                    if (array_key_exists($group->Code, $levels)) {
                        // Assign member specific group
                        $sell_price_tier = $group->Title;
                    }
                }
            }

            // Taxation (e.g. Sales Tax/GST)
            if (!empty($tax_modifier)) {
                $subtotal -= $tax_modifier->Amount;
                $taxable = true;
                $tax_code = $tax_modifier::config()->name;
                $tax_total = floatval($tax_modifier->Amount);
            }

            // Define Customer (use Company field of BillingAddress to allow for B2B eCommerce sites)
            if ($company = $billing_address->Company) {
                $customer_name = $company;    // use Organisation name
            } else {
                $customer_name = $order->getName();  // use Contact full name instead
            }

            if (!$member->Guid) {  // See if New Customer/Guest has previously purchased
                $response = UnleashedAPI::sendCall(
                    'GET',
                    'https://api.unleashedsoftware.com/Customers?contactEmail=' .  $member->Email
                );

                if ($response->getStatusCode() == '200') {
                    $contents = json_decode($response->getBody()->getContents(), true);
                    if ($items = $contents['Items']) {
                        $member->Guid = $items[0]['Guid'];
                    } else {
                        // Create new Customer in Unleashed
                        $member->Guid = (string) Utils::createGuid();
                        $body = [
                            'Addresses' => [
                                [
                                    'AddressName' => _t("Address.BillingAddress"),
                                    'AddressType' => 'Postal',
                                    'City' => $billing_address->City,
                                    'Country' => $countries[$billing_address->Country],
                                    'PostalCode' => $billing_address->PostalCode,
                                    'Region' => $billing_address->State,
                                    'StreetAddress' => $billing_address->Address,
                                    'Suburb' => $billing_address->AddressLine2
                                ],
                                [
                                    'AddressName' => _t("Address.ShippingAddress"),
                                    'AddressType' => 'Physical',
                                    'City' => $shipping_address->City,
                                    'Country' => $countries[$shipping_address->Country],
                                    'PostalCode' => $shipping_address->PostalCode,
                                    'Region' => $shipping_address->State,
                                    'StreetAddress' => $shipping_address->Address,
                                    'Suburb' => $shipping_address->AddressLine2
                                ]
                            ],
                            'Currency' =>[
                                'CurrencyCode' => $order->Currency()
                            ],
                            'CustomerCode' => $customer_name,
                            'CustomerName' => $customer_name,
                            'ContactFirstName' => $member->FirstName,
                            'ContactLastName' => $member->Surname,
                            'Email' => $member->Email,
                            'Guid' => $member->Guid,
                            'PaymentTerm' => $config->default_payment_term,
                            'PrintPackingSlipInsteadOfInvoice' => true,
                            'SellPriceTier' => $sell_price_tier
                        ];

                        if ($taxable) {
                            $body['Taxable'] = $taxable;
                        }

                        if ($created_by = $config->default_created_by) {
                            $body['CreatedBy'] = $created_by;
                        }

                        if ($customer_type = $config->default_customer_type) {
                            $body['CustomerType'] = $customer_type;
                        }

                        if ($phone = $billing_address->Phone) {  // add phone number if available
                            $body['PhoneNumber'] = $phone;
                        }

                        $response = UnleashedAPI::sendCall(
                            'POST',
                            'https://api.unleashedsoftware.com/Customers/' . $member->Guid,
                            ['json' => $body ]
                        );

                        if ($response->getReasonPhrase() == 'Created' && $order->Member()->exists()) {
                            $member->write();
                        }
                    }
                }
            }


            // Prepare Sales Order data
            if ($member->Guid) {  // Skip if previous calls to Customer have failed and the Guid has not been set

                // Dates
                $date_placed = new DateTime($order->Placed);
                $date_paid = new DateTime($order->Paid);
                $date_required = new DateTime($order->Paid);
                if ($expected_days_to_deliver = $config->expected_days_to_deliver) {
                    $date_required->modify('+' . $expected_days_to_deliver . 'day');
                }

                // Sales Order Lines
                foreach ($order->Items()->getIterator() as $item) {
                    // Definitions
                    $product = $item->Product();
                    $line_number += 1;

                    $sales_order_line = [
                        'DiscountRate' => 0,
                        'Guid' => $item->Guid,
                        'LineNumber' => $line_number,
                        'LineType' => null,
                        'LineTotal' => round(floatval($item->Total()), $config->rounding_precision),
                        'OrderQuantity' => (int) $item->Quantity,
                        'Product' => [
                            'Guid' => $product->Guid
                        ],
                        'UnitPrice' => round(floatval($product->getPrice()), $config->rounding_precision)
                    ];
                    if ($tax_class_name) {
                        $tax_calculator = new $tax_class_name;
                        $sales_order_line['LineTax'] = round($tax_calculator->value($item->Total()), $config->rounding_precision);
                        $sales_order_line['LineTaxCode'] = $tax_code;
                    }
                    $sales_order_lines[] = $sales_order_line;
                }

                // Add Modifiers that have an associated product_code
                foreach ($modifiers->sort('Sort')->getIterator() as $modifier) {
                    if ($modifier::config()->product_code && $modifier->Type !== 'Ignored') {
                        $line_number += 1;
                        $sales_order_line = [
                            'DiscountRate' => 0,
                            'Guid' => $modifier->Guid,
                            'LineNumber' => $line_number,
                            'LineTotal' => round(floatval($modifier->Amount), $config->rounding_precision),
                            'LineType' => null,
                            'OrderQuantity' => 1,
                            'Product' => [
                                'ProductCode' => $modifier::config()->product_code,
                            ],
                            'UnitPrice' => round(floatval($modifier->Amount), $config->rounding_precision)
                        ];
                        if ($tax_class_name) {
                            $tax_calculator = new $tax_class_name;
                            $sales_order_line['LineTax'] = round($tax_calculator->value($modifier->Amount), $config->rounding_precision);
                            $sales_order_line['LineTaxCode'] = $tax_code;
                        }
                        $sales_order_lines[] = $sales_order_line;
                    }
                }

                // Shipping Module
                if (class_exists('ShippingMethod')) {
                    if ($name = $order->ShippingMethod()->Name) {
                        $shipping_method = $name;
                    }
                }

                $body = [
                    'Comments' => $order->Notes,
                    'Currency' =>[
                        'CurrencyCode' => $order->Currency()
                    ],
                    'Customer' => [
                        'Guid' => $member->Guid
                    ],
                    'DeliveryCity' => $shipping_address->City,
                    'DeliveryCountry' => $countries[$shipping_address->Country],
                    'DeliveryPostCode' => $shipping_address->PostalCode,
                    'DeliveryRegion' => $shipping_address->State,
                    'DeliveryStreetAddress' => $shipping_address->Address,
                    'DeliverySuburb' => $shipping_address->AddressLine2,
                    'DiscountRate' => 0,
                    'Guid' => $order->Guid,
                    'OrderDate' => $date_placed->format('Y-m-d\TH:i:s'),
                    'OrderNumber' => $order->Reference,
                    'OrderStatus' => 'Parked',
                    'PaymentDueDate' => $date_paid->format('Y-m-d\TH:i:s'),
                    'ReceivedDate' => $date_placed->format('Y-m-d\TH:i:s'),
                    'RequiredDate' => $date_required->format('Y-m-d\TH:i:s'),
                    'SalesOrderLines' => $sales_order_lines,
                    'SubTotal' => $subtotal,
                    'Tax' => [
                        'TaxCode' => $tax_code
                    ],
                    'TaxTotal' => $tax_total,
                    'Total' => round(floatval($order->Total()), $config->rounding_precision)
                ];

                if ($shipping_method) {
                    $body['DeliveryMethod'] = $shipping_method;
                    $body['DeliveryName'] = $shipping_method;
                }

                if ($sales_order_group = $config->default_sales_order_group) {
                    $body['SalesOrderGroup'] = $sales_order_group;
                }

                if ($sales_person = $config->default_sales_person) {
                    $body['SalesPerson'] = $sales_person;
                }

                if ($source_id = $config->default_source_id) {
                    $body['SourceId'] = $source_id;
                }

                $this->owner->extend('updateUnleashedSalesOrder', $body);

                SS_Log::log(print_r($body, true), SS_Log::NOTICE);

                UnleashedAPI::sendCall(
                    'POST',
                    'https://api.unleashedsoftware.com/SalesOrders/' . $order->Guid,
                    ['json' => $body]
                );
            }
        }

    }
}
