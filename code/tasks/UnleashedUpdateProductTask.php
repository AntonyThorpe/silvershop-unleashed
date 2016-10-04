<?php

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Update Products with fresh data from Unleashed Inventory Management Software
 *
 * @package silvershop-unleashed
 * @subpackage tasks
 */

abstract class UnleashedUpdateProductTask extends UnleashedBuildTask
{
    /**
     * @var string
     */
    protected $title = "Unleashed: Update Products";

    /**
     * @var string
     */
    protected $description = "Update the Products in Silvershop with data from Unleashed.  Will not automatically bring over new items but will update Titles, Base Price, etc.";

    public function run($request)
    {
        // Definitions
        $config = $this->config();
        $silvershopInternalItemIDMustBeUnique = Product::get()->column('InternalItemID');
        $silvershopTitleMustBeUnique = Product::get()->column('Title');
        $consumer = Consumer::get()->find('Title', 'ProductUpdate');

        // Get Products from Unleashed
        if (!$consumer) {
            $response = UnleashedAPI::sendCall(
                'GET',
                'https://api.unleashedsoftware.com/Products'
            );

            $apidata_array = json_decode($response->getBody()->getContents(), true);
            $apidata = $apidata_array['Items'];
            $pagination = $apidata_array['Pagination'];
            $numberofpages = (int) $pagination['NumberOfPages'];

            if ($numberofpages > 1) {
                for ($i = 2; $i <= $numberofpages; $i++) {
                    $response = UnleashedAPI::sendCall(
                        'GET',
                        'https://api.unleashedsoftware.com/Products/' . $i
                    );
                    $apidata_array = json_decode($response->getBody()->getContents(), true);
                    $apidata = array_merge($apidata, $apidata_array['Items']);
                }
            }

        } else {
            $query = [];
            $date = new DateTime($consumer->ExternalLastEdited);
            $date->setTimezone(new DateTimeZone("UTC"));  // required for Unleashed Products (not for other endpoints)
            $query['modifiedSince'] = substr($date->format('Y-m-d\TH:i:s.u'), 0, 23);

            $response = UnleashedAPI::sendCall(
                'GET',
                'https://api.unleashedsoftware.com/Products',
                ['query' => $query]
            );

            $apidata_array = json_decode($response->getBody()->getContents(), true);
            $apidata = $apidata_array['Items'];
            $pagination = $apidata_array['Pagination'];
            $numberofpages = (int) $pagination['NumberOfPages'];

            if ($numberofpages > 1) {
                for ($i = 2; $i <= $numberofpages; $i++) {
                    $response = UnleashedAPI::sendCall(
                        'GET',
                        'https://api.unleashedsoftware.com/Products/' . $i,
                        ['query' => $query]
                    );
                    $apidata_array = json_decode($response->getBody()->getContents(), true);
                    $apidata = array_merge($apidata, $apidata_array['Items']);
                }
            }
        }

        $this->log('<h2>Preliminary Checks</h2>');
        // Check for duplicates in DataList before proceeding further
        $duplicates = Utilities::getDuplicates($silvershopInternalItemIDMustBeUnique);
        if (!empty($duplicates)) {
            echo "<h2>Duplicate check of Product InternalItemID within Silvershop</h2>";
            foreach ($duplicates as $duplicate) {
                $this->log($duplicate);
            }
            $this->log('Please remove duplicates from Silvershop before running this Build Task');
            $this->log('Exit');
            die();
        } else {
            $this->log('No duplicate found');
        }

        $duplicates = Utilities::getDuplicates($silvershopTitleMustBeUnique);
        if (!empty($duplicates)) {
            echo "<h2>Duplicate check of Product Titles within Silvershop</h2>";
            foreach ($duplicates as $duplicate) {
                $this->log($duplicate);
            }
            $this->log('Please remove duplicates from Silvershop before running this Build Task');
            $this->log('Exit');
            die();
        } else {
            $this->log('No duplicate found');
        }

        // Check for duplicates in apidata before proceeding further
        $duplicates = Utilities::getDuplicates(array_column($apidata, 'ProductCode'));
        if ($duplicates) {
            echo "<h2>Duplicate check of ProductCode within Unleashed</h2>";
            foreach ($duplicates as $duplicate) {
                $this->log(htmlspecialchars($duplicate, ENT_QUOTES, 'utf-8'));
            }
            $this->log(
                'Please remove duplicates from Unleashed before running this Build Task'
            );
            $this->log('Exit');
            die();
        } else {
            $this->log('No duplicate found');
        }

        // Update
        $this->log('<h3>Update Product records in Silvershop</h3>');
        $loader = ProductConsumerBulkLoader::create("Product");
        $loader->transforms = array(
            'Parent' => array(
                'callback' => function ($value, $placeholder) {
                    $obj = ProductCategory::get()->find('Guid', $value['Guid']);
                    if ($obj) {
                        return $obj;
                    } else {
                        return ProductCategory::get()->find('Title', $value['GroupName']);
                    }
                }
            ),
            'BasePrice' => array(
                'callback' => function ($value, $placeholder) {
                    return (float)$value;
                }
            ),
            'Title' => array(
                'callback' => function ($value, &$placeholder) {
                    $placeholder->URLSegment = Convert::raw2url($value);
                    return $value;
                }
            )
        );
        $results = $loader->updateRecords($apidata, $config->preview);

        if ($results->UpdatedCount()) {
            $this->log(Debug::text($results->getData()));
        }
        $this->log("Done");

        // Send email
        if ($results->Count() && !$config->preview) {
            // send email
            $email = Email::create(
                UnleashedAPI::config()->email_from,
                UnleashedAPI::config()->email_to,
                $config->email_subject,
                Debug::text($results->getData())
            );
            $dispatched = $email->send();
            if ($dispatched) {
                $this->log('Email sent');
            }
        }

        if (!$config->preview && $apidata) {
            if (!$consumer) {
                $consumer = Consumer::create(
                    array(
                        'Title' => 'ProductUpdate',
                        'ExternalLastEditedKey' => 'LastModifiedOn'
                    )
                );
            }
            $consumer->setMaxExternalLastEdited($apidata);
            $consumer->write();
        }
    }
}
