<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;

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
        return new Config_ForClass("UnleashedAPI");
    }

    /**
     * Log calls if failed
     * @var boolean
     */
    public static $logfailedcalls = true;

    /**
     * Guzzle debug setting
     * @var boolean
     */
    public static $debug = false;

    /**
     * Format for request
     * @var string
     */
    protected static $format = 'json';

    /**
     * id from Unleashed
     * @var string
     */
    private static $id;

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
                    SS_Log::log(print_r("Request Exception", true), SS_Log::NOTICE);
                    //SS_Log::log(print_r($reason->getMessage(), true), SS_Log::NOTICE);
                    if (!empty($reason->getResponse())) {
                        SS_Log::log(print_r($reason->getResponse()->getBody()->getContents(), true), SS_Log::NOTICE);
                    }
                    SS_Log::log(print_r($reason->getRequest()->getMethod(), true), SS_Log::NOTICE);
                    SS_Log::log(print_r($reason->getRequest()->getHeaders(), true), SS_Log::NOTICE);
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

        /* Unleashed will soon upgrade to TLS 1.2
        $config['curl'] = [
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
        ];*/

        // add the headers
        $config['headers'] = array(
            'Content-Type' => self::config()->format,
            'Accept' => 'application/' . self::config()->format,
            'api-auth-id' => self::config()->id,
            'api-auth-signature' => base64_encode(hash_hmac('sha256', $params, self::config()->key, true))
        );

        parent::__construct($config);
    }
}
