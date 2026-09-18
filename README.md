# Rechnerei Inquiries for Statamic

Forwards outgoing site emails (Statamic form notifications, auto-responders, etc.) to your Rechnerei inquiries inbox — without ever affecting the site's own email delivery.

## How it works

This addon listens to Laravel's `MessageSent` event, which fires **after** every outgoing email has already been handed to the mail transport — regardless of which Statamic form, notification, or app code sent it. Because it only observes mail that has already been sent, nothing it does can prevent, delay, or change the original email.

For every outgoing email it:

1. Skips it if the subject/body matches a configured "ignore" phrase (password resets, failed jobs, backups, ...).
2. Otherwise tries to extract a name, email address, phone number, and event date from the message's Reply-To header and body text.
3. If anything useful was found, forwards it to your Rechnerei inquiries endpoint — deferred until after the HTTP response has already been sent to the visitor (via `dispatch(...)->afterResponse()`), so a slow or unreachable Rechnerei API can never add latency to a form submission, and no queue worker is required.

Any error anywhere in this process is caught and logged, never allowed to bubble up into the request that triggered the email.

The settings screen itself is a real Statamic blueprint (toggle/text/textarea fieldtypes) rendered through Statamic's own `PublishContainer` Vue component via Inertia — the same rendering code every core CP screen uses, so it looks and behaves like a native Statamic settings page rather than a hand-rolled HTML form. This requires **Statamic 6+** (its Inertia-based Control Panel); the compiled JS is committed under `dist/`, so installing still only takes Composer — no `npm install`/build step needed.

## Requirements

- Statamic 6.0+
- PHP 8.1+

## Installation

```bash
composer require rechnerei/statamic-inquiries
```

(Or copy this directory into your site and `composer.json`'s `repositories`/`require` it as a local path package.)

## Setup

1. In Rechnerei, go to **Inquiries → Website integration** and copy the Endpoint URL and API Token.
2. In the Statamic Control Panel, open **Rechnerei Inquiries** in the Tools section, paste both in, and save.
3. Optionally use "Send test inquiry" to confirm the connection.

No per-form configuration is needed — every form that sends a notification email through Statamic is covered automatically.

## Where settings are stored

Settings are stored in `storage/app/rechnerei-inquiries/settings.json` — a plain file, not a database table, so installing/uninstalling this addon never requires a migration.

## Customizing what counts as a "service" email

Edit the "Ignore emails containing" list on the settings page. Each line is a case-insensitive substring match against the subject + body; wrap a line in slashes (e.g. `/invoice #\d+/i`) to use a full regular expression instead.
