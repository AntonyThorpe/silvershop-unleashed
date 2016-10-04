<?php

class OrderConsumerBulkLoader extends ConsumerBulkLoader
{
    public $columnMap = array(
        'Guid' => 'Guid',
        'OrderStatus' => 'Status'
    );

    public $duplicateChecks = array(
        'Guid'
    );
}
