# Contributing to RJ Multi Carrier

Thank you for your interest in contributing to RJ Multi Carrier! This document provides guidelines for contributing to the project.

## How to Contribute

### Reporting Bugs

If you find a bug, please create an issue on GitHub with:

- **Title**: Clear, descriptive title
- **Description**: Detailed description of the bug
- **Steps to reproduce**: Step-by-step instructions
- **Expected behavior**: What should happen
- **Actual behavior**: What actually happens
- **Environment**: 
  - PrestaShop version
  - PHP version
  - Module version
  - Browser (if frontend issue)
- **Screenshots**: If applicable
- **Error logs**: Any relevant error messages

### Suggesting Features

To suggest a new feature:

1. Check existing issues to avoid duplicates
2. Create a new issue with tag `enhancement`
3. Describe the feature and its benefits
4. Provide use cases and examples

### Pull Requests

#### Before You Start

1. Fork the repository
2. Create a new branch from `master`
3. Name your branch descriptively (e.g., `feature/carrier-priority`, `fix/database-error`)

#### Development Process

1. **Clone your fork**
   ```bash
   git clone https://github.com/YOUR-USERNAME/rj_multicarrier.git
   cd rj_multicarrier
   ```

2. **Create a branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

3. **Make your changes**
   - Follow the coding standards (see below)
   - Write clear, commented code
   - Test your changes thoroughly

4. **Commit your changes**
   ```bash
   git add .
   git commit -m "Add feature: your feature description"
   ```

5. **Push to your fork**
   ```bash
   git push origin feature/your-feature-name
   ```

6. **Create a Pull Request**
   - Go to the original repository
   - Click "New Pull Request"
   - Select your branch
   - Fill in the PR template

#### Pull Request Guidelines

- **One feature per PR**: Keep PRs focused on a single feature or fix
- **Clear description**: Explain what and why
- **Reference issues**: Link to related issues
- **Update documentation**: If you change functionality
- **Add tests**: If applicable
- **Follow code style**: Match existing code style

## Coding Standards

### PHP

Follow PrestaShop coding standards:

```php
<?php
/**
 * File description
 *
 * @author    Your Name
 * @copyright Year Your Name
 * @license   MIT License
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MyClass
{
    /**
     * Method description
     *
     * @param type $param Description
     * @return type Description
     */
    public function myMethod($param)
    {
        // Implementation
    }
}
```

### JavaScript

```javascript
/**
 * Function description
 */
function myFunction() {
    'use strict';
    // Implementation
}
```

### CSS

```css
/**
 * Component description
 */
.my-class {
    property: value;
}
```

### Smarty Templates

```smarty
{*
* Template description
*
* @author    Your Name
* @copyright Year Your Name
* @license   MIT License
*}

<div class="my-component">
    {* Content *}
</div>
```

## Code Style

- Use 4 spaces for indentation (no tabs)
- Use meaningful variable and function names
- Add comments for complex logic
- Keep functions small and focused
- Follow PSR-12 standards for PHP
- Use camelCase for JavaScript
- Use kebab-case for CSS classes

## Testing

Before submitting:

1. **Test installation/uninstallation**
   - Install the module fresh
   - Uninstall and reinstall
   - Check for errors

2. **Test functionality**
   - Test all features you modified
   - Test on different browsers (if frontend)
   - Test on different PHP versions

3. **Check for errors**
   - Enable debug mode
   - Check PrestaShop error logs
   - Check browser console (if frontend)

4. **Validate PHP syntax**
   ```bash
   php -l your-file.php
   ```

## Documentation

Update documentation when you:

- Add new features
- Change existing functionality
- Add new configuration options
- Change installation process

Files that may need updates:
- README.md
- INSTALL.md
- Code comments
- Template comments

## Commit Messages

Write clear commit messages:

### Good Examples
```
Add priority ordering feature
Fix database query error in carrier list
Update README with new configuration options
```

### Format
```
<type>: <subject>

<body>

<footer>
```

Types:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation
- `style`: Formatting
- `refactor`: Code restructuring
- `test`: Tests
- `chore`: Maintenance

## Getting Help

- **Documentation**: Check README.md and INSTALL.md
- **Issues**: Search existing issues
- **Email**: romell.jaramillo@gmail.com

## Code of Conduct

- Be respectful and inclusive
- Welcome newcomers
- Accept constructive criticism
- Focus on what's best for the community

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

## Recognition

Contributors will be:
- Listed in the CONTRIBUTORS file
- Mentioned in release notes
- Credited in commit messages

Thank you for contributing to RJ Multi Carrier! 🚀
