<?php

class ProductConsumerBulkLoader extends ConsumerBulkLoader
{
    /**
     * The default behaviour creating relations
     * @var boolean
     */
    protected $relationCreateDefault = false;

    /**
     * Specify a colsure to be run on every imported record.
     * @var Closure
     */
    public $recordCallback = 'setOtherProperties';

    public $columnMap = array(
        'Guid' => 'Guid',
        'ProductCode' => 'InternalItemID',
        'ProductDescription' => 'Title',
        'ProductGroup' => 'Parent',
        'DefaultSellPrice' => 'BasePrice',
        'Width' => 'Width',
        'Height' => 'Height',
        'Depth' => 'Depth'
    );

    public $duplicateChecks = array(
        'Guid', 'InternalItemID'
    );

    /**
     * Specify a colsure to be run on every imported record to set other records
     *
     * @param object $obj The placeholder
     * @param array $record A row from the external API
     */
    public function setOtherProperties(&$obj, $record)
    {
        if ($record['Obsolete']) {
            $obj->AllowPurchase = 0;
        }
    }
}
