# Montaža Bojlera Addon

WordPress plugin for WooCommerce that adds boiler installation service as an addon for products.

## Description

This plugin allows you to offer boiler installation and delivery service as an optional addon for your WooCommerce products. Customers can select the service on product pages, and it will be added to their cart with the configured price.

## Features

- ✅ Checkbox on single product pages to add installation service
- ✅ Checkbox on checkout page for products without service
- ✅ Admin panel to configure service price
- ✅ Configurable delivery time (min/max days)
- ✅ Shortcode for displaying service information in posts/pages
- ✅ Automatic price calculation in cart and checkout
- ✅ Service information displayed in order meta

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.2 or higher

## Installation

1. Upload the `montaza-bojlera-addon.php` file to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Montaža Bojlera' in the admin menu to configure settings

## Configuration

After activation, go to **Montaža Bojlera** in the WordPress admin menu to configure:

- **Service Price**: Set the price for boiler installation service
- **Delivery Time - Minimum**: Minimum number of delivery days
- **Delivery Time - Maximum**: Maximum number of delivery days

## Usage

### On Product Pages

When a customer views a product page, they will see a checkbox:
- "Montaža i dostava bojlera +[price]"
- Delivery time information
- Service details and limitations

If checked, the service will be added to the cart along with the product.

### On Checkout Page

For products in the cart that don't have the service, a checkbox will appear on the checkout page allowing customers to add the service.

### Shortcode

Use the shortcode `[montaza_bojlera]` in any post, page, or widget to display service information:

```
[montaza_bojlera]
```

This will display a formatted block with:
- Service price
- Delivery time
- Service description
- Important notes

## How It Works

1. **Product Page**: Customer can select the service via checkbox
2. **Cart**: Service is tied to each product individually
3. **Checkout**: Customers can add service for products that don't have it
4. **Order**: Service information is saved in order meta data

## Technical Details

- Uses WooCommerce hooks and filters
- AJAX functionality for checkout updates
- Compatible with Flatsome theme
- Stores settings in WordPress options
- Clean uninstall removes all data

## Support

For issues or questions, please contact the plugin author.

## Changelog

### Version 1.0
- Initial release
- Basic functionality with checkbox on product pages
- Admin panel for price configuration
- Checkout page integration
- Shortcode support
- Delivery time configuration

## License

This plugin is provided as-is for use with your WordPress/WooCommerce installation.

