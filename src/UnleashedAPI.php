<?php

namespace AntonyThorpe\SilverShopUnleashed;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use SilverStripe\Core\Config\Config_ForClass;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;

/**
 * Settings to incorporate Unleashed API into a Guzzle Http Client
 */
class UnleashedAPI extends Client
{
    /**
     * Helper for getting static config
     *
     * The 'config' static function isn't available through Guzzle
     * @return Config_ForClass configuration object
     */
    public static function config()
    {
        return new Config_ForClass("AntonyThorpe\SilverShopUnleashed\UnleashedAPI");
    }

    /**
     * Log calls if failed
     * @var boolean
     */
    public static $logfailedcalls = false;

    /**
     * Guzzle debug setting
     * @var boolean
     */
    public static $debug = false;

    /**
     * id from Unleashed
     * @var string
     */
    public static $id;

    /**
     * key from Unleashed
     * @var string
     */
    private static $key;

    /**
     * Send asynchronous request to Restful endpoint
     * @param  string $method Http Request Method
     * @param  string $uri the Restful endpoint and query
     * @return object either the Guzzle Response Interface or an Guzzle Request Exception
     */
    public static function sendCall($method, $uri, $options = [])
    {
        $config = [];
        if ($uri) {
            $config['base_url'] = $uri;
        }
        if (isset($options['query'])) {
            $config['query'] = $options['query'];
        }

        $client = new UnleashedAPI($config);

        if (self::config()->debug) {
            $options['debug'] = true;
        }

        $promise = $client->requestAsync($method, $uri, $options);
        $promise->then(
            function (ResponseInterface $response) {
                return $response;
            },
            function (RequestException $reason) {
                if (self::config()->logfailedcalls) {
                    $line_formatter = new LineFormatter(
                        null, // Format of message in log, default [%datetime%] %channel%.%level_name%: %message% %context% %extra%\n
                        null, // Datetime format
                        true, // allowInlineLineBreaks option, default false
                        true  // discard empty Square brackets in the end, default false
                    );
                    $logger = new Logger("unleashed-log");
                    $stream_handler = new StreamHandler('./z_silverstripe-unleashed.log', Logger::INFO);
                    $stream_handler->setFormatter($line_formatter);
                    $logger->pushHandler($stream_handler);
                    $logger->info($reason->getMessage());
                    if (!empty($reason->getResponse())) {
                        $logger->info(print_r($reason->getResponse()->getBody()->getContents(), true));
                    }
                    $logger->info(print_r($reason->getRequest()->getMethod(), true));
                    $logger->info(print_r($reason->getRequest()->getHeaders(), true));
                }
                return $reason;
            }
        );
        return $promise->wait();  // execute call and return result
    }

    /**
     * Clients accept an array of constructor parameters
     *
     * @param array $config Client configuration settings.
     * @see \GuzzleHttp\RequestOptions for a list of available request options.
     */
    public function __construct(array $config = [])
    {
        // create the params for the base64_enclose
        $params = "";
        if (isset($config['base_url'])) {
            $params = parse_url($config['base_url'], PHP_URL_QUERY);
        }
        if (isset($config['query'])) {
            if (!empty($params)) {
                $params .= '&';
            }
            $params .= urldecode(http_build_query($config['query']));
        }

        // add the headers
        $config['headers'] = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'api-auth-id' => self::config()->id,
            'api-auth-signature' => base64_encode(hash_hmac('sha256', $params, self::config()->key, true))
        ];

        parent::__construct($config);
    }
}
