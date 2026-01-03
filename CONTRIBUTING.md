# Contributing to Virtual Account Manager

Thank you for considering contributing to the Virtual Account Manager package!

## Development Setup

1. Clone the repository:
```bash
git clone https://github.com/payzephyr/laravel-virtual-account-manager.git
cd laravel-virtual-account-manager
```

2. Install dependencies:
```bash
composer install
```

3. Run tests:
```bash
composer test
```

## Code Style

We use Laravel Pint for code formatting:

```bash
composer format
```

## Testing

### Running Tests

```bash
# All tests
composer test

# With coverage
composer test-coverage

# Specific test suite
vendor/bin/phpunit tests/Unit
```

### Writing Tests

- Follow PSR-12 coding standards
- Write tests for all new features
- Maintain or improve code coverage
- Use descriptive test method names

## Pull Request Process

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Write/update tests
5. Ensure all tests pass
6. Run code style checks (`composer format`)
7. Commit your changes (`git commit -m 'Add amazing feature'`)
8. Push to the branch (`git push origin feature/amazing-feature`)
9. Open a Pull Request

## Adding a New Provider

1. Create driver class in `src/Drivers/`:
```php
class NewProviderDriver extends AbstractDriver
{
    protected string $name = 'newprovider';
    
    protected function validateConfig(): void
    {
        // Validate required config
    }
    
    protected function getDefaultHeaders(): array
    {
        // Return headers with auth
    }
    
    // Implement all interface methods
}
```

2. Add tests in `tests/Unit/`
3. Update documentation
4. Add to config example

## Reporting Issues

When reporting issues, please include:
- PHP version
- Laravel version
- Package version
- Steps to reproduce
- Expected vs actual behavior
- Error messages/logs

## Code of Conduct

- Be respectful and inclusive
- Welcome newcomers
- Focus on constructive feedback
- Follow the project's coding standards

## Questions?

Feel free to open an issue for questions or discussions.

