# plrsys

**A free, open-source home for plural systems.** Track your members and fronting, and share with friends on your terms. No ads, no algorithms, no pressure.

[![License: AGPL-3.0](https://img.shields.io/badge/license-AGPL--3.0-blue)](LICENSE)
![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777bb4)
![Status: early development](https://img.shields.io/badge/status-early%20development-orange)
![GitHub Tag](https://img.shields.io/github/v/tag/sarxzer/plrsys?label=version)


**Live site: [plrsys.xyz](https://plrsys.xyz)**

<!-- Add a screenshot here: ![plrsys screenshot](docs/screenshot.png) -->

## What is this?

A plural system is a group of distinct people (called members) who share one body. Many plural people use tools to keep track of who is present, to organize their system, and to let trusted friends know how they are doing. plrsys is one of those tools.

It was built to be smaller, more personal, and more intentional than the big platforms: no ads, no tracking, and privacy controls that make sense. It has a retro pixel look, because it should feel like home and not like enterprise software.

## Features

- **System and member management**: create your system, add members, and give each one a profile (name, pronouns, color, avatar, description, role)
- **Fronting tracking**: log who is fronting, with several members at once, notes, and a history page
- **Public pages**: share a system or member page with a link, with proper link previews
- **Accounts and security**: two-factor authentication (TOTP) with backup codes, CSRF protection, password reset, and email verification
- **Discord connection**: link your Discord account to your plrsys account
- **Installable**: works as a PWA on phones and desktops

## Roadmap

plrsys is in early development and not at 1.0 yet. What is still planned before the public launch:

- Friends system with mutual linking and per-friend access levels
- Member visibility controls (public, friends, private) in the interface
- Account deletion and data export flow
- Simply Plural importer

Progress and smaller tasks are tracked in the [issues](../../issues).

## Tech stack

PHP 8.4 (PHP-FPM), MySQL, Nginx, SCSS, and vanilla JavaScript. No framework. Dependencies are managed with Composer.

## Run it locally

The easiest way is the dev container, which gives you PHP, Nginx, and MySQL with the same setup every time. You need [Docker](https://docs.docker.com/get-docker/) and [VS Code](https://code.visualstudio.com/) with the **Dev Containers** extension.

```bash
git clone https://github.com/Sarxzer/plrsys.git
cd plrsys
cp .env.example .env     # then make sure DB_HOST=db
code .
```

Then press `F1` and run **Dev Containers: Reopen in Container**. When the build is done, open `http://localhost:8000`. The database schema is imported automatically on first start.

See [CONTRIBUTING](.github/CONTRIBUTING.md) for more details.

## Self-hosting

You can host your own instance. You need a server with PHP 8.4-FPM, MySQL or MariaDB, Nginx, and Composer.

1. Clone the repo and run `composer install --no-dev --optimize-autoloader`
2. Create a database and import `database/schema.sql`
3. Copy `.env.example` to `.env` and fill in your database, mail, and app settings (set `APP_DEBUG=false`)
4. Point Nginx at the `public/` folder. `deploy/nginx.conf` is the configuration used for plrsys.xyz and is a good starting point
5. Make sure the web server can write to `uploads/pfps/`

Self-hosting is not polished yet. Expect rough edges, and please open an issue if you get stuck.

## How this project is built (AI disclosure)

I use AI assistants as a tool while developing plrsys, mostly to move faster: drafting boilerplate, explaining unfamiliar code, suggesting fixes, and helping write documentation. I think they are genuinely useful.

This is not a "vibe-coded" project. I decide the design, I write and review the code that goes in, and I test it before it ships. I am responsible for everything in this repository, and I do not merge code I do not understand, especially in security-sensitive parts like authentication and data access.

If you spot something that looks wrong, please open an issue. If you contribute with AI help, see the guidelines in [CONTRIBUTING](.github/CONTRIBUTING.md).

## Contributing

Contributions are welcome. Read [CONTRIBUTING](.github/CONTRIBUTING.md) first. For security problems, see [SECURITY](.github/SECURITY.md) and do not open a public issue.

## Releases

Notable changes are listed in the [CHANGELOG](CHANGELOG.md). The maintainer release process is described in [docs/RELEASING.md](docs/RELEASING.md).

## License

plrsys is licensed under the [GNU Affero General Public License v3.0](LICENSE). In short: you can use, study, modify, and self-host it, and if you run a modified version as a public service, you must share your changes under the same license.

Built by [Sarxzer](https://github.com/Sarxzer).
