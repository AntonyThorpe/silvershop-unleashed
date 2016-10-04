# Documentation of Silvershop Unleashed

## Build Tasks
The objective of the Build Tasks are to call the Unleashed API and update the Silveshop dataobjects with the received items via a BulkLoader.

The default Build Tasks are abstract classes because it is imagined that most developers will have their own unique requirements and need to programme their on calls to Unleashed.

This module relies on [Consumer](https://github.com/antonythorpe/consumer), a Silverstripe BulkLoader focused on consuming data from external APIs.  This module has added additional methods to the `BetterBulkLoader` class of [Burnbright/silverstripe-importexport](https://github.com/burnbright/silverstripe-importexport), hence any configurations/instructions for [Silverstripe Import/Export](https://github.com/burnbright/silverstripe-importexport) equally apply to the `Consumer` class.

### Getting Started
To sync Products, subclass `UnleashedUpdateProductTask` and if needing to change the business rules copy and paste the `run` function to your new class and change as required.

Ditto for `UnleashedUpdateProductCategoryTask` and `UnleashedCompareProductCategories` for the Product Categories, and `UnleashedUpdateOrderTask` for Orders.

Configure the subclass' `title`, `description`, `email_subject` and `preview` settings in yaml:
```yaml
SubClassNameProductCategoryFromUnleashedTask:
  email_subject: Unleashed API - Update Product Categories Results
  preview: true  # When true, a dry run only.  Does not update the dataobect, however, creates a hypothetical change report.  

SubClassNameProductFromUnleashedTask:
  email_subject: Unleashed API - Update Product Results
  preview: true

SubClassNameOrdersFromUnleashedTask:
  email_subject: Unleashed API - Update Order Results
  preview: true
```
Tip: safety first - set `preview` to `true` in your config to inspect the results without updating the dataobject.

Note: the Product and Product Category Build Tasks only update the items that currently exist in Silvershop.  They do not import new items.

Idea: Create a cron job to run the build tasks on a regular basis.


## Sales Orders
Prerequisite: The Product Task has run and the Unleashed GUIDs are in the Product database table. 

Once configured, any new paid order in Silvershop will create a new Customer in Unleashed (if they don't already exist) and create an association with the Sales Order.




