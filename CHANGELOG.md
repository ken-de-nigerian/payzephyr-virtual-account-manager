# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Comprehensive test suite (Unit, Feature, Integration tests)
- Complete documentation (Getting Started, API Reference, Architecture, Security)
- DriverFactory service following PayZephyr patterns
- HasNetworkErrorHandling trait for better error handling
- HasWebhookValidation trait for webhook security
- Health check caching for performance
- HttpStatusCodes constants class
- Improved logging with sanitization
- Health check endpoint (`/virtual-accounts/health`)
- Convention over configuration for driver resolution

### Changed
- Migrated from Laravel HTTP to Guzzle client (matching PayZephyr)
- Updated AbstractDriver to use Guzzle and traits
- Enhanced VirtualAccountManager to use DriverFactory
- Improved ServiceProvider with proper service bindings
- Updated FlutterwaveDriver to use new request methods
- Enhanced error handling with network error context

### Fixed
- Improved error messages for network failures
- Better webhook signature verification
- Enhanced idempotency handling

## [1.0.0] - 2024-01-01

### Added
- Initial release
- Flutterwave driver implementation
- Moniepoint driver stub
- Providus driver stub
- Virtual account creation
- Webhook processing
- Deposit detection
- Reconciliation service
- Database migrations
- Event system (VirtualAccountCreated, DepositConfirmed)
- Fluent API builder
- Facade support

