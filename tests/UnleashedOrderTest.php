<?php

namespace AntonyThorpe\SilverShopUnleashed\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverShop\Page\Product;
use SilverShop\Model\Product\OrderItem;
use SilverShop\Model\Modifiers\OrderModifier;
use SilverShop\Model\Order;
use SilverShop\Checkout\OrderProcessor;
use SilverShop\Cart\ShoppingCart;
use SilverShop\Tests\ShopTest;
use SilverShop\Model\Modifiers\Shipping\Simple;
use SilverShop\Model\Modifiers\Tax\FlatTax;
use SilverShop\Shipping\Model\DistanceShippingMethod;
use AntonyThorpe\SilverShopUnleashed\OrderBulkLoader;
use AntonyThorpe\SilverShopUnleashed\Defaults;

class UnleashedOrderTest extends SapphireTest
{
    protected static $fixture_file = [
        'vendor/silvershop/core/tests/php/Fixtures/ShopMembers.yml',
        'vendor/silvershop/core/tests/php/Fixtures/shop.yml',
        'vendor/silvershop/shipping/tests/DistanceShippingMethod.yml',
        'vendor/silvershop/shipping/tests/Warehouses.yml',
        'fixtures/models.yml'
    ];

    public function setUp()
    {
        Defaults::config()->send_sales_orders_to_unleashed = false;
        Defaults::config()->tax_modifier_class_name = 'SilverShop\Model\Modifiers\Tax\FlatTax';
        Defaults::config()->shipping_modifier_class_name = 'SilverShop\Model\Modifiers\Shipping\Simple';
        parent::setUp();
        ShoppingCart::singleton()->clear();
        ShopTest::setConfiguration(); //reset config
        Config::modify()
            ->set(FlatTax::class, 'rate', 0.15)
            ->set(FlatTax::class, 'exclusive', true)
            ->set(FlatTax::class, 'name', 'GST')
            ->set(FlatTax::class, 'tax_code', 'OUTPUT2')
            ->set(Simple::class, 'default_charge', 8.95)
            ->set(Simple::class, 'product_code', 'Freight')
            ->merge(Order::class, 'modifiers', [FlatTax::class, Simple::class]);
    }

    protected $order_status_map = [
        'Open' => 'Unpaid',
        'Parked' => 'Paid',
        'Backordered' => 'Processing',
        'Placed' => 'Processing',
        'Picking' => 'Processing',
        'Picked' => 'Processing',
        'Packed' => 'Processing',
        'Dispatched' => 'Sent',
        'Complete' => 'Complete',
        'Deleted' => 'MemberCancelled'
    ];

    public function testChangeOrderStatus()
    {
        $apidata_array = json_decode($this->jsondata, true);
        $apidata_array = reset($apidata_array);
        $apidata = $apidata_array['Items'];

        $loader = OrderBulkLoader::create('SilverShop\Model\Order');
        $loader->transforms = [
            'Status' => [
                'callback' => function ($value, &$placeholder) {
                    // convert from Unleashed Sales Order status to Silvershop
                    return $this->order_status_map[$value];
                }
            ]
        ];
        $results = $loader->updateRecords($apidata);

        // Check Results
        $this->assertEquals($results->CreatedCount(), 0);
        $this->assertEquals($results->UpdatedCount(), 2);
        $this->assertEquals($results->DeletedCount(), 0);
        $this->assertEquals($results->SkippedCount(), 0);
        $this->assertEquals($results->Count(), 2);

        // Check Dataobjects
        $order1 = Order::get()->find('Reference', 'O1');
        //print_r($order1);
        $this->assertEquals(
            'Processing',
            $order1->Status,
            'OrderStatus of reference O1 is "Processing"'
        );

        $order2 = Order::get()->find('Reference', 'O2');
        $this->assertEquals(
            'Sent',
            $order2->Status,
            'OrderStatus of reference O1 is "Sent"'
        );
    }

    public function testSetBodyAddress()
    {
        $body = [
            'Addresses' => []
        ];
        $order = $this->objFromFixture(Order::class, "payablecart");
        OrderProcessor::create($order)->placeOrder();
        $body = $order->setBodyAddress($body, $order, 'Postal');
        $result = implode('|', array_values($body['Addresses'][0]));
        $this->assertSame(
            $result,
            '12 Foo Street Bar Farmville|Postal|Farmville|United States||New Sandwich|12 Foo Street|Bar',
            'Postal Address added to $body["Address"]'
        );

        $body = $order->setBodyAddress($body, $order, 'Physical');
        $result = implode('|', array_values($body['Addresses'][1]));
        $this->assertSame(
            $result,
            '12 Foo Street Bar Farmville|Physical|Farmville|United States||New Sandwich|12 Foo Street|Bar',
            'Physical Address added to $body["Address"]'
        );
        $result = implode('|', array_values($body['Addresses'][2]));
        $this->assertSame(
            $result,
            '12 Foo Street Bar Farmville|Shipping|Farmville|United States||New Sandwich|12 Foo Street|Bar',
            'Shipping Address added to $body["Address"]'
        );

        $result = $body['DeliveryCity'] . '|' . $body['DeliveryCountry'] . '|' . $body['DeliveryPostCode'] . '|' . $body['DeliveryRegion'] . '|' . $body['DeliveryStreetAddress'] . '|' . $body['DeliveryStreetAddress2'];
        $this->assertSame(
            $result,
            'Farmville|United States||New Sandwich|12 Foo Street|Bar',
            'Delivery Address added to $body'
        );
    }

    public function testSetBodyCurrencyCode()
    {
        $body = [
            'Currency' => []
        ];
        $order = $this->objFromFixture(Order::class, "payablecart");
        OrderProcessor::create($order)->placeOrder();
        $body = $order->setBodyCurrencyCode($body, $order);

        $this->assertSame(
            'NZD',
            $body['Currency']['CurrencyCode'],
            'Currency Code added to $body'
        );
    }

    public function testSetBodyCustomerCodeAndName()
    {
        $body = [];
        $order = $this->objFromFixture(Order::class, "payablecart");
        OrderProcessor::create($order)->placeOrder();
        $body = $order->setBodyCustomerCodeAndName($body, $order);
        $result = implode('|', array_values($body));
        $this->assertSame(
            $result,
            'Payable Smith|Payable Smith',
            'Set BodyCustomerCodeAndName'
        );

        $order->BillingAddress()->Company = 'Test Company';
        $body = $order->setBodyCustomerCodeAndName($body, $order);
        $result = implode('|', array_values($body));
        $this->assertSame(
            $result,
            'Test Company|Test Company',
            'Set BodyCustomerCodeAndName with Company name'
        );
    }

    public function testSetBodySalesOrderLines()
    {
        $body = [
            'Tax' => [
                'TaxCode' => 'OUTPUT2'
            ]
        ];
        $order = $this->objFromFixture(Order::class, "paid1");
        OrderProcessor::create($order)->placeOrder();
        $order->write();
        $order->calculate();
        $body = $order->setBodySalesOrderLines($body, $order, 'SilverShop\Model\Modifiers\Tax\FlatTax', 2);
        $result = json_encode($body);
        $this->assertContains(
            'SalesOrderLines',
            $result,
            'Contains SalesOrderLines'
        );
        $this->assertContains(
            '"LineType":null,"LineTotal":8,"OrderQuantity":1,"Product":{"Guid":"G11"},"UnitPrice":8,"LineTax":1.2,"LineTaxCode":"OUTPUT2"}',
            $result,
            'Set SetBodySalesOrderLines line 1',
        );

        $this->assertContains(
            '"LineType":null,"LineTotal":400,"OrderQuantity":2,"Product":{"Guid":"G15"},"UnitPrice":200,"LineTax":60,"LineTaxCode":"OUTPUT2"}',
            $result,
            'Set SetBodySalesOrderLines line 2'
        );
    }

    public function testSetBodySalesOrderLinesWithModifiers()
    {
        $body = [
            'Tax' => [
                'TaxCode' => 'OUTPUT2'
            ]
        ];
        $order = Order::create();
        $urntap = $this->objFromFixture(Product::class, 'urntap');
        $urntap->publishSingle();
        $order->Items()->add($urntap->createItem(1));
        $order->write();
        OrderProcessor::create($order)->placeOrder();
        $total = $order->calculate();

        $this->assertEquals(1, $order->Items()->count());
        $this->assertEquals(2, $order->Modifiers()->count()); // Shipping & GST
        $this->assertEquals(84.45, $total, 'Total equals $84.45');
        $body = $order->setBodySalesOrderLines($body, $order, 'SilverShop\Model\Modifiers\Tax\FlatTax', 2);
        $freight_modifier = $body['SalesOrderLines'][1];
        $result = $freight_modifier['DiscountRate'] . '|' . $freight_modifier['LineNumber'] . '|' . $freight_modifier['LineTotal'] . '|' . $freight_modifier['LineType'] . '|' . $freight_modifier['OrderQuantity'] . '|' . $freight_modifier['UnitPrice'] . '|' . $freight_modifier['LineTax'] . '|' . $freight_modifier['LineTaxCode'];
        $this->assertSame(
            $result,
            '0|2|8.95||1|8.95|1.34|OUTPUT2',
            'Modifiers in the SalesOrderLines added to $body'
        );
        $this->assertSame(
            $freight_modifier['Product']['ProductCode'],
            'Freight',
            'ProductCode of the Freight Modifier in $body is "Freight"'
        );
    }

    public function testSetBodySubTotalAndTax()
    {
        $order = Order::create();
        $urntap = $this->objFromFixture(Product::class, 'urntap');
        $urntap->publishSingle();
        $order->Items()->add($urntap->createItem(1));
        $order->write();
        OrderProcessor::create($order)->placeOrder();
        $total = $order->calculate();

        $body = $order->setBodySubTotalAndTax([], $order, 'SilverShop\Model\Modifiers\Tax\FlatTax', 2);
        $result = $body['Taxable'] . '|' . $body['TaxTotal'] . '|' . $body['SubTotal'];

        $this->assertSame(
            $result,
            '1|9.85|74.60',
            'Taxable, TaxTotal & SubTotal added to $body'
        );
        $this->assertSame(
            $body['Tax']['TaxCode'],
            'OUTPUT2',
            'TaxCode set in $body'
        );
    }

    public function testSetBodyDeliveryMethodAndDeliveryName()
    {
        Defaults::config()->shipping_modifier_class_name = 'SilverShop\Shipping\ShippingFrameworkModifier';
        Config::modify()->set('SilverShop\Shipping\ShippingFrameworkModifier', 'product_code', 'Freight');
        $body = [];
        $defaults = Defaults::config();
        $order = $this->objFromFixture(Order::class, "payablecart");
        $body = $order->setBodyDeliveryMethodAndDeliveryName($body, $order, $defaults->shipping_modifier_class_name);
        $result = implode('|', array_values($body));
        $this->assertSame(
            $result,
            'Freight|Freight',
            'Set BodyDeliveryMethodAndDeliveryName'
        );
    }

    /**
     * JSON data for test
     *
     * @link (Unleashed Software API Documentation, https://apidocs.unleashedsoftware.com/Products)
     * @var string
     */
    protected $jsondata = '[
        {
          "Pagination": {
            "NumberOfItems": 2,
            "PageSize": 200,
            "PageNumber": 1,
            "NumberOfPages": 1
          },
          "Items": [
            {
              "SalesOrderLines": [
                {
                  "LineNumber": 1,
                  "LineType": null,
                  "Product": {
                    "Guid": "G11",
                    "ProductCode": "IIID1",
                    "ProductDescription": "Socks"
                  },
                  "DueDate": "/Date(1473099415000)/",
                  "OrderQuantity": 1,
                  "UnitPrice": 8,
                  "DiscountRate": 0,
                  "LineTotal": 8,
                  "Volume": null,
                  "Weight": null,
                  "Comments": null,
                  "AverageLandedPriceAtTimeOfSale": 8,
                  "TaxRate": 0,
                  "LineTax": 0,
                  "XeroTaxCode": "G.S.T.",
                  "BCUnitPrice": 8,
                  "BCLineTotal": 8,
                  "BCLineTax": 0,
                  "LineTaxCode": "G.S.T.",
                  "XeroSalesAccount": null,
                  "SerialNumbers": null,
                  "BatchNumbers": null,
                  "Guid": "G401",
                  "LastModifiedOn": "/Date(1473149768263)/"
                },
                {
                  "LineNumber": 2,
                  "LineType": null,
                  "Product": {
                    "Guid": "G15",
                    "ProductCode": "IIID5",
                    "ProductDescription": "Mp3 Player"
                  },
                  "DueDate": "/Date(1473099415000)/",
                  "OrderQuantity": 2,
                  "UnitPrice": 200,
                  "DiscountRate": 0,
                  "LineTotal": 400,
                  "Volume": null,
                  "Weight": null,
                  "Comments": null,
                  "AverageLandedPriceAtTimeOfSale": 200,
                  "TaxRate": 0,
                  "LineTax": 0,
                  "XeroTaxCode": "G.S.T.",
                  "BCUnitPrice": 200,
                  "BCLineTotal": 400,
                  "BCLineTax": 0,
                  "LineTaxCode": "G.S.T.",
                  "XeroSalesAccount": null,
                  "SerialNumbers": null,
                  "BatchNumbers": null,
                  "Guid": "G402",
                  "LastModifiedOn": "/Date(1473149768279)/"
                }
              ],
              "OrderNumber": "O1",
              "OrderDate": "/Date(1473116400000)/",
              "RequiredDate": "/Date(1473548400000)/",
              "OrderStatus": "Placed",
              "Customer": {
                "CustomerCode": "Jeremy Peremy",
                "CustomerName": "Jeremy Peremy",
                "CurrencyId": 110,
                "Guid": "test",
                "LastModifiedOn": "/Date(1472624588017)/"
              },
              "CustomerRef": null,
              "Comments": "Test",
              "Warehouse": {
                "WarehouseCode": "test",
                "WarehouseName": "Queen St",
                "IsDefault": true,
                "StreetNo": "1",
                "AddressLine1": "Queen St",
                "AddressLine2": null,
                "City": "Invercargill",
                "Region": "Southland",
                "Country": "New Zealand",
                "PostCode": "9999",
                "PhoneNumber": "1234 567",
                "FaxNumber": null,
                "MobileNumber": null,
                "DDINumber": null,
                "ContactName": "Ed Hillary",
                "Obsolete": false,
                "Guid": "test",
                "LastModifiedOn": "/Date(1471582972964)/"
              },
              "ReceivedDate": "/Date(1473099415000)/",
              "DeliveryName": null,
              "DeliveryStreetAddress": "15 Ray St",
              "DeliverySuburb": "",
              "DeliveryCity": "Kaitaia",
              "DeliveryRegion": "Northland",
              "DeliveryCountry": "New Zealand",
              "DeliveryPostCode": "1111",
              "Currency": {
                "CurrencyCode": "NZD",
                "Description": "New Zealand, Dollars",
                "Guid": "test",
                "LastModifiedOn": "/Date(1415058050647)/"
              },
              "ExchangeRate": 1,
              "DiscountRate": 0,
              "Tax": {
                "TaxCode": "G.S.T.",
                "Description": null,
                "TaxRate": 0,
                "CanApplyToExpenses": false,
                "CanApplyToRevenue": false,
                "Obsolete": false,
                "Guid": "00000000-0000-0000-0000-000000000000",
                "LastModifiedOn": null
              },
              "TaxRate": 0,
              "XeroTaxCode": "G.S.T.",
              "SubTotal": 408,
              "TaxTotal": 0,
              "Total": 408,
              "TotalVolume": 0,
              "TotalWeight": 0,
              "BCSubTotal": 408,
              "BCTaxTotal": 0,
              "BCTotal": 408,
              "PaymentDueDate": "/Date(1473106568169)/",
              "SalesOrderGroup": null,
              "DeliveryMethod": null,
              "SalesPerson": null,
              "SendAccountingJournalOnly": false,
              "SourceId": "web",
              "CreatedBy": "api@unleashedsoftware.com",
              "Guid": "G201",
              "LastModifiedOn": "/Date(1473149768310)/"
            },
            {
              "SalesOrderLines": [
                {
                  "LineNumber": 1,
                  "LineType": null,
                  "Product": {
                    "Guid": "G11",
                    "ProductCode": "IIID1",
                    "ProductDescription": "Socks"
                  },
                  "DueDate": "/Date(1473099415000)/",
                  "OrderQuantity": 1,
                  "UnitPrice": 8,
                  "DiscountRate": 0,
                  "LineTotal": 8,
                  "Volume": null,
                  "Weight": null,
                  "Comments": null,
                  "AverageLandedPriceAtTimeOfSale": 8,
                  "TaxRate": 0,
                  "LineTax": 0,
                  "XeroTaxCode": "G.S.T.",
                  "BCUnitPrice": 8,
                  "BCLineTotal": 8,
                  "BCLineTax": 0,
                  "LineTaxCode": "G.S.T.",
                  "XeroSalesAccount": null,
                  "SerialNumbers": null,
                  "BatchNumbers": null,
                  "Guid": "G403",
                  "LastModifiedOn": "/Date(1473149768263)/"
                },
                {
                  "LineNumber": 2,
                  "LineType": null,
                  "Product": {
                    "Guid": "G15",
                    "ProductCode": "IIID5",
                    "ProductDescription": "Mp3 Player"
                  },
                  "DueDate": "/Date(1473099415000)/",
                  "OrderQuantity": 2,
                  "UnitPrice": 200,
                  "DiscountRate": 0,
                  "LineTotal": 400,
                  "Volume": null,
                  "Weight": null,
                  "Comments": null,
                  "AverageLandedPriceAtTimeOfSale": 200,
                  "TaxRate": 0,
                  "LineTax": 0,
                  "XeroTaxCode": "G.S.T.",
                  "BCUnitPrice": 200,
                  "BCLineTotal": 400,
                  "BCLineTax": 0,
                  "LineTaxCode": "G.S.T.",
                  "XeroSalesAccount": null,
                  "SerialNumbers": null,
                  "BatchNumbers": null,
                  "Guid": "G404",
                  "LastModifiedOn": "/Date(1473149768279)/"
                }
              ],
              "OrderNumber": "O2",
              "OrderDate": "/Date(1473116400000)/",
              "RequiredDate": "/Date(1473548400000)/",
              "OrderStatus": "Dispatched",
              "Customer": {
                "CustomerCode": "Jeremy Peremy",
                "CustomerName": "Jeremy Peremy",
                "CurrencyId": 110,
                "Guid": "test",
                "LastModifiedOn": "/Date(1472624588017)/"
              },
              "CustomerRef": null,
              "Comments": "Test",
              "Warehouse": {
                "WarehouseCode": "test",
                "WarehouseName": "Queen St",
                "IsDefault": true,
                "StreetNo": "1",
                "AddressLine1": "Queen St",
                "AddressLine2": null,
                "City": "Invercargill",
                "Region": "Southland",
                "Country": "New Zealand",
                "PostCode": "9999",
                "PhoneNumber": "1234 567",
                "FaxNumber": null,
                "MobileNumber": null,
                "DDINumber": null,
                "ContactName": "Ed Hillary",
                "Obsolete": false,
                "Guid": "test",
                "LastModifiedOn": "/Date(1471582972964)/"
              },
              "ReceivedDate": "/Date(1473099415000)/",
              "DeliveryName": null,
              "DeliveryStreetAddress": "15 Ray St",
              "DeliverySuburb": "",
              "DeliveryCity": "Kaitaia",
              "DeliveryRegion": "Northland",
              "DeliveryCountry": "New Zealand",
              "DeliveryPostCode": "1111",
              "Currency": {
                "CurrencyCode": "NZD",
                "Description": "New Zealand, Dollars",
                "Guid": "test",
                "LastModifiedOn": "/Date(1415058050647)/"
              },
              "ExchangeRate": 1,
              "DiscountRate": 0,
              "Tax": {
                "TaxCode": "G.S.T.",
                "Description": null,
                "TaxRate": 0,
                "CanApplyToExpenses": false,
                "CanApplyToRevenue": false,
                "Obsolete": false,
                "Guid": "00000000-0000-0000-0000-000000000000",
                "LastModifiedOn": null
              },
              "TaxRate": 0,
              "XeroTaxCode": "G.S.T.",
              "SubTotal": 408,
              "TaxTotal": 0,
              "Total": 408,
              "TotalVolume": 0,
              "TotalWeight": 0,
              "BCSubTotal": 408,
              "BCTaxTotal": 0,
              "BCTotal": 408,
              "PaymentDueDate": "/Date(1473106568169)/",
              "SalesOrderGroup": null,
              "DeliveryMethod": null,
              "SalesPerson": null,
              "SendAccountingJournalOnly": false,
              "SourceId": "web",
              "CreatedBy": "api@unleashedsoftware.com",
              "Guid": "G202",
              "LastModifiedOn": "/Date(1473149768310)/"
            }
          ]
        }
      ]';
}
