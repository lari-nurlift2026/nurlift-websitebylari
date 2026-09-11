# Nurlift site V1 — Deploy

## Files
- `/index.html`: Portuguese homepage
- `/en/index.html`: English homepage
- `/styles.css`: visual styles
- `/script.js`: smooth scrolling
- `/robots.txt`, `/sitemap.xml`, `/llms.txt`, `/ai.json`: discovery/SEO/GEO support
- `/lovable_prompt.txt`: paste into a new Lovable project

## Plesk — fastest path (File Manager)
1. Back up the current website.
2. In Plesk: Websites & Domains > nurlift.com > Files.
3. Open `httpdocs` (default document root unless your hosting settings show another root).
4. Rename the current site folder/files or download a backup before replacing anything.
5. Upload the contents of this package into `httpdocs`, preserving the `/en/` folder.
6. Confirm that `index.html` is at `httpdocs/index.html` and English is at `httpdocs/en/index.html`.
7. Open https://nurlift.com/ and https://nurlift.com/en/.
8. Verify SSL and preferred domain in Hosting Settings.

## Plesk — SSH option
Replace USER and HOST with the hosting credentials shown by Plesk.

```bash
ssh USER@HOST
cd httpdocs
mkdir -p ../backup-$(date +%Y%m%d-%H%M)
cp -a . ../backup-$(date +%Y%m%d-%H%M)/
```

From your local machine, upload the extracted site:

```bash
scp -r nurlift-site-v1/* USER@HOST:httpdocs/
```

If you are already inside the Plesk webspace through chrooted SSH, `httpdocs` is commonly visible directly under your home/root.

## Lovable
Lovable currently does not start a new project by importing an existing GitHub/codebase. Create a NEW Lovable project and paste the contents of `lovable_prompt.txt` as the first prompt.

After Lovable generates the project:
1. Compare it with this HTML/copy.
2. Ask Lovable to preserve the information architecture and PT/EN routes.
3. Connect GitHub in Settings > Connectors > GitHub if you want version control/self-hosting.
4. Lovable syncs its project to GitHub; you can then deploy externally or continue in Lovable.

## Recommended production workflow
For this immediate MVP, the static Plesk version is the fastest route.
Use Lovable in parallel to iterate on the visual/UI version. When Lovable's version is approved, connect it to GitHub and replace the static MVP with the built production output or host Lovable directly.

## Before production
- Confirm the real commercial contact email; this V1 currently uses `hello@nurlift.com` as a placeholder.
- Add legal/privacy links.
- Add analytics/tag manager if needed.
- Add final logo/assets and favicon.
- Validate all claims about Dropper capabilities before publishing.

## Contact Backend — QA-049 / QA-051 / QA-052 / QA-053

This foundation does not add or activate frontend forms. The four future forms
submit JSON to `/api/contact.php`. Do not deploy or enable it before review.
No production change has been performed by this QA.

### Confirmed environment and private deployment layout

- PHP 8.2.33, PHP-FPM served by Apache in Plesk.
- Actual public root: `/var/www/vhosts/idigital.net.br/nurlift.com/public`.
  This supersedes the generic `httpdocs` example above for this deployment.
- Real config: `/var/www/vhosts/idigital.net.br/private/nurlift-contact/config.php`.
- Private state root: `/var/www/vhosts/idigital.net.br/private/nurlift-contact`.
- Under that private root: `rate-limit/`, `idempotency/`, `logs/`, `vendor/`.
- Never upload runtime state, credentials or Composer dependencies into public/.
- The endpoint uses the fixed private config path, not a client-controlled path.

### Dependencies

There was no PHP/Composer/vendor setup in the repository. PHPMailer via Composer
is the only application dependency, for maintained authenticated SMTP support;
no custom SMTP protocol implementation, framework, database or Redis is used.
Official reference: https://github.com/PHPMailer/PHPMailer

Resolve/install with Composer on a trusted PHP 8.2 build environment. Use the
committed composer.lock (PHPMailer 7.1.1). Run `composer validate --strict`,
`composer audit`, `composer install --no-dev --prefer-dist --no-interaction --no-plugins --no-scripts`
and `composer test` before packaging. Do not run dependency updates
on the production server. Deploy the resulting `vendor/` into the PRIVATE root,
where the endpoint expects `vendor/autoload.php`. Keep dependency manifests and
lockfile in the deployment record. Recheck platform requirements on PHP 8.2.33.

### Manual private configuration (authorized deployment only)

1. Create config.php manually in the private root using
   `config/contact-config.example.php` as a structural template. The committed
   template deliberately has placeholders; it is not a working production config.
2. Set these confirmed non-secret values privately:
   - smtp_host: `nurlift.com`
   - smtp_port: `465`
   - smtp_username: `contato@nurlift.com`
   - smtp_encryption: `ssl` (implicit TLS, certificate verification enabled)
   - mail_from / mail_to: `contato@nurlift.com`
   - allowed_origin: `https://nurlift.com` (exact origin, no trailing slash)
   - state_dir: `/var/www/vhosts/idigital.net.br/private/nurlift-contact`
3. Supply the SMTP password manually. Generate an independent cryptographically
   random HMAC secret of at least 32 bytes (for example a 64-character hex value).
   Do not send either value through frontend code, Git, screenshots or logs.
4. Assign ownership to the confirmed PHP-FPM account. Use 0700 for private state
   directories and 0600 for config/state/log files. Never use 0777. Verify actual
   ownership with the host administrator; do not invent a production username.
5. Create the three runtime subdirectories. The endpoint can create them with
   restrictive permissions, but production setup should verify permissions first.
6. Deploy reviewed PHP files under public/api and the mail dependency privately.
   Exclude tests and the example configuration from the public deployment package.
7. Verify HTTPS, PHP execution (never source download), Origin and Fetch Metadata
   headers, body-size handling, safe errors, and private-directory inaccessibility.
8. Only during an authorized live test, submit synthetic details and verify the
   message actually arrives at contato@nurlift.com, including spam handling,
   From/Reply-To, all attribution fields and timestamp. SMTP acceptance alone is
   not proof of inbox delivery. Do not disable certificate checks to fix SMTP.

The current repository's static upload command is insufficient for this backend:
private config and private vendor installation are separate manual steps.

### Contract and attribution

POST application/json, maximum 8192 bytes. Exactly nine string keys:
`name`, `email`, `phone`, `subject`, `source`, `page_language`, `page`,
`idempotency_key`, `website` (empty honeypot).

Allowed tuples:
- `Nurlift PT`, `pt`, `/`
- `Nurlift EN`, `en`, `/en/`
- `Dropper PT`, `pt`, `/dropper/`
- `Dropper EN`, `en`, `/en/dropper/`

Name: 2–100 Unicode code points. Subject: 2–200. Both trimmed and single-line.
Email: validated, max 254 bytes, local part preserved, domain lowercased.
Phone: max 32 characters, international formatting allowed, 7–15 ASCII digits.
Controls are rejected before trimming. No extra timestamp/recipient fields.
Idempotency key: 22–80 ASCII letters/digits/underscore/hyphen; future frontend
must generate a fresh random key (UUID is suitable), keep it in memory, and reuse
it for retries of the same submission. Never treat the key as authentication.

The server generates UTC timestamp and random request ID. Attribution tuples are
validated for consistency; they cannot prove the visitor's actual browsing path.
Mail is plain text, with fixed server-generated subject and configured recipient.
Visitor email is Reply-To only. No IP or user agent is included in the lead.

Responses are JSON, UTF-8, no-store: 200 success with language/message/request_id;
422 validation; 429 rate limit (Retry-After: 600); 405 method; 415 content type;
403 origin; 413 size; 503 operational failure. Errors never include submitted
fields, exceptions or SMTP detail. Errors before a validated language is available
use Portuguese. Subject detection counts distinct PT/EN indicators, requires two
and a margin of two, otherwise falls back to page_language. No external API.

### Abuse, concurrency and temporary state

Origin must exactly match the configured origin; missing/null origins fail.
Fetch Metadata, if present, must be same-origin. No cross-origin allow headers
are emitted. JSON-only avoids cross-site simple form POSTs; origin restrictions
are not authentication and cannot prevent direct bot clients.

Five attempts per rolling ten minutes, after method/content/origin checks and
before payload validation. Invalid payloads and honeypots count. Only REMOTE_ADDR
is used, never client-supplied forwarded headers. Confirm what REMOTE_ADDR means
behind the actual Plesk proxy; shared proxy/NAT addresses can group visitors.
Do not add trust for X-Forwarded-For without a separately approved proxy policy.

Rate filenames contain HMAC-derived IP identifiers; state contains timestamps,
not raw IP. Idempotency stores HMAC payload digest, random request ID, expiry and
safe response, not field contents. State is protected by one stable flock inode
and atomic file replacements. SMTP runs outside the lock after persisting pending
state. Cleanup uses the same lock to avoid unlink/lock races. This assumes all
PHP workers share the same private filesystem with working flock semantics.

Completed same-key/same-payload returns the original result without another send;
different payload fails. Pending/uncertain delivery returns 503 rather than
sending again. Pending and completed state expires after 24 hours. After expiry,
the same key can send again: this is bounded duplicate protection, not exactly-once
SMTP. If transport times out after server acceptance, an operator must investigate
before asking a visitor to submit again. Secret rotation also invalidates existing
HMAC keys; coordinate it to avoid duplicate retries.

Cleanup runs on eligible requests: rate records expire after ten minutes,
idempotency after 24 hours; technical daily logs are cleaned after seven days.
These are technical defaults, not lead-retention periods. Without traffic, expired
files persist until cleanup runs. Schedule a daily private maintenance invocation
of State::cleanup if strict wall-clock deletion is required; never expose a public
cleanup URL. Bound traffic at hosting level if needed; no unavailable service is
assumed. Test locking and load on the actual shared filesystem before activation.

### Governance and operational ownership

Controller: Nurlift Tecnologia Para Publicidade Ltda., CNPJ 32.211.857/0001-47.
Privacy/contact channel: contato@nurlift.com. Purpose: respond to the request and
conduct the requested commercial follow-up. No automatic newsletter, advertising
audience or unrelated campaign use.

No separate lead database is created. Lead content is delivered to Nurlift's email
workflow. Restrict mailbox access to designated responders; restrict infrastructure
access to designated administrators. Designate who handles privacy requests without
assuming a particular person or job title. Prefer individual accounts and revoke
access when no longer needed. Do not routinely export leads; authorize necessary
exports and keep them in controlled corporate storage.

For access/correction/deletion requests: record receipt at contato@nurlift.com,
verify identity proportionately, locate relevant email/workflow records, assess
applicable retention exceptions, act and respond. Keep only necessary evidence
of handling. Retain leads only while necessary for response, the relevant business
relationship or legal/regulatory/evidentiary obligations. Backups may expire on a
separate cycle; restrict access and reapply deletions after restoration. Do not
promise immediate erasure from every backup or mail copy.

Technical application logs contain only request ID, UTC timestamp and category.
No name, email, phone, subject, body, credentials or idempotency token is logged.
Review host/SMTP logs separately: this implementation does not assert zero server
logging. Monitoring may record a generic failure when private logging is unavailable.

Privacy pages now list four fields and describe backend-to-email processing, while
still explicitly stating that forms are not yet available. When frontend forms are
approved and activated, update that availability statement in a separate reviewed
change. Cookie policies, trackers, UI, CSS and frontend JS remain unchanged.
