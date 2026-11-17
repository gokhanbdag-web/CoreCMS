# Forms and email templates

The first forms/email milestone adds a native-PHP storage and validation layer. It does not require Composer or command-line setup.

## Form definitions

Forms are stored in `core_forms`. A form has a stable key, display name, optional description, notification recipient, success message, enabled flag and a JSON field definition.

Supported field types:
- `text`
- `email`
- `tel`
- `textarea`
- `select` (with an explicit option list)
- `checkbox`

Field keys are stable identifiers. Base labels and select option values are stored with the form. Locale-specific form names, descriptions, success messages, field labels and select option labels can be edited from the form editor after choosing an enabled language. Option values remain unchanged for validation and stored submissions, while only their displayed labels are translated. The submission manager validates required fields, email addresses, select options, value lengths and enabled locale codes. Unknown submitted fields are discarded rather than persisted.

Submissions are stored in `core_form_submissions` with the form ID, locale, validated payload and a simple `new` / `read` / `archived` status. Raw IP addresses and user-agent strings are not stored. The public endpoint stores a keyed SHA-256 hash of the remote address for rate limiting; hashes older than 24 hours are pruned during submissions.

## Email templates

Base templates are stored in `core_email_templates`. Locale-specific subject/body overrides are stored in `core_email_template_translations`. When a locale has no translation, the base template is returned.

Templates support simple placeholders such as `{{name}}`. Placeholder values are HTML-escaped before insertion. Template content should therefore be authored as HTML email content when markup is required.

## Public rendering and submissions

Add a standalone shortcode to a page body on its own paragraph:

`[[form:contact_request]]`

The form key must match an enabled form in the admin panel. The renderer escapes labels, options and feedback; emits CSRF-protected POST forms; and includes a visually hidden honeypot. Public submissions are validated by the same form manager used by the admin layer. A keyed SHA-256 hash of the remote address is used for a per-form limit of five attempts per ten minutes; old rate-limit records are pruned after 24 hours. The secret is derived from the database password in the server-side configuration, so no extra setup is required.

The rate-limit schema is migration `0025_form_submission_rate_limits.php`; public button/success translations are migration `0026_public_form_frontend_translations.php`. Apply pending core migrations from the admin dashboard before enabling public forms.

## Email notification delivery

When a form has a notification recipient configured, a successful submission creates a `core_mail_queue` item. The notification uses the localized `forms.submission_notification` email template when present; otherwise CoreCMS creates a localized safe HTML summary of the submitted fields. The template supports `{{form_name}}`, `{{submission_id}}`, `{{site_name}}` and `{{submission_data}}` placeholders. Placeholder values and field content are HTML-escaped.

Configure **Sender name** and **Sender email address** under Administration → Site settings. Delivery uses PHP's native `mail()` function, so no Composer package or SSH setup is required. The hosting provider must permit PHP mail and accept the configured sender address. Messages are attempted immediately; failures are retried after increasing delays (1 minute, 5 minutes, 15 minutes and 1 hour). After five failed attempts, the item is marked failed and can be retried from Administration → Email templates → Email delivery queue. Pending retries are processed when the admin area is visited.

The queue schema is migration `0027_mail_queue.php`; its admin translations are migration `0028_mail_queue_admin_translations.php`. Form field translations are stored by migration `0029_form_field_translations.php`; Turkish and English field-translation admin labels are seeded by migration `0030_form_translation_admin_labels.php`; form metadata translation guidance is seeded by migration `0031_form_metadata_translation_admin_labels.php`. Apply pending core migrations before using notifications or localized form labels.

## Remaining work

- SMTP transport support for hosts that disable PHP `mail()`
- Form names, descriptions, success messages, field labels and select option labels support per-language editing
- Retention controls and stronger concurrent rate-limit enforcement
