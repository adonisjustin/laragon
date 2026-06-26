# Laragon Local Development Environment — Full Setup Guide

> **Author:** Adonis  
> **Purpose:** Complete step-by-step guide to rebuild the full Laragon dev environment  
> (PHP, Apache, MySQL, MailHog, custom landing page) after a fresh Windows install.

---

## Table of Contents

1. [System Requirements](#1-system-requirements)
2. [Download Everything First](#2-download-everything-first)
3. [Install Laragon](#3-install-laragon)
4. [Configure Laragon Settings](#4-configure-laragon-settings)
5. [Set Up the Custom Landing Page](#5-set-up-the-custom-landing-page)
6. [Install & Configure MailHog](#6-install--configure-mailhog)
7. [Configure PHP to Use MailHog](#7-configure-php-to-use-mailhog)
8. [Auto-Start MailHog Silently on Login](#8-auto-start-mailhog-silently-on-login)
9. [Configure Laravel Projects for MailHog](#9-configure-laravel-projects-for-mailhog)
10. [Test Everything](#10-test-everything)
11. [Quick Reference Card](#11-quick-reference-card)
12. [Troubleshooting](#12-troubleshooting)

---

## 1. System Requirements

| Item | Minimum |
|------|---------|
| OS | Windows 10 / 11 (64-bit) |
| RAM | 4 GB (8 GB recommended) |
| Disk | 2 GB free for Laragon + projects |
| Account | Windows user with Admin rights |

---

## 2. Download Everything First

Download all of these before starting so you have everything offline:

| Tool | URL | File to grab |
|------|-----|-------------|
| **Laragon Full** | https://laragon.org/download/ | `laragon-wamp.exe` (Full version) |
| **MailHog** | https://github.com/mailhog/MailHog/releases | `MailHog_windows_amd64.exe` |
| **VS Code** *(optional)* | https://code.visualstudio.com | `VSCodeSetup-x64.exe` |
| **Git** *(optional)* | https://git-scm.com/download/win | `Git-x.x.x-64-bit.exe` |

> **Important:** Always download the **Full** version of Laragon — it includes Apache, PHP, MySQL, and phpMyAdmin pre-bundled.

---

## 3. Install Laragon

1. Run `laragon-wamp.exe` as Administrator
2. Accept the default install path: `C:\laragon`
   - Do **not** change this path — many internal references depend on it
3. Click through the installer — defaults are fine
4. Launch Laragon when the installer finishes

**After first launch you should see:**
- The Laragon tray icon (bottom-right taskbar)
- Apache and MySQL showing as green/running

---

## 4. Configure Laragon Settings

### 4a. Enable Auto-Start

Right-click the Laragon tray icon → **Preferences**:

| Setting | Value |
|---------|-------|
| Auto start on startup | ✅ Checked |
| Auto-start app | ✅ Checked |
| Silent on startup | ✅ Checked (optional, hides the UI on boot) |

### 4b. Enable Pretty URLs (.test domains)

Right-click tray → **Laragon** → **Preferences** → **Services & Ports**:

- Apache port: `80`
- MySQL port: `3306`

Right-click tray → **Apache** → **mod_rewrite** → make sure it is enabled (tick).

> **How `.test` domains work:** Laragon auto-creates a virtual host for every folder inside `C:\laragon\www`. A folder named `myapp` is instantly available at `http://myapp.test` — no extra config needed.

### 4c. PHP Version

Right-click tray → **PHP** → select the version you need.  
Laragon supports switching PHP versions without reinstalling.

---

## 5. Set Up the Custom Landing Page

This replaces the default Laragon homepage with a Windows-style dashboard showing all your projects with quick-launch links.

**Create the file** at:

```
C:\laragon\www\index.php
```

Paste the full contents of `index.php` from this repo (the Windows 11 Fluent Design dashboard). It auto-detects:

- Laravel projects (looks for `artisan` file or `app/` + `routes/` folders)
- WordPress projects (looks for `wp-config.php` or `wp-login.php`)
- Plain PHP projects (everything else)

No configuration needed — just save the file and visit `http://localhost`.

---

## 6. Install & Configure MailHog

MailHog is a fake SMTP server. It catches all outgoing emails from your local apps and displays them in a browser UI instead of actually sending them.

### 6a. Place the Executable

1. Rename `MailHog_windows_amd64.exe` → `MailHog.exe`
2. Create the folder `C:\laragon\bin\mailhog\` if it doesn't exist
3. Move `MailHog.exe` into it:

```
C:\laragon\bin\mailhog\MailHog.exe
```

### 6b. Create the Silent Launcher Script

Create a new file at:

```
C:\laragon\bin\mailhog\mailhog.vbs
```

Paste this exact content:

```vbscript
Set WshShell = CreateObject("WScript.Shell")
WshShell.Run "C:\laragon\bin\mailhog\MailHog.exe", 0, False
```

> The `0` parameter means `SW_HIDE` — it launches MailHog with no visible window.  
> Never run `MailHog.exe` directly at startup or you will always get a console window.

---

## 7. Configure PHP to Use MailHog

### 7a. Open php.ini

Right-click the Laragon tray icon → **PHP** → **php.ini**

### 7b. Find and update the `[mail function]` section

Search for `[mail function]` and replace the whole block with:

```ini
[mail function]
SMTP = localhost
smtp_port = 1025
sendmail_path = "C:/laragon/bin/mailhog/MailHog.exe sendmail --smtp-addr=localhost:1025"
```

> Use **forward slashes** in the path even on Windows — PHP requires it.

### 7c. Restart Apache

Right-click tray → **Apache** → **Restart**  
Or click the **Reload** button in the Laragon UI.

---

## 8. Auto-Start MailHog Silently on Login

This makes MailHog start automatically and silently every time you log into Windows.

### Step 1 — Open Task Scheduler

Press `Win + R` → type `taskschd.msc` → Enter

### Step 2 — Create a New Task

Click **Create Task** (not "Basic Task") in the right Actions panel.

#### General tab:
| Field | Value |
|-------|-------|
| Name | `MailHog` |
| Run only when user is logged on | ✅ Selected |
| Run with highest privileges | ✅ Checked |
| Hidden | ✅ Checked |
| Configure for | Windows 10 |

#### Triggers tab:
1. Click **New**
2. Begin the task: **At log on**
3. Specific user: your Windows account (e.g. `DESKTOP-XXXXX\adonis`)
4. Delay task for: `10 seconds` (gives Windows time to fully boot)
5. Click OK

#### Actions tab:
1. Click **New**
2. Action: **Start a program**
3. Program/script:
```
wscript.exe
```
4. Add arguments:
```
"C:\laragon\bin\mailhog\mailhog.vbs"
```
5. Click OK

#### Conditions tab:
| Setting | Value |
|---------|-------|
| Start only if AC power | ❌ Unchecked |
| Start only if network available | ❌ Unchecked |

#### Settings tab:
| Setting | Value |
|---------|-------|
| Allow task to be run on demand | ✅ Checked |
| If task fails, restart every | 1 minute, up to 3 times |

### Step 3 — Save and Test

Click **OK** to save.  
In the task list, right-click `MailHog` → **Run**.  
Open your browser and visit `http://localhost:8025` — the inbox should load with no console window anywhere.

---

## 9. Configure Laravel Projects for MailHog

In every Laravel project's `.env` file, set these values:

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Then clear the config cache:

```bash
php artisan config:clear
```

---

## 10. Test Everything

### Test 1 — Laragon is running

Visit `http://localhost` — you should see your custom project dashboard.

### Test 2 — .test domains work

Create a test folder:

```
C:\laragon\www\testapp\index.php
```

With any content, e.g. `<?php echo "Hello from testapp"; ?>`

Visit `http://testapp.test` — it should load instantly with no extra config.

### Test 3 — phpMyAdmin is accessible

Visit `http://localhost/phpmyadmin`  
Default credentials: username `root`, password is blank.

### Test 4 — MailHog is running silently

Visit `http://localhost:8025` — the MailHog inbox UI should load.  
Check Task Manager (`Ctrl + Shift + Esc`) → there should be **no** console window for MailHog.

### Test 5 — Email sending works in Laravel

```bash
php artisan tinker
```

```php
Mail::raw('Test email from Laragon', function($m) {
    $m->to('test@test.com')->subject('MailHog Test');
});
```

Then check `http://localhost:8025` — the email should appear in the inbox.

### Test 6 — Check all services in Task Scheduler

Open `taskschd.msc` → find `MailHog` task → Status should say **Running**.

---

## 11. Quick Reference Card

| What | URL / Path |
|------|------------|
| Local homepage | `http://localhost` |
| phpMyAdmin | `http://localhost/phpmyadmin` |
| MailHog inbox | `http://localhost:8025` |
| MailHog SMTP port | `1025` |
| All projects folder | `C:\laragon\www\` |
| Laragon root | `C:\laragon\` |
| PHP ini file | `C:\laragon\bin\php\php-x.x.x\php.ini` |
| Apache config | `C:\laragon\etc\apache2\apache2.conf` |
| MySQL data | `C:\laragon\data\mysql\` |
| MailHog exe | `C:\laragon\bin\mailhog\MailHog.exe` |
| MailHog VBS launcher | `C:\laragon\bin\mailhog\mailhog.vbs` |
| Custom landing page | `C:\laragon\www\index.php` |

**Default MySQL credentials:**

```
Host:     127.0.0.1
Port:     3306
Username: root
Password: (blank)
```

---

## 12. Troubleshooting

### `.test` domain not loading

- Make sure Laragon is running (check tray icon)
- Right-click tray → **Apache** → **Restart**
- Check that Windows hosts file isn't blocking it: `C:\Windows\System32\drivers\etc\hosts`
- Try flushing DNS: open CMD as Admin → `ipconfig /flushdns`

### MailHog terminal window keeps showing

- Make sure Task Scheduler is running `wscript.exe` with the `.vbs` file — **not** `MailHog.exe` directly
- Open task → Actions tab → confirm it says `wscript.exe` in Program field

### MailHog not starting on reboot

- Open `taskschd.msc` → check the task Status
- Right-click the task → **Run** manually to test
- Make sure the path in `mailhog.vbs` matches exactly where `MailHog.exe` is saved
- Check the **History** tab on the task for error codes

### Emails not appearing in MailHog

- Confirm MailHog is running: visit `http://localhost:8025`
- Confirm `php.ini` has the correct SMTP settings (Section 7)
- Confirm Apache was restarted after editing `php.ini`
- In Laravel: confirm `.env` has `MAIL_PORT=1025` and run `php artisan config:clear`

### phpMyAdmin showing access denied

- Username: `root` — Password: leave **blank**
- If it still fails, right-click tray → MySQL → Reset root password

### Port conflict (something else using port 80 or 3306)

- Right-click tray → Preferences → Services & Ports → change Apache to `8080` or MySQL to `3307`
- Common culprits: IIS, Skype (port 80), another MySQL install

---

*Last updated: June 2026 — Adonis's personal dev environment*
