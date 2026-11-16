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

Field keys are stable identifiers. Labels are presentation text and can be localized in a later admin/editor phase. The submission manager validates required fields, email addresses, select options, value lengths and enabled locale codes. Unknown submitted fields are discarded rather than persisted.

Submissions are stored in `core_form_submissions` with the form ID, locale, validated payload and a simple `new` / `read` / `archived` status. No IP address or user-agent is stored by this foundation.

## Email templates

Base templates are stored in `core_email_templates`. Locale-specific subject/body overrides are stored in `core_email_template_translations`. When a locale has no translation, the base template is returned.

Templates support simple placeholders such as `{{name}}`. Placeholder values are HTML-escaped before insertion. Template content should therefore be authored as HTML email content when markup is required.

## Current milestone boundary

This commit provides schema, validation, persistence and regression tests. The following work remains before the feature is end-user complete:
- Admin form builder and submission inbox
- Admin email-template editor with locale selector
- Public form rendering and submission endpoint
- Mail transport configuration, delivery feedback and safe retry behavior
- Anti-spam/rate-limit controls and retention settings
