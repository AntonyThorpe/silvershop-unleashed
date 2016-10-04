<?php

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Update ProductCategory with fresh data from Unleashed Software Inventory system
 *
 * @package silvershop-unleashed
 * @subpackage tasks
 */

abstract class UnleashedUpdateProductCategoryTask extends UnleashedBuildTask
{
    /**
     * @var string
     */
    protected $title = "Unleashed: Update Product Categories";

    /**
     * @var string
     */
    protected $description = "Update Product Categories in Silvershop with with data from Unleashed.  Will not automatically bring over new items but will update Title and record the Guid.";


    public function run($request)
    {
        // Definitions
        $config = $this->config();
        $apidata_array;
        $apidata;
        $silvershopDataListMustBeUnique = ProductCategory::get()->column('Title');

        // Get Product Categories from Unleashed
        $response = UnleashedAPI::sendCall(
            'GET',
            'https://api.unleashedsoftware.com/ProductGroups'
        );

        // Extract data
        $apidata_array = json_decode($response->getBody()->getContents(), true);
        if (isset($apidata_array)) {
            $apidata = $apidata_array['Items'];
        }

        $this->log('<h2>Preliminary Checks</h2>');
        // Check for duplicates in DataList before proceeding further
        $duplicates = Utilities::getDuplicates($silvershopDataListMustBeUnique);
        if ($duplicates) {
            echo "<h2>Duplicate check of Product Categories within Silvershop</h2>";
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
        $duplicates = Utilities::getDuplicates(array_column($apidata, 'GroupName'));
        if ($duplicates) {
            echo "<h2>Duplicate check of Product Categories within Unleashed</h2>";
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


        $this->log('<h2>Update Silvershop Product Categories from Unleashed</h2>');
        $loader_clear = ProductCategoryConsumerBulkLoader::create("ProductCategory");

        $this->log('<h3>Clear a Guid from the Silvershop Product Category if it does not exist</h3>');
        $results_absent = $loader_clear->clearAbsentRecords($apidata, 'Guid', 'Guid', $config->preview);
        if ($results_absent->UpdatedCount()) {
            $this->log(Debug::text($results_absent->getData()));
        }
        $this->log('Done');


        $this->log('<h3>Update Product Category records in Silvershop</h3>');
        $loader = ProductCategoryConsumerBulkLoader::create("ProductCategory");
        $loader->transforms = array(
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
        $count = $results_absent->Count() + $results->Count();
        if ($count && $config->email_subject && !$config->preview) {
            $data = $results_absent->getData();
            $data->merge($results->getData());
            $email = Email::create(
                ShopConfig::config()->email_from ? ShopConfig::config()->email_from : Email::config()->admin_email,
                Email::config()->admin_email,
                $config->email_subject,
                Debug::text($data)
            );
            $dispatched = $email->send();
            if ($dispatched) {
                $this->log('<h3>Email</h3>');
                $this->log('Sent');
            }
        }
    }
}
