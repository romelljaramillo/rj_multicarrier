# Changelog

All notable changes to the RJ Multi Carrier module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned Features
- Advanced carrier rules engine
- Carrier availability by country/zone
- Carrier availability by product/category
- Price-based carrier filtering
- Weight-based carrier filtering
- Customer group restrictions
- Carrier scheduling (time-based availability)
- Import/export carrier configurations
- Carrier analytics and statistics

## [1.0.0] - 2025-01-XX

### Added
- Initial release of RJ Multi Carrier module
- PrestaShop 8+ compatibility
- Multi-carrier management interface
- Priority ordering for carriers
- Database tables for carrier configurations and rules
- Configuration page with enable/disable toggle
- Debug mode for troubleshooting
- Frontend carrier list display with custom template
- Backend integration with order detail pages
- Admin tab showing carrier information
- CarrierHelper class with utility methods
- Hooks for frontend and backend integration:
  - `displayHeader`
  - `displayBackOfficeHeader`
  - `displayCarrierList`
  - `actionCarrierUpdate`
  - `displayAdminOrderTabContent`
  - `displayAdminOrderTabLink`
- Responsive CSS for frontend and backend
- JavaScript for carrier selection and admin actions
- English translations
- Comprehensive documentation (README, INSTALL, CONTRIBUTING)
- MIT License
- Composer support with PSR-4 autoloading
- Security index.php files in all directories
- Upgrade script structure
- Multi-shop support

### Technical Details
- PHP 7.2.5+ compatible
- MySQL database integration
- Smarty template engine support
- Bootstrap-compatible admin interface
- PSR-4 autoloading with namespace `RjMulticarrier`

### Documentation
- Installation guide
- Configuration guide
- API/Helper class documentation
- Troubleshooting section
- Contributing guidelines
- Code of conduct

## Version History

### Version Numbering

We use Semantic Versioning:
- **MAJOR** version for incompatible API changes
- **MINOR** version for new functionality in a backwards compatible manner
- **PATCH** version for backwards compatible bug fixes

### Support Policy

- Latest version: Full support
- Previous minor version: Security fixes only
- Older versions: No support

## Upgrade Notes

### From 0.x to 1.0.0
This is the initial stable release. No upgrade path from previous versions.

### Future Upgrades
When upgrading:
1. Always backup your database first
2. Backup the module folder
3. Read the changelog for breaking changes
4. Test in a staging environment
5. Follow upgrade instructions in INSTALL.md

## Links

- [Homepage](https://github.com/romelljaramillo/rj_multicarrier)
- [Documentation](README.md)
- [Installation Guide](INSTALL.md)
- [Contributing Guide](CONTRIBUTING.md)
- [Issue Tracker](https://github.com/romelljaramillo/rj_multicarrier/issues)
- [Releases](https://github.com/romelljaramillo/rj_multicarrier/releases)

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

**Note**: This changelog is maintained manually. For a complete list of changes, see the [commit history](https://github.com/romelljaramillo/rj_multicarrier/commits/).
