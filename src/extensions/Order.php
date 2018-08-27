<?php namespace AntonyThorpe\SilvershopUnleashed;

use DateTime;
use SilverStripe\Security\Member;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\DataExtension;
use SilverShop\Extension\ShopConfigExtension;
use MarkGuinn\SilverShopExtendedPricing\HasGroupPricing;  // not upgraded yet
use AntonyThorpe\SilverShopUnleashed\UnleashedAPI;
use AntonyThorpe\SilverShopUnleashed\Defaults;
use AntonyThorpe\SilverShopUnleashed\Utils;

class Order extends DataExtension
{
    /**
     * Record when an order is sent to Unleashed
     */
    private static $db = [
        'OrderSentToUnleashed' => 'Datetime'
    ];

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
     * Return the Address Name
     * @return string
     */
    public function getAddressName($address)
    {
        if (is_array($address)) {
            $address_name = $address['StreetAddress'];
            if ($address['StreetAddress2']) {
                $address_name .= ' ' . $address['StreetAddress2'];
            }
            $address_name .= ' ' . $address['City'];
        } else {
            $address_name = $address->Address;
            if ($address->AddressLine2) {
                $address_name .= ' ' . $address->AddressLine2;
            }
            $address_name .= ' ' . $address->City;
        }
        return $address_name;
    }

    /**
     * Match the order's shipping address to items returned from Unleashed
     * @return boolean
     */
    public function matchCustomerAddress($items, $shipping_address)
    {
        // Obtain the delivery address
        $address = $items[0]['Addresses'][0];
        if ($address['AddressType'] != "Physical") {
            if (isset($items[0]['Addresses'][1])) {
                $address = $items[0]['Addresses'][1];
            }
        }
        return strtoupper($this->getAddressName($shipping_address)) == strtoupper($this->getAddressName($address));
    }

    /**
     * Send a sales order to Unleashed upon paid status
     * Note: create Customer first
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();
        $config = $this->owner->config();
        $defaults = Defaults::config();

        if ($defaults->send_sales_orders_to_unleashed
            && $this->owner->Status == 'Paid'
            && !$this->owner->OrderSentToUnleashed) {
            // Definitions
            $order = $this->owner;
            $billing_address = $order->BillingAddress();
            $shipping_address = $order->ShippingAddress();
            $member = $order->Member();
            $comments = $order->Notes;
            $countries = ShopConfigExtension::config()->iso_3166_country_codes;
            $subtotal = $order->Total();
            $sell_price_tier = ShopConfigExtension::current()->CustomerGroup()->Title;
            $taxable = false;
            $tax_code = '';
            $tax_total = 0;
            $tax_class_name = $defaults->tax_modifier_class_name;
            $modifiers = $order->Modifiers();
            $tax_modifier = $order->getModifier($defaults->tax_modifier_class_name);
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

            // Define Customer Code/Name (use Company field of BillingAddress to allow for B2B eCommerce sites)
            if ($billing_address->Company) {
                $customer_code_and_name = $billing_address->Company;    // use Organisation name
            } else {
                $customer_code_and_name = $order->getName();  // use Contact full name instead
            }

            if (!$member->Guid) {  // See if New Customer/Guest has previously purchased
                $response = UnleashedAPI::sendCall(
                    'GET',
                    'https://api.unleashedsoftware.com/Customers?contactEmail=' .  $member->Email
                );

                if ($response->getStatusCode() == '200') {
                    $contents = json_decode($response->getBody()->getContents(), true);
                    $items = $contents['Items'];
                    if ($items) {
                        // Email address exists
                        $member->Guid = $items[0]['Guid'];
                    } else {
                        // A Customer is not returned so we have a unique email address.
                        // Check to see if the Customer Code exists (we cannot double up on the Customer Code)
                        $response = UnleashedAPI::sendCall(
                            'GET',
                            'https://api.unleashedsoftware.com/Customers?customerCode=' .  $customer_code_and_name
                        );

                        if ($response->getStatusCode() == '200') {
                            $contents = json_decode($response->getBody()->getContents(), true);
                            $items = $contents['Items'];
                            if ($items) {
                                // A Customer Code already exists (and the email address is unique).
                                // If the address is the same then this is the Customer
                                if ($this->matchCustomerAddress($items, $shipping_address)) {
                                    $member->Guid = $items[0]['Guid'];

                                    //Note the existing email address in the Comment
                                    //PUT Customer is not available in Unleashed
                                    if ($comments) {
                                        $comments .= PHP_EOL;
                                    }
                                    $comments .= _t(
                                        'UnleashedAPI.addEmailToCustomerComment',
                                        'Add email to Customer: {email_address}',
                                        '',
                                        ['email_address' => $member->Email]
                                    );
                                } else {
                                    // The Customer Code already exists, we have a unique email address, but
                                    // the delivery address is new
                                    // We need to create a new Customer with a unique Customer Code
                                    $customer_code_and_name .= rand(10000000, 99999999);
                                }
                            }
                        }
                    }
                }
            }

            if (!$member->Guid) {
                // The Customer Code does not exists in Unleashed and the email address is unique
                // Create in Unleashed
                $member->Guid = (string) Utils::createGuid();
                $address_name_postal_new_customer = $this->getAddressName($billing_address);
                $address_name_physical_new_customer = $this->getAddressName($shipping_address);

                $body = [
                    'Addresses' => [
                        [
                            'AddressName' => $address_name_postal_new_customer,
                            'AddressType' => 'Postal',
                            'City' => $billing_address->City,
                            'Country' => $countries[$billing_address->Country],
                            'PostalCode' => $billing_address->PostalCode,
                            'Region' => $billing_address->State,
                            'StreetAddress' => $billing_address->Address,
                            'StreetAddress2' => $billing_address->AddressLine2
                        ],
                        [
                            'AddressName' => $address_name_physical_new_customer,
                            'AddressType' => 'Physical',
                            'City' => $shipping_address->City,
                            'Country' => $countries[$shipping_address->Country],
                            'PostalCode' => $shipping_address->PostalCode,
                            'Region' => $shipping_address->State,
                            'StreetAddress' => $shipping_address->Address,
                            'StreetAddress2' => $shipping_address->AddressLine2
                        ]
                    ],
                    'Currency' =>[
                        'CurrencyCode' => $order->Currency()
                    ],
                    'CustomerCode' => $customer_code_and_name,
                    'CustomerName' => $customer_code_and_name,
                    'ContactFirstName' => $member->FirstName,
                    'ContactLastName' => $member->Surname,
                    'Email' => $member->Email,
                    'Guid' => $member->Guid,
                    'PaymentTerm' => $defaults->payment_term,
                    'PrintPackingSlipInsteadOfInvoice' => true,
                    'SellPriceTier' => $sell_price_tier
                ];

                if ($taxable) {
                    $body['Taxable'] = $taxable;
                }

                if ($defaults->created_by) {
                    $body['CreatedBy'] = $defaults->created_by;
                }

                if ($defaults->customer_type) {
                    $body['CustomerType'] = $defaults->customer_type;
                }

                if ($billing_address->Phone) {  // add phone number if available
                    $body['PhoneNumber'] = $billing_address->Phone;
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


            // Prepare Sales Order data
            if ($member->Guid) {  // Skip if previous calls to Customer have failed and the Guid has not been set

                // Dates
                $date_placed = new DateTime($order->Placed);
                $date_paid = new DateTime($order->Paid);
                $date_required = new DateTime($order->Paid);
                if ($defaults->expected_days_to_deliver) {
                    $date_required->modify('+' . $defaults->expected_days_to_deliver . 'day');
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
                        $sales_order_line['LineTax'] = round(
                            $tax_calculator->value($item->Total()),
                            $config->rounding_precision
                        );
                        $sales_order_line['LineTaxCode'] = $tax_code;
                    }
                    $sales_order_lines[] = $sales_order_line;
                }

                // Add Modifiers that have an associated product_code
                foreach ($modifiers->sort('Sort')->getIterator() as $modifier) {
                    $line_total = round(floatval($modifier->Amount), $config->rounding_precision);

                    if ($modifier::config()->product_code &&
                        $modifier->Type !== 'Ignored' &&
                        !empty($line_total)
                    ) {
                        $line_number += 1;
                        $sales_order_line = [
                            'DiscountRate' => 0,
                            'Guid' => $modifier->Guid,
                            'LineNumber' => $line_number,
                            'LineTotal' => $line_total,
                            'LineType' => null,
                            'OrderQuantity' => 1,
                            'Product' => [
                                'ProductCode' => $modifier::config()->product_code,
                            ],
                            'UnitPrice' => round(floatval($modifier->Amount), $config->rounding_precision)
                        ];
                        if ($tax_class_name) {
                            $tax_calculator = new $tax_class_name;
                            $sales_order_line['LineTax'] = round(
                                $tax_calculator->value($modifier->Amount),
                                $config->rounding_precision
                            );
                            $sales_order_line['LineTaxCode'] = $tax_code;
                        }
                        $sales_order_lines[] = $sales_order_line;
                    }
                }

                // Shipping Module
                if (class_exists('SilverShop\Shipping\Model\ShippingMethod')) {
                    $name = $order->ShippingMethod()->Name;
                    if ($name) {
                        $shipping_method = $name;
                    }
                }

                $body = [
                    'Comments' => $comments,
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
                    'DeliveryStreetAddress2' => $shipping_address->AddressLine2,
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

                if ($defaults->sales_order_group) {
                    $body['SalesOrderGroup'] = $defaults->sales_order_group;
                }

                if ($defaults->source_id) {
                    $body['SourceId'] = $defaults->source_id;
                }

                $this->owner->extend('updateUnleashedSalesOrder', $body);

                $response = UnleashedAPI::sendCall(
                    'POST',
                    'https://api.unleashedsoftware.com/SalesOrders/' . $order->Guid,
                    ['json' => $body]
                );
                if ($response->getReasonPhrase() == 'Created') {
                    $this->owner->OrderSentToUnleashed = DBDatetime::now()->Rfc2822();
                    $this->owner->write();
                }
            }
        }
    }
}
