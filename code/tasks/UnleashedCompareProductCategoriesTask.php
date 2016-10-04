<?php

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Compare Silvershop Categories against Product Categories in Unleashed Inventory Management Software
 *
 * Compares and provides a report
 */

class UnleashedCompareProductCategoriesTask extends UnleashedBuildTask
{
    /**
     * @var string
     */
    protected $title = "Unleashed: Product Categories comparison with Silvershop";

    /**
     * @var string
     */
    protected $description = "Compare Product Categories with those in Unleashed";


    public function run($request)
    {
        // Definitions
        $silvershopProductCategoryTitle = ProductCategory::get()->column('Title');

        // Get Product Categories from Unleashed
        $response = UnleashedAPI::sendCall(
            'GET',
            'https://api.unleashedsoftware.com/ProductGroups'
        );

        // Response body
        $unleashedCategoriesList = json_decode($response->getBody()->getContents(), true);

        if ($response->getStatusCode() == '200' && is_array($unleashedCategoriesList)) {
            $unleashedCategories = array_column($unleashedCategoriesList['Items'], 'GroupName');

            // Presentation
            echo "<h2>Product Categories in Silvershop</h2>";
            foreach ($silvershopProductCategoryTitle as $category) {
                $this->log($category);
            }

            echo "<h2>Product Categories in Unleashed</h2>";
            foreach ($unleashedCategoriesList['Items'] as $category) {
                $this->log(htmlspecialchars($category['GroupName'], ENT_QUOTES, 'utf-8'));
            }

            echo "<h2>Duplicate Check: Silvershop</h2>";
            $duplicates = Utilities::getDuplicates($silvershopProductCategoryTitle);
            if (!empty($duplicates)) {
                foreach ($duplicates as $duplicate) {
                    $this->log($duplicate);
                }
                $this->log(
                    'Please remove duplicates from Silvershop before running any Unleased Update Build Tasks'
                );
            } else {
                $this->log('None');
            }

            echo "<h2>Duplicate Check: Unleashed</h2>";
            $duplicates = Utilities::getDuplicates($unleashedCategories);
            if (!empty($duplicates)) {
                foreach ($duplicates as $duplicate) {
                    $this->log(htmlspecialchars($duplicate, ENT_QUOTES, 'utf-8'));
                }
                $this->log(
                    'Please remove duplicates from Unleashed before running any Unleased Update Build Tasks'
                );
            } else {
                $this->log('None');
            }

            echo "<h2>Product Categories in both Silvershop and Unleashed</h2>";
            foreach ($silvershopProductCategoryTitle as $category) {
                if (in_array($category, $unleashedCategories)) {
                    $this->log($category);
                }
            }
            $this->log('<b>Done</b>');

            echo "<h2>Product Categories in Silvershop but not in Unleashed</h2>";
            foreach ($silvershopProductCategoryTitle as $category) {
                if (!in_array($category, $unleashedCategories)) {
                    $this->log($category);
                }
            }
            $this->log('<b>Done</b>');

            echo "<h2>Product Categories in Unleashed but not the Silvershop</h2>";
            foreach ($unleashedCategories as $category) {
                if (!in_array($category, $silvershopProductCategoryTitle)) {
                    $this->log(htmlspecialchars($category), ENT_QUOTES, 'utf-8');
                }
            }
            $this->log('<b>Done</b>');
        } else {
            $this->log('Response contains no body');
            $this->log('Exit');
        }
    }
}
