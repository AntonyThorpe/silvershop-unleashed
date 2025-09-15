<?php

namespace AntonyThorpe\SilverShopUnleashed\BulkLoader;

use AntonyThorpe\Consumer\BulkLoader;
use SilverShop\Page\Product;

class ProductBulkLoader extends BulkLoader
{
    /**
     * The default behaviour for creating relations
     */
    protected bool $relationCreateDefault = false;

    /**
     * Specify a colsure to be run on every imported record.
     */
    public $recordCallback = 'setOtherProperties';

    /**
     * Column Map
     * @var array
     */
    public $columnMap = [
        'Guid' => 'Guid',
        'ProductCode' => 'InternalItemID',
        'ProductDescription' => 'Title',
        'ProductGroup' => 'Parent',
        'DefaultSellPrice' => 'BasePrice',
        'Width' => 'Width',
        'Height' => 'Height',
        'Depth' => 'Depth'
    ];

    /**
     * Keys that need to be unique
     * @var array
     */
    public $duplicateChecks = [
        'Guid', 'InternalItemID'
    ];

    /**
     * Specify a closure to be run on every imported record to set other records
     * @param Product $product The placeholder
     * @param array $record A row from the external API
     */
    public function setOtherProperties(Product &$product, array $record): void
    {
        if ($record['Obsolete']) {
            $product->AllowPurchase = false;
        }
    }
}
