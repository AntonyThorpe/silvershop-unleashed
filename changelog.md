# Change Log of Silvershop Unleashed

* 5.0.0 Upgrade to PHP 8.1
Breaking changes.  Changed namespace `AntonyThorpe\SilvershopUnleashed` to `AntonyThorpe\SilverShopUnleashed`.  Minimum PHP version 8.1.

* 4.0.0 Updated Namespaces to comply with PSR-4.  See [Upgrading](docs/en/upgrading.md) documentation.

* 3.0.0 Expanded default setting options and added tests
Breaking changes.  A tax code is now required against the Tax Modifier.  As well as that a Shipping Modifier needs to be specified.  Example below.
```yaml
SilverShop\Model\Modifiers\FlatTax:
  tax_code: OUTPUT2
AntonyThorpe\SilverShopUnleashed\Defaults:
  shipping_modifier_class_name: 'SilverShop\Model\Modifiers\Shipping\Simple'
  # Note: the `product_code` still needs to be set against the Shipping Modifier
```
Added additional defaults (print_packingslip_instead_of_invoice, shipping_modifier_class_name).
* 2.0.0 Upgrade for SilverShop 3/SilverStripe 4
* 1.0.0 Initial Release
