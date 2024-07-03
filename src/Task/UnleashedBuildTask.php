<?php

namespace AntonyThorpe\SilverShopUnleashed\Task;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DatabaseAdmin;
use SilverStripe\ORM\DB;

/**
 * Base BuildTask for Unleashed Software
 */
abstract class UnleashedBuildTask extends BuildTask
{
    protected string $email_subject = "API Unleashed Software";

    protected bool $preview = false;

    /**
     * echo to screen for Build Reports
     */
    protected function log(string $text): void
    {
        if (Controller::curr() instanceof DatabaseAdmin) {
            DB::alteration_message($text, 'obsolete');
        } elseif (Director::is_cli()) {
            echo $text . "\n";
        } else {
            echo $text . "<br/>";
        }
    }
}
