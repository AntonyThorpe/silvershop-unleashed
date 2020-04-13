<?php namespace AntonyThorpe\SilvershopUnleashed;

use DateTime;
use SilverStripe\Security\Member;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\DataExtension;
use SilverShop\Extension\ShopConfigExtension;
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
     * add the address components to the body array
     * @param array $body
     * @param object $order
     * @param string $type (either Postal or Physical)
     * @return array $body
     */
    public function setBodyAddress($body, $order, $type)
    {
        $countries = ShopConfigExtension::config()->iso_3166_country_codes;

        if ($type == 'Postal') {
            $address = $order->BillingAddress();
            array_push(
                $body['Addresses'],
                [
                    'AddressName' => $this->getAddressName($address),
                    'AddressType' => $type,
                    'City' => $address->City,
                    'Country' => $countries[$address->Country],
                    'PostalCode' => $address->PostalCode,
                    'Region' => $address->State,
                    'StreetAddress' => $address->Address,
                    'StreetAddress2' => $address->AddressLine2
                ]
            );
        }

        if ($type == 'Physical') {
            $address = $order->ShippingAddress();
            $body['DeliveryCity'] = $address->City;
            $body['DeliveryCountry'] = $countries[$address->Country];
            $body['DeliveryPostCode'] = $address->PostalCode;
            $body['DeliveryRegion'] = $address->State;
            $body['DeliveryStreetAddress'] = $address->Address;
            $body['DeliveryStreetAddress2'] = $address->AddressLine2;

            array_push(
                $body['Addresses'],
                [
                    'AddressName' => $this->getAddressName($address),
                    'AddressType' => 'Physical',
                    'City' => $address->City,
                    'Country' => $countries[$address->Country],
                    'PostalCode' => $address->PostalCode,
                    'Region' => $address->State,
                    'StreetAddress' => $address->Address,
                    'StreetAddress2' => $address->AddressLine2
                ]
            );

            array_push(
                $body['Addresses'],
                [
                    'AddressName' => $this->getAddressName($address),
                    'AddressType' => 'Shipping',
                    'City' => $address->City,
                    'Country' => $countries[$address->Country],
                    'PostalCode' => $address->PostalCode,
                    'Region' => $address->State,
                    'StreetAddress' => $address->Address,
                    'StreetAddress2' => $address->AddressLine2
                ]
            );
        }

        return $body;
    }

    /**
     * add the currency code to the body array
     * @param array $body
     * @param object $order
     * @return array $body
     */
    public function setBodyCurrencyCode($body, $order)
    {
        $body['Currency']['CurrencyCode'] = $order->Currency();
        return $body;
    }

    /**
     * Add the Customer Code/Name (use Company field of BillingAddress to allow for B2B eCommerce sites)
     * @param array $body
     * @param object $order
     * @return array $body
     */
    public function setBodyCustomerCodeAndName($body, $order)
    {
        $billing_address = $order->BillingAddress();
        if ($billing_address->Company) {
            // use Organisation name
            $body['CustomerCode'] = $billing_address->Company;
            $body['CustomerName'] = $billing_address->Company;
        } else {
            // use Contact full name instead
            $body['CustomerCode'] = $order->getName();
            $body['CustomerName'] = $order->getName();
        }
        return $body;
    }

    /**
     * Set Delivery Method and Delivery Name
     * Allow for the SilverShop Shipping module
     * @param array $body
     * @param object $order
     * @return array $body
     */
    public function setBodyDeliveryMethodAndDeliveryName($body, $order, $shipping_modifier_class_name)
    {
        $shipping_modifier = $order->getModifier($shipping_modifier_class_name);
        if (!empty($shipping_modifier)) {
            $body['DeliveryMethod'] = $shipping_modifier::config()->product_code;
            $body['DeliveryName'] = $shipping_modifier::config()->product_code;
        }
        return $body;
    }

    /**
     * Set Sales Order Lines
     * @param array $body
     * @param object $order
     * @param string $tax_modifier_class_name
     * @param int $rounding_precision
     * @return array $body
     */
    public function setBodySalesOrderLines($body, $order, $tax_modifier_class_name, $rounding_precision)
    {
        $line_number = 0;

        // Sales Order Lines
        foreach ($order->Items()->getIterator() as $item) {
            // Definitions
            $product = $item->Product();
            $line_number += 1;
            $sales_order_line = [
                'DiscountRate' => 0,
                'Guid' => $item->Guid,
                'LineNumber' => (int) $line_number,
                'LineType' => null,
                'LineTotal' => round(floatval($item->Total()), $rounding_precision),
                'OrderQuantity' => (int) $item->Quantity,
                'Product' => [
                    'Guid' => $product->Guid
                ],
                'UnitPrice' => round(floatval($product->getPrice()), $rounding_precision)
            ];
            if ($tax_modifier_class_name) {
                $tax_calculator = new $tax_modifier_class_name;
                $sales_order_line['LineTax'] = round(
                    $tax_calculator->value($item->Total()),
                    $rounding_precision
                );
                $sales_order_line['LineTaxCode'] = $body['Tax']['TaxCode'];
            }
            $body['SalesOrderLines'][] = $sales_order_line;
        }

        // Add Modifiers that have a product_code
        foreach ($order->Modifiers()->sort('Sort')->getIterator() as $modifier) {
            $line_total = round(floatval($modifier->Amount), $rounding_precision);

            if ($modifier::config()->product_code &&
                $modifier->Type !== 'Ignored' &&
                !empty($line_total)
            ) {
                $line_number += 1;
                $sales_order_line = [
                    'DiscountRate' => 0,
                    'Guid' => $modifier->Guid,
                    'LineNumber' => (int) $line_number,
                    'LineTotal' => $line_total,
                    'LineType' => null,
                    'OrderQuantity' => 1,
                    'Product' => [
                        'ProductCode' => $modifier::config()->product_code,
                    ],
                    'UnitPrice' => round(floatval($modifier->Amount), $rounding_precision)
                ];
                if ($tax_modifier_class_name) {
                    $tax_calculator = new $tax_modifier_class_name;
                    $sales_order_line['LineTax'] = round(
                        $tax_calculator->value($modifier->Amount),
                        $rounding_precision
                    );
                    $sales_order_line['LineTaxCode'] = $body['Tax']['TaxCode'];
                }
                $body['SalesOrderLines'][] = $sales_order_line;
            }
        }
        return $body;
    }

    /**
     * Set the Tax Codes
     * @param array $body
     * @param object $order
     * @param string $tax_modifier_class_name
     * @return array $body
     */
    public function setBodyTaxCode($body, $order, $tax_modifier_class_name)
    {
        if ($tax_modifier_class_name) {
            $tax_modifier = $order->getModifier($tax_modifier_class_name);
            if (!empty($tax_modifier)) {
                $body['Taxable'] = true;
                $body['Tax']['TaxCode'] = $tax_modifier::config()->tax_code;
            }
        }
        return $body;
    }


    /**
     * Calculate the SubTotal and TaxTotal
     * @param array $body
     * @param object $order
     * @param string $tax_modifier_class_name
     * @param int $rounding_precision
     * @return array $body
     */
    public function setBodySubTotalAndTax($body, $order, $tax_modifier_class_name, $rounding_precision)
    {
        if ($tax_modifier_class_name) {
            $tax_modifier = $order->getModifier($tax_modifier_class_name);

            if (!empty($tax_modifier)) {
                $sub_total = 0;
                $tax_total = 0;
                foreach ($body['SalesOrderLines'] as $item) {
                    $sub_total = bcadd(
                        $sub_total,
                        $item['LineTotal'],
                        $rounding_precision
                    );
                    $tax_total = bcadd(
                        $tax_total,
                        $item['LineTax'],
                        $rounding_precision
                    );
                }
                $body['TaxTotal'] = $tax_total;
                $body['SubTotal'] = $sub_total;

                $rounding = round(floatval($order->Total() - $tax_total - $sub_total), $rounding_precision);
                // if there is some rounding, adjust the Tax on the first sales order line
                // and adjust the Tax Total by the same amount
                if ($rounding) {
                    $body['SalesOrderLines'][0]['LineTax'] = round($body['SalesOrderLines'][0]['LineTax'] + $rounding, $rounding_precision);
                    $body['TaxTotal'] = round($body['TaxTotal'] + $rounding, $rounding_precision);
                }
            }
        } else {
            $body['SubTotal'] = round(floatval($order->Total()), $rounding_precision);
        }
        return $body;
    }

    /**
     * Send a sales order to Unleashed upon paid status
     * May need to create the Customer first
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
            $member = $order->Member();
            $date_paid = new DateTime($order->Paid);
            $date_placed = new DateTime($order->Placed);
            $body = [
                'Addresses' => [],
                'Currency' => [],
                'Customer' => [],
                'DiscountRate' => 0,
                'Guid' => $order->Guid,
                'OrderDate' => $date_placed->format('Y-m-d\TH:i:s'),
                'OrderNumber' => $order->Reference,
                'OrderStatus' => $defaults->order_status,
                'PaymentDueDate' => $date_paid->format('Y-m-d\TH:i:s'),
                'PaymentTerm' => $defaults->payment_term,
                'PrintPackingSlipInsteadOfInvoice' => $defaults->print_packingslip_instead_of_invoice,
                'ReceivedDate' => $date_placed->format('Y-m-d\TH:i:s'),
                'SalesOrderLines' => [],
                'SellPriceTier' => ShopConfigExtension::current()->CustomerGroup()->Title,
                'Taxable' => false,
                'Tax'  => [],
                'Total' => round(floatval($order->Total()), $config->rounding_precision),
            ];

            $body = $this->setBodyAddress($body, $order, 'Postal');
            $body = $this->setBodyAddress($body, $order, 'Physical');
            $body = $this->setBodyCurrencyCode($body, $order);
            $body = $this->setBodyCustomerCodeAndName($body, $order);
            $body = $this->setBodyDeliveryMethodAndDeliveryName($body, $order, $defaults->shipping_modifier_class_name);
            $body = $this->setBodyTaxCode($body, $order, $defaults->tax_modifier_class_name);
            $body = $this->setBodySalesOrderLines($body, $order, $defaults->tax_modifier_class_name, $config->rounding_precision);
            $body = $this->setBodySubTotalAndTax($body, $order, $defaults->tax_modifier_class_name, $config->rounding_precision);

            // Add optional defaults
            if ($defaults->created_by) {
                $body['CreatedBy'] = $defaults->created_by;
            }

            if ($defaults->customer_type) {
                $body['CustomerType'] = $defaults->customer_type;
            }

            if ($defaults->sales_order_group) {
                $body['SalesOrderGroup'] = $defaults->sales_order_group;
            }

            if ($defaults->source_id) {
                $body['SourceId'] = $defaults->source_id;
            }

            // add phone number if available
            if ($order->BillingAddress()->Phone) {
                $body['PhoneNumber'] = $order->BillingAddress()->Phone;
            }

            // add required date
            $date_required = new DateTime($order->Paid);
            if ($defaults->expected_days_to_deliver) {
                $date_required->modify('+' . $defaults->expected_days_to_deliver . 'day');
            }
            $body['RequiredDate'] = $date_required->format('Y-m-d\TH:i:s');

            if ($order->Notes) {
                $body['Comments'] = $order->Notes;
            }

            // Create Member for Guests
            if (!$member->exists()) {
                $member = Member::create();
                $member->FirstName = $order->FirstName;
                $member->Surname = $order->Surname;
                $member->Email = $order->getLatestEmail();
            }

            // See if New Customer/Guest has previously purchased
            if (!$member->Guid) {
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
                        // A Customer is not returned, we have a unique email address.
                        // Check to see if the Customer Code exists (note that the Customer Code cannot be doubled up)
                        $response = UnleashedAPI::sendCall(
                            'GET',
                            'https://api.unleashedsoftware.com/Customers?customerCode=' . $body['CustomerCode']
                        );

                        if ($response->getStatusCode() == '200') {
                            $contents = json_decode($response->getBody()->getContents(), true);
                            $items = $contents['Items'];
                            if ($items) {
                                // A Customer Code already exists (and the email address is unique).
                                // If the address is the same then this is the Customer
                                if ($this->matchCustomerAddress($items, $order->ShippingAddress())) {
                                    $member->Guid = $items[0]['Guid'];

                                    //Note the existing email address in the Comment
                                    //PUT Customer is not available in Unleashed
                                    if ($body['Comments']) {
                                        $body['Comments'] .= PHP_EOL;
                                    }
                                    $body['Comments'] .= _t(
                                        'UnleashedAPI.addEmailToCustomerComment',
                                        'Add email to Customer: {email_address}',
                                        '',
                                        ['email_address' => $member->Email]
                                    );
                                } else {
                                    // The Customer Code already exists, we have a unique email address, but
                                    // the delivery address is new.
                                    // Therefore, we need to create a new Customer with a unique Customer Code.
                                    $body['CustomerCode'] .= rand(10000000, 99999999);
                                }
                            }
                        }
                    }
                }
            }

            if (!$member->Guid) {
                // The Customer Code does not exists in Unleashed and the email address is unique
                // therefore create in Unleashed
                $member->Guid = (string) Utils::createGuid();
                $body_member = [
                    'Addresses' => $body['Addresses'],
                    'ContactFirstName' => $member->FirstName,
                    'ContactLastName' => $member->Surname,
                    'CreatedBy' => $body['CreatedBy'],
                    'Currency' => $body['Currency'],
                    'CustomerCode' => $body['CustomerCode'],
                    'CustomerName' => $body['CustomerName'],
                    'CustomerType' => $body['CustomerType'],
                    'Email' => $member->Email,
                    'Guid' => $member->Guid,
                    'PaymentTerm' => $body['PaymentTerm'],
                    'PhoneNumber' => $body['PhoneNumber'],
                    'PrintPackingSlipInsteadOfInvoice' => $body['PrintPackingSlipInsteadOfInvoice'],
                    'SellPriceTier' => $body['SellPriceTier'],
                    'SourceId' => $body['SourceId'],
                    'Taxable' => $body['Taxable'],
                    'TaxCode' => $body['Tax']['TaxCode']
                ];

                foreach ($body_member['Addresses'] as $index => $value) {
                    $body_member['Addresses'][$index]['IsDefault'] = true;
                }

                $response = UnleashedAPI::sendCall(
                    'POST',
                    'https://api.unleashedsoftware.com/Customers/' . $member->Guid,
                    ['json' => $body_member ]
                );

                if ($response->getReasonPhrase() == 'Created' && $order->Member()->exists()) {
                    $member->write();
                }
            }

            // Prepare Sales Order data
            // Skip if previous calls to Customer have failed and the Guid has not been set
            if ($member->Guid) {
                $body['Customer']['Guid'] = $member->Guid;

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
