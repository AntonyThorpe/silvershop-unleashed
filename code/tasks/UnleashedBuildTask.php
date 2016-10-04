<?php

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Base BuildTask for Unleashed Software
 */

abstract class UnleashedBuildTask extends BuildTask
{
    /**
     * @var string
     */
    protected $email_subject;

    /**
     * @var booleen
     */
    protected $preview = false;

    /**
     * echo to screen for Build Reports
     * @param  string $msg the message to be printed
     */
    protected function log($msg)
    {
        echo "<div>" . $msg . "</div>";
    }
}
