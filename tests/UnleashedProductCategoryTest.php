<?php

namespace AntonyThorpe\SilverShopUnleashed\Tests;

use AntonyThorpe\SilverShopUnleashed\BulkLoader\ProductCategoryBulkLoader;
use AntonyThorpe\SilverShopUnleashed\Defaults;
use SilverShop\Page\ProductCategory;
use SilverShop\Tests\ShopTest;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\SapphireTest;

class UnleashedProductCategoryTest extends SapphireTest
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

        //publish some product categories and products
        $this->objFromFixture(ProductCategory::class, 'products')->copyVersionToStage('Stage', 'Live');
        $this->objFromFixture(ProductCategory::class, 'clothing')->copyVersionToStage('Stage', 'Live');
        $this->objFromFixture(ProductCategory::class, 'electronics')->copyVersionToStage('Stage', 'Live');
        $this->objFromFixture(ProductCategory::class, 'musicplayers')->copyVersionToStage('Stage', 'Live');
        $this->objFromFixture(ProductCategory::class, 'clearance')->copyVersionToStage('Stage', 'Live');
        $this->objFromFixture(ProductCategory::class, 'newguy')->copyVersionToStage('Stage', 'Live');
    }

    public function testSetGuidAndAdjustTitle(): void
    {
        $apidata = (array) json_decode($this->jsondata, true, flags: JSON_BIGINT_AS_STRING | JSON_OBJECT_AS_ARRAY);
        $apidata = reset($apidata);

        // Test the setting of a Guid
        $productCategoryBulkLoader = ProductCategoryBulkLoader::create(ProductCategory::class);
        $productCategoryBulkLoader->transforms = [
            'Title' => [
                'callback' => function ($value, &$placeholder) {
                    $placeholder->URLSegment = Convert::raw2url($value);
                    return $value;
                }
            ]
        ];
        $bulkLoaderResult = $productCategoryBulkLoader->updateRecords($apidata['Items']);

        // Check Results
        $this->assertEquals(0, $bulkLoaderResult->CreatedCount());
        $this->assertEquals(2, $bulkLoaderResult->UpdatedCount());
        $this->assertEquals(0, $bulkLoaderResult->DeletedCount());
        $this->assertEquals(3, $bulkLoaderResult->SkippedCount());
        $this->assertCount(2, $bulkLoaderResult);

        // Check Dataobjects
        $newguy = ProductCategory::get()->find('Title', 'New Guy');
        $this->assertEquals(
            'G116',
            $newguy->Guid,
            'New Guy has a Guid of G116'
        );

        $electronics = ProductCategory::get()->find('Title', 'Electroncis Adjusted Title');
        $this->assertEquals(
            'Electroncis Adjusted Title',
            $electronics->Title,
            'Electroncis has a new title of "Electonics Adjusted Title"'
        );
        $this->assertEquals(
            'electroncis-adjusted-title',
            $electronics->URLSegment,
            'Electroncis has a new URLSegment of "electroncis-adjusted-title"'
        );

        $results_absent = $productCategoryBulkLoader->clearAbsentRecords($apidata['Items'], 'Guid', 'Guid');

        // Check Results
        $this->assertEquals(0, $results_absent->CreatedCount());
        $this->assertEquals(1, $results_absent->UpdatedCount());
        $this->assertEquals(0, $results_absent->DeletedCount());
        $this->assertEquals(0, $results_absent->SkippedCount());
        $this->assertCount(1, $results_absent);

        $clearance = ProductCategory::get()->find('Title', 'Clearance');
        $this->assertNull(
            $clearance->Guid,
            'Guid of Clearance is null'
        );
    }


    /**
     * JSON data for test
     * UnleashedProductCategoryTest.php @link https://apidocs.unleashedsoftware.com/Products
     * @var string
     */
    protected $jsondata = '[
        {
          "Items": [
            {
              "GroupName": "Products",
              "Guid": "G111",
              "LastModifiedOn": "/Date(1471582359667)/"
            },
            {
              "GroupName": "Clothing",
              "Guid": "G112",
              "LastModifiedOn": "/Date(1471582359667)/"
          },
            {
              "GroupName": "Electroncis Adjusted Title",
              "Guid": "G113",
              "LastModifiedOn": "/Date(1471582366307)/"
            },
            {
              "GroupName": "Music Players",
              "Guid": "G114",
              "LastModifiedOn": "/Date(1471582476917)/"
            },
            {
              "GroupName": "New Guy",
              "Guid": "G116",
              "LastModifiedOn": "/Date(1475209329508)/"
            }
          ]
        }
      ]';
}
