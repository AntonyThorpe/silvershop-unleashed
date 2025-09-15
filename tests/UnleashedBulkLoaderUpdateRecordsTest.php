<?php

namespace AntonyThorpe\SilverShopUnleashed\Tests;

use AntonyThorpe\SilverShopUnleashed\BulkLoader\ProductBulkLoader;
use AntonyThorpe\SilverShopUnleashed\Defaults;
use SilverShop\Page\Product;
use SilverShop\Page\ProductCategory;
use SilverShop\Tests\ShopTest;
use SilverStripe\Dev\SapphireTest;

class UnleashedBulkLoaderUpdateRecordsTest extends SapphireTest
{
    protected static $fixture_file = [
        'vendor/silvershop/core/tests/php/Fixtures/ShopMembers.yml',
        'fixtures/models.yml'
    ];


    protected function setUp(): void
    {
        Defaults::config()->set('send_sales_orders_to_unleashed', false);
        parent::setUp();
        ShopTest::setConfiguration(); //reset config

        $product = $this->objFromFixture(Product::class, 'mp3player');
        $socks = $this->objFromFixture(Product::class, 'socks');

        //publish some product categories and products
        $this->objFromFixture(ProductCategory::class, 'products')->copyVersionToStage('Stage', 'Live');
        $this->objFromFixture(ProductCategory::class, 'clothing')->copyVersionToStage('Stage', 'Live');
        $this->objFromFixture(ProductCategory::class, 'clearance')->copyVersionToStage('Stage', 'Live');
        $this->objFromFixture(ProductCategory::class, 'musicplayers')->copyVersionToStage('Stage', 'Live');
        $this->objFromFixture(ProductCategory::class, 'electronics')->copyVersionToStage('Stage', 'Live');
        $product->copyVersionToStage('Stage', 'Live');
        $socks->copyVersionToStage('Stage', 'Live');
    }

    public function testUpdate(): void
    {
        $apidata = (array) json_decode($this->jsondata, true);
        $apidata = reset($apidata);

        $productBulkLoader = ProductBulkLoader::create(Product::class);
        $productBulkLoader->transforms = [
            'Parent' => [
                'callback' => function ($value, $placeholder) {
                    $obj = ProductCategory::get()->find('Guid', $value);
                    if ($obj) {
                        return $obj;
                    }

                    return ProductCategory::get()->find('Title', $value);
                }
            ]
        ];
        $bulkLoaderResult = $productBulkLoader->updateRecords($apidata['Items']);

        // Check Results
        $this->assertEquals(0, $bulkLoaderResult->CreatedCount(), 'zero created');
        $this->assertEquals(2, $bulkLoaderResult->UpdatedCount(), 'two updated');
        $this->assertEquals(0, $bulkLoaderResult->DeletedCount(), 'zero deleted');
        $this->assertEquals(0, $bulkLoaderResult->SkippedCount(), 'zero skipped');
        $this->assertCount(2, $bulkLoaderResult);

        // Check Dataobjects
        $product_category_clothing = ProductCategory::get()->find('Title', 'Clothing');
        $product_category_electronics = ProductCategory::get()->find('Title', 'Electronics');
        $socks = Product::get()->find('InternalItemID', 'IIID1');
        $mp3player = Product::get()->find('InternalItemID', 'IIID5');
        $this->assertInstanceOf(Product::class, $socks);

        // Check Parent
        $this->assertSame(
            $product_category_clothing->Title,
            $socks->Parent()->Title,
            'Socks should have a parent page with title of Clothing'
        );
        $this->assertInstanceOf(Product::class, $mp3player);
        $this->assertSame(
            $product_category_electronics->Title,
            $mp3player->Parent()->Title,
            'MP3 Player should have a parent page with title of Electronics as it was changed from Music Players'
        );

        $this->assertEquals(
            false,
            $mp3player->AllowPurchase,
            'AllowPurchase of the MP3 player set to false'
        );

        $this->assertSame(
            'Socks Updated',
            $socks->Title,
            'Title is set to Socks Updated'
        );
        $this->assertEqualsWithDelta(1.0, $socks->Width, PHP_FLOAT_EPSILON, 'Width of socks updated to 1');
        $this->assertEqualsWithDelta(2.0, $socks->Height, PHP_FLOAT_EPSILON, 'Height of socks updated to 2');
        $this->assertEqualsWithDelta(3.0, $socks->Depth, PHP_FLOAT_EPSILON, 'Depth of socks updated to 3');
        $this->assertEqualsWithDelta(8.50, $socks->getPrice(), PHP_FLOAT_EPSILON, 'Socks BasePrice updated to 8.50');
    }


    /**
     * JSON data for test
     * Unleashed Software API Documentation @link https://apidocs.unleashedsoftware.com/Products
     * @var string
     */
    protected $jsondata = '[
      {
          "Pagination": {
              "NumberOfItems": 14,
              "PageSize": 200,
              "PageNumber": 1,
              "NumberOfPages": 1
          },
          "Items": [
              {
                  "ProductCode": "IIID1",
                  "ProductDescription": "Socks Updated",
                  "Barcode": null,
                  "PackSize": null,
                  "Width": 1,
                  "Height": 2,
                  "Depth": 3,
                  "Weight": 0.1,
                  "MinStockAlertLevel": null,
                  "MaxStockAlertLevel": null,
                  "ReOrderPoint": null,
                  "UnitOfMeasure": {
                      "Guid": "2b9e913b-f815-440f-83ac-3639e3f04d64",
                      "Name": "EA"
                  },
                  "NeverDiminishing": false,
                  "LastCost": 47.5,
                  "DefaultPurchasePrice": 47.5,
                  "DefaultSellPrice": 8.50,
                  "AverageLandPrice": 40.4294,
                  "Obsolete": false,
                  "Notes": null,
                  "SellPriceTier1": {
                      "Name": "Sell Price Tier 1",
                      "Value": null
                  },
                  "SellPriceTier2": {
                      "Name": "Sell Price Tier 2",
                      "Value": null
                  },
                  "SellPriceTier3": {
                      "Name": "Sell Price Tier 3",
                      "Value": null
                  },
                  "SellPriceTier4": {
                      "Name": "Sell Price Tier 4",
                      "Value": null
                  },
                  "SellPriceTier5": {
                      "Name": "Sell Price Tier 5",
                      "Value": null
                  },
                  "SellPriceTier6": {
                      "Name": "Sell Price Tier 6",
                      "Value": null
                  },
                  "SellPriceTier7": {
                      "Name": "Sell Price Tier 7",
                      "Value": null
                  },
                  "SellPriceTier8": {
                      "Name": "Sell Price Tier 8",
                      "Value": null
                  },
                  "SellPriceTier9": {
                      "Name": "Sell Price Tier 9",
                      "Value": null
                  },
                  "SellPriceTier10": {
                      "Name": "Sell Price Tier 10",
                      "Value": null
                  },
                  "XeroTaxCode": null,
                  "XeroTaxRate": null,
                  "TaxablePurchase": true,
                  "TaxableSales": true,
                  "XeroSalesTaxCode": null,
                  "XeroSalesTaxRate": null,
                  "IsComponent": false,
                  "IsAssembledProduct": false,
                  "CanAutoAssemble": false,
                  "ProductGroup": {
                      "GroupName": "Clothing",
                      "Guid": "G112",
                      "LastModifiedOn": "2015-11-21T08:07:20"
                  },
                  "XeroSalesAccount": null,
                  "XeroCostOfGoodsAccount": null,
                  "BinLocation": null,
                  "Supplier": null,
                  "SourceId": null,
                  "CreatedBy": "admin",
                  "SourceVariantParentId": null,
                  "Guid": "G11",
                  "LastModifiedOn": "2015-11-21T08:07:20"
              },
              {
                  "ProductCode": "IIID5",
                  "ProductDescription": "Mp3 Player Updated",
                  "Barcode": null,
                  "PackSize": null,
                  "Width": 0.4,
                  "Height": 1,
                  "Depth": 1,
                  "Weight": 1,
                  "MinStockAlertLevel": 5,
                  "MaxStockAlertLevel": 100,
                  "ReOrderPoint": null,
                  "UnitOfMeasure": {
                      "Guid": "2b9e913b-f815-440f-83ac-3639e3f04d64",
                      "Name": "EA"
                  },
                  "NeverDiminishing": false,
                  "LastCost": 12.8529,
                  "DefaultPurchasePrice": 200,
                  "DefaultSellPrice": 24.95,
                  "AverageLandPrice": 12.4539,
                  "Obsolete": true,
                  "Notes": null,
                  "SellPriceTier1": {
                      "Name": "Sell Price Tier 1",
                      "Value": null
                  },
                  "SellPriceTier2": {
                      "Name": "Sell Price Tier 2",
                      "Value": null
                  },
                  "SellPriceTier3": {
                      "Name": "Sell Price Tier 3",
                      "Value": null
                  },
                  "SellPriceTier4": {
                      "Name": "Sell Price Tier 4",
                      "Value": null
                  },
                  "SellPriceTier5": {
                      "Name": "Sell Price Tier 5",
                      "Value": null
                  },
                  "SellPriceTier6": {
                      "Name": "Sell Price Tier 6",
                      "Value": null
                  },
                  "SellPriceTier7": {
                      "Name": "Sell Price Tier 7",
                      "Value": null
                  },
                  "SellPriceTier8": {
                      "Name": "Sell Price Tier 8",
                      "Value": null
                  },
                  "SellPriceTier9": {
                      "Name": "Sell Price Tier 9",
                      "Value": null
                  },
                  "SellPriceTier10": {
                      "Name": "Sell Price Tier 10",
                      "Value": null
                  },
                  "XeroTaxCode": null,
                  "XeroTaxRate": null,
                  "TaxablePurchase": true,
                  "TaxableSales": true,
                  "XeroSalesTaxCode": null,
                  "XeroSalesTaxRate": null,
                  "IsComponent": false,
                  "IsAssembledProduct": false,
                  "CanAutoAssemble": false,
                  "ProductGroup": {
                      "GroupName": "Electronics",
                      "Guid": "G113",
                      "LastModifiedOn": "2015-11-21T08:07:20"
                  },
                  "XeroSalesAccount": null,
                  "XeroCostOfGoodsAccount": null,
                  "BinLocation": null,
                  "Supplier": null,
                  "SourceId": null,
                  "CreatedBy": "admin",
                  "SourceVariantParentId": null,
                  "Guid": "G15",
                  "LastModifiedOn": "2015-11-21T08:07:20"
              }
          ]
      }
    ]';
}
