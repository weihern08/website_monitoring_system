# UptimeGuard - Website Monitoring System
Admin-only uptime monitor with Telegram alerts (UptimeRobot-style).

## 快速开始（XAMPP）
1. 把项目放在 `C:\xampp1\htdocs\website_monitoring_system`
2. 复制 `config/database.example.php` 为 `config/database.php`，填入数据库账号
3. 启动 XAMPP 的 Apache + MySQL
3. 打开 http://localhost/website_monitoring_system/
4. 安装向导里数据库一般是：`localhost` / `root` / 密码留空
5. 创建管理员账号并登录
6. 添加网站（例如 `https://www.google.com`）
7. 点 Dashboard 的 **Run checks now**，或按下面配置每分钟自动检测
8. 在 **Settings** 填写 Telegram Bot Token 和 Chat ID，即可在网站 DOWN / 恢复 UP 时收到通知

忘记密码：打开 `forgot-password.php`，恢复密钥在 `config/config.php` 的 `RECOVERY_KEY`。

## Features
- Admin login only (no public registration)
- Add / edit / pause / delete website monitors
- HTTP uptime checks with response time
- Green UP / Red DOWN / Yellow SLOW / Paused
- 24-hour uptime bar + 24h / 7d / 30d uptime %
- Full check logs and status-change history
- Telegram alerts only when status changes (no duplicate spam)
- Cron / Task Scheduler monitoring engine
- Search and filter (name, URL, UP/DOWN, today / last 7 days)
- Public status page (`status.php`) – no login required

## Folder structure
```
website_monitoring_system/
├── admin/                 Admin panel pages
├── assets/css & js        Simple UI
├── config/                Database + app config
├── cron/monitor.php       Monitoring engine
├── database/schema.sql    MySQL tables
├── includes/              Auth, Telegram, helpers, layout
├── install.php            XAMPP web installer
├── login.php
└── forgot-password.php
```

## 1. XAMPP setup
1. Copy this folder to `C:\xampp1\htdocs\website_monitoring_system`
2. Start **Apache** and **MySQL** in XAMPP
3. Open: http://localhost/website_monitoring_system/
4. The installer will appear. Typical XAMPP values:
   - Host: `localhost`
   - Database: `website_monitoring`
   - User: `root`
   - Password: empty
5. Create the admin username and password
6. Login at: http://localhost/website_monitoring_system/login.php

If you prefer phpMyAdmin:
1. Import `database/schema.sql`
2. Edit `config/database.php`
3. Insert an admin row with a hashed password, then create `config/installed.lock`
4. Easier path: just use `install.php`

## 2. Add monitors (like UptimeRobot)
In **Monitors → Add website**:
- Name: `Google`
- URL: `https://www.google.com`
- Interval: `5` minutes

Use **Check now** or **Dashboard → Run checks now** for an immediate result.
Color meaning:
- Green = UP (HTTP 200-399)
- Red = DOWN (timeout, connection error, or HTTP 400+)
- Yellow = UP but slower than the threshold
- Grey = Paused

## 3. Telegram bot
1. Open Telegram and chat with [@BotFather](https://t.me/BotFather)
2. Send `/newbot` and follow the steps
3. Copy the bot token
4. Open your new bot and send it any message (e.g. `hello`)
5. In this app go to **Settings**
6. Paste the token, tick **Enable Telegram alerts**, Save
7. Click **Find my chat ID**, copy your Chat ID, Save
8. Click **Send test message**

Alert types:
- `ALERT: Website DOWN` — site was up (or never checked) and is now down
- `RECOVERY: Website back UP` — site recovered
- `WARNING: Slow response detected` — still up, but slower than the threshold

The engine does **not** send another DOWN alert while the site stays down.

## 4. Cron / automatic monitoring
The engine only checks a site when its own interval has elapsed. Run the engine **every 1 minute**.

### Windows Task Scheduler (XAMPP)
1. Open Task Scheduler → Create Basic Task
2. Trigger: Daily, then in Properties set:
   - Repeat task every: `1 minute`
   - For a duration of: `Indefinitely`
3. Action: Start a program
   - Program: `C:\xampp1\php\php.exe`
   - Arguments: `C:\xampp1\htdocs\website_monitoring_system\cron\monitor.php`
4. Confirm PHP path in your XAMPP folder

Or call the web URL every minute (secret is in Settings):

`http://localhost/website_monitoring_system/cron/monitor.php?key=YOUR_CRON_SECRET`

### Linux crontab
```
* * * * * php /var/www/html/website_monitoring_system/cron/monitor.php
```

Without cron you can still click **Run checks now** on the dashboard.

## 5. Forgot password
Open `forgot-password.php`.
Use the admin username plus the recovery key in `config/config.php`:

```
define('RECOVERY_KEY', 'MONITOR-RESET-2026');
```

Change that key after install.

## 6. Default ports / PHP
- Apache: http://localhost/website_monitoring_system/
- PHP cURL must be enabled (`extension=curl` in `php.ini`)
- Timezone: `Asia/Kuala_Lumpur` in `config/config.php`

To reinstall: delete `config/installed.lock`, drop the `website_monitoring` database in phpMyAdmin, then open the site again.

## System flow
1. Admin logs in
2. Admin adds websites
3. Cron (or Run checks now) hits each due website
4. Result is stored in `logs`
5. Current status is compared with the previous status
6. Telegram is notified only on a change
7. Dashboard / logs / alerts update

## Security notes
- There is only one admin role
- Passwords are hashed with `password_hash()`
- Sessions are used after login
- `config/` and `includes/` are blocked by `.htaccess`
- The cron script requires a secret key when opened in a browser
