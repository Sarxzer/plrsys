#!/usr/bin/env python3
"""
Interactive release script for plrsys.
Manages versioning, changelog, commits, tagging, and deployment to Skynet.
"""

import os
import re
import shlex
import shutil
import subprocess
import sys
from datetime import datetime
from pathlib import Path

def load_env_file(path):
    """Load simple KEY=VALUE entries without requiring a third-party package."""
    if not path.exists():
        return

    for line in path.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue

        match = re.match(r"(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)", line)
        if not match:
            continue

        key, value = match.groups()
        value = value.strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in "'\"":
            value = value[1:-1]
        else:
            value = value.split(" #", 1)[0].rstrip()
        os.environ.setdefault(key, value)

# ------------------------------------------------------------------------------------------
#  Config - edit these to match your setup
# ------------------------------------------------------------------------------------------

ROOT_DIR       = Path(__file__).resolve().parent.parent  # Repo root (where VERSION and CHANGELOG live)
ENV_FILE       = ROOT_DIR / ".env"

load_env_file(ENV_FILE)

SKYNET_USER     = os.getenv("RELEASE_SERVER_USER")        # SSH user on Skynet
SKYNET_HOST     = os.getenv("RELEASE_SERVER_HOST")        # SSH host or alias (~/.ssh/config)
REMOTE_PATH     = os.getenv("RELEASE_SERVER_PATH")        # plrsys root on Skynet
GIT_BRANCH      = os.getenv("RELEASE_GIT_BRANCH")         # Git branch to release from
PHP_FPM_SERVICE = os.getenv("RELEASE_PHP_FPM_SERVICE")    # PHP FPM service name

# Folders (relative to REMOTE_PATH) the web server must be able to write to.
WRITABLE_DIRS   = ["uploads/pfps"]

# Pre-release checklist items shown before every release.
# Confirm each one manually - the script won't proceed past skipped items without asking.
CHECKLIST = [
    "Privacy Policy is up to date and matches what the database actually stores",
    "Terms of Service is up to date",
    "Account deletion flow works correctly",
    "Cookie disclosure is present",
    "CHANGELOG reflects all user-facing changes",
    "Schema changes were applied on Skynet (database.sql is in sync)",
    "CSRF tokens present on all POST forms",
    "No hardcoded secrets or API keys in code",
]

# Patterns that should never appear in tracked files (checked automatically).
SECRET_PATTERN = r"discord(app)?\.com/api/webhooks/[0-9]+|BEGIN (RSA |OPENSSH |EC )?PRIVATE KEY"
VERSION_PATTERN = re.compile(
    r"^v?(?P<major>\d+)\.(?P<minor>\d+)\.(?P<patch>\d+)"
    r"(?:-(?P<phase>alpha|beta)(?:\.(?P<prerelease>\d+))?)?$"
)


# ------------------------------------------------------------------------------------------
#  Helpers
# ------------------------------------------------------------------------------------------

RESET  = "\033[0m"
BOLD   = "\033[1m"
DIM    = "\033[2m"
RED    = "\033[91m"
GREEN  = "\033[92m"
YELLOW = "\033[93m"
CYAN   = "\033[96m"
WHITE  = "\033[97m"

def c(color, text): return f"{color}{text}{RESET}"
def ok(msg):   print(c(GREEN,  f"  + {msg}"))
def err(msg):  print(c(RED,    f"  x {msg}"))
def info(msg): print(c(CYAN,   f"  > {msg}"))
def warn(msg): print(c(YELLOW, f"  ! {msg}"))
def div():     print(c(DIM, "  " + "-" * 56))

def confirm(prompt, default="y"):
    hint = "[Y/n]" if default == "y" else "[y/N]"
    answer = input(f"\n  {prompt} {hint}: ").strip().lower()
    if not answer:
        return default == "y"
    return answer == "y"

def run(cmd, check=True):
    """Run a shell command and return stripped stdout. Exits on failure if check=True."""
    result = subprocess.run(cmd, shell=True, capture_output=True, text=True)
    if check and result.returncode != 0:
        err(f"Command failed: {cmd}")
        if result.stderr:
            print(c(RED, f"    {result.stderr.strip()}"))
        sys.exit(1)
    return result.stdout.strip()


# ------------------------------------------------------------------------------------------
#  ReleaseManager
# ------------------------------------------------------------------------------------------

class ReleaseManager:

    def __init__(self, project_root="."):
        self.root          = Path(project_root)
        self.version_file  = self.root / "VERSION"
        self.changelog     = self.root / "CHANGELOG.md"
        self.current       = self._read_version()
        self.new_version   = None
        self.changelog_edited = False

    # ---- Version ------------------------------------------------------------

    def _read_version(self):
        if self.version_file.exists():
            version = self.version_file.read_text().strip()
            match = VERSION_PATTERN.fullmatch(version)
            if match:
                return self._format_version(match)
        return "0.1.0"

    def _write_version(self, version):
        self.version_file.write_text(version + "\n")

    def _version_parts(self, version=None):
        match = VERSION_PATTERN.fullmatch(version or self.current)
        if not match:
            raise ValueError(f"Invalid version: {version or self.current}")
        return match

    def _format_version(self, match, phase=None, prerelease=None):
        version = f"{match.group('major')}.{match.group('minor')}.{match.group('patch')}"
        phase = phase if phase is not None else match.group("phase")
        prerelease = prerelease if prerelease is not None else match.group("prerelease")
        if phase:
            version += f"-{phase}"
            if prerelease:
                version += f".{prerelease}"
        return version

    def _bump(self, part):
        match = self._version_parts()
        major = int(match.group("major"))
        minor = int(match.group("minor"))
        patch = int(match.group("patch"))
        if part == "major": return f"{major + 1}.0.0"
        if part == "minor": return f"{major}.{minor + 1}.0"
        if part == "patch": return f"{major}.{minor}.{patch + 1}"
        if part == "stable": return f"{major}.{minor}.{patch}"
        if part in ("alpha", "beta"):
            current_prerelease = int(match.group("prerelease") or 0)
            next_prerelease = current_prerelease + 1 if match.group("phase") == part else 1
            return self._format_version(match, phase=part, prerelease=str(next_prerelease))

    def ask_version(self):
        print(f"\n  Current version: {c(BOLD, self.current)}")
        div()
        print(f"  {c(CYAN, '1')}  Patch  -> {self._bump('patch')}   (bug fixes)")
        print(f"  {c(CYAN, '2')}  Minor  -> {self._bump('minor')}   (new features, backwards compat)")
        print(f"  {c(CYAN, '3')}  Major  -> {self._bump('major')}   (breaking changes)")
        print(f"  {c(CYAN, '4')}  Alpha  -> {self._bump('alpha')}   (unstable prerelease)")
        print(f"  {c(CYAN, '5')}  Beta   -> {self._bump('beta')}    (feature-complete prerelease)")
        print(f"  {c(CYAN, '6')}  Stable -> {self._bump('stable')}  (remove prerelease label)")
        print(f"  {c(CYAN, '7')}  Custom")
        div()
        while True:
            choice = input("  Pick [1-7]: ").strip()
            if choice == "1": return self._bump("patch")
            if choice == "2": return self._bump("minor")
            if choice == "3": return self._bump("major")
            if choice == "4": return self._bump("alpha")
            if choice == "5": return self._bump("beta")
            if choice == "6": return self._bump("stable")
            if choice == "7":
                while True:
                    version = input("  Enter version (X.Y.Z[-alpha[.N]|-beta[.N]]): ").strip()
                    match = VERSION_PATTERN.fullmatch(version)
                    if match:
                        return self._format_version(match)
                    err("Must be X.Y.Z, optionally followed by -alpha, -beta, or a numbered prerelease")
            err("Pick 1-7")

    # ---- Git ----------------------------------------------------------------

    def show_status(self):
        print(f"\n  {c(BOLD, 'Git status')}")
        div()
        status = run("git status --short")
        if status:
            for line in status.splitlines():
                print(f"    {line}")
        else:
            ok("Working tree clean")

        print(f"\n  {c(BOLD, 'Recent commits')}")
        div()
        commits = run("git log --oneline -8", check=False)
        if commits:
            for line in commits.splitlines():
                print(f"    {c(DIM, line)}")

    def preflight(self):
        """Refuse to release from the wrong branch, and warn about unreleased work or secrets."""
        print(f"\n  {c(BOLD, 'Preflight checks')}")
        div()

        branch = run("git rev-parse --abbrev-ref HEAD")
        if branch != GIT_BRANCH:
            err(f"You are on '{branch}', but releases must come from '{GIT_BRANCH}'")
            sys.exit(1)
        ok(f"On branch {GIT_BRANCH}")

        changelog_status = run("git status --porcelain -- CHANGELOG.md")
        other_status = run("git status --porcelain -- . ':(exclude)CHANGELOG.md'")
        if other_status:
            warn("You have uncommitted changes outside CHANGELOG.md. They will NOT be part of this release.")
            if not confirm("Continue anyway?", default="n"):
                err("Release cancelled")
                sys.exit(1)
        elif changelog_status:
            ok("Pre-written CHANGELOG.md changes will be included in this release")
        else:
            ok("Working tree clean")

        # Secret scan over tracked files (git grep only looks at tracked files).
        hits = run(f"git grep -nIE {shlex.quote(SECRET_PATTERN)}", check=False)
        if hits:
            err("Possible secrets found in tracked files:")
            for line in hits.splitlines()[:10]:
                print(c(RED, f"    {line}"))
            if not confirm("Continue despite possible secrets?", default="n"):
                err("Release cancelled")
                sys.exit(1)
        else:
            ok("No obvious secrets in tracked files")

    def get_commits_since_last_tag(self):
        tag = run("git describe --tags --abbrev=0", check=False)
        if tag:
            return run(f"git log {shlex.quote(tag)}..HEAD --pretty=format:'%h %s'", check=False)
        return run("git log --pretty=format:'%h %s' -20", check=False)

    def tag_exists(self, version):
        return bool(run(f"git tag --list {shlex.quote('v' + version)}", check=False))

    # ---- Checklist ----------------------------------------------------------

    def run_checklist(self):
        print(f"\n  {c(BOLD + YELLOW, 'Pre-release checklist')}")
        div()
        warn("Go through each item. Press Enter to confirm, or type a note to skip.")
        print()

        skipped = []
        for i, item in enumerate(CHECKLIST, 1):
            answer = input(f"  [{i}/{len(CHECKLIST)}] {item}\n        > ").strip()
            if answer == "":
                ok("Confirmed")
            else:
                warn(f"Skipped: {answer}")
                skipped.append((item, answer))
            print()

        if skipped:
            print(c(YELLOW, f"  {len(skipped)} item(s) skipped:"))
            for item, note in skipped:
                print(c(DIM, f"    - {item}: {note}"))
            if not confirm("Continue despite skipped items?", default="n"):
                err("Release cancelled")
                sys.exit(1)
        else:
            ok("All checklist items confirmed")

    # ---- Changelog ----------------------------------------------------------

    def build_changelog_entry(self):
        print(f"\n  {c(BOLD, 'Changelog entry')}")
        div()
        commits = self.get_commits_since_last_tag()
        if commits:
            info("Commits since last tag:")
            for line in commits.splitlines():
                print(c(DIM, f"    {line}"))
        else:
            warn("No commits found since last tag")

        print()
        print("  Opening CHANGELOG.md in your text editor.")
        print(c(DIM, "  Add or update the release section, then save and close the editor."))
        print()

        editor = os.environ.get("VISUAL") or os.environ.get("EDITOR") or "nano"
        editor_command = shlex.split(editor)
        if not editor_command:
            editor_command = ["nano"]
        if Path(editor_command[0]).name in ("code", "code-insiders", "codium"):
            editor_command.append("--wait")
        editor_command.append(str(self.changelog))

        result = subprocess.run(editor_command)
        if result.returncode != 0:
            err(f"Text editor exited with status {result.returncode}")
            sys.exit(1)

        changes = run("git diff HEAD -- CHANGELOG.md", check=False)
        if changes:
            self.changelog_edited = True
            print(f"\n  {c(BOLD, 'CHANGELOG.md changes to be committed')}")
            div()
            print(changes)
        else:
            warn("No changes were made to CHANGELOG.md")

        return None

    def write_changelog(self, version, entry):
        date   = datetime.now().strftime("%Y-%m-%d")
        header = f"## [v{version}] - {date}\n\n"
        body   = (entry + "\n\n") if entry else "- No changes documented.\n\n"

        default_top = "# Changelog\n\nAll notable changes to plrsys are documented here.\n\n"

        if self.changelog.exists():
            existing = self.changelog.read_text()
            idx = existing.find("\n## ")
            if idx == -1:
                # Title/intro only, no releases yet
                top, rest = existing.rstrip() + "\n\n", ""
            else:
                # Keep the title and intro on top, newest release goes right after
                top, rest = existing[: idx + 1], existing[idx + 1:]
        else:
            top, rest = default_top, ""

        self.changelog.write_text(top + header + body + rest)
        ok("Wrote CHANGELOG.md")

    # ---- Commit + tag -------------------------------------------------------

    def commit_and_tag(self, message, version):
        print(f"\n  {c(BOLD, 'Committing')}")
        div()

        files = [f for f in ("VERSION", "CHANGELOG.md") if (self.root / f).exists()]
        run("git add " + " ".join(files))
        ok("Staged " + " and ".join(files))

        run(f"git commit -m {shlex.quote(message)}")
        ok(f"Committed: {message}")

        tag = f"v{version}"
        run(f"git tag -a {shlex.quote(tag)} -m {shlex.quote('Release ' + tag)}")
        ok(f"Tagged {tag}")

    def push(self, version):
        print(f"\n  {c(BOLD, 'Push to remote')}")
        div()
        if not confirm("Push the commit and the new tag to origin?"):
            warn("Skipped push")
            return False
        run(f"git push origin {shlex.quote(GIT_BRANCH)}")
        ok(f"Pushed {GIT_BRANCH}")
        run(f"git push origin {shlex.quote('v' + version)}")
        ok(f"Pushed tag v{version}")
        return True

    def github_release(self, version, entry):
        """Optional: publish a GitHub Release using the GitHub CLI, if installed."""
        if not shutil.which("gh"):
            return
        if not confirm("Create a GitHub release from this changelog entry?", default="n"):
            return
        notes = entry or "No changes documented."
        result = subprocess.run(
            ["gh", "release", "create", f"v{version}",
             "--title", f"v{version}", "--notes", notes],
            capture_output=True, text=True,
        )
        if result.returncode == 0:
            ok("GitHub release created")
        else:
            warn("GitHub release failed (the tag and push are fine)")
            if result.stderr.strip():
                print(c(DIM, f"    {result.stderr.strip()}"))

    # ---- Deploy -------------------------------------------------------------

    def _ssh(self, remote_cmd):
        return subprocess.run(
            ["ssh", f"{SKYNET_USER}@{SKYNET_HOST}", remote_cmd],
            capture_output=True, text=True,
        )

    def deploy(self):
        print(f"\n  {c(BOLD, 'Deploy to Skynet')}")
        div()
        info(f"Target: {SKYNET_USER}@{SKYNET_HOST}:{REMOTE_PATH}")

        if not confirm("Deploy now?"):
            warn("Skipped deployment")
            return

        # Safety check: debug mode must be off on production.
        env_check = self._ssh(f"grep -E '^APP_DEBUG=' {shlex.quote(REMOTE_PATH)}/.env")
        debug_line = env_check.stdout.strip()
        if "true" in debug_line.lower():
            err(f"Remote .env has {debug_line} - turn debug off before deploying")
            sys.exit(1)
        elif debug_line:
            ok(f"Remote {debug_line}")
        else:
            warn("Could not read APP_DEBUG from the remote .env")
            if not confirm("Continue anyway?", default="n"):
                err("Deployment aborted")
                sys.exit(1)

        rp = shlex.quote(REMOTE_PATH)
        steps = [
            ("git pull",         f"cd {rp} && git pull --ff-only origin {shlex.quote(GIT_BRANCH)}"),
            ("composer install", f"cd {rp} && composer install --no-dev --optimize-autoloader"),
        ]
        for d in WRITABLE_DIRS:
            path = shlex.quote(f"{REMOTE_PATH}/{d}")
            steps.append((f"writable: {d}",
                          f"mkdir -p {path} && sudo -n chown -R www-data:www-data {path}"))
        steps += [
            ("reload php-fpm", f"sudo -n systemctl reload {PHP_FPM_SERVICE}"),
            ("reload nginx",   "sudo -n systemctl reload nginx"),
        ]

        for label, remote_cmd in steps:
            info(f"Running: {label}")
            result = self._ssh(remote_cmd)
            if result.returncode == 0:
                ok(label)
            else:
                warn(f"{label} exited {result.returncode}")
                if result.stderr.strip():
                    print(c(DIM, f"    {result.stderr.strip()}"))
                if not confirm("Continue deployment despite error?", default="n"):
                    err("Deployment aborted")
                    sys.exit(1)

        ok("Deployment complete")

    # ---- Main flow ----------------------------------------------------------

    def run(self):
        print()
        print(c(BOLD + WHITE, "  +--------------------------------------+"))
        print(c(BOLD + WHITE, "  |       plrsys  RELEASE  MANAGER       |"))
        print(c(BOLD + WHITE, "  +--------------------------------------+"))

        # 1. Preflight + git status
        self.preflight()
        self.show_status()

        # 2. Checklist
        self.run_checklist()

        # 3. Version
        print(f"\n  {c(BOLD, 'Version bump')}")
        div()
        self.new_version = self.ask_version()
        if self.tag_exists(self.new_version):
            err(f"Tag v{self.new_version} already exists")
            sys.exit(1)

        # 4. Changelog
        entry = self.build_changelog_entry()
        if not entry and not self.changelog_edited:
            if not confirm("No changelog entry. Continue anyway?", default="n"):
                err("Release cancelled")
                sys.exit(1)

        # 5. Commit message
        print(f"\n  {c(BOLD, 'Commit message')}")
        div()
        default_msg = f"chore(release): v{self.new_version}"
        raw = input(f"  Message [{default_msg}]: ").strip()
        commit_msg = raw if raw else default_msg

        # 6. Preview
        print(f"\n  {c(BOLD, 'Release preview')}")
        div()
        print(f"  Version  : {c(YELLOW, self.current)} -> {c(GREEN, self.new_version)}")
        print(f"  Commit   : {commit_msg}")
        if entry:
            print("  Changelog:")
            for line in entry.splitlines():
                print(f"    {c(DIM, line)}")
        div()

        if not confirm("Proceed with release?"):
            err("Release cancelled")
            sys.exit(1)

        # 7. Apply (nothing has been modified before this point)
        self._write_version(self.new_version)
        ok(f"VERSION -> {self.new_version}")

        if entry:
            self.write_changelog(self.new_version, entry)

        self.commit_and_tag(commit_msg, self.new_version)

        # 8. Push
        pushed = self.push(self.new_version)

        # 9. GitHub release + deploy (only if pushed)
        if not pushed:
            warn("Skipped GitHub release and deploy (nothing was pushed)")
        else:
            self.github_release(self.new_version, entry)
            self.deploy()

        # Done
        print()
        print(c(GREEN + BOLD, "  " + "=" * 38))
        print(c(GREEN + BOLD, f"   plrsys v{self.new_version} shipped. nice work."))
        print(c(GREEN + BOLD, "  " + "=" * 38))
        print()


if __name__ == "__main__":
    # Always run from the repo root, wherever the script is launched from
    os.chdir(ROOT_DIR)
    try:
        ReleaseManager().run()
    except (KeyboardInterrupt, EOFError):
        print()
        err("Release cancelled")
        sys.exit(130)