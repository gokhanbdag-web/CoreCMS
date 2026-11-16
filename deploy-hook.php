<?php
declare(strict_types=1);

/**
 * CoreCMS GitHub webhook receiver.
 * Keep the webhook secret and deployment configuration outside the public web root.
 */
const CORECMS_DEPLOY_CONFIG = '/opt/corecms-deploy/deploy.env';
const CORECMS_WEBHOOK_SECRET = '/opt/corecms-deploy/webhook.secret';
const CORECMS_DEPLOY_SCRIPT = '/opt/corecms-deploy/deploy.sh';
const CORECMS_DEPLOY_LOG = '/opt/corecms-deploy/deploy.log';
const CORECMS_EXPECTED_REPOSITORY = 'gokhanbdag-web/CoreCMS';
const CORECMS_EXPECTED_BRANCH = 'refs/heads/main';

function respond(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $message;
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, 'Method Not Allowed');
}

$length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length < 1 || $length > 1048576) {
    respond(413, 'Invalid payload size');
}

$secret = @file_get_contents(CORECMS_WEBHOOK_SECRET);
if ($secret === false || trim($secret) === '') {
    error_log('[CoreCMS deploy] Webhook secret is missing.');
    respond(500, 'Webhook is not configured');
}

$body = file_get_contents('php://input');
if ($body === false || $body === '') {
    respond(400, 'Empty payload');
}

$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$expected = 'sha256=' . hash_hmac('sha256', $body, trim($secret));
if (!is_string($signature) || !hash_equals($expected, $signature)) {
    respond(403, 'Invalid signature');
}

$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
if ($event === 'ping') {
    respond(200, 'CoreCMS webhook is alive');
}
if ($event !== 'push') {
    respond(202, 'Event ignored');
}

$payload = json_decode($body, true);
if (!is_array($payload)) {
    respond(400, 'Invalid JSON payload');
}
if (($payload['repository']['full_name'] ?? '') !== CORECMS_EXPECTED_REPOSITORY) {
    respond(403, 'Repository mismatch');
}
if (($payload['ref'] ?? '') !== CORECMS_EXPECTED_BRANCH) {
    respond(202, 'Branch ignored');
}

if (!is_file(CORECMS_DEPLOY_CONFIG) || !is_executable(CORECMS_DEPLOY_SCRIPT)) {
    error_log('[CoreCMS deploy] Deployment files are missing or not executable.');
    respond(500, 'Deployment is not configured');
}
if (!function_exists('exec')) {
    respond(500, 'Background process support is unavailable');
}

// Preserve the live directory and file permissions exactly as configured.
// The existing deployment script remains the only deployment mechanism.
$worker = '/bin/bash ' . escapeshellarg(CORECMS_DEPLOY_SCRIPT);
$command = 'nohup /bin/bash -c ' . escapeshellarg($worker)
    . ' >> ' . escapeshellarg(CORECMS_DEPLOY_LOG)
    . ' 2>&1 < /dev/null & echo $!';
$output = [];
$exitCode = 1;
exec($command, $output, $exitCode);
if ($exitCode !== 0 || empty($output)) {
    error_log('[CoreCMS deploy] Failed to start deployment process.');
    respond(500, 'Could not start deployment');
}

header('X-CoreCMS-Deploy-Pid: ' . preg_replace('/[^0-9]/', '', (string)end($output)));
respond(202, 'Deployment accepted');
