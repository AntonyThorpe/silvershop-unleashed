<?php

namespace AntonyThorpe\SilverShopUnleashed\Tests;

use SilverShop\Shipping\ShippingFrameworkModifier;
use AntonyThorpe\SilverShopUnleashed\BulkLoader\OrderBulkLoader;
use AntonyThorpe\SilverShopUnleashed\Defaults;
use SilverShop\Cart\ShoppingCart;
use SilverShop\Checkout\OrderProcessor;
use SilverShop\Model\Modifiers\Shipping\Simple;
use SilverShop\Model\Modifiers\Tax\FlatTax;
use SilverShop\Model\Order;
use SilverShop\Page\Product;
use SilverShop\Tests\ShopTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

class UnleashedOrderTest extends SapphireTest
{
    protected static $fixture_file = [
        'vendor/silvershop/core/tests/php/Fixtures/ShopMembers.yml',
        'vendor/silvershop/core/tests/php/Fixtures/shop.yml',
        'vendor/silvershop/shipping/tests/DistanceShippingMethod.yml',
        'vendor/silvershop/shipping/tests/Warehouses.yml',
        'fixtures/models.yml'
    ];

    protected function setUp(): void
    {
        Defaults::config()->set('send_sales_orders_to_unleashed', false);
        Defaults::config()->set('tax_modifier_class_name', FlatTax::class);
        Defaults::config()->set('shipping_modifier_class_name', Simple::class);
        parent::setUp();
        ShoppingCart::singleton()->clear();
        ShopTest::setConfiguration(); //reset config
        $this->logInWithPermission('ADMIN');
        Config::modify()
            ->set(Simple::class, 'default_charge', 8.95)
            ->set(Simple::class, 'product_code', 'Freight')
            ->set(FlatTax::class, 'rate', 0.15)
            ->set(FlatTax::class, 'exclusive', true)
            ->set(FlatTax::class, 'name', 'GST')
            ->set(FlatTax::class, 'tax_code', 'OUTPUT2')
            ->merge(Order::class, 'modifiers', [Simple::class, FlatTax::class]);
    }

    protected array $order_status_map = [
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

    public function testChangeOrderStatus(): void
    {
        $apidata_array = (array) json_decode($this->jsondata, true);
        $apidata_array = reset($apidata_array);

        $items = $apidata_array['Items'];

        $orderBulkLoader = OrderBulkLoader::create(Order::class);
        $orderBulkLoader->transforms = [
            'Status' => [
                'callback' => fn($value, &$placeholder) =>
                    // convert from Unleashed Sales Order status to Silvershop
                    $this->order_status_map[$value]
            ]
        ];
        $bulkLoaderResult = $orderBulkLoader->updateRecords($items);

        // Check Results
        $this->assertEquals(0, $bulkLoaderResult->CreatedCount());
        $this->assertEquals(2, $bulkLoaderResult->UpdatedCount());
        $this->assertEquals(0, $bulkLoaderResult->DeletedCount());
        $this->assertEquals(0, $bulkLoaderResult->SkippedCount());
        $this->assertCount(2, $bulkLoaderResult);

        // Check Dataobjects
        $order1 = Order::get()->find('Reference', 'O1');
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

    public function testSetBodyAddress(): void
    {
        $body = [
            'Addresses' => []
        ];
        $order = $this->objFromFixture(Order::class, "payablecart");
        OrderProcessor::create($order)->placeOrder();
        $body = $order->setBodyAddress($body, $order, 'Postal');
        $result = implode('|', array_values($body['Addresses'][0]));
        $this->assertSame(
            '12 Foo Street Bar Farmville|Postal|Farmville|United States||New Sandwich|12 Foo Street|Bar',
            $result,
            'Postal Address added to $body["Address"]'
        );

        $body = $order->setBodyAddress($body, $order, 'Physical');
        $result = implode('|', array_values($body['Addresses'][1]));
        $this->assertSame(
            '12 Foo Street Bar Farmville|Physical|Farmville|United States||New Sandwich|12 Foo Street|Bar',
            $result,
            'Physical Address added to $body["Address"]'
        );
        $result = implode('|', array_values($body['Addresses'][2]));
        $this->assertSame(
            '12 Foo Street Bar Farmville|Shipping|Farmville|United States||New Sandwich|12 Foo Street|Bar',
            $result,
            'Shipping Address added to $body["Address"]'
        );

        $result = $body['DeliveryCity'] . '|' . $body['DeliveryCountry'] . '|' . $body['DeliveryPostCode'] . '|' . $body['DeliveryRegion'] . '|' . $body['DeliveryStreetAddress'] . '|' . $body['DeliveryStreetAddress2'];
        $this->assertSame(
            'Farmville|United States||New Sandwich|12 Foo Street|Bar',
            $result,
            'Delivery Address added to $body'
        );
    }

    public function testSetBodyCurrencyCode(): void
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

    public function testSetBodyCustomerCodeAndName(): void
    {
        $body = [];
        $order = $this->objFromFixture(Order::class, "payablecart");
        OrderProcessor::create($order)->placeOrder();
        $body = $order->setBodyCustomerCodeAndName($body, $order);
        $result = implode('|', array_values($body));
        $this->assertSame(
            'Payable Smith|Payable Smith',
            $result,
            'Set BodyCustomerCodeAndName'
        );

        $order->BillingAddress()->Company = 'Test Company';
        $body = $order->setBodyCustomerCodeAndName($body, $order);
        $result = implode('|', array_values($body));
        $this->assertSame(
            'Test Company|Test Company',
            $result,
            'Set BodyCustomerCodeAndName with Company name'
        );
    }

    public function testSetBodySalesOrderLines(): void
    {
        $order = $this->objFromFixture(Order::class, "paid1");
        OrderProcessor::create($order)->placeOrder();
        $this->assertEquals(408, $order->Total(), "check totals");
        $body = $order->setBodySalesOrderLines(
            [
                'Tax' => [
                    'TaxCode' => 'OUTPUT2'
                ]
            ],
            $order,
            FlatTax::class,
            2
        );
        $result = (string) json_encode($body, JSON_HEX_QUOT | JSON_HEX_TAG);
        $this->assertStringContainsString(
            'SalesOrderLines',
            $result,
            'Contains SalesOrderLines'
        );
        $this->assertStringContainsString(
            '"LineType":null,"LineTotal":8,"OrderQuantity":1,"Product":{"Guid":"G11"},"UnitPrice":8,"LineTax":1.2,"LineTaxCode":"OUTPUT2"}',
            $result,
            'Set SetBodySalesOrderLines line 1',
        );

        $this->assertStringContainsString(
            '"LineType":null,"LineTotal":400,"OrderQuantity":2,"Product":{"Guid":"G15"},"UnitPrice":200,"LineTax":60,"LineTaxCode":"OUTPUT2"}',
            $result,
            'Set SetBodySalesOrderLines line 2'
        );
    }

    public function testSetBodySalesOrderLinesWithModifiers(): void
    {
        $product = $this->objFromFixture(Product::class, 'urntap');
        $product->publishSingle();

        $shoppingCart = ShoppingCart::singleton();
        $shoppingCart->clear();
        $shoppingCart->add($product);

        $order = $shoppingCart->current();
        $order->calculate();

        $this->assertCount(
            2,
            $order->Modifiers(),
            'Shipping & Tax Modifiers in order'
        );
        $body = $order->setBodySalesOrderLines(
            [
                'Tax' => [
                    'TaxCode' => 'OUTPUT2'
                ]
            ],
            $order,
            FlatTax::class,
            2
        );
        $freight_modifier = $body['SalesOrderLines'][1];
        $result = $freight_modifier['DiscountRate'] . '|' . $freight_modifier['LineNumber'] . '|' . $freight_modifier['LineTotal'] . '|' . $freight_modifier['LineType'] . '|' . $freight_modifier['OrderQuantity'] . '|' . $freight_modifier['UnitPrice'] . '|' . $freight_modifier['LineTax'] . '|' . $freight_modifier['LineTaxCode'];

        $this->assertSame(
            '0|2|8.95||1|8.95|1.34|OUTPUT2',
            $result,
            'Modifiers in the SalesOrderLines added to $body'
        );
        $this->assertSame(
            'Freight',
            $freight_modifier['Product']['ProductCode'],
            'ProductCode of the Freight Modifier in $body is "Freight"'
        );
    }

    public function testSetBodySubTotalAndTax(): void
    {
        $product = $this->objFromFixture(Product::class, 'urntap');
        $product->publishSingle();

        $shoppingCart = ShoppingCart::singleton();
        $shoppingCart->clear();
        $shoppingCart->add($product);

        $order = $shoppingCart->current();
        $order->calculate();

        $body = $order->setBodyTaxCode(
            [],
            $order,
            FlatTax::class
        );
        $body = $order->setBodySalesOrderLines(
            $body,
            $order,
            FlatTax::class,
            2
        );
        $body = $order->setBodySubTotalAndTax(
            $body,
            $order,
            FlatTax::class,
            2
        );

        $this->assertTrue(
            $body['Taxable'],
            'Taxable is set to true'
        );
        $this->assertEqualsWithDelta(11.19, $body['TaxTotal'], PHP_FLOAT_EPSILON, 'TaxTotal is set to $11.19 ((65.65 + 8.95) * .15) in $body');
        $this->assertEqualsWithDelta(74.60, $body['SubTotal'], PHP_FLOAT_EPSILON, 'SubTotal is set to $74.60 (65.65 + 8.95) in $body');
    }

    public function testTaxRounding(): void
    {
        $product = $this->objFromFixture(Product::class, 'filter');
        $product->publishSingle();

        $tax_modifier_class_name = FlatTax::class;
        $shoppingCart = ShoppingCart::singleton();
        $shoppingCart->clear();
        $shoppingCart->add($product);

        $order = $shoppingCart->current();
        $total = $order->calculate();

        $body = $order->setBodyTaxCode(
            [],
            $order,
            $tax_modifier_class_name
        );
        $body = $order->setBodySalesOrderLines(
            $body,
            $order,
            $tax_modifier_class_name,
            2
        );
        $body = $order->setBodySubTotalAndTax(
            $body,
            $order,
            $tax_modifier_class_name,
            2
        );
        $this->assertEquals(
            '8.39',
            $body['TaxTotal'],
            'TaxTotal is set to $8.39 ((46.96 + 8.95) * .15) in $body'
        );
        $this->assertEquals(
            '55.91',
            $body['SubTotal'],
            'SubTotal is set to $55.91 (46.96 + 8.95) in $body'
        );
        $this->assertEqualsWithDelta(64.3, round($total, 2), PHP_FLOAT_EPSILON, 'Total equals $64.30');
        $this->assertEqualsWithDelta(64.3, round(floatval($body['TaxTotal'] + $body['SubTotal']), 2), PHP_FLOAT_EPSILON, 'TaxTotal plus SubTotal equals $64.30');
    }

    public function testTaxRounding2(): void
    {
        ShopTest::setConfiguration(); //reset config
        $this->logInWithPermission('ADMIN');
        Config::modify()
            ->set(Simple::class, 'default_charge', 15.50)
            ->set(Simple::class, 'product_code', 'Freight')
            ->set(FlatTax::class, 'rate', 0.15)
            ->set(FlatTax::class, 'exclusive', true)
            ->set(FlatTax::class, 'name', 'GST')
            ->set(FlatTax::class, 'tax_code', 'OUTPUT2')
            ->merge(Order::class, 'modifiers', [Simple::class, FlatTax::class]);
        $product = $this->objFromFixture(Product::class, 'boiler');
        $product->publishSingle();

        $tax_modifier_class_name = FlatTax::class;
        $shoppingCart = ShoppingCart::singleton();
        $shoppingCart->clear();
        $shoppingCart->add($product);

        $order = $shoppingCart->current();
        $total = $order->calculate();

        $body = $order->setBodyTaxCode(
            [],
            $order,
            $tax_modifier_class_name
        );
        $body = $order->setBodySalesOrderLines(
            $body,
            $order,
            $tax_modifier_class_name,
            2
        );
        $body = $order->setBodySubTotalAndTax(
            $body,
            $order,
            $tax_modifier_class_name,
            2
        );

        $this->assertEquals(
            '139.15',
            $body['TaxTotal'],
            'TaxTotal is set to $139.15 ((912.17 + 15.50) * .15) in $body'
        );
        $this->assertEquals(
            '927.67',
            $body['SubTotal'],
            'SubTotal is set to $927.67 (912.17 + 15.50) in $body'
        );
        $this->assertEqualsWithDelta(1066.82, round($total, 2), PHP_FLOAT_EPSILON, 'Total equals $1,066.82');
        $this->assertEqualsWithDelta(1066.82, round(floatval($body['TaxTotal'] + $body['SubTotal']), 2), PHP_FLOAT_EPSILON, 'TaxTotal plus SubTotal equals $1,066.82');
    }


    public function testSetBodyDeliveryMethodAndDeliveryName(): void
    {
        Defaults::config()->set('shipping_modifier_class_name', ShippingFrameworkModifier::class);
        Config::modify()->set(ShippingFrameworkModifier::class, 'product_code', 'Freight');
        $body = [];
        $configForClass = Defaults::config();
        $order = $this->objFromFixture(Order::class, "payablecart");
        $body = $order->setBodyDeliveryMethodAndDeliveryName($body, $order, $configForClass->get('shipping_modifier_class_name'));
        $result = implode('|', array_values($body));
        $this->assertSame(
            'Freight|Freight',
            $result,
            'Set BodyDeliveryMethodAndDeliveryName'
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
