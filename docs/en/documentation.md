# Documentation of Silvershop Unleashed

## Build Tasks
The objective of the Build Tasks are to call the Unleashed API and update the Silveshop dataobjects with the items received via a BulkLoader.

The default Build Tasks are abstract classes because it is assumed that most developers will have their own unique requirements and need to program their own calls to Unleashed.

This module relies on [Consumer](https://github.com/antonythorpe/consumer), a Silverstripe BulkLoader focused on consuming data from external APIs.

### Getting Started
To sync Products, subclass `UnleashedUpdateProductTask` and if needing to change the business rules copy and paste the `run` function to your new class and change as required.

Ditto for `UnleashedUpdateProductCategoryTask` and `UnleashedCompareProductCategories` for the Product Categories, and `UnleashedUpdateOrderTask` for Orders.

Configure the subclass' `title`, `description`, `email_subject` and `preview` settings in your subclass.  Set preview to `true` for a dry run without changing the dataobject (the report is still generated).


Note: the Product and Product Category Build Tasks only update the items that currently exist in SilverShop.  They do not automatically import new items.

## Pushing Orders to Unleashed as Sales Orders
Prerequisites:
* the Product Task has run and the Unleashed GUIDs are in the SilverShop_Product database table
* the default group for new members must be the same as one of the Pricing Tiers in Unleashed (required for new customer setups)

Once [configured](installationconfiguration.md), any new paid order in Silvershop will create a new Customer in Unleashed (if they don't already exist) and create an associated Sales Order.
