<?php

namespace AntonyThorpe\SilverShopUnleashed;

use AntonyThorpe\Consumer\BulkLoader;

class OrderBulkLoader extends BulkLoader
{
    public $columnMap = [
        'Guid' => 'Guid',
        'OrderStatus' => 'Status'
    ];

    public $duplicateChecks = [
        'Guid'
    ];
}
