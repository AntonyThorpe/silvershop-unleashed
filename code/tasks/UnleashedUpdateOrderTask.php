<?php

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Update Order with fresh data from Unleashed's Sales Orders
 *
 * @package silvershop-unleashed
 * @subpackage tasks
 */

abstract class UnleashedUpdateOrderTask extends UnleashedBuildTask
{
    /**
     * @var string
     */
    protected $title = "Unleashed: Update Orders";

    /**
     * @var string
     */
    protected $description = "Update Orders in Silvershop with with data received from Unleashed.  Will update the OrderStatus of the Silvershop items.";

    /**
     * Order status map from Unleashed to Silvershop
     *
     * For converting from Unleashed Sales Order Status to Silvershop
     * @var array
     */
    protected $order_status_map = [
        'Open' => 'Unpaid',
        'Parked' => 'Paid',
        'Backordered' => 'Processing',
        'Placed' => 'Processing',
        'Picking' => 'Processing',
        'Picked' => 'Processing',
        'Packed' => 'Processing',
        'Dispatched' => 'Sent',
        'Complete' => 'Complete',
        'Deleted' => 'MemberCancelled'
    ];

    public function run($request)
    {
        // Definitions
        $config = $this->config();
        $default_source_id = Order::config()->default_source_id;
        $query = [];
        $consumer = Consumer::get()->find('Title', 'OrderUpdate');  // to get modifiedSince

        if ($consumer) {
            $date = new DateTime($consumer->ExternalLastEdited);
            $query['modifiedSince'] = substr($date->format('Y-m-d\TH:i:s.u'), 0, 23);
        }

        if ($default_source_id) {
            $query['sourceId'] = $default_source_id;
        }

        $response = UnleashedAPI::sendCall(
            'GET',
            'https://api.unleashedsoftware.com/SalesOrders',
            ['query' => $query]
        );

        if ($response->getStatusCode() == '200') {
            $apidata_array = json_decode($response->getBody()->getContents(), true);
            $apidata = $apidata_array['Items'];
            $pagination = $apidata_array['Pagination'];
            $numberofpages = (int) $pagination['NumberOfPages'];

            if ($numberofpages > 1) {
                for ($i = 2; $i <= $numberofpages; $i++) {
                    $response = UnleashedAPI::sendCall(
                        'GET',
                        'https://api.unleashedsoftware.com/SalesOrders/' . $i,
                        ['query' => $query]
                    );
                    if ($response->getStatusCode() == '200') {
                        $apidata_array = json_decode($response->getBody()->getContents(), true);
                        $apidata = array_merge($apidata, $apidata_array['Items']);
                    }
                }
            }

            $this->log('<h3>Update the OrderStatus in Silvershop</h3>');
            $loader = OrderConsumerBulkLoader::create("Order");
            $loader->transforms = array(
                'Status' => array(
                    'callback' => function ($value, &$placeholder) {
                        // convert from Unleashed Sales Order status to Silvershop
                        return $this->order_status_map[$value];
                    }
                )
            );
            $results = $loader->updateRecords($apidata, $config->preview);

            if ($results->UpdatedCount()) {
                $this->log(Debug::text($results->getData()));
            }
            $this->log("Done");

            // Send email
            if ($results->Count() && $config->email_subject && !$config->preview) {
                $data = $results->getData();
                $email = Email::create(
                    ShopConfig::config()->email_from ? ShopConfig::config()->email_from : Email::config()->admin_email,
                    Email::config()->admin_email,
                    $config->email_subject,
                    Debug::text($data)
                );
                $dispatched = $email->send();
                if ($dispatched) {
                    $this->log('Email sent');
                }
            }

            // Create/update Consumer
            if (!$config->preview && $apidata) {
                if (!$consumer) {
                    $consumer = Consumer::create(
                        array(
                            'Title' => 'OrderUpdate',
                            'ExternalLastEditedKey' => 'LastModifiedOn'
                        )
                    );
                }
                $consumer->setMaxExternalLastEdited($apidata);
                $consumer->write();
            }

        } // end if response == 200
    }
}
