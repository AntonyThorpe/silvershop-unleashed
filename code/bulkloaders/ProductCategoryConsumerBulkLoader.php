<?php

class ProductCategoryConsumerBulkLoader extends ConsumerBulkLoader
{
    public $columnMap = array(
        'Guid' => 'Guid',
        'GroupName' => 'Title'
    );

    public $duplicateChecks = array(
        'Guid', 'Title'
    );
}
