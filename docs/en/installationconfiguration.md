# Installation and Configuration of Silvershop Unleashed
## Installation
In a terminal:
`composer require antonythorpe/silvershop-unleashed`
If using Guzzle:
`composer require guzzlehttp/guzzle`

## Configuration
Unleashed API Setup (relies on Guzzle).  See 'Integration/Unleashed API Access' in Unleashed for your id and key.
```yaml
AntonyThorpe\SilverShopUnleashed\UnleashedAPI:
  id: 'XXX'
  key: 'XXXXX'
  logfailedcalls: true
  debug: true
```

Example for posting sales orders to Unleashed:
```yaml
AntonyThorpe\SilverShopUnleashed\Defaults:
  send_sales_orders_to_unleashed: true
  expected_days_to_deliver: 5  # five days from the paid date
  tax_modifier_class_name: 'SilverShop\Model\Modifiers\Tax\FlatTax'
  # Note: some of the below optional settings must already exist in Unleashed
  created_by: Web
  payment_term: 7 Days # Default is Same Day
  customer_type: General
  sales_order_group: Online
  source_id: web # This is handy.  A source id is later used to limit calls when updating Order Status (see UnleashedUpdateOrderTask)

SilverShop\Model\Modifiers\Shipping\Simple:  # or SilverShop\Shipping\ShippingFrameworkModifier if using silvershop-shipping
  product_code: Freight  # Freight is an existing Product Code in Unleashed

SilverShop\Model\Modifiers\Tax\FlatTax:
  name: G.S.T.  # This matches a Tax Code in the Systems Settings of Unleashed
```

This module's Build Tasks are abstract classes so you will need to subclass them:
```php
class SubClassNameOrdersFromUnleashedTask extends UnleashedUpdateOrderTask
{

}
```
Plus override the BulkLoader configuration setting if needed:
```yaml
AntonyThorpe\Consumer\Consumer:
  publishPages: false  # to cancel automatically staging to Live (default is true)
```

See the [Documentation](documentation.md) for the next step.
