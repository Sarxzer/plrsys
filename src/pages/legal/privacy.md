# Privacy Policy

**Last updated:** October 2, 2026
**Effective:** October 2, 2026

---

## 1. Who we are {#1-who-we-are}

plrsys (`plrsys.xyz`) is an independent web application operated by **Sarxzer** and the **plrsys Team**. plrsys is designed as a safe, private space for plural systems to track fronting sessions, manage system members, and share with trusted friends.

For all privacy-related matters, see [Section 13 - Contact](#13-contact).

---

## 2. Scope of this policy {#2-scope-of-this-policy}

This Privacy Policy applies to all data processing activities carried out by plrsys when you access or use our service, including when you register, use the application, or simply visit our pages.

This policy is written in compliance with the **General Data Protection Regulation (GDPR)**, applicable under French and EU law.

---

## 3. Data we collect {#3-data-we-collect}

### 3.1 - Account data (mandatory)

To create an account, you must provide:

- **Username** - used to identify your account. Does not need to be your real name.
- **Password** - stored as a secure one-way hash (bcrypt). We never have access to your plaintext password.

### 3.2 - Account data (optional)

- **Email address** - only needed if you want account recovery. Entirely optional.
- **Profile information** - any additional information you choose to add (e.g. system description, member profiles, avatar images).

### 3.3 - Usage data

- **Fronting sessions** - logs of which member is fronting, with timestamps. Stored persistently and associated with your account.
- **Member statistics** - aggregated stats derived from your fronting history (e.g. time fronted per member).
- **System & member data** - names, descriptions, and other details about your system and members, as entered by you.

### 3.4 - Technical data

- **Session data** - a session cookie is created when you log in. Optionally, a remember-me token may be stored to keep you logged in across sessions (see [Section 5 - Cookies](#5-cookies--local-storage)).
- **Active visitor data** - temporary, in-memory data collected while you are actively using the service (see [Section 6](#6-active-visitor-tracking)).

> **Note:** We do not collect real names, physical addresses, phone numbers, financial information, or any government-issued identifiers. We collect only what is necessary for the service to function.

---

## 4. How we use your data {#4-how-we-use-your-data}

We use the data we collect for the following purposes:

- **Authentication & account management** - to identify you, manage your session, and secure your account.
- **Service delivery** - to display your system, members, fronting history, and statistics.
- **Account recovery** - to send a password reset email, if you provided one and request recovery.
- **Security** - to detect and prevent abuse and unauthorized access. Errors are logged internally via a private Discord webhook.
- **Service improvement** - aggregated, non-identifiable usage patterns may be reviewed to improve plrsys.

We rely on the following **legal bases** under GDPR Article 6:

| Purpose | Legal basis |
|---|---|
| Authentication & account management | Performance of a contract (Art. 6.1.b) |
| Service delivery | Performance of a contract (Art. 6.1.b) |
| Account recovery (email) | Consent (Art. 6.1.a) - optional |
| Security & error logging | Legitimate interests (Art. 6.1.f) |

---

## 5. Cookies & local storage {#5-cookies--local-storage}

plrsys uses only functional cookies. We do not use advertising, analytics, or tracking cookies.

| Cookie | Purpose | Duration |
|---|---|---|
| Session cookie | Keeps you logged in during your visit | Session (deleted on browser close) |
| Remember-me token | Keeps you logged in across sessions if you opt in | 30 days (rotating) |

The remember-me token is **rotated on every use** for security. No third-party cookies are set by plrsys.

---

## 6. Active visitor tracking {#6-active-visitor-tracking}

While you are actively using plrsys, the following data is held **temporarily in server memory** to enable real-time features:

- IP address
- User agent (browser/device info)
- Account ID
- Current page

**This data is never written to disk or to the database.** It exists only in memory and is cleared when your session becomes inactive. It is not shared, sold, or used for profiling.

---

## 7. Data sharing & third parties {#7-data-sharing--third-parties}

We do not sell, rent, or share your personal data with third parties for commercial purposes.

Data may be shared only in the following limited circumstances:

- **Legal obligations** - if required by French or EU law, a court order, or a competent authority.
- **Error logging** - internal error events are sent to a private Discord webhook accessible only to the operator. These logs may contain technical identifiers (e.g. account ID, IP address) but are used solely for debugging.
- **Cloudflare, Inc.** - all traffic to plrsys is routed through Cloudflare's reverse proxy network (US-based). Cloudflare may process technical data such as IP addresses as part of its network, DDoS protection, and DNS services. See [Cloudflare's Privacy Policy](https://www.cloudflare.com/privacypolicy/).

No third-party analytics services (e.g. Google Analytics) are used.

---

## 8. Data retention {#8-data-retention}

| Data type | Retention period |
|---|---|
| Account data & system content | Until account deletion |
| Fronting session history | Until account deletion or manual deletion |
| Session cookies | Until browser close |
| Remember-me tokens | 30 days (rolling), or until logout |
| Active visitor data (in-memory) | Until session inactivity (minutes) |
| Error logs (Discord webhook) | Subject to Discord's own retention policies |

When you delete your account, your personal data and content are **anonymized or permanently deleted** in accordance with your request.

---

## 9. Your rights (GDPR) {#9-your-rights-gdpr}

As a user located in the EU/EEA (or under French law), you have the following rights regarding your personal data:

- **Right of access** - you can request a copy of the data we hold about you.
- **Right to rectification** - you can correct inaccurate or incomplete data directly in your account settings.
- **Right to erasure** - you can delete your account and all associated data at any time via the account deletion feature.
- **Right to restriction** - you can request that we limit how we process your data in certain circumstances.
- **Right to data portability** - you can request an export of your personal data in a structured, machine-readable format.
- **Right to object** - you can object to processing based on legitimate interests.
- **Right to withdraw consent** - if processing is based on consent (e.g. email address), you may withdraw it at any time without affecting prior processing.

To exercise any of these rights, contact us at the address in [Section 13](#13-contact). We will respond within 30 days.

You also have the right to lodge a complaint with the **CNIL** (Commission Nationale de l'Informatique et des Libertés):
[https://www.cnil.fr/fr/plaintes](https://www.cnil.fr/fr/plaintes)

---

## 10. Security {#10-security}

We take reasonable technical and organizational measures to protect your data, including:

- Passwords hashed with **bcrypt**
- CSRF tokens on all state-changing requests
- Rotating remember-me tokens
- TOTP two-factor authentication (optional, user-initiated)
- HTTPS enforced via Nginx
- DDoS protection and network-level security via **Cloudflare**
- Private error logging (Discord webhook, operator-only)

No system is 100% secure. In the event of a data breach that poses a significant risk to your rights and freedoms, we will notify affected users and the relevant authority (CNIL) as required by GDPR.

---

## 11. Minors {#11-minors}

plrsys is not directed at children under the age of 13. We do not knowingly collect personal data from children under 13. If you believe a minor has provided us with personal data, please contact us and we will delete it promptly.

Users between 13 and 16 years of age may require parental consent under applicable law.

---

## 12. Changes to this policy {#12-changes-to-this-policy}

We may update this Privacy Policy from time to time. When we do, we will update the "Last updated" date at the top of this page.

For significant changes, we will make a reasonable effort to notify registered users (e.g. via an in-app notice). Continued use of plrsys after a policy update constitutes acceptance of the revised policy.

---

## 13. Contact {#13-contact}

For any questions, data requests, or privacy concerns:

**Sarxzer** / **plrsys Team**
Email: [sarxzer@sarxzer.xyz](mailto:sarxzer@sarxzer.xyz)

You may also reach us via the plrsys platform if you are a registered user.