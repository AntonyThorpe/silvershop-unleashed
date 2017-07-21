# silvershop-unleashed
Silvershop submodule that integrates with Unleashed Software Inventory Management

[![Build Status](https://travis-ci.org/antonythorpe/silvershop-unleashed.svg?branch=master)](https://travis-ci.org/antonythorpe/silvershop-unleashed)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/antonythorpe/silvershop-unleashed/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/antonythorpe/silvershop-unleashed/?branch=master)
![helpfulrobot](https://helpfulrobot.io/antonythorpe/silvershop-unleashed/badge)
[![Latest Stable Version](https://poser.pugx.org/antonythorpe/silvershop-unleashed/v/stable)](https://packagist.org/packages/antonythorpe/silvershop-unleashed)
[![Total Downloads](https://poser.pugx.org/antonythorpe/silvershop-unleashed/downloads)](https://packagist.org/packages/antonythorpe/silvershop-unleashed)
[![License](https://poser.pugx.org/antonythorpe/silvershop-unleashed/license)](https://packagist.org/packages/antonythorpe/silvershop-unleashed)
## Features
* Build Tasks to sync Products, Product Categories and Sales Orders with [Unleashed](https://apidocs.unleashedsoftware.com)
* Creates new Customers and Sales Orders in Unleashed upon the payment of a Silvershop order (includes Guest customers)

## Use Case
Keeps Silvershop in alignment with an external source of truth - the inventory system.  Save time with updating product prices in Silvershop; simply run the BuildTask and review the report.  When a order is paid, the new Customer and Order are sent to Unleashed, saving time/data entry mistakes.

## How it works
* Adds a GUID property to `Product`, `ProductCategory`, `OrderItem`, `OrderModifier`, and `Member` classes.  The `Product`, `ProductCategory`, and `Order` dataobjects are updated via the Build Tasks.  This saves the Unleashed GUID in the database to identify changes found with future API calls.
* Upon payment of an Order, the module checks the Customer's email address with Unleashed and obtains the GUID.  This is used with the Sales Order post.  A couple of config settings are needed for the modifiers to flow through to Unleashed as an order item.
* The Order Build Task keeps the order status up to date.
* Uses [Guzzle](http://docs.guzzlephp.org/en/latest/) to make the API calls (though easily replaced with another system).

## Limitations
* Assumes that the Silvershop product prices exclude Tax
* Does not utilised the 'Charge' line type for Shipping when Sales Orders are created
* Discounts hardcoded to NIL in the Sales Order Items
* They Product/Product Categories Build Tasks only sync existing items with Unleashed.  New items need to be added manually, via upload or modification to the existing Build Task.
* Upon a paid order, will only send modifiers with a value to Unleashed

## Requirements
* [Silvershop (a SilverStripe module)](https://github.com/silvershop/silvershop-core)
* [Consumer (a SilverStripe BulkLoader)](https://github.com/antonythorpe/consumer)
* Recommended: [Guzzle](http://docs.guzzlephp.org/en/latest/).  Note: not required in composer.json.  Add if needed.

## Documentation
[Index](/docs/en/index.md)

## Support
None sorry.

## Change Log
[Link](changelog.md)

## Contributing
[Link](contributing.md)

## License
[MIT](LICENSE)
