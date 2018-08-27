# Upgrading

## From 1 to 2
* Class names changes for your config yaml files are:
  * from `UnleashedAPI` to  `AntonyThorpe\SilverShopUnleashed\UnleashedAPI`
  * from `Order` to `AntonyThorpe\SilverShopUnleashed\Defaults` (removed the private statics from the Order Extension as yaml file settings are overridden by the extension class)
* Removed the Sales Person setting as this requires an additional call to find/setup
