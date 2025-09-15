<?php

namespace AntonyThorpe\SilverShopUnleashed\Tests;

use AntonyThorpe\SilverShopUnleashed\Defaults;
use SilverShop\Model\Order;
use SilverShop\Tests\ShopTest;
use SilverStripe\Dev\SapphireTest;

class UnleashedCustomerTest extends SapphireTest
{
    public Order $order;

    protected static $fixture_file = [
        'vendor/silvershop/core/tests/php/Fixtures/ShopMembers.yml',
        'vendor/silvershop/core/tests/php/Fixtures/Orders.yml',
        'fixtures/models.yml'
    ];

    protected function setUp(): void
    {
        Defaults::config()->set('send_sales_orders_to_unleashed', false);
        parent::setUp();
        ShopTest::setConfiguration(); //reset config
        $this->order = $this->objFromFixture(Order::class, "cart1");
    }

    public function testGetAddressNameFromOrder(): void
    {
        $this->assertSame(
            '201-203 BROADWAY AVE U 235 WEST BEACH',
            $this->order->getAddressName($this->order->ShippingAddress()),
            'Result matches 201-203 Broadway Ave U 235 West Beach'
        );
    }

    public function testGetAddressNameItems(): void
    {
        $apidata_array = (array) json_decode($this->jsondata, true);
        $apidata_array = reset($apidata_array);

        $items = $apidata_array['Items'];

        $this->assertSame(
            '31 Hurstmere Road RD1 Auckland',
            $this->order->getAddressName($items[0]['Addresses'][1]),
            'Result matches 31 Hurstmere Road RD1 Auckland'
        );
    }

    public function testMatchCustomerAddress(): void
    {
        $apidata_array = (array) json_decode($this->jsondata, true);
        $apidata_array = reset($apidata_array);

        $items = $apidata_array['Items'];

        // Test a failed match
        $this->assertFalse(
            $this->order->matchCustomerAddress($items, $this->order->ShippingAddress()),
            "The address in the API data does not match the order's shipping address"
        );

        // Test a direct match
        $address = $this->order->ShippingAddress();
        $address->Address = '31 Hurstmere Road';
        $address->AddressLine2 = 'RD1';
        $address->City = 'Auckland';

        $this->assertTrue(
            $this->order->matchCustomerAddress($items, $address),
            "The address in the API data matches the order's shipping address"
        );
    }

    /**
     * JSON data for test
     * Unleashed Software API Documentation @link https://apidocs.unleashedsoftware.com/Products
     * @var string
     */
    protected $jsondata = '[
        {
          "Pagination": {
            "NumberOfItems": 11,
            "PageSize": 200,
            "PageNumber": 1,
            "NumberOfPages": 1
          },
          "Items": [
            {
              "Addresses": [
                  {
                      "AddressType": "Postal",
                      "AddressName": "1 Queen St",
                      "StreetAddress": "1 Queen St",
                      "StreetAddress2": "RD 2",
                      "Suburb": "",
                      "City": "Pukekohe",
                      "Region": "Auckland",
                      "Country": "New Zealand",
                      "PostalCode": "0622"
                  },
                  {
                      "AddressType": "Physical",
                      "AddressName": "Main Warehouse",
                      "StreetAddress": "31 Hurstmere Road",
                      "StreetAddress2": "RD1",
                      "Suburb": "Takapuna",
                      "City": "Auckland",
                      "Region": "North Shore",
                      "Country": "New Zealand",
                      "PostalCode": "0622"
                  }
              ],
              "CustomerCode": "FRANCK",
              "CustomerName": "Franck & Co.",
              "GSTVATNumber": null,
              "BankName": null,
              "BankBranch": null,
              "BankAccount": null,
              "Website": null,
              "PhoneNumber": null,
              "FaxNumber": null,
              "MobileNumber": null,
              "DDINumber": null,
              "TollFreeNumber": null,
              "Email": null,
              "EmailCC": null,
              "Currency": {
                  "CurrencyCode": "USD",
                  "Description": "United States of America, Dollars",
                  "Guid": "3088672d-2a24-4d23-bb1c-c91813ed4c76",
                  "LastModifiedOn": "/Date(1458675959843)/"
              },
              "Notes": null,
              "Taxable": true,
              "XeroContactId": null,
              "SalesPerson": {
                  "FullName": "John Smith",
                  "Email": "john.smith@acme.co",
                  "Obsolete": false,
                  "Guid": "224ae802-88d3-43c1-b9cc-208d5d6d0ccf",
                  "LastModifiedOn": "/Date(1459218946890)/"
              },
              "DiscountRate": 0,
              "PrintPackingSlipInsteadOfInvoice": false,
              "PrintInvoice": false,
              "StopCredit": false,
              "Obsolete": false,
              "XeroSalesAccount": null,
              "XeroCostOfGoodsAccount": null,
              "SellPriceTier": "",
              "SellPriceTierReference": null,
              "CustomerType": "Cash",
              "PaymentTerm": "20th Month following",
              "ContactFirstName": null,
              "ContactLastName": null,
              "SourceId": null,
              "CreatedBy": "admin",
              "CreatedOn": "/Date(1411781315923)/",
              "Guid": "d32d0ca7-fd8e-4a99-9da6-be546ac5252b",
              "LastModifiedOn": "/Date(1459218740823)/"
            }
          ]
        }]';
}
