# RJ Multi Carrier

Multi carrier module for PrestaShop 8+

## Description

RJ Multi Carrier is a comprehensive module for PrestaShop 8+ that allows you to manage multiple carriers with advanced configuration options. The module provides enhanced control over carrier display, priority ordering, and conditional rules for carrier availability.

## Features

- **Multi-carrier Management**: Manage all your carriers from a centralized interface
- **Priority Ordering**: Set priority for carriers to control display order
- **Conditional Rules**: Create rules for carrier availability based on various conditions
- **PrestaShop 8+ Compatible**: Fully compatible with PrestaShop 8.0.0 and above
- **Debug Mode**: Built-in debug mode for troubleshooting
- **Multi-shop Support**: Works seamlessly with PrestaShop multi-shop feature
- **Admin Integration**: Adds carrier information to order detail pages in admin
- **Responsive Design**: Modern and responsive UI for both frontend and backend

## Requirements

- PrestaShop 8.0.0 or higher
- PHP 7.2.5 or higher
- MySQL 5.6 or higher

## Installation

### Method 1: Manual Installation

1. Download the module from the releases page
2. Upload the `rj_multicarrier` folder to your PrestaShop `/modules/` directory
3. Go to your PrestaShop back office
4. Navigate to **Modules > Module Manager**
5. Search for "Multi Carrier"
6. Click **Install**

### Method 2: ZIP Upload

1. Download the module as a ZIP file
2. Go to your PrestaShop back office
3. Navigate to **Modules > Module Manager**
4. Click **Upload a module**
5. Select the ZIP file and upload
6. Click **Install**

### Method 3: Composer (for developers)

```bash
composer require romelljaramillo/rj_multicarrier
```

## Configuration

After installation:

1. Go to **Modules > Module Manager**
2. Search for "Multi Carrier"
3. Click **Configure**
4. Configure the following settings:
   - **Enable module**: Enable or disable the module
   - **Debug mode**: Enable debug logging for troubleshooting
   - **Priority order**: Choose ascending or descending priority order

## Usage

### Managing Carriers

The module automatically displays all available carriers in the configuration page. You can:

- View all active carriers
- See carrier details (ID, Name, Delay, Status)
- Configure priority and rules for each carrier

### Frontend Integration

The module hooks into the carrier selection process and displays carriers according to your configuration. Carriers are displayed with:

- Carrier logo (if available)
- Carrier name
- Delivery delay information
- Price information

### Admin Integration

In the order detail page, administrators can see:

- A dedicated "Multi Carrier" tab
- Complete carrier information for the order
- Carrier status and configuration

## Database Structure

The module creates two tables:

### rj_multicarrier

Stores carrier configurations:
- `id_rj_multicarrier`: Primary key
- `id_carrier`: Reference to carrier
- `id_shop`: Shop ID (for multi-shop)
- `active`: Carrier status
- `priority`: Display priority
- `date_add`: Creation date
- `date_upd`: Last update date

### rj_multicarrier_rule

Stores conditional rules for carriers:
- `id_rj_multicarrier_rule`: Primary key
- `id_rj_multicarrier`: Reference to carrier config
- `rule_type`: Type of rule
- `rule_value`: Rule value
- `active`: Rule status
- `date_add`: Creation date
- `date_upd`: Last update date

## Hooks

The module registers the following hooks:

- `displayHeader`: Load frontend CSS and JavaScript
- `displayBackOfficeHeader`: Load backend CSS and JavaScript
- `displayCarrierList`: Customize carrier list display
- `actionCarrierUpdate`: React to carrier updates
- `displayAdminOrderTabContent`: Display carrier info in order details
- `displayAdminOrderTabLink`: Add tab link in order details

## API / Helper Class

The module includes a `CarrierHelper` class with useful methods:

```php
// Get all carriers with configuration
RjMulticarrier\CarrierHelper::getCarriers($id_shop, $id_lang);

// Get carrier configuration
RjMulticarrier\CarrierHelper::getCarrierConfig($id_carrier, $id_shop);

// Save carrier configuration
RjMulticarrier\CarrierHelper::saveCarrierConfig($id_carrier, $data, $id_shop);

// Delete carrier configuration
RjMulticarrier\CarrierHelper::deleteCarrierConfig($id_carrier, $id_shop);

// Get carrier rules
RjMulticarrier\CarrierHelper::getCarrierRules($id_carrier);

// Save carrier rule
RjMulticarrier\CarrierHelper::saveCarrierRule($id_carrier, $rule_type, $rule_value);
```

## Troubleshooting

### Module doesn't appear after installation

- Clear PrestaShop cache: Delete files in `/var/cache/`
- Regenerate class index: Go to **Advanced Parameters > Performance** and click **Clear cache**

### Carriers not displaying correctly

- Enable debug mode in module configuration
- Check PrestaShop logs in **Advanced Parameters > Logs**
- Verify that carriers are active in **Shipping > Carriers**

### Database errors

- Ensure your MySQL user has proper permissions
- Check PHP error logs for detailed error messages
- Try uninstalling and reinstalling the module

## Support

For support, please:

1. Check the [documentation](README.md)
2. Open an issue on [GitHub](https://github.com/romelljaramillo/rj_multicarrier/issues)
3. Contact the author: romell.jaramillo@gmail.com

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This module is released under the [MIT License](LICENSE).

```
MIT License

Copyright (c) 2025 Romell Jaramillo

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

## Author

**Romell Jaramillo**
- Email: romell.jaramillo@gmail.com
- GitHub: [@romelljaramillo](https://github.com/romelljaramillo)

## Changelog

### Version 1.0.0 (2025)
- Initial release
- Multi-carrier management
- Priority ordering
- Conditional rules support
- PrestaShop 8+ compatibility
- Multi-shop support
- Admin integration
- Debug mode
