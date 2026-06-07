# 🚀 Release Manager

Interactive release workflow for Innerspace. This script automates versioning, changelog management, commits, and git tagging.

## Quick Start

```bash
python3 release.py
```

Or if you have it aliased:
```bash
./release.py
```

## What It Does

The release script walks you through a complete release workflow:

1. **Shows Git Status** - Displays current changes and recent commits
2. **Version Update** - Prompts for new version (semantic versioning: MAJOR.MINOR.PATCH)
3. **Changelog Entry** - Lets you document changes made in this release
4. **Commit Message** - Enter a custom commit message or use default
5. **Preview** - Shows what will be released before confirming
6. **Auto Updates**:
   - Updates `APP_VERSION` in `.env`
   - Updates `CHANGELOG.md` with new entry and timestamp
   - Creates git commit with both files
   - Creates git tag (e.g., `v1.2.3`)
7. **Push** - Optionally pushes commits and tags to remote

## Example Workflow

```
$ ./release.py

============================================================
🚀 INNERSPACE RELEASE MANAGER
============================================================

📊 Git Status:
============================================================
 M src/pages/dashboard/fronting.php
 M src/scss/_dashboard.scss

📝 Recent Commits:
============================================================
faf1d82 (HEAD -> main, origin/main) feat(dashboard): Add session history
8ad7040 feat(sitemap): add initial sitemap.xml for SEO

🔄 Current version: 1.0.0
Version format: MAJOR.MINOR.PATCH (e.g., 1.2.3)

Enter new version: 1.1.0

📋 Generating Changelog Entry
============================================================
Recent commits:
faf1d82 feat(dashboard): Add session history and fronting pages
8ad7040 feat(sitemap): add initial sitemap.xml for SEO

Enter changelog entry (multi-line, end with empty line):
- Added session history page with duration tracking
- Created fronting page for managing active sessions
- Improved dashboard styles and responsiveness

💬 Commit Message
============================================================
Enter commit message: feat(release): Add session management features

📋 Release Preview
============================================================
Version: 1.0.0 → 1.1.0
Commit: feat(release): Add session management features
Changelog:
- Added session history page with duration tracking
- Created fronting page for managing active sessions
- Improved dashboard styles and responsiveness

✅ Proceed with release? (y/n): y

✓ Updated version in .env to 1.1.0
✓ Updated CHANGELOG.md
✓ Staged .env and CHANGELOG.md
✓ Committed with message: feat(release): Add session management features
✓ Created tag: v1.1.0

📤 Pushing to Remote
============================================================
Push to remote? (y/n): y
✓ Pushed commits to origin/main
✓ Pushed tags to origin

============================================================
🎉 Release complete!
============================================================
```

## Features

✅ **Semantic Versioning** - Enforces MAJOR.MINOR.PATCH format  
✅ **Git Integration** - Automatic commits, tags, and optional push  
✅ **Changelog Management** - Maintains CHANGELOG.md with timestamps  
✅ **Interactive Prompts** - User-friendly questions at each step  
✅ **Preview Before Commit** - Review all changes before finalizing  
✅ **Recent Commits Display** - Shows what you've been working on  

## Files Modified

- `.env` - `APP_VERSION` updated to new version
- `CHANGELOG.md` - New entry created/updated with version, date, and changes
- Git commits and tags created

## Requirements

- Python 3.6+
- Git repository initialized
- `.env` file in project root

## Tips

- Use conventional commit messages (feat:, fix:, chore:, etc.)
- Be descriptive in changelog entries
- Review the preview before confirming
- Always push to keep remote synchronized
- Tags are created as annotated tags (include timestamps)

## Troubleshooting

**Command not found**
```bash
chmod +x release.py  # Make executable
python3 release.py   # Run with python3
```

**Version format error**
- Use format: `1.0.0` (not `v1.0.0` or `1.0`)
- Numbers only, separated by dots

**Git errors**
- Ensure working directory is clean or staged
- Ensure .git directory exists
- Check git configuration (user.name, user.email)

## Advanced Usage

To check current version:
```bash
grep APP_VERSION .env
```

To view changelog:
```bash
cat CHANGELOG.md
```

To view all tags:
```bash
git tag -l
```

To view release history:
```bash
git log --oneline --decorate
```
