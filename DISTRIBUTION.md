# Distribution Guide

This document outlines the complete distribution and release process for the Ogini Laravel Scout Driver package.

## Table of Contents

- [Release Process](#release-process)
- [Version Management](#version-management)
- [Distribution Channels](#distribution-channels)
- [Packagist Submission](#packagist-submission)
- [GitHub Releases](#github-releases)
- [Update Management](#update-management)
- [Security Updates](#security-updates)
- [Documentation Maintenance](#documentation-maintenance)
- [Quality Assurance](#quality-assurance)

## Release Process

### Prerequisites

Before creating a release, ensure:

1. ✅ All tests pass (430/430 tests)
2. ✅ Code coverage meets thresholds (90%+)
3. ✅ Documentation is up to date
4. ✅ CHANGELOG.md is current
5. ✅ No security vulnerabilities
6. ✅ Compatibility tested with Laravel 8.x-11.x

### Automated Release

The package includes a comprehensive release automation script:

```bash
# Patch release (1.0.0 -> 1.0.1)
composer run release:patch

# Minor release (1.0.0 -> 1.1.0)
composer run release:minor

# Major release (1.0.0 -> 2.0.0)
composer run release:major

# Prerelease (1.0.0 -> 1.0.0-beta.1)
composer run release:prerelease
```

### Manual Release Steps

If you need to perform a manual release:

1. **Update Version Numbers:**
   ```bash
   # Update version in composer.json
   composer config version "1.1.0"
   
   # Update version constant in OginiServiceProvider
   sed -i 's/const VERSION = .*/const VERSION = "1.1.0";/' src/OginiServiceProvider.php
   ```

2. **Update Documentation:**
   ```bash
   # Update README badges
   sed -i 's/v[0-9]\+\.[0-9]\+\.[0-9]\+/v1.1.0/g' README.md
   ```

3. **Generate Changelog:**
   ```bash
   # Add new entry to CHANGELOG.md
   echo "## [1.1.0] - $(date +%Y-%m-%d)" >> CHANGELOG.md
   git log --oneline --no-merges v1.0.0..HEAD >> CHANGELOG.md
   ```

4. **Create Release:**
   ```bash
   git add .
   git commit -m "chore: release version 1.1.0"
   git tag -a v1.1.0 -m "Release version 1.1.0"
   git push origin main --tags
   ```

## Version Management

### Semantic Versioning

This package follows [Semantic Versioning (SemVer)](https://semver.org/):

- **MAJOR** version for incompatible API changes
- **MINOR** version for backward-compatible functionality additions
- **PATCH** version for backward-compatible bug fixes

### Version Tracking

Version information is maintained in multiple locations:

1. **composer.json** - Primary version field
2. **OginiServiceProvider::VERSION** - PHP constant for runtime access
3. **README.md** - Documentation badges
4. **CHANGELOG.md** - Release history

### Pre-release Versioning

For beta releases:
- Format: `1.0.0-beta.1`, `1.0.0-beta.2`, etc.
- Used for testing new features before stable release
- Not recommended for production use

## Distribution Channels

### Primary Channels

1. **Packagist (Primary)**
   - Official PHP package repository
   - Automatic installation via Composer
   - URL: https://packagist.org/packages/ogini-search/laravel-scout-driver

2. **GitHub Releases**
   - Source code distribution
   - Release notes and changelogs
   - URL: https://github.com/ogini-search/laravel-scout-driver/releases

### Installation Methods

#### Composer (Recommended)

```bash
composer require ogini-search/laravel-scout-driver
```

#### Development Installation

```bash
composer require ogini-search/laravel-scout-driver:dev-main
```

#### Specific Version

```bash
composer require ogini-search/laravel-scout-driver:^1.0
```

## Packagist Submission

### Initial Submission

1. **Create Packagist Account:**
   - Visit https://packagist.org/
   - Sign up with GitHub account
   - Verify email address

2. **Submit Package:**
   - Go to https://packagist.org/packages/submit
   - Enter repository URL: `https://github.com/ogini-search/laravel-scout-driver`
   - Click "Check" and then "Submit"

3. **Auto-Update Hook:**
   ```bash
   # Add GitHub webhook for automatic updates
   # URL: https://packagist.org/api/github?username=ogini-search
   # Events: Just the push event
   ```

### Package Maintenance

#### Update Package Information

```bash
# If auto-update fails, manually update:
curl -XPOST -H'content-type:application/json' \
  'https://packagist.org/api/update-package?username=ogini-search&apiToken=YOUR_API_TOKEN' \
  -d'{"repository":{"url":"https://github.com/ogini-search/laravel-scout-driver"}}'
```

#### Monitor Package Statistics

- Download statistics: https://packagist.org/packages/ogini-search/laravel-scout-driver/stats
- Dependents: https://packagist.org/packages/ogini-search/laravel-scout-driver/dependents

## GitHub Releases

### Automated Release Creation

The release script automatically creates GitHub releases with:

- Release notes from CHANGELOG.md
- Downloadable source code archives
- Version tags
- Pre-release flags for beta versions

### Manual Release Creation

If GitHub CLI is not available:

1. Go to https://github.com/ogini-search/laravel-scout-driver/releases/new
2. Choose the version tag (e.g., `v1.1.0`)
3. Set release title: "Release 1.1.0"
4. Copy release notes from CHANGELOG.md
5. Attach any additional files if needed
6. Publish release

### Release Notes Format

```markdown
## What's Changed

### New Features
- Feature 1 description
- Feature 2 description

### Bug Fixes
- Bug fix 1 description
- Bug fix 2 description

### Improvements
- Performance improvement 1
- Performance improvement 2

### Breaking Changes
- Breaking change description (for major versions)

## Upgrade Guide

Instructions for upgrading from previous version...

## Full Changelog
https://github.com/ogini-search/laravel-scout-driver/compare/v1.0.0...v1.1.0
```

## Update Management

### Update Checker Service

The package includes an automated update checking system:

```php
// Check for updates programmatically
$updateChecker = app(UpdateChecker::class);
$updates = $updateChecker->checkForUpdates();

if ($updates['has_update']) {
    // Handle update notification
}
```

### Command Line Interface

```bash
# Check for updates
php artisan ogini:check-updates

# Check for updates with verbose output
php artisan ogini:check-updates --verbose

# Force refresh update cache
php artisan ogini:check-updates --refresh
```

### Update Notifications

Configure automatic update notifications in `config/ogini.php`:

```php
'update_notifications' => [
    'enabled' => true,
    
    'email' => [
        'enabled' => false,
        'recipients' => ['admin@example.com'],
    ],
    
    'slack' => [
        'enabled' => false,
        'webhook_url' => env('OGINI_SLACK_WEBHOOK'),
    ],
    
    'log' => [
        'enabled' => true,
        'level' => 'info', // info, warning, error
    ],
],
```

## Security Updates

### Security Release Process

1. **Critical Security Issues:**
   - Immediate patch release
   - Security advisory on GitHub
   - Notification to all users

2. **Security Release Identification:**
   - Version bump with security flag
   - Clear security warning in release notes
   - Documentation of vulnerability and fix

3. **Communication:**
   - GitHub Security Advisory
   - Package changelog
   - Email notifications (if configured)
   - Social media announcements

### Security Update Detection

The update checker automatically detects security updates by:

- Analyzing release notes for security keywords
- Checking GitHub security advisories
- Flagging updates with high priority

### Response Procedures

When a security update is available:

1. **Immediate Assessment:**
   - Review severity and impact
   - Determine upgrade urgency
   - Check for breaking changes

2. **Testing Process:**
   - Test in development environment
   - Verify fix addresses security issue
   - Ensure no functionality regression

3. **Deployment:**
   - Schedule emergency deployment if critical
   - Follow normal deployment for low-severity issues
   - Monitor application after update

## Documentation Maintenance

### Documentation Sources

1. **README.md** - Primary package documentation
2. **CHANGELOG.md** - Version history and changes
3. **CONTRIBUTING.md** - Development guidelines
4. **docs/** - Detailed documentation
5. **API Documentation** - Generated from code comments

### Update Procedures

#### Version Release Documentation

1. Update README.md version badges
2. Add changelog entry
3. Update any version-specific documentation
4. Regenerate API documentation if needed

#### Continuous Documentation

- Keep examples current with latest version
- Update configuration options
- Maintain compatibility matrices
- Update troubleshooting guides

## Quality Assurance

### Pre-Release Quality Gates

All releases must pass:

1. **Full Test Suite:**
   ```bash
   composer test # 430/430 tests must pass
   ```

2. **Code Analysis:**
   ```bash
   composer analyse # No static analysis errors
   ```

3. **Security Scan:**
   ```bash
   composer audit # No known vulnerabilities
   ```

4. **Performance Benchmarks:**
   - Indexing performance within acceptable range
   - Search response times maintained
   - Memory usage within limits

### Release Validation

```bash
# Complete validation before release
composer run validate-release
```

This command runs:
- `composer validate` - Validates composer.json
- `composer test` - Runs full test suite
- `composer analyse` - Static code analysis

### Post-Release Monitoring

After each release:

1. **Package Installation Test:**
   ```bash
   # Test fresh installation
   composer create-project --prefer-dist laravel/laravel test-app
   cd test-app
   composer require ogini-search/laravel-scout-driver
   ```

2. **Integration Testing:**
   - Test with different Laravel versions
   - Verify example code works
   - Check documentation accuracy

3. **Community Feedback:**
   - Monitor GitHub issues
   - Review Packagist comments
   - Track download statistics

### Rollback Procedures

If a release has critical issues:

1. **Immediate Response:**
   ```bash
   # Tag a quick patch release
   git tag -a v1.1.1 -m "Hotfix for critical issue"
   git push origin v1.1.1
   ```

2. **Communication:**
   - GitHub issue explaining the problem
   - Updated documentation
   - Clear upgrade path for affected users

3. **Prevention:**
   - Add tests to prevent regression
   - Update quality gates
   - Review release process

---

## Automation Scripts

### Release Script Usage

```bash
# Show help
./scripts/release.sh --help

# Dry run (shows what would be done)
./scripts/release.sh --dry-run patch

# Full patch release
./scripts/release.sh patch

# Prerelease
./scripts/release.sh prerelease
```

### GitHub Actions (Future Enhancement)

Consider adding automated workflows for:

- Automated testing on pull requests
- Automatic release creation on version tags
- Security scanning
- Documentation generation
- Package validation

---

*Last Updated: $(date '+%Y-%m-%d')*
*Version: 1.0.0* 