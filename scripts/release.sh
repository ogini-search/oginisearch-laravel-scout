#!/bin/bash
# When you're ready for your first official release
#./scripts/release.sh major

# Release Automation Script for Ogini Laravel Scout Driver
# Usage: ./scripts/release.sh [version_type]
# version_type: patch|minor|major|prerelease|prepatch|preminor|premajor

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PACKAGE_NAME="ogini/oginisearch-laravel-scout"
BRANCH="main"
REMOTE="origin"

# Functions
log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check if we're in the correct directory
check_directory() {
    if [[ ! -f "composer.json" || ! -f "src/OginiServiceProvider.php" ]]; then
        log_error "This script must be run from the root of the Laravel Scout Driver project"
        exit 1
    fi
}

# Check if working directory is clean
check_git_status() {
    log_info "Checking git status..."
    
    if [[ -n $(git status --porcelain) ]]; then
        log_error "Working directory is not clean. Please commit or stash your changes."
        git status --short
        exit 1
    fi
    
    log_success "Working directory is clean"
}

# Check if we're on the correct branch
check_branch() {
    local current_branch=$(git rev-parse --abbrev-ref HEAD)
    
    if [[ "$current_branch" != "$BRANCH" ]]; then
        log_warning "You're on branch '$current_branch', not '$BRANCH'"
        read -p "Do you want to continue? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            log_info "Aborting release"
            exit 0
        fi
    fi
}

# Pull latest changes
pull_latest() {
    log_info "Pulling latest changes from $REMOTE/$BRANCH..."
    git pull $REMOTE $BRANCH
    log_success "Repository is up to date"
}

# Run tests
run_tests() {
    log_info "Running test suite..."
    
    if ! composer test; then
        log_error "Tests failed. Please fix all tests before releasing."
        exit 1
    fi
    
    log_success "All tests passed"
}

# Get current version from composer.json
get_current_version() {
    php -r "
        \$composer = json_decode(file_get_contents('composer.json'), true);
        echo \$composer['version'] ?? '0.0.0';
    "
}

# Calculate new version
calculate_new_version() {
    local version_type=$1
    local current_version=$(get_current_version)
    
    log_info "Current version: $current_version"
    
    # Parse version components
    IFS='.' read -ra VERSION_PARTS <<< "$current_version"
    local major=${VERSION_PARTS[0]}
    local minor=${VERSION_PARTS[1]}
    local patch=${VERSION_PARTS[2]}
    
    case $version_type in
        "patch")
            patch=$((patch + 1))
            ;;
        "minor")
            minor=$((minor + 1))
            patch=0
            ;;
        "major")
            major=$((major + 1))
            minor=0
            patch=0
            ;;
        "prerelease")
            if [[ $current_version == *"-"* ]]; then
                # Increment prerelease number
                local pre_version=${current_version##*-}
                local base_version=${current_version%-*}
                local pre_num=${pre_version##*.}
                local pre_base=${pre_version%.*}
                echo "${base_version}-${pre_base}.$((pre_num + 1))"
                return
            else
                echo "${current_version}-beta.1"
                return
            fi
            ;;
        *)
            log_error "Invalid version type: $version_type"
            log_info "Valid types: patch, minor, major, prerelease"
            exit 1
            ;;
    esac
    
    echo "$major.$minor.$patch"
}

# Update version in files
update_version_files() {
    local new_version=$1
    
    log_info "Updating version to $new_version..."
    
    # Update composer.json
    php -r "
        \$composer = json_decode(file_get_contents('composer.json'), true);
        \$composer['version'] = '$new_version';
        file_put_contents('composer.json', json_encode(\$composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    "
    
    # Update version constant in service provider
    sed -i.bak "s/const VERSION = '[^']*'/const VERSION = '$new_version'/" src/OginiServiceProvider.php
    rm -f src/OginiServiceProvider.php.bak
    
    # Update README.md version badges
    sed -i.bak "s/v[0-9]\+\.[0-9]\+\.[0-9]\+/v$new_version/g" README.md
    rm -f README.md.bak
    
    log_success "Version updated in all files"
}

# Generate changelog entry
generate_changelog_entry() {
    local version=$1
    local date=$(date '+%Y-%m-%d')
    
    log_info "Generating changelog entry for version $version..."
    
    # Get commits since last tag
    local last_tag=$(git describe --tags --abbrev=0 2>/dev/null || echo "")
    local commits
    
    if [[ -n "$last_tag" ]]; then
        commits=$(git log --oneline --no-merges ${last_tag}..HEAD)
    else
        commits=$(git log --oneline --no-merges)
    fi
    
    # Create changelog entry
    local changelog_entry="## [$version] - $date"$'\n\n'
    
    if [[ -n "$commits" ]]; then
        changelog_entry+="### Changes"$'\n'
        while IFS= read -r commit; do
            local commit_msg=$(echo "$commit" | cut -d' ' -f2-)
            changelog_entry+="- $commit_msg"$'\n'
        done <<< "$commits"
    else
        changelog_entry+="- No changes since last release"$'\n'
    fi
    
    changelog_entry+=$'\n'
    
    # Prepend to CHANGELOG.md
    if [[ -f "CHANGELOG.md" ]]; then
        # Read existing changelog
        local existing_changelog=$(cat CHANGELOG.md)
        
        # Write new entry followed by existing content
        {
            echo "# Changelog"
            echo ""
            echo "All notable changes to this project will be documented in this file."
            echo ""
            echo "$changelog_entry"
            # Skip the header of existing changelog
            echo "$existing_changelog" | sed '1,/^## \[/d' | sed '1i\
## ['
        } > CHANGELOG.md.tmp
        mv CHANGELOG.md.tmp CHANGELOG.md
    else
        # Create new changelog
        {
            echo "# Changelog"
            echo ""
            echo "All notable changes to this project will be documented in this file."
            echo ""
            echo "$changelog_entry"
        } > CHANGELOG.md
    fi
    
    log_success "Changelog updated"
}

# Create release notes
create_release_notes() {
    local version=$1
    
    log_info "Creating release notes for version $version..."
    
    # Extract the latest changelog entry
    local release_notes_file="release-notes-$version.md"
    
    if [[ -f "CHANGELOG.md" ]]; then
        # Extract just the current version's notes
        awk "/^## \[$version\]/{flag=1; next} /^## \[/{flag=0} flag" CHANGELOG.md > "$release_notes_file"
    else
        echo "Release $version" > "$release_notes_file"
        echo "" >> "$release_notes_file"
        echo "See CHANGELOG.md for details." >> "$release_notes_file"
    fi
    
    log_success "Release notes created: $release_notes_file"
}

# Commit and tag
commit_and_tag() {
    local version=$1
    
    log_info "Committing changes and creating tag..."
    
    # Add changed files
    git add composer.json src/OginiServiceProvider.php README.md CHANGELOG.md
    
    # Commit
    git commit -m "chore: release version $version

- Update version number to $version
- Update changelog
- Update documentation"
    
    # Create tag
    git tag -a "v$version" -m "Release version $version"
    
    log_success "Version committed and tagged"
}

# Push changes
push_changes() {
    local version=$1
    
    log_info "Pushing changes to remote repository..."
    
    # Push commits
    git push $REMOTE HEAD
    
    # Push tags
    git push $REMOTE "v$version"
    
    log_success "Changes pushed to remote repository"
}

# Create GitHub release
create_github_release() {
    local version=$1
    local release_notes_file="release-notes-$version.md"
    
    log_info "Creating GitHub release..."
    
    if command -v gh &> /dev/null; then
        if [[ -f "$release_notes_file" ]]; then
            gh release create "v$version" \
                --title "Release $version" \
                --notes-file "$release_notes_file" \
                --latest
        else
            gh release create "v$version" \
                --title "Release $version" \
                --notes "Release $version" \
                --latest
        fi
        
        log_success "GitHub release created"
    else
        log_warning "GitHub CLI not found. Please create the release manually at:"
        log_warning "https://github.com/ogini-search/oginisearch-laravel-scout/releases/new?tag=v$version"
    fi
}

# Cleanup
cleanup() {
    local version=$1
    
    # Remove temporary release notes file
    rm -f "release-notes-$version.md"
    
    log_success "Cleanup completed"
}

# Main release function
main() {
    local version_type=${1:-"patch"}
    
    echo "🚀 Starting release process for $PACKAGE_NAME"
    echo "================================================"
    
    # Pre-release checks
    check_directory
    check_git_status
    check_branch
    pull_latest
    run_tests
    
    # Calculate new version
    local new_version=$(calculate_new_version "$version_type")
    
    log_info "Preparing to release version: $new_version"
    read -p "Continue with release $new_version? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_info "Release cancelled"
        exit 0
    fi
    
    # Release process
    update_version_files "$new_version"
    generate_changelog_entry "$new_version"
    create_release_notes "$new_version"
    commit_and_tag "$new_version"
    push_changes "$new_version"
    create_github_release "$new_version"
    cleanup "$new_version"
    
    echo ""
    echo "🎉 Release $new_version completed successfully!"
    echo "================================================"
    log_success "Package released: $PACKAGE_NAME v$new_version"
    log_info "Next steps:"
    echo "  1. Submit to Packagist (if not auto-submitted)"
    echo "  2. Update documentation"
    echo "  3. Announce the release"
}

# Help function
show_help() {
    cat << EOF
Ogini Laravel Scout Driver Release Script

Usage: $0 [version_type]

Version Types:
  patch      Increment patch version (0.0.x)
  minor      Increment minor version (0.x.0)
  major      Increment major version (x.0.0)
  prerelease Increment prerelease version (0.0.0-beta.x)

Examples:
  $0 patch      # 1.0.0 -> 1.0.1
  $0 minor      # 1.0.0 -> 1.1.0
  $0 major      # 1.0.0 -> 2.0.0
  $0 prerelease # 1.0.0 -> 1.0.0-beta.1

Requirements:
  - Clean git working directory
  - All tests passing
  - Internet connection for pushing to GitHub

Optional:
  - GitHub CLI (gh) for automatic release creation
EOF
}

# Script entry point
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    case ${1:-} in
        -h|--help)
            show_help
            exit 0
            ;;
        *)
            main "$@"
            ;;
    esac
fi 