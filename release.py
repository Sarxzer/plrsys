#!/usr/bin/env python3
"""
Interactive Release/Update Script for plrsys
Manages versioning, changelog, commits, tagging, and deployment to Skynet.
"""

import os
import re
import sys
import subprocess
from datetime import datetime
from pathlib import Path


# ─────────────────────────────────────────────
#  Config — edit these to match your setup
# ─────────────────────────────────────────────

SKYNET_USER   = "ori"                          # SSH user on Skynet
SKYNET_HOST   = "skynet"                       # SSH host or alias (~/.ssh/config)
REMOTE_PATH   = "/var/www/plrsys"              # plrsys root on Skynet
GIT_BRANCH    = "main"

# Pre-release checklist items shown before every release.
# Check off each one manually — the script won't proceed until all are confirmed.
CHECKLIST = [
    "Privacy Policy is up to date",
    "Terms of Service is up to date",
    "Account deletion flow works correctly",
    "Cookie disclosure is present",
    "CHANGELOG reflects all user-facing changes",
    "No debug flags left on (APP_DEBUG=false in .env)",
    "CSRF tokens present on all POST forms",
    "No hardcoded secrets or API keys in code",
]


# ─────────────────────────────────────────────
#  Helpers
# ─────────────────────────────────────────────

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
def div():     print(c(DIM, "  " + "─" * 56))

def confirm(prompt, default="y"):
    hint = "[Y/n]" if default == "y" else "[y/N]"
    answer = input(f"\n  {prompt} {hint}: ").strip().lower()
    if not answer:
        return default == "y"
    return answer == "y"

def run(cmd, check=True, silent=False):
    result = subprocess.run(
        cmd, shell=True, capture_output=True, text=True, check=False
    )
    if check and result.returncode != 0:
        err(f"Command failed: {cmd}")
        if result.stderr:
            print(c(RED, f"    {result.stderr.strip()}"))
        sys.exit(1)
    if not silent and result.stdout.strip():
        return result.stdout.strip()
    return result.stdout.strip()


# ─────────────────────────────────────────────
#  ReleaseManager
# ─────────────────────────────────────────────

class ReleaseManager:

    def __init__(self, project_root="."):
        self.root          = Path(project_root)
        self.version_file  = self.root / "VERSION"
        self.changelog     = self.root / "CHANGELOG.md"
        self.current       = self._read_version()
        self.new_version   = None

    # ── Version ──────────────────────────────

    def _read_version(self):
        if self.version_file.exists():
            v = self.version_file.read_text().strip()
            if re.match(r"^\d+\.\d+\.\d+$", v):
                return v
        return "0.1.0"

    def _write_version(self, version):
        self.version_file.write_text(version + "\n")

    def _bump(self, part):
        major, minor, patch = map(int, self.current.split("."))
        if part == "major": return f"{major + 1}.0.0"
        if part == "minor": return f"{major}.{minor + 1}.0"
        if part == "patch": return f"{major}.{minor}.{patch + 1}"

    def ask_version(self):
        print(f"\n  Current version: {c(BOLD, self.current)}")
        div()
        print(f"  {c(CYAN, '1')}  Patch  → {self._bump('patch')}   (bug fixes)")
        print(f"  {c(CYAN, '2')}  Minor  → {self._bump('minor')}   (new features, backwards compat)")
        print(f"  {c(CYAN, '3')}  Major  → {self._bump('major')}   (breaking changes)")
        print(f"  {c(CYAN, '4')}  Custom")
        div()
        while True:
            choice = input("  Pick [1-4]: ").strip()
            if choice == "1": return self._bump("patch")
            if choice == "2": return self._bump("minor")
            if choice == "3": return self._bump("major")
            if choice == "4":
                while True:
                    v = input("  Enter version (X.Y.Z): ").strip()
                    if re.match(r"^\d+\.\d+\.\d+$", v):
                        return v
                    err("Must be X.Y.Z format")
            err("Pick 1–4")

    # ── Git ──────────────────────────────────

    def show_status(self):
        print(f"\n  {c(BOLD, 'Git status')}")
        div()
        status = run("git status --short", silent=True)
        if status:
            for line in status.splitlines():
                print(f"    {line}")
        else:
            ok("Working tree clean")

        print(f"\n  {c(BOLD, 'Recent commits')}")
        div()
        commits = run("git log --oneline -8", silent=True)
        if commits:
            for line in commits.splitlines():
                print(f"    {c(DIM, line)}")

    def get_commits_since_last_tag(self):
        try:
            tag = run("git describe --tags --abbrev=0", check=True, silent=True)
            return run(f"git log {tag}..HEAD --pretty=format:'%h %s'", silent=True)
        except SystemExit:
            return run("git log --pretty=format:'%h %s' -20", silent=True)

    # ── Checklist ────────────────────────────

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

    # ── Changelog ────────────────────────────

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
        print("  Enter changelog lines (empty line to finish):")
        print(c(DIM, "  Examples: '- Added fronting session tracking'"))
        print(c(DIM, "            '- Fixed CSRF token rotation bug'"))
        print()

        lines = []
        while True:
            line = input("  > ").strip()
            if not line:
                break
            if not line.startswith("-"):
                line = f"- {line}"
            lines.append(line)

        return "\n".join(lines) if lines else None

    def write_changelog(self, version, entry):
        date   = datetime.now().strftime("%Y-%m-%d")
        header = f"## [v{version}] - {date}\n\n"
        body   = (entry + "\n\n") if entry else "- No changes documented.\n\n"

        if self.changelog.exists():
            existing = self.changelog.read_text()
        else:
            existing = "# Changelog\n\nAll notable changes to plrsys are documented here.\n\n"

        self.changelog.write_text(header + body + existing)
        ok(f"Wrote CHANGELOG.md")

    # ── Commit + tag ─────────────────────────

    def commit_and_tag(self, message, version):
        print(f"\n  {c(BOLD, 'Committing')}")
        div()

        run("git add VERSION CHANGELOG.md")
        ok("Staged VERSION and CHANGELOG.md")

        run(f'git commit -m "{message}"')
        ok(f"Committed: {message}")

        tag = f"v{version}"
        run(f'git tag -a {tag} -m "Release {tag}"')
        ok(f"Tagged {tag}")

    def push(self):
        print(f"\n  {c(BOLD, 'Push to remote')}")
        div()
        if not confirm("Push commits and tags to origin?"):
            warn("Skipped push")
            return False
        run(f"git push origin {GIT_BRANCH}")
        ok(f"Pushed {GIT_BRANCH}")
        run("git push origin --tags")
        ok("Pushed tags")
        return True

    # ── Deploy ───────────────────────────────

    def deploy(self):
        print(f"\n  {c(BOLD, 'Deploy to Skynet')}")
        div()
        info(f"Target: {SKYNET_USER}@{SKYNET_HOST}:{REMOTE_PATH}")

        if not confirm("Deploy now?"):
            warn("Skipped deployment")
            return

        ssh = f"ssh {SKYNET_USER}@{SKYNET_HOST}"
        steps = [
            ("git pull",            f"cd {REMOTE_PATH} && git pull origin {GIT_BRANCH}"),
            ("composer install",    f"cd {REMOTE_PATH} && composer install --no-dev --optimize-autoloader"),
            ("fix permissions",     f"chown -R www-data:www-data {REMOTE_PATH}/storage {REMOTE_PATH}/cache 2>/dev/null || true"),
            ("reload php-fpm",      "sudo systemctl reload php8.4-fpm"),
            ("reload nginx",        "sudo systemctl reload nginx"),
        ]

        for label, remote_cmd in steps:
            info(f"Running: {label}")
            result = subprocess.run(
                f'{ssh} "{remote_cmd}"',
                shell=True, capture_output=True, text=True
            )
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

    # ── Main flow ────────────────────────────

    def run(self):
        print()
        print(c(BOLD + WHITE, "  ╔══════════════════════════════════════╗"))
        print(c(BOLD + WHITE, "  ║       plrsys  RELEASE  MANAGER       ║"))
        print(c(BOLD + WHITE, "  ╚══════════════════════════════════════╝"))

        # 1. Git status
        self.show_status()

        # 2. Checklist
        self.run_checklist()

        # 3. Version
        print(f"\n  {c(BOLD, 'Version bump')}")
        div()
        self.new_version = self.ask_version()

        # 4. Changelog
        entry = self.build_changelog_entry()
        if not entry:
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
        print(f"  Version  : {c(YELLOW, self.current)} → {c(GREEN, self.new_version)}")
        print(f"  Commit   : {commit_msg}")
        if entry:
            print(f"  Changelog:")
            for line in entry.splitlines():
                print(f"    {c(DIM, line)}")
        div()

        if not confirm("Proceed with release?"):
            err("Release cancelled")
            sys.exit(1)

        # 7. Apply
        self._write_version(self.new_version)
        ok(f"VERSION → {self.new_version}")

        if entry:
            self.write_changelog(self.new_version, entry)

        self.commit_and_tag(commit_msg, self.new_version)

        # 8. Push
        pushed = self.push()

        # 9. Deploy (only if pushed, or override)
        if not pushed:
            warn("Skipped deploy (nothing was pushed)")
        else:
            self.deploy()

        # Done
        print()
        print(c(GREEN + BOLD, "  ══════════════════════════════════════"))
        print(c(GREEN + BOLD, f"   plrsys v{self.new_version} shipped. nice work."))
        print(c(GREEN + BOLD, "  ══════════════════════════════════════"))
        print()


if __name__ == "__main__":
    ReleaseManager().run()