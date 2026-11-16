# CoreCMS server-side webhook deployment

CoreCMS uses the established server-pulls-from-GitHub deployment model. GitHub Actions does not upload files over FTP/SFTP. A signed GitHub webhook tells the server to fetch `main`, validate PHP syntax, back up the current release, and synchronize the new code.

## Deployment target used for the first test instance

- Repository: `gokhanbdag-web/CoreCMS` (public)
- Branch: `main`
- Live directory: `/home/kodvadisi.com.tr/public_html/corecms`
- Staging checkout: `/opt/corecms-staging`
- Private deployment files: `/opt/corecms-deploy`
- PHP CLI: `/usr/local/lsws/lsphp85/bin/php`
- Webhook URL: `https://kodvadisi.com.tr/corecms/deploy-hook.php`

The live directory is a deployment target only. It is not a fixed application base URL. CoreCMS itself must remain installable at a domain root, a subdirectory of any depth, or a subdomain. The runtime must derive its public base path from the current installation location; do not hardcode `/corecms` into application routes or assets.

## Server-only files

Create `/opt/corecms-deploy/deploy.env` from `deploy/deploy.env.example`, then create:

- `/opt/corecms-deploy/webhook.secret`: a long random secret shared with the GitHub webhook.
- `/opt/corecms-deploy/deploy.sh`: copy of `deploy/deploy.sh`, mode `750`.
- `/opt/corecms-deploy/deploy.env`: mode `600`.
- `/opt/corecms-deploy/webhook.secret`: mode `600`.
- `/opt/corecms-deploy` and `/opt/corecms-staging`: owned by the deployment account and not writable by other users.

Generate a dedicated read-only GitHub deploy key for this repository at `/home/kodva7153/.ssh/corecms_deploy`. Add its public key to this repository as a read-only Deploy Key. Do not put the private key or webhook secret in Git.

Install `deploy-hook.php` as `/home/kodvadisi.com.tr/public_html/corecms/deploy-hook.php`, owned by the site account and not writable by other users. The hook must remain in place between deployments; the deploy script explicitly excludes it from rsync deletion.

## GitHub webhook

In repository Settings → Webhooks, add the URL above, use the exact same random secret stored in `webhook.secret`, select **application/json**, and subscribe to **Just the push event**. GitHub's ping request is also accepted for connectivity testing.

The receiver validates `X-Hub-Signature-256`, limits request bodies to 1 MiB, verifies the repository identity, and only deploys `refs/heads/main`. It starts the deployment in the background and returns HTTP 202. The deploy script uses a lock to prevent overlapping runs.

## Deployment safety

- PHP lint runs against staging before the live directory is modified.
- A timestamped tar backup is created before synchronization; the ten newest backups are retained.
- `config.php`, `storage/`, `uploads/`, `deploy-hook.php`, `.git/`, and `.github/` are excluded from deletion/replacement.
- The deployment script never runs database migrations. Schema upgrades must use CoreCMS's authenticated, versioned migration process.
- A failed PHP lint leaves the live application untouched.

## Important: install path independence

Do not derive the deployment target from the request URL. The server's `LIVE_DIR` is deployment configuration, while CoreCMS's public base URL is detected at runtime. When installing the same package elsewhere, only the server-side `LIVE_DIR` and webhook URL change; no application source code should need path edits.

## Current setup status

The repository contains the webhook receiver and deployment script templates. Server-side files, SSH deploy key, webhook secret, GitHub webhook registration, and a real deployment are separate operational steps and must be verified on the hosting account before calling deployment active.
