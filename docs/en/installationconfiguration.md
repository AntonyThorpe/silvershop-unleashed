# Installation and Configuration of Silvershop Unleashed
## Installation
In a terminal:
`composer require antonythorpe/silvershop-unleashed`

## Configuration
Unleashed API Setup: see 'Integration/Unleashed API Access' in Unleashed for your id and key.
```yaml
AntonyThorpe\SilverShopUnleashed\UnleashedAPI:
  id: 'XXX'
  key: 'XXXXX'
  client_type: 'your_business_name/your_environment_type' # for API Tracking
  logfailedcalls: true
  logsuccessfulcalls: true
  debug: true
```

Example for posting sales orders to Unleashed:
```yaml
AntonyThorpe\SilverShopUnleashed\Defaults:
  send_sales_orders_to_unleashed: true
  expected_days_to_deliver: 5  # five days from the paid date
  print_packingslip_instead_of_invoice: false # defaults to `true` as order has already been emailed by SilverShop
  tax_modifier_class_name: 'SilverShop\Model\Modifiers\Tax\FlatTax'
  shipping_modifier_class_name: 'SilverShop\Model\Modifiers\Shipping\Simple' # or SilverShop\Shipping\ShippingFrameworkModifier if using silvershop-shipping
  created_by: Web
  source_id: web # This is handy.  A source id is later used to limit calls when updating Order Status (see UnleashedUpdateOrderTask)
  # Note: some of the below optional settings must already exist in Unleashed
  payment_term: 7 Days # Default is Same Day
  customer_type: General
  sales_order_group: Online
  client_type: Your_Business_Name/Type_of_Call #tracking for api statistics

SilverShop\Model\Modifiers\Shipping\Simple:  # or SilverShop\Shipping\ShippingFrameworkModifier if using silvershop-shipping
  product_code: Freight  # Freight is an existing Product Code in Unleashed

SilverShop\Model\Modifiers\Tax\FlatTax:
  tax_code: G.S.T.  # This matches a Tax Code in the Systems Settings of Unleashed
```

### Build Tasks
This module's Build Tasks are abstract classes so you will need to subclass them:
```php
class SubClassNameOrdersFromUnleashedTask extends UnleashedUpdateOrderTask
{

}
```

### BulkLoader
Plus override the BulkLoader configuration setting if needed:
```yaml
AntonyThorpe\Consumer\Consumer:
  publishPages: false  # to cancel automatically staging to Live (default is true)
```

See the [Documentation](documentation.md) for the next step.
