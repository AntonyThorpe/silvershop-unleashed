# Upgrading

## From 3 to 4
New namespaces to comply with [PSR-4](https://www.php-fig.org/psr/psr-4/).  For subclassing the Tasks classes change:
* `AntonyThorpe\SilverShopUnleashed\UnleashedBuildTask` to `AntonyThorpe\SilverShopUnleashed\Task\UnleashedBuildTask`
* `AntonyThorpe\SilverShopUnleashed\UnleashedCompareProductCategoriesTask` to `AntonyThorpe\SilverShopUnleashed\Task\UnleashedCompareProductCategoriesTask`
* `AntonyThorpe\SilverShopUnleashed\UnleashedUpdateOrderTask` to `AntonyThorpe\SilverShopUnleashed\Task\UnleashedUpdateOrderTask`
* `AntonyThorpe\SilverShopUnleashed\UnleashedUpdateProductCategoryTask` to `AntonyThorpe\SilverShopUnleashed\Task\UnleashedUpdateProductCategoryTask`
* `AntonyThorpe\SilverShopUnleashed\UnleashedUpdateProductCategoryTask` to `AntonyThorpe\SilverShopUnleashed\Task\UnleashedUpdateProductCategoryTask`
* `AntonyThorpe\SilverShopUnleashed\UnleashedUpdateProductTask` to `AntonyThorpe\SilverShopUnleashed\Task\UnleashedUpdateProductTask`

For the namespaces of the Bulk Loaders change:
* `AntonyThorpe\SilverShopUnleashed\OrderBulkLoader` to `AntonyThorpe\SilverShopUnleashed\BulkLoader\OrderBulkLoader`
* `AntonyThorpe\SilverShopUnleashed\ProductBulkLoader` to `AntonyThorpe\SilverShopUnleashed\BulkLoader\ProductBulkLoader`
* `AntonyThorpe\SilverShopUnleashed\ProductCategoryBulkLoader` to `AntonyThorpe\SilverShopUnleashed\BulkLoader\ProductCategoryBulkLoader`

## From 2 to 3
Adjustments to the below modifiers:
```yaml
SilverShop\Model\Modifiers\FlatTax:
  tax_code: OUTPUT2
AntonyThorpe\SilverShopUnleashed\Defaults:
  shipping_modifier_class_name: 'SilverShop\Model\Modifiers\Shipping\Simple'
  # Note: the `product_code` still needs to be set against the Shipping Modifier
```
## From 1 to 2
* Class names changes for your config yaml files are:
  * from `UnleashedAPI` to  `AntonyThorpe\SilverShopUnleashed\UnleashedAPI`
  * from `Order` to `AntonyThorpe\SilverShopUnleashed\Defaults` (removed the private statics from the Order Extension as yaml file settings are overridden by the extension class)
* Removed the Sales Person setting as this requires an additional call to find/setup
