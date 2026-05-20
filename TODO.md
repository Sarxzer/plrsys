# Innerspace — TODO

> Last updated: 2026-05-19

---

## 🔴 Security

- [ ] **[HIGH]** Make logout a POST request with CSRF token to prevent cross-site logout attacks
- [ ] **[HIGH]** Add security headers in nginx: HSTS, CSP, X-Content-Type-Options, Referrer-Policy, frame-ancestors
- [ ] **[HIGH]** Fix login rate limiting — the 5-attempt lockout check is dead code (comes after an `exit`)
- [ ] **[MEDIUM]** Strengthen login throttling beyond session-only (per IP or shared store, not just session)
- [ ] **[MEDIUM]** Sanitize `HTTP_REFERER` redirect in CSRF fail handler (currently unsanitized open redirect)
- [x] **[LOW]** Don't log CSRF tokens in debug alerts

---

## 🐛 Bugs

- [x] **[HIGH]** Gate `/tests` route behind `APP_DEBUG` check (currently publicly accessible in prod)
- [x] **[HIGH]** Fix `mailer.php` — `require_once` path points to `src/php/vendor/` which doesn't exist
- [x] **[HIGH]** `admin-test.php` is publicly accessible via `?user_id=N` and dumps full system data
- [ ] **[MEDIUM]** Fix potential undefined variable `$member_name` in breadcrumb generation (outside the if block)

---

## ✨ UX & Polish

- [ ] **[MEDIUM]** Build out the dashboard — currently just a list of 3 links
- [ ] **[MEDIUM]** Show current fronting status on dashboard
- [ ] **[MEDIUM]** Add delete system functionality (button exists but is disabled with "Coming soon")
- [ ] **[LOW]** Sync footer version (hardcoded `v0.0.0`) with changelog page's dynamic GitHub commit fetch

---

## 🚀 Features

- [ ] **[MEDIUM]** Complete the friends system (invite flow exists, friends page is incomplete)
- [ ] **[MEDIUM]** Implement fronting history page (route exists, page is empty)
- [ ] **[MEDIUM]** Add member visibility controls (public/friends/private — in DB schema, not in UI)
- [ ] **[MEDIUM]** Password reset via email (`sendPasswordResetEmail` method exists, no route for it)
- [ ] **[MEDIUM]** Recovery codes at registration (for password loss without email)
- [ ] **[LOW]** Add member description field to member edit form (in DB, not in form)
- [ ] **[LOW]** Add avatar upload to member profiles (`intervention/image` is already installed)
- [x] **[LOW]** Email verification on registration (done but in settings page)
- [ ] **[LOW]** Support multiple systems per user (`systems.php` already redirects to first system only)

---

## 🧹 Cleanup

- [x] **[MEDIUM]** Move or protect `admin-test.php` and `manual-signup.php`
- [x] **[LOW]** Remove all commented-out code blocks in `system/system.php`

---

## 🏗️ Infra

- [x] **[MEDIUM]** Set up a proper cron schedule for `cron.php`
- [ ] **[LOW]** Write PHPUnit tests for `Auth`, `Guards`, `Csrf`, and `totp_*` functions
- [x] **[LOW]** Compile SCSS to CSS as part of a proper build step

---

## ✅ Done

- [x] CSRF protection for all state-changing POST handlers
- [x] Enforce ownership/authorization checks on manage routes (IDOR prevention)
- [x] Escape breadcrumb names from DB values (stored XSS prevention)
- [x] Regenerate session ID on login and after 2FA (session fixation prevention)
- [x] Harden session cookie settings (secure, httponly, samesite)
- [x] Harden remember-me cookies and store only hashed tokens in DB
- [x] Fix 2FA lockout bug (clear pending_2fa_user, block after max attempts)
- [x] Add login rate limiting / throttling
- [x] Lock down upload handler (auth required, size limits, safe image validation)
- [x] Fix remember-me revoke flow (hash cookie token before DB revoke)
- [x] Enforce system privacy (block non-public systems from anonymous users)
- [x] Escape "Now fronting" names on public system page (stored XSS prevention)
- [x] Harden canonical URL rendering
- [x] Reduce error detail sent to Discord in production