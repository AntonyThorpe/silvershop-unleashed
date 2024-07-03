<?php

namespace AntonyThorpe\SilverShopUnleashed\Task;

use AntonyThorpe\Consumer\Utilities;
use AntonyThorpe\SilverShopUnleashed\Task\UnleashedBuildTask;
use AntonyThorpe\SilverShopUnleashed\UnleashedAPI;
use SilverShop\Page\ProductCategory;

/**
 * Compare Silvershop Categories against Product Categories in Unleashed Inventory Management Software
 * and provides a report
 */
abstract class UnleashedCompareProductCategoriesTask extends UnleashedBuildTask
{
    protected $title = "Unleashed: Product Categories comparison with Silvershop";

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

        // Response body contents
        $unleashedCategoriesList = (array) json_decode($response->getBody(), true);

        if ($response->getStatusCode() == '200' && is_array($unleashedCategoriesList)) {
            $unleashedCategories = array_column($unleashedCategoriesList['Items'], 'GroupName');

            // Presentation
            echo "<h2>Product Categories in Silvershop</h2>";
            foreach ($silvershopProductCategoryTitle as $category) {
                $this->log($category);
            }

            echo "<h2>Product Categories in Unleashed</h2>";
            foreach ($unleashedCategoriesList['Items'] as $category) {
                $this->log(htmlspecialchars((string) $category['GroupName'], ENT_QUOTES, 'utf-8'));
            }

            echo "<h2>Duplicate Check: Silvershop</h2>";
            $duplicates = Utilities::getDuplicates($silvershopProductCategoryTitle);
            if ($duplicates !== []) {
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
            if ($duplicates !== []) {
                foreach ($duplicates as $duplicate) {
                    $this->log(htmlspecialchars((string) $duplicate, ENT_QUOTES, 'utf-8'));
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
                    $this->log(htmlspecialchars((string) $category, ENT_QUOTES, 'utf-8'));
                }
            }
            $this->log('<b>Done</b>');
        } else {
            $this->log('Response contains no body');
            $this->log('Exit');
        }
    }
}
