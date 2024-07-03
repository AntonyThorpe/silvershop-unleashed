<?php

namespace AntonyThorpe\SilverShopUnleashed\Tests;

use AntonyThorpe\SilverShopUnleashed\BulkLoader\ProductCategoryBulkLoader;
use AntonyThorpe\SilverShopUnleashed\Defaults;
use SilverShop\Model\Order;
use SilverShop\Page\ProductCategory;
use SilverShop\Tests\ShopTest;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

class UnleashedProductCategoryTest extends SapphireTest
{
    protected static $fixture_file = [
        'vendor/silvershop/core/tests/php/Fixtures/ShopMembers.yml',
        'fixtures/models.yml'
    ];

    public function setUp(): void
    {
        Defaults::config()->send_sales_orders_to_unleashed = false;
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
        $loader = ProductCategoryBulkLoader::create(ProductCategory::class);
        $loader->transforms = [
            'Title' => [
                'callback' => function ($value, &$placeholder) {
                    $placeholder->URLSegment = Convert::raw2url($value);
                    return $value;
                }
            ]
        ];
        $results = $loader->updateRecords($apidata['Items']);

        // Check Results
        $this->assertEquals($results->CreatedCount(), 0);
        $this->assertEquals($results->UpdatedCount(), 2);
        $this->assertEquals($results->DeletedCount(), 0);
        $this->assertEquals($results->SkippedCount(), 3);
        $this->assertEquals($results->Count(), 2);

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

        $results_absent = $loader->clearAbsentRecords($apidata['Items'], 'Guid', 'Guid');

        // Check Results
        $this->assertEquals($results_absent->CreatedCount(), 0);
        $this->assertEquals($results_absent->UpdatedCount(), 1);
        $this->assertEquals($results_absent->DeletedCount(), 0);
        $this->assertEquals($results_absent->SkippedCount(), 0);
        $this->assertEquals($results_absent->Count(), 1);

        $clearance = ProductCategory::get()->find('Title', 'Clearance');
        $this->assertNull(
            $clearance->Guid,
            'Guid of Clearance is null'
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
