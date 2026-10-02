# Security Policy

plrsys stores sensitive personal information (system and member profiles, fronting history, account data), so security reports are taken seriously.

## Reporting a vulnerability

**Please do not open a public issue for security problems.**

Report it privately, using either of these:

- GitHub's private reporting: go to the **Security** tab of this repository and click **Report a vulnerability**
- Email: [sarxzer@sarxzer.xyz](mailto:sarxzer@sarxzer.xyz) with "plrsys security" in the subject

Please include:

- What the problem is and where it is (page, route, or file)
- Steps to reproduce it
- What an attacker could do with it
- Your suggested fix, if you have one (optional)

## What to expect

plrsys is maintained by one person, so there is no guaranteed response time. I aim to acknowledge reports within a few days, keep you updated while I work on a fix, and credit you in the changelog if you want that.

Please give me reasonable time to fix the issue before you disclose it publicly.

## Supported versions

Only the latest release (the `main` branch) receives security fixes.

## In scope

- The plrsys application code in this repository (authentication, sessions, 2FA, CSRF protection, access control between systems and friends, the API)
- Anything that could expose another user's data

## Out of scope

- Problems that only exist on a self-hosted instance because of its own configuration (weak server setup, exposed `.env`, outdated PHP)
- Denial-of-service through brute-force traffic
- Social engineering, or physical attacks
- Vulnerabilities in third-party services (Cloudflare, Discord, GitHub) or in dependencies without a working exploit against plrsys

## Testing guidelines

- Only test against your own accounts and your own local copy (the dev container is the easiest way)
- Do not access, change, or delete other people's data
- Do not run automated scanners against `plrsys.xyz`

Thank you for helping keep people's data safe.