<?php

namespace AntonyThorpe\SilverShopUnleashed;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\Controller;
use SilverStripe\ORM\DatabaseAdmin;
use SilverStripe\ORM\DB;
use SilverStripe\Control\Director;

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
