<?php

namespace AntonyThorpe\SilverShopUnleashed;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use SilverStripe\Core\Config\Config_ForClass;

/**
 * Settings to incorporate Unleashed API into a Guzzle Http Client
 * @link https://apidocs.unleashedsoftware.com/AuthenticationHelp
 */
class UnleashedAPI extends Client
{
    /**
     * Helper for getting static config
     * The 'config' static function isn't available through Guzzle
     */
    public static function config(): Config_ForClass
    {
        return new Config_ForClass("AntonyThorpe\SilverShopUnleashed\UnleashedAPI");
    }

    /**
     * Log calls if failed
     */
    private static bool $logfailedcalls = false;

    /**
     * Log calls if failed
     */
    private static bool $logsuccessfulcalls = false;

    /**
     * Guzzle debug setting
     */
    private static bool $debug = false;

    /**
     * id from Unleashed
     */
    private static string $id = '';

    /**
     * key from Unleashed
     */
    private static string $key = '';

    /**
     * For the header to enable API tracking
     */
    private static string $client_type = '';

    /**
     * Send asynchronous request to Restful endpoint
     * @param string $method Http Request Method
     * @param string $uri the Restful endpoint and query
     * @param array $options for Guzzle Request
     * @return mixed either the Guzzle Response Interface or an Guzzle Request Exception
     */
    public static function sendCall(string $method, string $uri, array $options = []): mixed
    {
        $config = [];
        if ($uri) {
            $config['base_url'] = $uri;
        }
        if (isset($options['query'])) {
            $config['query'] = $options['query'];
        }

        $client = new UnleashedAPI($config);

        if (UnleashedAPI::config()->debug) {
            $options['debug'] = true;
        }

        try {
            $response = $client->request($method, $uri, $options);
            if (UnleashedAPI::config()->logsuccessfulcalls) {
                $line_formatter = new LineFormatter(
                    null, // Format of message in log, default [%datetime%] %channel%.%level_name%: %message% %context% %extra%\n
                    null, // Datetime format
                    true, // allowInlineLineBreaks option, default false
                    true  // discard empty Square brackets in the end, default false
                );
                $logger = new Logger('unleashed-successful');
                $stream_handler = new StreamHandler('./z_unleashed-successful.log', Logger::INFO);
                $stream_handler->setFormatter($line_formatter);
                $logger->pushHandler($stream_handler);
                $logger->info(_t('UnleashedAPI.RequestSuccessTitle', 'Request successful') . '\n');
                $logger->info(_t('UnleashedAPI.Method', 'Request method: ') . $method);
                $logger->info(_t('UnleashedAPI.Uri', 'Request uri: ') . $uri);
                $logger->info(_t('UnleashedAPI.Options', 'Request options: '));
                $logger->info(print_r($options, true));
                $logger->info(_t('UnleashedAPI.ResponseContent', 'Response Content: '));
                $logger->info(print_r($response->getBody()->getContents(), true));
            }
            return $response;
        } catch (ClientException $e) {
            if (UnleashedAPI::config()->logfailedcalls) {
                $line_formatter = new LineFormatter(
                    null, // Format of message in log, default [%datetime%] %channel%.%level_name%: %message% %context% %extra%\n
                    null, // Datetime format
                    true, // allowInlineLineBreaks option, default false
                    true  // discard empty Square brackets in the end, default false
                );
                $logger = new Logger('unleashed-client-exception');
                $stream_handler = new StreamHandler('./z_unleashed-client-exception.log', Logger::INFO);
                $stream_handler->setFormatter($line_formatter);
                $logger->pushHandler($stream_handler);
                $logger->info(_t('UnleashedAPI.ClientExceptionTitle', 'Request failed'). '\n');
                $logger->info($e->getMessage());
                $logger->info(print_r($e->getResponse()->getBody()->getContents(), true));
                $logger->info($e->getRequest()->getMethod());
                $logger->info(print_r($e->getRequest()->getHeaders(), true));
            }
            return $e;
        }
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
        $params = '';
        if (isset($config['base_url'])) {
            $params = parse_url($config['base_url'], PHP_URL_QUERY) ?? '';
        }

        if (isset($config['query'])) {
            if ($params) {
                $params .= '&';
            }
            $params .= urldecode(http_build_query($config['query']));
        }

        // add the headers
        $config['headers'] = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'api-auth-id' => UnleashedAPI::config()->get('id'),
            'api-auth-signature' => base64_encode(hash_hmac('sha256', $params, UnleashedAPI::config()->key, true))
        ];

        if (UnleashedAPI::config()->get('client_type')) {
            $config['headers']['client-type'] = UnleashedAPI::config()->get('client_type');
        }

        parent::__construct($config);
    }
}
