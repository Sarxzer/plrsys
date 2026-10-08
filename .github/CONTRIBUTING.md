# Contributing to plrsys

Thanks for wanting to help! plrsys is a free, open-source home for plural systems, and it is built with care by a small team (mostly one person), so every bit of help counts.

## Before you start

- **Small fixes** (typos, bugs, small improvements): just open a pull request.
- **Bigger changes or new features**: please open an issue first so we can agree on the direction before you spend time on it.
- **Security problems**: do not open an issue. See [SECURITY.md](SECURITY.md).

plrsys is used by people sharing personal information. Please never put real user data, real names, or real credentials in issues, screenshots, or pull requests. Use dummy data only.

## Running it locally

The easiest way is the dev container. It gives you PHP 8.4, Nginx, and MySQL with the same setup every time.

You need [Docker](https://docs.docker.com/get-docker/) and [VS Code](https://code.visualstudio.com/) with the **Dev Containers** extension.

1. Clone the repo and open the folder in VS Code
2. Copy the example config: `cp .env.example .env`, and make sure `DB_HOST=db` in it
3. Press `F1` and run **Dev Containers: Reopen in Container**
4. Wait for the first build, then open `http://localhost:8000`

The database schema (`database/schema.sql`) is imported automatically the first time the database container starts. If you change the schema and want a fresh database, remove the containers and volumes (`docker compose down -v` on the host) and reopen the container.

To create a test account without the email flow, use `scripts/manual-signup.php` from the container terminal.

### Compiling stylesheets

The stylesheets are written in SCSS in `src/scss/`. The entrypoint is
`src/scss/style.scss`, which imports the partials used by the application.
The compiled files are written to `public/assets/css/`:

- `style.css` - the compiled stylesheet used by the site
- `style.css.map` - the source map used by browser developer tools

The repository includes a standalone Dart Sass workflow, so Node.js and a VS
Code extension are not required:

```sh
scripts/install-sass.sh
scripts/compile-scss.sh
```

To recompile automatically while editing:

```sh
scripts/compile-scss.sh --watch
```

The installer downloads the platform-specific Dart Sass binary into `.tools/`,
which is ignored by git. The compiler script always uses
`src/scss/style.scss` as the entrypoint and writes to `public/assets/css/`.

Do not edit the generated CSS or source map by hand. If the compiled output is
wrong, fix the SCSS source and compile it again. Keep the generated files in
the pull request when the project tracks an output change.

## Project layout

- `public/` - web root (entry point, assets, API routes)
- `src/pages/` - page templates and handlers
- `src/php/` - application classes (auth, mailer, database, ...)
- `src/includes/` - shared page parts (navbar, footer, alerts)
- `src/scss/` - stylesheet sources
- `tests/` - tests and test scripts

## Code guidelines

- PHP 8.4. Use prepared statements (PDO) for every query, never string-built SQL
- Every `POST` form needs a CSRF token and a check on the server
- Escape output with `htmlspecialchars()` unless it is intentionally HTML
- Never commit secrets: `.env` stays out of git, and new settings go into `.env.example` with placeholder values
- If you change the database, update `database/schema.sql` in the same pull request
- Keep pull requests focused: one change per PR is much easier to review

### Do not modify CSS files directly

Do not modify CSS files directly. See the "Compiling stylesheets" section above for how to edit SCSS and compile it into CSS.

## Pull requests

1. Fork the repo and create a branch from `main`
2. Make your change and test it in the dev container
3. Describe what you changed and why in the pull request
4. Be patient: reviews can take a few days

## AI-assisted contributions

The maintainer uses AI tools too (see the README), so using them is fine. The rules are the same as for any code:

- You are responsible for what you submit: read it, understand it, and test it before opening the pull request
- Mention it in the pull request if a substantial part was AI-generated
- Pull requests that look like unreviewed AI output (code that does not run, invents functions, or ignores the project's conventions) will be closed
- Be extra careful in security-sensitive code (authentication, sessions, access control, anything touching user data)

## License

plrsys is licensed under the [AGPL-3.0](../LICENSE). By contributing, you agree that your contributions are released under the same license.

## Be kind

This project exists for a community that is often misunderstood. Please be respectful and welcoming in issues, reviews, and discussions. Harassment or discrimination is not tolerated.