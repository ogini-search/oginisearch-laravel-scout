# Distribution Guide

This document outlines the distribution process for the Ogini Laravel Scout Driver package.

## Package Distribution Channels

### 1. Packagist (Primary)

Packagist is the main Composer repository for PHP packages.

#### Registration Steps

1. **Create Packagist Account**
   - Visit https://packagist.org/
   - Register with GitHub account (recommended)

2. **Submit Package**
   - Go to https://packagist.org/packages/submit
   - Enter GitHub repository URL: `https://github.com/[username]/laravel-scout-driver`
   - Click "Check" to validate
   - Submit the package

3. **Configure Auto-Update**
   - Navigate to package settings on Packagist
   - Enable "GitHub Service Hook" 
   - Or use manual webhook: `https://packagist.org/api/github?username=[username]`

#### Package Information

- **Package Name**: `ogini-search/laravel-scout-driver`
- **Description**: Laravel Scout driver for OginiSearch
- **Type**: `library`
- **License**: MIT
- **Homepage**: [Package website or GitHub repository]

### 2. GitHub Releases

Automated through GitHub Actions when tags are pushed.

#### Release Process

1. **Automatic Releases**
   ```bash
   # Tag and push to trigger release
   git tag v1.0.0
   git push origin v1.0.0
   ```

2. **Manual Release Script**
   ```bash
   # Use the release script
   ./scripts/release.sh 1.0.0
   git push origin v1.0.0
   ```

### 3. Private Composer Repository (Optional)

For enterprise distributions or pre-release versions.

#### Setup Private Repository

1. **Satis Setup** (Self-hosted)
   ```json
   {
     "name": "Ogini Private Repository",
     "homepage": "https://packages.ogini.com",
     "repositories": [
       {
         "type": "vcs",
         "url": "https://github.com/ogini-search/laravel-scout-driver"
       }
     ],
     "require-all": true
   }
   ```

2. **Composer Configuration**
   ```json
   {
     "repositories": [
       {
         "type": "composer",
         "url": "https://packages.ogini.com"
       }
     ]
   }
   ```

## Distribution Checklist

### Pre-Release Validation

- [ ] All tests passing
- [ ] Documentation up to date
- [ ] Version number updated in relevant files
- [ ] Changelog updated
- [ ] Breaking changes documented
- [ ] Security review completed
- [ ] License file present and correct

### Release Steps

1. **Prepare Release**
   - [ ] Run `./scripts/release.sh [version] --dry-run` to preview
   - [ ] Review generated changelog
   - [ ] Verify test suite passes
   - [ ] Check for security vulnerabilities

2. **Create Release**
   - [ ] Run `./scripts/release.sh [version]`
   - [ ] Push tag: `git push origin v[version]`
   - [ ] Verify GitHub release created
   - [ ] Confirm Packagist update

3. **Post-Release**
   - [ ] Test installation: `composer require ogini-search/laravel-scout-driver`
   - [ ] Verify package download from Packagist
   - [ ] Update documentation websites
   - [ ] Announce release (if applicable)

### Version Numbering

Follow [Semantic Versioning](https://semver.org/):

- **MAJOR** (X.0.0): Breaking changes
- **MINOR** (1.X.0): New features, backward compatible
- **PATCH** (1.0.X): Bug fixes, backward compatible

#### Pre-release Versions

- **Alpha**: `1.0.0-alpha.1` (internal testing)
- **Beta**: `1.0.0-beta.1` (external testing)
- **Release Candidate**: `1.0.0-rc.1` (final testing)

### Package Metadata

#### composer.json Requirements

```json
{
  "name": "ogini-search/laravel-scout-driver",
  "description": "Laravel Scout driver for OginiSearch",
  "type": "library",
  "license": "MIT",
  "keywords": ["laravel", "scout", "search", "ogini"],
  "homepage": "https://github.com/ogini-search/laravel-scout-driver",
  "support": {
    "issues": "https://github.com/ogini-search/laravel-scout-driver/issues",
    "docs": "https://github.com/ogini-search/laravel-scout-driver#readme",
    "source": "https://github.com/ogini-search/laravel-scout-driver"
  }
}
```

#### Required Files

- [ ] `composer.json` - Package definition
- [ ] `README.md` - Installation and usage instructions
- [ ] `LICENSE.md` - MIT license
- [ ] `CHANGELOG.md` - Version history
- [ ] `CONTRIBUTING.md` - Contribution guidelines
- [ ] `src/` - Source code directory
- [ ] `tests/` - Test suite

### Quality Gates

#### Automated Checks

- **CI/CD Pipeline**: All tests must pass
- **Code Coverage**: Minimum 90% coverage
- **Static Analysis**: PHPStan level 8
- **Security Scan**: No known vulnerabilities
- **Dependency Check**: All dependencies up to date

#### Manual Review

- **Documentation Review**: Accuracy and completeness
- **API Consistency**: Laravel conventions followed
- **Performance Check**: Benchmarks within acceptable limits
- **Security Review**: Input validation and authentication

## Troubleshooting Distribution Issues

### Common Issues

1. **Packagist Not Updating**
   - Check webhook configuration
   - Manually trigger update on Packagist
   - Verify tag format (must be `vX.Y.Z`)

2. **Composer Install Fails**
   - Check minimum stability requirements
   - Verify dependency constraints
   - Review autoload configuration

3. **GitHub Release Not Created**
   - Check GitHub Actions permissions
   - Verify `GITHUB_TOKEN` secret
   - Review workflow YAML syntax

### Support Channels

- **GitHub Issues**: Technical problems and bugs
- **Discussions**: Questions and feature requests
- **Email**: security@oginisearch.com (security issues only)

## Monitoring and Analytics

### Package Metrics

- **Download Statistics**: Monitor via Packagist
- **GitHub Stars/Forks**: Community interest
- **Issue Resolution Time**: Support quality
- **Version Adoption**: Upgrade patterns

### Monitoring Tools

- **Packagist**: Download statistics and version info
- **GitHub Insights**: Repository analytics
- **Composer Audit**: Security vulnerability tracking
- **Dependabot**: Automated dependency updates

---

*Last updated: 6th June, 2025*
*Version: 1.0.0* 