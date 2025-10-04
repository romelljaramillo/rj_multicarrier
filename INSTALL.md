# Installation Guide - RJ Multi Carrier

## Quick Start

### For PrestaShop Users

1. **Download the module**
   - Download as ZIP from GitHub releases or clone the repository

2. **Install via PrestaShop Admin**
   ```
   - Go to: Modules > Module Manager
   - Click: "Upload a module"
   - Select the ZIP file or upload the rj_multicarrier folder
   - Click: "Install"
   ```

3. **Configure the module**
   ```
   - Go to: Modules > Module Manager
   - Search: "Multi Carrier"
   - Click: "Configure"
   - Enable the module
   - Set your preferences (Debug mode, Priority order)
   - Click: "Save"
   ```

### For Developers

#### Using Composer

```bash
# Navigate to PrestaShop modules directory
cd /path/to/prestashop/modules/

# Clone the repository
git clone https://github.com/romelljaramillo/rj_multicarrier.git

# Or using composer
composer require romelljaramillo/rj_multicarrier
```

#### Manual Installation

```bash
# Upload the rj_multicarrier folder to your PrestaShop modules directory
/path/to/prestashop/modules/rj_multicarrier/

# Set proper permissions
chmod -R 755 rj_multicarrier/
chown -R www-data:www-data rj_multicarrier/
```

## Post-Installation

### 1. Verify Installation

After installation, verify:
- [ ] Module appears in Module Manager
- [ ] Configuration page loads correctly
- [ ] No errors in PrestaShop logs

### 2. Configure Carriers

1. Go to **Shipping > Carriers**
2. Ensure you have active carriers configured
3. Return to **Modules > Multi Carrier > Configure**
4. You should see your carriers listed

### 3. Test Frontend

1. Add a product to cart
2. Proceed to checkout
3. Verify carrier options are displayed
4. Test carrier selection

### 4. Test Backend

1. Go to an order detail page
2. Check for the "Multi Carrier" tab
3. Verify carrier information is displayed

## Troubleshooting

### Module doesn't appear

```bash
# Clear cache
rm -rf var/cache/*

# Regenerate class index
# Go to: Advanced Parameters > Performance > Clear cache
```

### Permission errors

```bash
# Fix file permissions
chmod -R 755 modules/rj_multicarrier/
chown -R www-data:www-data modules/rj_multicarrier/
```

### Database errors

Check MySQL error logs:
```bash
tail -f /var/log/mysql/error.log
```

Verify user permissions:
```sql
SHOW GRANTS FOR 'prestashop_user'@'localhost';
```

### Enable Debug Mode

1. In module configuration, enable "Debug mode"
2. Check logs at: **Advanced Parameters > Logs**
3. Look for entries starting with "RJ MultiCarrier:"

## Upgrading

### From previous versions

1. Backup your database
2. Backup the module folder
3. Upload the new version
4. Go to Module Manager
5. Click "Upgrade" on the module

## Uninstalling

**Warning**: This will remove all module data!

1. Go to **Modules > Module Manager**
2. Find "Multi Carrier"
3. Click "Uninstall"
4. Confirm the action

This will:
- Remove database tables
- Delete configuration values
- Unregister all hooks

## Need Help?

- Check the [README](README.md) for full documentation
- Open an issue on [GitHub](https://github.com/romelljaramillo/rj_multicarrier/issues)
- Contact: romell.jaramillo@gmail.com

## System Requirements

- PrestaShop 8.0.0+
- PHP 7.2.5+
- MySQL 5.6+
- Apache/Nginx web server
- mod_rewrite enabled

## Compatibility

✅ PrestaShop 8.0.x
✅ PrestaShop 8.1.x
✅ PHP 7.2, 7.3, 7.4, 8.0, 8.1, 8.2

## License

MIT License - See [LICENSE](LICENSE) file for details.
