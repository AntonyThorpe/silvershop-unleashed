<?php

class UnleashedOrderTest extends SapphireTest
{
    protected static $fixture_file = array(
      'silvershop/tests/fixtures/ShopMembers.yml',
      'silvershop-unleashed/tests/fixtures/models.yml'
    );

    public function setUp()
    {
        parent::setUp();
        ShopTest::setConfiguration(); //reset config
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
        'Complete' => 'Complete'
    ];
    
    public function testChangeOrderStatus()
    {
        $apidata_array = json_decode($this->jsondata, true);
        $apidata_array = reset($apidata_array);
        $apidata = $apidata_array['Items'];

        $loader = OrderConsumerBulkLoader::create("Order");
        $loader->transforms = array(
            'Status' => array(
                'callback' => function ($value, &$placeholder) {
                    // convert from Unleashed Sales Order status to Silvershop
                    return $this->order_status_map[$value];
                }
            )
        );
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
