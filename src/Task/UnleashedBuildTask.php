<?php

namespace AntonyThorpe\SilverShopUnleashed\Task;

use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
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
    /**
     * @var string
     */
    protected $email_subject = "API Unleashed Software";

    /**
     * @var boolean
     */
    protected $preview = false;

    /**
     * echo to screen for Build Reports
     * @param  string $text the message to be printed
     */
    protected function log($text)
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
