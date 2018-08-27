<?php

namespace AntonyThorpe\SilverShopUnleashed;

use AntonyThorpe\Consumer\BulkLoader;

class ProductCategoryBulkLoader extends BulkLoader
{
    /**
     * Column Map
     * @var array
     */
    public $columnMap = [
        'Guid' => 'Guid',
        'GroupName' => 'Title'
    ];

    /**
     * Keys that need to be unique
     * @var array
     */
    public $duplicateChecks = [
        'Guid', 'Title'
    ];
}
