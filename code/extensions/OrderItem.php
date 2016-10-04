<?php namespace SilvershopUnleashed\Model;

use DataExtension;
use Utils;
use SS_Log;

class OrderItem extends DataExtension
{
    /**
     * Apply Guid if absent
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->owner->getField("Guid")) {
            $this->owner->Guid = (string) Utils::createGuid();
        }
    }
}
