# Contributing to OginiSearch Laravel Scout Driver

We're excited that you're interested in contributing to the OginiSearch Laravel Scout Driver! This document outlines how to get started with development and our contribution guidelines.

## Development Environment Setup

### Prerequisites

- PHP 8.2+
- Composer
- Docker and Docker Compose (optional, for local testing)

### Local Setup

1. Clone the repository:

```bash
git clone https://github.com/ogini-search/oginisearch-laravel-scout.git
cd oginisearch-laravel-scout
```

2. Install dependencies:

```bash
composer install
```

3. (Optional) Start the Docker environment for testing against OginiSearch:

```bash
docker-compose up -d
```

## Testing

We maintain comprehensive testing with enterprise-grade quality assurance:

### Test Categories

```bash
# Full test suite (430+ tests)
composer test

# Specific test categories
composer test -- --filter=Performance     # Performance tests
composer test -- --filter=Security        # Security tests
composer test -- --filter=EdgeCase        # Edge case tests
composer test -- --filter=UpdateChecker   # Update management tests

# Code coverage
composer test-coverage
```

### Quality Requirements

- **90%+ Code Coverage**: All new features must maintain high test coverage
- **Edge Case Testing**: Include boundary conditions and error scenarios
- **Security Testing**: Validate against common vulnerabilities
- **Laravel Compatibility**: Test across Laravel 8.x-11.x versions
- **PHPDoc Coverage**: All public methods must have documentation

## Coding Standards

We follow the PSR-12 coding standards. Please ensure your code follows these standards. We use PHP-CS-Fixer to maintain code style:

```bash
composer run format
```

## Pull Request Process

1. Fork the repository and create a branch from `main`
2. Make your changes
3. Add or update tests as necessary
4. Ensure all tests pass
5. Update documentation if needed
6. Submit a pull request

## Commit Message Guidelines

We follow conventional commits for commit messages:

- `feat:` A new feature
- `fix:` A bug fix
- `docs:` Documentation only changes
- `style:` Changes that do not affect the meaning of the code
- `refactor:` A code change that neither fixes a bug nor adds a feature
- `test:` Adding missing tests or correcting existing tests
- `chore:` Changes to the build process or auxiliary tools

## Code of Conduct

Please note that this project adheres to a [Code of Conduct](CODE_OF_CONDUCT.md).

## License

By contributing your code, you agree to license your contribution under the [MIT License](LICENSE.md). 