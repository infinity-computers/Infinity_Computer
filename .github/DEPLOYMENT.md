# Auto-Deployment Setup Guide

This project supports **two complementary approaches** for auto-deploying when
code is pushed to the `main` branch:

---

## Option A — GitHub Webhook (recommended for simple shared hosting)

The `deploy.php` script in the repository root acts as a webhook endpoint.
GitHub calls it over HTTPS whenever you push, and it runs `git pull` on the
server.

### Steps

1. **Copy `deploy.php` to your web server's document root** (if you're deploying
   the whole repository there, this is already done).

2. **Set a strong secret** — open `deploy.php` and replace the placeholder
   value of `DEPLOY_SECRET`:
   ```php
   define('DEPLOY_SECRET', 'replace_with_a_long_random_string');
   ```
   Alternatively, set the `DEPLOY_SECRET` environment variable on your server.

3. **Ensure the web-server user can run `git pull`** inside the document root.
   On most Linux servers this means:
   ```bash
   # Allow the www-data user to read the .git directory
   sudo chown -R www-data:www-data /var/www/html/Infinity_Computer
   ```

4. **Register the webhook in GitHub:**
   1. Open your repository on GitHub.
   2. Go to **Settings → Webhooks → Add webhook**.
   3. Fill in the form:
      | Field | Value |
      |-------|-------|
      | Payload URL | `https://your-domain.com/deploy.php` |
      | Content type | `application/json` |
      | Secret | *(same value as `DEPLOY_SECRET`)* |
      | Which events | **Just the push event** |
   4. Click **Add webhook**.

5. Push a commit to `main` and check the **Recent Deliveries** tab on the
   webhook page to confirm GitHub received a `200 OK` response.

---

## Option B — GitHub Actions (recommended for VPS / cloud servers)

The workflow file `.github/workflows/deploy.yml` connects to your server over
SSH and runs `git pull` automatically after every push to `main`.

### Prerequisites

- SSH access to your production server.
- The server already has a clone of this repository.

### Steps

1. **Generate a dedicated SSH key pair** (do this on your local machine):
   ```bash
   ssh-keygen -t ed25519 -C "github-deploy" -f ~/.ssh/github_deploy
   ```

2. **Add the public key to the server:**
   ```bash
   ssh-copy-id -i ~/.ssh/github_deploy.pub user@your-server
   ```

3. **Add the private key and other values as GitHub Secrets:**
   Go to **Settings → Secrets and variables → Actions → New repository secret**
   and create each of the following:

   | Secret name | Value |
   |-------------|-------|
   | `SSH_PRIVATE_KEY` | Contents of `~/.ssh/github_deploy` (private key) |
   | `SSH_HOST` | Hostname or IP of your server |
   | `SSH_USER` | SSH username on your server |
   | `SSH_PORT` | SSH port (leave blank to use `22`) |
   | `DEPLOY_PATH` | Absolute path to the repo on the server, e.g. `/var/www/html/Infinity_Computer` |

4. Push a commit to `main`. GitHub Actions will run the **Auto Deploy**
   workflow and SSH into your server to pull the latest code.

5. Check the **Actions** tab in your repository to see the workflow result.

---

## Choosing between Option A and Option B

| | Option A (Webhook) | Option B (GitHub Actions) |
|---|---|---|
| Hosting type | Shared hosting, cPanel | VPS, cloud, Dedicated |
| Requires SSH access | No | Yes |
| Deployment log | `deploy.log` on the server | GitHub Actions run log |
| Extra configuration | Secret in `deploy.php` | 5 GitHub Secrets |
