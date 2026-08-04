<?php
/**
 * GitHub Webhook Auto-Deploy Handler
 *
 * This script receives POST requests from GitHub's webhook service whenever
 * code is pushed to the configured branch. It verifies the HMAC-SHA256
 * signature using a shared secret, then runs `git pull` to update the server.
 *
 * Setup steps:
 *  1. Copy this file to the root of your web server document root.
 *  2. Set the DEPLOY_SECRET constant below to a long, random string.
 *  3. In your GitHub repository go to:
 *       Settings → Webhooks → Add webhook
 *     - Payload URL : https://your-domain.com/deploy.php
 *     - Content type: application/json
 *     - Secret      : (same value as DEPLOY_SECRET below)
 *     - Events      : Just the push event
 *  4. Make sure the web-server user has permission to run `git pull`
 *     inside the document root directory.
 */

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

/**
 * The same secret value you enter in GitHub → Settings → Webhooks → Secret.
 * Change this to a long, random string before deploying.
 */
$_deploySecret = getenv('DEPLOY_SECRET');
if ($_deploySecret === false || $_deploySecret === '') {
    // No environment variable set – fall back to the constant below.
    // IMPORTANT: replace this placeholder with a long, random string before
    // deploying, or set the DEPLOY_SECRET environment variable on the server.
    $_deploySecret = 'change_me_to_a_strong_random_secret';
    error_log('deploy.php WARNING: DEPLOY_SECRET environment variable is not set; using insecure default.');
}
define('DEPLOY_SECRET', $_deploySecret);
unset($_deploySecret);

/**
 * Only deploy when pushes arrive on this branch.
 * Typical values: 'refs/heads/main'  or  'refs/heads/master'
 */
define('DEPLOY_BRANCH', 'refs/heads/main');

/**
 * Absolute path to the repository root on the server.
 * Defaults to the directory that contains this file.
 */
define('REPO_PATH', __DIR__);

/**
 * Path to the log file. Set to '' to disable logging.
 */
define('LOG_FILE', __DIR__ . '/deploy.log');

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Append a timestamped message to the log file (if configured).
 */
function deploy_log(string $message): void
{
    if (LOG_FILE === '') {
        return;
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    $existed = file_exists(LOG_FILE);
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    // Restrict permissions to owner-read/write only so the log is not
    // world-readable when the file is created inside the document root.
    if (!$existed) {
        @chmod(LOG_FILE, 0600);
    }
}

/**
 * Send an HTTP response and terminate.
 */
function respond(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain');
    echo $message;
    exit;
}

// ---------------------------------------------------------------------------
// Request validation
// ---------------------------------------------------------------------------

// Only accept POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    deploy_log('Rejected: non-POST request from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    respond(405, 'Method Not Allowed');
}

// Read the raw request body.
$payload = file_get_contents('php://input');
if ($payload === false || $payload === '') {
    deploy_log('Rejected: empty payload');
    respond(400, 'Bad Request: empty payload');
}

// Verify the HMAC-SHA256 signature sent by GitHub.
$githubSignature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
if ($githubSignature === '') {
    deploy_log('Rejected: missing X-Hub-Signature-256 header');
    respond(401, 'Unauthorized: missing signature');
}

$expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, DEPLOY_SECRET);

if (!hash_equals($expectedSignature, $githubSignature)) {
    deploy_log('Rejected: signature mismatch');
    respond(401, 'Unauthorized: invalid signature');
}

// ---------------------------------------------------------------------------
// Event handling
// ---------------------------------------------------------------------------

$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';

// We only care about push events.
if ($event !== 'push') {
    deploy_log('Ignored event: ' . $event);
    respond(200, 'OK: event ignored');
}

// Decode the JSON payload.
$data = json_decode($payload, true);
if (!is_array($data)) {
    deploy_log('Rejected: invalid JSON payload');
    respond(400, 'Bad Request: invalid JSON');
}

// Only deploy for the configured branch.
$ref = $data['ref'] ?? '';
if ($ref !== DEPLOY_BRANCH) {
    deploy_log('Ignored push to branch: ' . $ref);
    respond(200, 'OK: branch ignored');
}

// ---------------------------------------------------------------------------
// Pull latest code
// ---------------------------------------------------------------------------

deploy_log('Deploying push to ' . $ref . ' by ' . ($data['pusher']['name'] ?? 'unknown'));

$command = sprintf(
    'cd %s && git fetch origin 2>&1 && git reset --hard origin/main 2>&1',
    escapeshellarg(REPO_PATH)
);

$output = shell_exec($command);

// Strip potentially sensitive content (file paths, author info) – keep only
// the first 500 characters and mask anything that looks like a path.
$safeOutput = substr(preg_replace('/\/\S+/', '[path]', (string) $output), 0, 500);
deploy_log('git output: ' . trim($safeOutput));

respond(200, 'Deployed successfully');
