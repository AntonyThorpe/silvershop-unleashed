<?php

namespace AntonyThorpe\SilverShopUnleashed\Task;

use SilverShop\Model\Order;
use AntonyThorpe\Consumer\Consumer;
use AntonyThorpe\SilverShopUnleashed\BulkLoader\OrderBulkLoader;
use AntonyThorpe\SilverShopUnleashed\Defaults;
use AntonyThorpe\SilverShopUnleashed\Task\UnleashedBuildTask;
use AntonyThorpe\SilverShopUnleashed\UnleashedAPI;
use SilverShop\Extension\ShopConfigExtension;
use SilverStripe\Control\Email\Email;
use SilverStripe\Dev\Debug;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;


/**
 * Update Order with fresh data from Unleashed's Sales Orders
 */
abstract class UnleashedUpdateOrderTask extends UnleashedBuildTask
{
    protected $title = "Unleashed: Update Orders";

    protected $description = "Update Orders in Silvershop with with data received from Unleashed.  Will update the OrderStatus of the Silvershop items.";

    protected string $email_subject = "API Unleashed Software - Update Order Results";

    /**
     * Order status map from Unleashed to Silvershop
     * For converting from Unleashed Sales Order Status to Silvershop
     */
    protected array $order_status_map = [
        'Open' => 'Unpaid',
        'Parked' => 'Paid',
        'Backordered' => 'Processing',
        'Placed' => 'Processing',
        'Picking' => 'Processing',
        'Picked' => 'Processing',
        'Packed' => 'Processing',
        'Dispatched' => 'Sent',
        'Completed' => 'Completed',
        'Deleted' => 'MemberCancelled'
    ];

    public function run($request): void
    {
        // Definitions
        $default_source_id = Defaults::config()->get('source_id');
        $query = [];
        $consumer = Consumer::get()->find('Title', 'OrderUpdate');  // to get modifiedSince

        if ($consumer) {
            $query['modifiedSince'] = substr(
                $consumer->dbObject('ExternalLastEdited')->format('Y-m-d\TH:i:s.u'),
                0,
                23
            );
        }

        if ($default_source_id) {
            $query['sourceId'] = $default_source_id;
        }

        $response = UnleashedAPI::sendCall(
            'GET',
            'https://api.unleashedsoftware.com/SalesOrders',
            ['query' => $query]
        );

        if ($response->getStatusCode() == 200) {
            $apidata_array = (array) json_decode((string) $response->getBody(), true);
            $apidata = $apidata_array['Items'];
            $pagination = $apidata_array['Pagination'];
            $numberofpages = intval($pagination['NumberOfPages']);

            if ($numberofpages > 1) {
                for ($i = 2; $i <= $numberofpages; $i++) {
                    $response = UnleashedAPI::sendCall(
                        'GET',
                        'https://api.unleashedsoftware.com/SalesOrders/' . $i,
                        ['query' => $query]
                    );
                    if ($response->getStatusCode() == 200) {
                        $apidata_array = (array) json_decode((string) $response->getBody(), true);
                        $apidata = array_merge($apidata, $apidata_array['Items']);
                    }
                }
            }

            $this->log('<h3>Update the OrderStatus in Silvershop</h3>');
            $loader = OrderBulkLoader::create(Order::class);
            $loader->transforms = [
                'Status' => [
                    'callback' => fn($value) =>
                        // convert from Unleashed Sales Order status to Silvershop
                        $this->order_status_map[$value]
                ]
            ];
            $results = $loader->updateRecords($apidata, $this->preview);

            if ($results->UpdatedCount()) {
                $this->log(Debug::text($results->getData()));
            }

            $this->log("Done");

            // Send email
            if ($results->Count() && $this->email_subject && !$this->preview && Email::config()->get('admin_email')) {
                $data = $results->getData();
                $email = Email::create(
                    ShopConfigExtension::config()->get('email_from') ?: Email::config()->get('admin_email'),
                    Email::config()->get('admin_email'),
                    $this->email_subject,
                    Debug::text($data)
                );

                $dispatched = true;
                try {
                    $email->send();
                } catch (TransportExceptionInterface $e) {
                    $dispatched = false;
                    $this->log("Email not sent: " . $e->getDebug());
                } finally {
                    if ($dispatched) {
                        $this->log("Email sent");
                    }
                }
            }

            // Create/update Consumer
            if (!$this->preview && $apidata) {
                if (!$consumer) {
                    $consumer = Consumer::create([
                        'Title' => 'OrderUpdate',
                        'ExternalLastEditedKey' => 'LastModifiedOn'
                    ]);
                }

                $consumer->setMaxExternalLastEdited($apidata);
                $consumer->write();
            }
        }
    }
}
