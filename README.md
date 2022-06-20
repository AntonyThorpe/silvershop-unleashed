# silvershop-unleashed
Silvershop submodule that integrates with Unleashed Software Inventory Management

[![CI](https://github.com/AntonyThorpe/silvershop-unleashed/actions/workflows/ci.yml/badge.svg)](https://github.com/AntonyThorpe/silvershop-unleashed/actions/workflows/ci.yml)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/antonythorpe/silvershop-unleashed/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/antonythorpe/silvershop-unleashed/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/antonythorpe/silvershop-unleashed/v/stable)](https://packagist.org/packages/antonythorpe/silvershop-unleashed)
[![Total Downloads](https://poser.pugx.org/antonythorpe/silvershop-unleashed/downloads)](https://packagist.org/packages/antonythorpe/silvershop-unleashed)
[![License](https://poser.pugx.org/antonythorpe/silvershop-unleashed/license)](https://packagist.org/packages/antonythorpe/silvershop-unleashed)
## Features
* Build Tasks to sync Products, Product Categories and Sales Orders with [Unleashed](https://apidocs.unleashedsoftware.com)
* Creates new Customers and Sales Orders in Unleashed upon the payment of a SilverShop order

## Use Case
Keeps Silvershop in alignment with an external source of truth - the inventory system.  Save time with updating product prices in Silvershop; simply run the BuildTask and review the report.  When an order is paid, the new Customer and Order are sent to Unleashed, saving time/data entry mistakes.

## How it works
* Adds a `Guid` property to `Product`, `ProductCategory`, `OrderItem`, `OrderModifier`, and `Member` classes.  The `Product`, `ProductCategory`, and `Order` dataobjects are updated via the Build Tasks.  This saves the Unleashed GUID in the database to identify changes found in results from API calls.
* Upon the payment of an Order, the module checks the Customer's email address with Unleashed and obtains the GUID.  This is used with the Sales Order post.  A couple of config settings are needed for the modifiers to flow through to Unleashed as an order item.
* The Order Build Task keeps the order status up to date.

## Requirements
* [Silvershop (a SilverStripe module)](https://github.com/silvershop/silvershop-core)
* [Consumer (a SilverStripe BulkLoader)](https://github.com/antonythorpe/consumer)
* [Guzzle](http://docs.guzzlephp.org/en/latest/).

## Creating a Sales Order in Unleashed
### Notes regarding getting/creating a Customer
* The customer is logged in and has a Guid from Unleashed?
    * true: use Guid of the Customer for the Sales Order
    * false: GET Customer filtered by the email address
        * if a Customer is returned.  Use the returned Guid of the Customer for the Sales Order
        * if a Customer is not returned.  As there might already be another customer with the same Customer Code then GET Customer filtered by the Customer Code (the Company name or the first & last name)
            * a Customer is returned.  Is the delivery address the same?
                * true: It must be the same one.  Use the returned Guid of the Customer for the Sales Order.
                * false: Add a random digit to the Customer Code.  POST Customer and use the returned Guid for the Sales Order
            * a Customer is not returned.  POST Customer and use the returned Guid for the Sales Order

### Adding an Order
* Post SalesOrders with order data

## Limitations
* Does not utilise the 'Charge' line type for Shipping when Sales Orders are created
* Discounts hardcoded to NIL in the Sales Order Items
* SalesPerson has not been implemented
* The Product/Product Categories Build Tasks only sync existing items within Unleashed.  New ones need to be added manually, via upload or modification to the existing Build Task.
* Will only send modifiers with a value to Unleashed.  NIL values are automatically skipped.
* If a user is logged in and changes the email address in the checkout form, then this new email address will not be passed onto Unleashed - no PUT Customer calls are available with Unleashed.
* If a Guest purchases a second time with a unique email and delivery address then a new Customer will be created in Unleashed.  A random number will be attached to its Customer Code to avoid double ups with the CustomerCode in Unleashed.

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
