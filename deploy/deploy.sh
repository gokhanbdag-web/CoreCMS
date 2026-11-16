#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

CONFIG="/opt/corecms-deploy/deploy.env"
[[ -r "$CONFIG" ]] || { echo "Missing deployment config: $CONFIG"; exit 1; }
# shellcheck disable=SC1090
source "$CONFIG"

: "${REPO_URL:?REPO_URL is required}"
: "${BRANCH:=main}"
: "${LIVE_DIR:?LIVE_DIR is required}"
: "${STAGING_DIR:?STAGING_DIR is required}"
: "${BACKUP_DIR:?BACKUP_DIR is required}"
: "${PHP_BIN:=/usr/local/lsws/lsphp85/bin/php}"
: "${DEPLOY_KEY:=/home/kodva7153/.ssh/corecms_deploy}"

DEPLOY_DIR="$(dirname "$CONFIG")"
LOG_FILE="$DEPLOY_DIR/deploy.log"
LOCK_FILE="$DEPLOY_DIR/deploy.lock"
mkdir -p "$STAGING_DIR" "$BACKUP_DIR" "$DEPLOY_DIR" "$(dirname "$LIVE_DIR")"
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  echo "A CoreCMS deployment is already running."
  exit 0
fi

export GIT_SSH_COMMAND="ssh -i $DEPLOY_KEY -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"
if [[ ! -d "$STAGING_DIR/.git" ]]; then
  git clone --branch "$BRANCH" --single-branch "$REPO_URL" "$STAGING_DIR"
fi
git -C "$STAGING_DIR" fetch --prune origin "$BRANCH"
git -C "$STAGING_DIR" checkout -f "$BRANCH"
git -C "$STAGING_DIR" reset --hard "origin/$BRANCH"

# Validate every PHP source file before touching the live directory.
while IFS= read -r -d '' file; do
  "$PHP_BIN" -d opcache.enable_cli=0 -l "$file" >/dev/null
done < <(find "$STAGING_DIR" -type f -name '*.php' \
  -not -path "$STAGING_DIR/.git/*" -print0)

# Keep a dated snapshot of the live application before deployment.
if [[ -d "$LIVE_DIR" ]]; then
  stamp="$(date -u +%Y%m%dT%H%M%SZ)"
  mkdir -p "$BACKUP_DIR"
  tar -czf "$BACKUP_DIR/corecms-$stamp.tar.gz" \
    -C "$(dirname "$LIVE_DIR")" "$(basename "$LIVE_DIR")"
fi

mkdir -p "$LIVE_DIR"
rsync -a --delete \
  --exclude='/.git/' \
  --exclude='/.github/' \
  --exclude='/config.php' \
  --exclude='/storage/' \
  --exclude='/uploads/' \
  --exclude='/deploy-hook.php' \
  "$STAGING_DIR/" "$LIVE_DIR/"

# Retain the ten newest deployment snapshots.
find "$BACKUP_DIR" -maxdepth 1 -type f -name 'corecms-*.tar.gz' \
  -printf '%T@ %p\n' | sort -nr | tail -n +11 | cut -d' ' -f2- | while IFS= read -r old; do
    [[ -n "$old" ]] && rm -f -- "$old"
  done

echo "CoreCMS deployment completed: $(date -u +%FT%TZ)"
