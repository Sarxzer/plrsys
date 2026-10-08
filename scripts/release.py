#!/usr/bin/env python3
"""
Interactive release script for plrsys.
Manages versioning, changelog, commits, tagging, and deployment to Skynet.

Settings come from the environment or the repo's .env file:
  RELEASE_GIT_BRANCH        branch to release from (required)
  RELEASE_SERVER_USER       SSH user on Skynet            \
  RELEASE_SERVER_HOST       SSH host or alias              |  all four are needed
  RELEASE_SERVER_PATH       plrsys root on Skynet          |  for the deploy step,
  RELEASE_PHP_FPM_SERVICE   PHP-FPM service name           /  otherwise it is skipped
  RELEASE_HEALTH_URL        optional URL checked after deploy (expects HTTP 2xx)
"""

import os
import re
import shlex
import shutil
import subprocess
import sys
import tempfile
import urllib.error
import urllib.request
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
HEALTH_URL      = os.getenv("RELEASE_HEALTH_URL")         # Optional: URL to probe after deploy

# Folders (relative to REMOTE_PATH) the web server must be able to write to.
WRITABLE_DIRS   = ["uploads/pfps"]
WRITABLE_OWNER  = "www-data"

# Pre-release checklist items shown before every release.
# Confirm each one manually - the script won't proceed past skipped items without asking.
# (Translations and compiled CSS are checked automatically in the preflight.)
CHECKLIST = [
    "Privacy Policy is up to date and matches what the database actually stores",
    "Terms of Service is up to date",
    "Account deletion flow works correctly",
    "Cookie disclosure is present",
    "CHANGELOG reflects all user-facing changes",
    "Schema changes were applied on Skynet (database/schema.sql is in sync)",
    "docs/openapi.yaml matches any API changes",
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
    """Ask a yes/no question. Accepts y/yes and n/no; Enter picks the default."""
    hint = "[Y/n]" if default == "y" else "[y/N]"
    while True:
        answer = input(f"\n  {prompt} {hint}: ").strip().lower()
        if not answer:
            return default == "y"
        if answer in ("y", "yes"):
            return True
        if answer in ("n", "no"):
            return False
        warn("Please answer y or n")

def run(cmd, check=True):
    """Run a shell command and return stripped stdout. Exits on failure if check=True."""
    result = subprocess.run(cmd, shell=True, capture_output=True, text=True)
    if check and result.returncode != 0:
        err(f"Command failed: {cmd}")
        if result.stderr:
            print(c(RED, f"    {result.stderr.strip()}"))
        sys.exit(1)
    return result.stdout.strip()

def sh(cmd):
    """Run a shell command and return the CompletedProcess. Never exits."""
    return subprocess.run(cmd, shell=True, capture_output=True, text=True)

def check_config():
    """
    Fail early on missing settings instead of discovering them halfway through a release.
    Returns the list of deploy settings that are missing (deploy is skipped if any).
    """
    if not GIT_BRANCH:
        err("RELEASE_GIT_BRANCH is not set (looked in the environment and in .env)")
        err(f"Copy the RELEASE_* lines from .env.example into {ENV_FILE}")
        sys.exit(1)

    deploy_settings = {
        "RELEASE_SERVER_USER": SKYNET_USER,
        "RELEASE_SERVER_HOST": SKYNET_HOST,
        "RELEASE_SERVER_PATH": REMOTE_PATH,
        "RELEASE_PHP_FPM_SERVICE": PHP_FPM_SERVICE,
    }
    return [name for name, value in deploy_settings.items() if not value]


# ------------------------------------------------------------------------------------------
#  ReleaseManager
# ------------------------------------------------------------------------------------------

class ReleaseManager:

    def __init__(self, project_root="."):
        self.root           = Path(project_root)
        self.version_file   = self.root / "VERSION"
        self.changelog      = self.root / "CHANGELOG.md"
        self.current        = self._read_version()
        self.new_version    = None
        self.release_commit = None   # SHA of the commit this script created
        self.deploy_missing = []     # deploy settings that are not configured
        self.warnings       = []     # problems to repeat in the final summary

    # ---- Version ------------------------------------------------------------

    def _read_version(self):
        if self.version_file.exists():
            version = self.version_file.read_text().strip()
            match = VERSION_PATTERN.fullmatch(version)
            if match:
                return self._format_version(match)
            warn(f"VERSION contains '{version}', which is not a valid version - assuming 0.1.0")
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
            if not match.group("phase"):
                # 0.1.0-alpha.1 sorts BEFORE 0.1.0, so from a stable version a
                # prerelease has to target the next patch instead.
                return f"{major}.{minor}.{patch + 1}-{part}.1"
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

    def check_in_sync(self):
        """Refuse to release when local and origin have diverged or origin is ahead."""
        remote_ref = f"origin/{GIT_BRANCH}"
        fetch = sh(f"git fetch origin {shlex.quote(GIT_BRANCH)}")
        if fetch.returncode != 0:
            warn("Could not fetch from origin, so I can't check you are in sync")
            if fetch.stderr.strip():
                print(c(DIM, f"    {fetch.stderr.strip()}"))
            if not confirm("Continue without the sync check?", default="n"):
                err("Release cancelled")
                sys.exit(1)
            return

        counts = run(f"git rev-list --left-right --count HEAD...{shlex.quote(remote_ref)}", check=False)
        try:
            ahead, behind = (int(n) for n in counts.split())
        except ValueError:
            warn(f"Could not compare with {remote_ref}")
            return

        if behind and ahead:
            err(f"{GIT_BRANCH} has diverged from {remote_ref} ({ahead} local, {behind} remote commits)")
            err("Reconcile first (rebase or merge), then run the release again")
            sys.exit(1)
        if behind:
            err(f"{GIT_BRANCH} is {behind} commit(s) behind {remote_ref}")
            err("Run `git pull --ff-only`, then run the release again")
            sys.exit(1)
        if ahead:
            warn(f"{ahead} local commit(s) are not on origin yet; they will be pushed with this release")
        else:
            ok(f"In sync with {remote_ref}")

    def preflight(self):
        """Refuse to release from the wrong branch or out of sync, and warn about unreleased work or secrets."""
        print(f"\n  {c(BOLD, 'Preflight checks')}")
        div()

        branch = run("git rev-parse --abbrev-ref HEAD")
        if branch != GIT_BRANCH:
            err(f"You are on '{branch}', but releases must come from '{GIT_BRANCH}'")
            sys.exit(1)
        ok(f"On branch {GIT_BRANCH}")

        self.check_in_sync()

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

        self.automated_checks()

    def automated_checks(self):
        """Checks the checklist used to leave to memory: translations and compiled CSS."""
        print(f"\n  {c(BOLD, 'Automated checks')}")
        div()
        problems = []

        # Translations. check-lang.php always exits 0 and prints one line per problem.
        lang_script = ROOT_DIR / "scripts" / "check-lang.php"
        if lang_script.exists() and shutil.which("php"):
            result = sh("php scripts/check-lang.php")
            output = (result.stdout + result.stderr).strip()
            if result.returncode != 0 or output:
                problems.append("Translation catalogs (EN/FR) are out of sync")
                for line in output.splitlines()[:10]:
                    print(c(YELLOW, f"    {line}"))
            else:
                ok("Translation catalogs match (EN/FR)")
        else:
            info("Skipped translation check (php or scripts/check-lang.php not available)")

        # Compiled CSS must match what the SCSS sources produce.
        sass   = ROOT_DIR / ".tools" / "dart-sass" / "sass"
        source = ROOT_DIR / "src" / "scss" / "style.scss"
        css    = ROOT_DIR / "public" / "assets" / "css" / "style.css"
        if sass.exists() and source.exists() and css.exists():
            with tempfile.TemporaryDirectory() as tmp:
                out = Path(tmp) / "style.css"
                result = subprocess.run(
                    [str(sass), str(source), str(out), "--style=expanded", "--source-map"],
                    capture_output=True, text=True,
                )
                if result.returncode != 0:
                    problems.append("SCSS does not compile")
                    for line in result.stderr.strip().splitlines()[:10]:
                        print(c(YELLOW, f"    {line}"))
                elif out.read_bytes() != css.read_bytes():
                    problems.append("public/assets/css/style.css is out of date (run scripts/compile-scss.sh)")
                else:
                    ok("Compiled CSS matches the SCSS sources")
        else:
            info("Skipped CSS check (run scripts/install-sass.sh to enable it)")

        if problems:
            for problem in problems:
                warn(problem)
            if not confirm("Continue despite failed checks?", default="n"):
                err("Release cancelled")
                sys.exit(1)

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
        warn("Go through each item. Press Enter (or type y) to confirm, or type a note to skip.")
        print()

        skipped = []
        for i, item in enumerate(CHECKLIST, 1):
            answer = input(f"  [{i}/{len(CHECKLIST)}] {item}\n        > ").strip()
            if answer.lower() in ("", "y", "yes", "ok"):
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

    def changelog_section(self, version):
        """Return (heading, body) of the '## [vX.Y.Z]' section in CHANGELOG.md, or None."""
        if not self.changelog.exists():
            return None
        pattern = rf"^(## \[v{re.escape(version)}\][^\n]*)\n(.*?)(?=^## |\Z)"
        match = re.search(pattern, self.changelog.read_text(), re.S | re.M)
        if not match:
            return None
        return match.group(1), match.group(2).strip()

    def _open_editor(self):
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

    def build_changelog_entry(self):
        """Let the user write the changelog, then return the body of this release's section."""
        print(f"\n  {c(BOLD, 'Changelog entry')}")
        div()
        commits = self.get_commits_since_last_tag()
        if commits:
            info("Commits since last tag:")
            for line in commits.splitlines():
                print(c(DIM, f"    {line}"))
        else:
            warn("No commits found since last tag")

        today = datetime.now().strftime("%Y-%m-%d")
        print()
        print("  Opening CHANGELOG.md in your text editor. Add (or update) a section headed:")
        print(c(CYAN, f"    ## [v{self.new_version}] - {today}"))
        print(c(DIM, "  Then save and close the editor."))
        print()

        while True:
            self._open_editor()
            found = self.changelog_section(self.new_version)
            if found is None:
                err(f"CHANGELOG.md has no '## [v{self.new_version}]' section")
            elif found[1] in ("", "-"):
                err(f"The '## [v{self.new_version}]' section is empty")
            else:
                heading, body = found
                break
            if not confirm("Open the editor again?"):
                err("Release cancelled (your CHANGELOG.md edits were kept)")
                sys.exit(1)

        if today not in heading:
            warn(f"The heading date is not today ({today}): {heading}")

        changes = run("git diff HEAD -- CHANGELOG.md", check=False)
        if changes:
            print(f"\n  {c(BOLD, 'CHANGELOG.md changes to be committed')}")
            div()
            print(changes)
        return body

    # ---- Commit + tag -------------------------------------------------------

    def rollback(self, tag=None):
        """
        Undo what this script did locally: the tag, the release commit and the VERSION bump.
        Edits to CHANGELOG.md are kept, and other staged files are left staged.
        """
        if tag:
            sh(f"git tag -d {shlex.quote(tag)}")
        head = run("git rev-parse HEAD", check=False)
        if self.release_commit and head == self.release_commit:
            sh("git reset --soft HEAD~1")
        sh("git reset -q -- VERSION CHANGELOG.md")
        self._write_version(self.current)
        ok(f"Local release undone (VERSION is back to {self.current}, CHANGELOG.md edits kept)")

    def commit_and_tag(self, message, version):
        print(f"\n  {c(BOLD, 'Committing')}")
        div()

        files = [f for f in ("VERSION", "CHANGELOG.md") if (self.root / f).exists()]
        run("git add " + " ".join(files))
        ok("Staged " + " and ".join(files))

        # Pathspec form: commit ONLY these files, even if something else is staged.
        result = sh(f"git commit -m {shlex.quote(message)} -- " + " ".join(files))
        if result.returncode != 0:
            err("git commit failed")
            if result.stderr.strip():
                print(c(RED, f"    {result.stderr.strip()}"))
            self.rollback()
            sys.exit(1)
        self.release_commit = run("git rev-parse HEAD")
        ok(f"Committed: {message}")

        tag = f"v{version}"
        result = sh(f"git tag -a {shlex.quote(tag)} -m {shlex.quote('Release ' + tag)}")
        if result.returncode != 0:
            err("git tag failed")
            if result.stderr.strip():
                print(c(RED, f"    {result.stderr.strip()}"))
            self.rollback()
            sys.exit(1)
        ok(f"Tagged {tag}")

    def push(self, version):
        print(f"\n  {c(BOLD, 'Push to remote')}")
        div()
        tag = f"v{version}"
        if not confirm("Push the commit and the new tag to origin?"):
            warn("Skipped push")
            info(f"Push later with: git push --atomic origin {GIT_BRANCH} {tag}")
            return False

        # --atomic: either the branch AND the tag land, or neither does.
        result = sh(f"git push --atomic origin {shlex.quote(GIT_BRANCH)} {shlex.quote(tag)}")
        if result.returncode != 0:
            err("Push failed")
            if result.stderr.strip():
                print(c(RED, f"    {result.stderr.strip()}"))
            warn("The release commit and tag exist only on this machine.")
            if confirm("Undo the local release commit, tag and VERSION bump so you can retry cleanly?"):
                self.rollback(tag)
            else:
                info(f"Retry later with: git push --atomic origin {GIT_BRANCH} {tag}")
            sys.exit(1)
        ok(f"Pushed {GIT_BRANCH} and tag {tag}")
        return True

    def github_release(self, version, entry):
        """Optional: publish a GitHub Release using the GitHub CLI, if installed."""
        if not shutil.which("gh"):
            return
        if not confirm("Create a GitHub release from this changelog entry?", default="n"):
            return
        notes = entry or "No changes documented."
        command = ["gh", "release", "create", f"v{version}",
                   "--title", f"v{version}", "--notes", notes]
        if "-" in version:  # alpha / beta
            command.append("--prerelease")
        result = subprocess.run(command, capture_output=True, text=True)
        if result.returncode == 0:
            ok("GitHub release created")
        else:
            warn("GitHub release failed (the tag and push are fine)")
            if result.stderr.strip():
                print(c(DIM, f"    {result.stderr.strip()}"))

    # ---- Deploy -------------------------------------------------------------

    def _ssh(self, remote_cmd):
        return subprocess.run(
            ["ssh", "-o", "ConnectTimeout=10", f"{SKYNET_USER}@{SKYNET_HOST}", remote_cmd],
            capture_output=True, text=True,
        )

    def _hard_step(self, label, remote_cmd):
        """A step the deploy cannot do without. On failure, ask before going on."""
        info(f"Running: {label}")
        result = self._ssh(remote_cmd)
        if result.returncode == 0:
            ok(label)
            return
        warn(f"{label} exited {result.returncode}")
        if result.stderr.strip():
            print(c(DIM, f"    {result.stderr.strip()}"))
        if not confirm("Continue deployment despite error?", default="n"):
            err("Deployment aborted")
            sys.exit(1)

    def _soft_step(self, label, remote_cmd):
        """
        A step that needs sudo. If it fails the deploy still counts: the code is already
        on the server, so we warn, remember it for the summary and keep going.
        Returns True on success, 'sudo' if sudo refused, False for any other failure.
        """
        info(f"Running: {label}")
        result = self._ssh(remote_cmd)
        if result.returncode == 0:
            ok(label)
            return True
        stderr = result.stderr.strip()
        warn(f"{label} failed (exit {result.returncode}) - continuing")
        if stderr:
            print(c(DIM, f"    {stderr}"))
        self.warnings.append(f"Deploy step failed: {label}")
        return "sudo" if "sudo" in stderr.lower() else False

    def _check_remote_debug(self):
        """Safety check: debug mode must be off on production."""
        rp = shlex.quote(REMOTE_PATH)
        result = self._ssh(f"grep -E '^APP_DEBUG=' {rp}/.env")
        debug_line = result.stdout.strip()
        value = ""
        if debug_line:
            value = debug_line.split("=", 1)[1].split("#", 1)[0].strip().strip("'\"").lower()

        if value in ("true", "1", "yes", "on"):
            err(f"Remote .env has {debug_line} - turn debug off before deploying")
            sys.exit(1)
        elif debug_line:
            ok(f"Remote {debug_line}")
        else:
            if result.returncode == 255:
                warn("Could not reach the server over SSH")
            else:
                warn("Could not read APP_DEBUG from the remote .env")
            if result.stderr.strip():
                print(c(DIM, f"    {result.stderr.strip()}"))
            if not confirm("Continue anyway?", default="n"):
                err("Deployment aborted")
                sys.exit(1)

    def _print_sudo_hint(self):
        service = shlex.quote(PHP_FPM_SERVICE)
        print()
        warn("The server asked for a sudo password, and a script can't type one.")
        print(c(DIM, "    Fix once on Skynet, with a rule limited to exactly these commands:"))
        print(c(DIM, "      sudo visudo -f /etc/sudoers.d/plrsys-deploy"))
        print(c(DIM, f"      {SKYNET_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl reload {PHP_FPM_SERVICE}, /usr/bin/systemctl reload nginx"))
        print(c(DIM, "    (check the systemctl path with: command -v systemctl)"))
        print(c(DIM, "    Until then, run the failed step by hand, e.g.:"))
        print(c(DIM, f"      sudo systemctl reload {service}"))

    def deploy(self, version):
        print(f"\n  {c(BOLD, 'Deploy to Skynet')}")
        div()

        if self.deploy_missing:
            warn("Deploy skipped, these settings are missing: " + ", ".join(self.deploy_missing))
            return
        info(f"Target: {SKYNET_USER}@{SKYNET_HOST}:{REMOTE_PATH}")

        if not confirm("Deploy now?"):
            warn("Skipped deployment")
            return

        self._check_remote_debug()

        rp = shlex.quote(REMOTE_PATH)
        old_head = self._ssh(f"git -C {rp} rev-parse HEAD").stdout.strip()

        # Hard steps: without these the new code is not on the server.
        self._hard_step("git pull",
                        f"cd {rp} && git pull --ff-only origin {shlex.quote(GIT_BRANCH)}")
        self._hard_step("composer install",
                        f"cd {rp} && composer install --no-dev --optimize-autoloader")

        changed = []
        if old_head:
            diff = self._ssh(f"git -C {rp} diff --name-only {shlex.quote(old_head)} HEAD")
            changed = diff.stdout.split()

        # Soft steps: need sudo, and a failure must not undo or abort a deploy whose code is live.
        sudo_refused = False
        owner = shlex.quote(WRITABLE_OWNER)
        for d in WRITABLE_DIRS:
            path = shlex.quote(f"{REMOTE_PATH}/{d}")
            # Only chown when the owner is wrong, so a normal release needs no sudo here.
            cmd = (f"mkdir -p {path} && [ \"$(stat -c %U {path})\" = {owner} ] "
                   f"|| sudo -n chown -R {owner}:{owner} {path}")
            sudo_refused |= self._soft_step(f"writable: {d}", cmd) == "sudo"

        sudo_refused |= self._soft_step(
            "reload php-fpm", f"sudo -n systemctl reload {shlex.quote(PHP_FPM_SERVICE)}") == "sudo"

        # Nothing here copies deploy/nginx.conf, so only reload nginx if that file changed.
        if "deploy/nginx.conf" in changed:
            sudo_refused |= self._soft_step("reload nginx", "sudo -n systemctl reload nginx") == "sudo"
        else:
            info("Skipped nginx reload (deploy/nginx.conf did not change)")

        if sudo_refused:
            self._print_sudo_hint()

        self.verify_deploy(version)
        ok("Deployment finished")

    def verify_deploy(self, version):
        """Confirm the server really is on the new version, and the site answers."""
        rp = shlex.quote(REMOTE_PATH)
        remote_version = self._ssh(f"cat {rp}/VERSION").stdout.strip()
        if remote_version == version:
            ok(f"Skynet reports VERSION {remote_version}")
        else:
            warn(f"Skynet reports VERSION '{remote_version or 'unreadable'}', expected '{version}'")
            self.warnings.append("Server VERSION does not match the release")

        if HEALTH_URL:
            try:
                with urllib.request.urlopen(HEALTH_URL, timeout=10) as response:
                    if 200 <= response.status < 300:
                        ok(f"{HEALTH_URL} answered HTTP {response.status}")
                    else:
                        raise urllib.error.URLError(f"HTTP {response.status}")
            except (urllib.error.URLError, OSError) as exc:
                warn(f"Health check failed for {HEALTH_URL}: {exc}")
                self.warnings.append("Health check failed after deploy")
        else:
            info("No RELEASE_HEALTH_URL set, skipping the health check")

    # ---- Main flow ----------------------------------------------------------

    def run(self):
        print()
        print(c(BOLD + WHITE, "  +--------------------------------------+"))
        print(c(BOLD + WHITE, "  |       plrsys  RELEASE  MANAGER       |"))
        print(c(BOLD + WHITE, "  +--------------------------------------+"))

        # 0. Settings
        self.deploy_missing = check_config()
        if self.deploy_missing:
            warn("Deploy settings missing (" + ", ".join(self.deploy_missing) + "): the deploy step will be skipped")

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

        # 4. Changelog (returns the body of this release's section; exits if there isn't one)
        entry = self.build_changelog_entry()

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
        print("  Changelog:")
        for line in entry.splitlines():
            print(f"    {c(DIM, line)}")
        div()

        if not confirm("Proceed with release?"):
            err("Release cancelled (CHANGELOG.md edits were kept)")
            sys.exit(1)

        # 7. Apply (VERSION is the only file changed before the commit; rollback restores it)
        self._write_version(self.new_version)
        ok(f"VERSION -> {self.new_version}")

        self.commit_and_tag(commit_msg, self.new_version)

        # 8. Push
        pushed = self.push(self.new_version)

        # 9. GitHub release + deploy (only if pushed)
        if not pushed:
            warn("Skipped GitHub release and deploy (nothing was pushed)")
        else:
            self.github_release(self.new_version, entry)
            self.deploy(self.new_version)

        # Done
        print()
        if self.warnings:
            print(c(YELLOW + BOLD, "  " + "=" * 38))
            print(c(YELLOW + BOLD, f"   plrsys v{self.new_version} released, with {len(self.warnings)} warning(s):"))
            for warning in self.warnings:
                print(c(YELLOW, f"    - {warning}"))
            print(c(YELLOW + BOLD, "  " + "=" * 38))
        else:
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