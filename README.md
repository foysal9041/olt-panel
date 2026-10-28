# OLT Panel

A web panel for running an ISP's network and back office in one place: OLT, switch and router monitoring with LibreNMS-style graphs, alerts, NTTN and latency checks, billing with emailed PDF invoices, accounts, inventory, tickets, tasks, and HR with attendance straight from ZKTeco fingerprint devices. The interface is in **English and বাংলা**.

Built with Laravel 12 (PHP 8.2+), MariaDB/MySQL, AdminLTE 3 and Chart.js.

**White-label, one installation per company.** Every company gets its own installation of the same code: its own server, domain, database, uploaded files and settings. Each company sets its own name, logos, colours, contact details and document text under **Settings → Company Branding**, without touching the code. The panel has no multi-company switching, and one installation never sees another's data.

---

## Features

| Module | What it does |
|---|---|
| **NOC** | OLT dashboard and records, zones, VLAN and IP management, NTTN links, support contacts |
| **Switches & routers (SNMP)** | Port status and traffic, CPU, memory, temperature and other sensors, port events. Alerts when a port runs above 85 % |
| **Graphs** | LibreNMS-style graphs: 1 h to 1 year or any custom range, zoom, CSV/PNG export, 1- or 5-minute averages |
| **Graph dashboards** | Your own dashboards of chosen interfaces and devices. Drag graphs into place, resize them by the corner, share by role |
| **Weathermaps** | Network maps with links coloured by live traffic |
| **Latency checker** | Ping graphs for chosen targets |
| **Alerts** | Telegram and WhatsApp alerts for OLTs, links, ports and devices going down |
| **HR & attendance** | Pushed live from ZKTeco F18 devices. Shifts with end times, office hours, late time and extra duty, overtime on off days, leave, absence report, HR letters, ID cards |
| **Accounts** | Income and expenses, daily cash book, salary sheet, zone settlement (Excel · bKash), bandwidth billing, net profit and partner shares. Invoices and money receipts can be emailed as PDFs |
| **Inventory** | Products, stock in and out, and company assets |
| **Tickets & tasks** | Support tickets, and tasks with a team board |
| **Users** | Per-module and sub-module permissions, zone-limited access, activity log |
| **Company branding** | Name, legal name, logos, favicon, colours, contact details, invoice-number prefix, footer and legal links. They appear on every page, invoice, receipt, letter, ID card and e-mail |

---

## Install — one command

On a fresh **Ubuntu 22.04 / 24.04** or **Debian 12** server, as a user with sudo:

```bash
curl -fsSL https://raw.githubusercontent.com/foysal9041/olt-panel/master/install.sh | sudo bash
```

The installer asks four things:

- the company's name
- the domain or IP for the panel
- the admin username
- the admin password (leave it blank to get a random one)

Then it sets up everything:

- nginx, PHP-FPM (8.2 or newer) with the extensions the panel needs (SNMP, GD, intl, …), MariaDB and Composer
- the code in `/var/www/olt-panel`, a database with its own user and a random password, and `.env`
- all database tables and the first admin user
- the scheduler, which runs every minute as cron and polls OLTs and switches, pings, and checks links and alerts
- an nginx site that answers on the server's IP as well. The ZKTeco devices push to `http://<server-ip>/iclock/…`.
- optionally, a free HTTPS certificate from Let's Encrypt

It ends with the address to open and the sign-in details. These are also saved in `/root/olt-panel-install.txt`, which only root can read.

### Options

Add options after `bash -s --`:

```bash
curl -fsSL https://raw.githubusercontent.com/foysal9041/olt-panel/master/install.sh \
  | sudo bash -s -- --domain=noc.example.com --ssl --email=you@example.com
```

| Option | Meaning |
|---|---|
| `--company="Acme Fiber"` | The company's name. Logos, colours and the rest are set later in the panel. |
| `--domain=noc.example.com` | Address the panel is opened on (default: the server's IP) |
| `--admin-user=admin` `--admin-pass=…` | The first admin (default: asked; a blank password gets a random one) |
| `--ssl --email=you@example.com` | HTTPS from Let's Encrypt. The domain must already point to the server, with port 80 open. |
| `--restore=/path/backup.tar.gz` | Move an existing panel here (see [Moving to another server](#moving-to-another-server)) |
| `--dir=/var/www/olt-panel` | Where the app is installed |
| `--branch=master` `--repo=URL` | Which code to install |
| `--version=v1.0.0` | Install, or update to, a particular release (a git tag or commit) |
| `--no-backup` | When updating: skip the automatic backup that is otherwise taken first |
| `--php=8.3` | PHP version (default: the system's, if 8.2+, else 8.3 from the PHP repository) |
| `-y` | No questions: defaults for everything not given |

Each option also works as an environment variable, for example `DOMAIN=… ADMIN_PASS=… sudo -E bash install.sh -y`.

From a copy of the repository the installer works the same way:

```bash
git clone https://github.com/foysal9041/olt-panel.git
cd olt-panel
sudo bash install.sh
```

---

## After installing

1. Open the address shown at the end and sign in. Change the admin password under **Profile**.
2. **Settings → Company Branding**:
   - names, address, phone, e-mail and website
   - logo, square icon and favicon
   - colours
   - invoice-number prefix, copyright and footer lines, privacy and terms links
3. **Settings → Email**: the SMTP mailbox used to send invoices and receipts to clients. It has a button for a test mail.
4. **Settings → Telegram** and **WhatsApp**: where alerts go, and the alert thresholds.
5. Add **zones**, then **OLTs**, **switches and routers** (SNMP v2c or v1 community), **NTTN links** and **latency targets**.
6. **HR & Attendance**:
   - Add employees with their device user ID.
   - Set **Duty Shifts** (start, end and grace period).
   - Set the weekly off day and the **Office Time** under Devices.
7. **ZKTeco F18 devices**: on the device, open *Menu → COMM → Cloud Server Setting*. Set *Enable Domain Name* to Off, the server address to this server's IP, and the port to **80**. Then add the device under **HR & Attendance → Devices** with its serial number.
8. **Users**: create accounts for staff and tick the modules each one can use.

---

## Company branding (white-label)

**Settings → Company Branding** is for admins, and for users given the *Company Branding* permission. It holds everything that belongs to the company rather than the product:

| Setting | Where it shows |
|---|---|
| Name, legal name, application title | Sidebar, browser title, sign-in page, documents, e-mails, copyright line |
| Logo, icon, favicon | Sidebar, sign-in page, letterheads, invoices and receipts (also as PDF), ID cards, e-mails, browser tab |
| Primary, secondary, accent and background colours | Buttons and links, sidebar and sign-in panel, the current menu item, page background, e-mail header |
| E-mail, accounts e-mail, phone, website, address | Letterheads, receipts, ID cards, HR letters, e-mail footer |
| Invoice-number prefix | New bandwidth and zone-settlement invoice numbers, e.g. `ACME/OCT26/01` |
| Copyright holder, footer credit, privacy and terms links | Footer of every page and of the sign-in page |

How it behaves:

- **Logos.** If a company uploads no logo, a plain one is drawn from its name and primary colour, so a new installation never shows another company's logo. Uploads must be PNG, JPG or WebP, or ICO for the favicon, up to 2 MB. SVG is not accepted.
- **Colours.** A colour left switched off keeps the product's own design colour. The layout never changes, only the colours.
- **Where it is kept.** The settings live in the database (`app_settings` → `branding`) and the uploaded files in `storage/app/branding/`. Neither is part of the code, so updates never overwrite them, and `deploy/backup.sh` includes both.
- **From the command line.** `php artisan app:branding --name=… --legal-name=… --primary-color=#e11d48 --logo=/path/logo.png` sets the same values, and `php artisan app:branding --show` lists them.

### Installing for a new company

1. Give the company its own server: a VM or VPS with Ubuntu 22.04/24.04 or Debian 12. Point its domain, e.g. `noc.company-b.com`, at the server.
2. Run the installer there:

   ```bash
   curl -fsSL https://raw.githubusercontent.com/foysal9041/olt-panel/master/install.sh \
     | sudo bash -s -- --company="Company B" --domain=noc.company-b.com --ssl --email=it@company-b.com
   ```

   It creates that server's own database, database user (with a random password), app key, `.env`, files and first admin. Nothing is shared with any other installation.
3. Sign in and fill in **Settings → Company Branding**, then the steps under [After installing](#after-installing).

Every company runs the same code from this repository. Keep company-specific things in the panel's settings, not in the code, so that every installation can take the same updates (see [Customer-specific changes](#customer-specific-changes)).

---

## Updating, versions and rollback

Run the installer again:

```bash
sudo bash /var/www/olt-panel/install.sh                    # latest code on master
sudo bash /var/www/olt-panel/install.sh --version=v1.1.0   # a particular release
```

What happens:

1. **Backup first.** It takes a backup of the database, the uploaded files and `.env`, and puts it in `/var/backups/olt-panel/`. The 10 newest are kept.
2. **Update.** It pulls the code, installs packages and runs the new database migrations.
3. **Kept as they were:** `.env`, the company's branding, all data, uploaded files and nginx.
4. **Local changes.** If the folder has local code changes, it doesn't pull, and says so.

**Releases** are git tags (`v1.0.0`, `v1.1.0`, …). Installations that need to stay put can stay on a tag and move to the next one when ready.

**Rolling back.** If something is wrong after an update, the installer's last lines show the command:

```bash
sudo bash /var/www/olt-panel/deploy/rollback.sh /var/backups/olt-panel/olt-panel-backup-YYYYmmdd-HHMM.tar.gz
```

This:

- puts the code back to the version the backup was made with
- restores the database and uploaded files from the backup
- removes tables that the newer version's migrations added, so the next update runs cleanly

Graph and latency history isn't in these backups and is left as it is. Anything entered in the panel after the backup is lost.

**Database changes** are made only by migrations in `database/migrations`, never by hand. Existing data is changed with care, and a fresh install builds the full schema from them.

**Health check:** `https://<domain>/up` answers `200` when the app is running. Use it for uptime monitoring or load balancers. The installer checks it at the end.

---

## Backups and moving to another server

`deploy/backup.sh` packs the whole panel into one file: the database, uploaded files and the **app key**. The saved OLT, SMTP and Telegram passwords are encrypted with that key, and a new server needs it to read them.

```bash
# on the old server
sudo bash /var/www/olt-panel/deploy/backup.sh                 # everything
sudo bash /var/www/olt-panel/deploy/backup.sh --no-history    # without graph/latency history (much smaller)
# → /root/olt-panel-backup-YYYYmmdd-HHMM.tar.gz
```

To move to a new server, copy that file over, then install from it:

```bash
scp /root/olt-panel-backup-*.tar.gz root@NEW-SERVER:/root/
curl -fsSL https://raw.githubusercontent.com/foysal9041/olt-panel/master/install.sh \
  | sudo bash -s -- --restore=/root/olt-panel-backup-YYYYmmdd-HHMM.tar.gz --domain=noc.example.com
```

Everyone signs in with the same usernames and passwords as before. Then point the ZKTeco devices, DNS and any firewall rules (for example SNMP access on the switches and routers) at the new server's IP.

The backup file contains passwords. Keep it private, and delete copies you no longer need. For daily backups, put `deploy/backup.sh --no-history` in root's crontab and copy the file off the server.

---

## HTTPS

- **Let's Encrypt:** install with `--ssl --email=…`, or run the installer again later with them. The certificate renews automatically. Plain HTTP stays open only for `/iclock/` (the ZKTeco devices) and the certificate checks. Everything else redirects to HTTPS.
- **Behind Cloudflare** with an Origin Certificate: `sudo bash deploy/enable-https.sh`. It asks for the certificate and key, takes the domain from `APP_URL` in `.env` (or `DOMAIN=…`), and writes the nginx site from `deploy/nginx/https-site.conf.template`. `deploy/enable-letsencrypt.sh` does the same with a Let's Encrypt certificate.

---

## Manual install (other systems)

Requirements:

- PHP 8.2+ with `mbstring xml curl zip gd bcmath intl snmp mysql opcache`
- MariaDB 10.6+ or MySQL 8
- nginx or Apache
- Composer 2
- `ping` (iputils)
- cron

```bash
git clone https://github.com/foysal9041/olt-panel.git /var/www/olt-panel && cd /var/www/olt-panel
composer install --no-dev --optimize-autoloader
cp .env.example .env            # set APP_URL, APP_ENV=production, APP_DEBUG=false, DB_* (DB_CONNECTION=mysql)
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan app:create-admin    # asks for a username and password
php artisan optimize
```

Then:

- Make `storage/` and `bootstrap/cache/` writable by the web server user.
- Point the web root at `public/`.
- Add the scheduler to cron:

```
* * * * * www-data cd /var/www/olt-panel && php artisan schedule:run >> /dev/null 2>&1
```

The sign-in page's CSS and JS are built into `public/build`. If you change `resources/css` or `resources/js`, rebuild them with Node 20+: `npm ci && npm run build`.

---

## Customer-specific changes

- **Prefer settings to code.** Names, logos, colours, contact details, document text, e-mail sender, alert channels, office hours, shifts and permissions are all settings.
- **No per-company forks.** A permanent copy of the code for one company would miss every later fix.
- **Code that one company needs.** Add it to this repository behind a setting or an `.env` flag that is off by default, so every installation keeps the same code and only that company turns it on.
- **Keep the list.** Note which installations use such flags, so updates can be checked against them.

---

## Useful commands

```bash
cd /var/www/olt-panel
sudo -u www-data php artisan app:create-admin --username=admin   # new admin, or reset a forgotten password
sudo -u www-data php artisan schedule:list                       # what runs, and when
sudo -u www-data php artisan app:poll-switches                   # poll switches/routers now
tail -f storage/logs/laravel-$(date +%F).log                     # app errors
sudo -u www-data php artisan optimize                            # after editing .env
sudo -u www-data php artisan app:branding --show                 # the company's branding
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/up      # health check (200 = up)
```

## Troubleshooting

| Problem | Check |
|---|---|
| Graphs stay empty | Is the scheduler running (`/etc/cron.d/olt-panel`)? Can this server reach the device by SNMP (`snmpwalk -v2c -c COMMUNITY IP sysName`)? |
| OLTs show offline | Can this server ping them? |
| No attendance punches | The device's cloud server must be this server's IP on port 80. See the device's *Last seen* under HR & Attendance → Devices. |
| Emails don't go out | Settings → Email: SMTP host, port, encryption and password. Use the test button. |
| “500 Server Error” | `storage/logs/`, and `/var/log/nginx/error.log` |
| Changed `.env` but nothing happened | `sudo -u www-data php artisan optimize` |
| An update went wrong | `sudo bash deploy/rollback.sh <the backup file named at the end of the update>` |
| Logo doesn't change after uploading | It is cached by the browser under a new address each time. Reload the page with Ctrl+F5. |

---

## বাংলায় সংক্ষেপে

**নতুন সার্ভারে ইনস্টল** (Ubuntu 22.04/24.04 বা Debian 12):

```bash
curl -fsSL https://raw.githubusercontent.com/foysal9041/olt-panel/master/install.sh | sudo bash
```

ইনস্টলার কোম্পানির নাম, ডোমেইন/IP, অ্যাডমিন ইউজারনেম আর পাসওয়ার্ড জিজ্ঞেস করবে, বাকি সব নিজেই করে নেবে:

- nginx, PHP, MariaDB
- ডাটাবেস আর অ্যাডমিন ইউজার
- প্রতি মিনিটের শিডিউলার
- চাইলে HTTPS

শেষে লগইনের ঠিকানা আর পাসওয়ার্ড দেখাবে। এগুলো `/root/olt-panel-install.txt`-এও সেভ থাকবে।

- **এক ইনস্টলেশন = এক কোম্পানি:** প্রতিটি কোম্পানির নিজস্ব সার্ভার, ডোমেইন, ডাটাবেস আর ফাইল থাকবে। নাম, লোগো, রং, ঠিকানা, ইনভয়েস নম্বরের শুরু আর ফুটার দিতে হবে **Settings → Company Branding**-এ, কোড বদলাতে হবে না।
- **আপডেট:** `sudo bash /var/www/olt-panel/install.sh`।
  - আপডেটের আগে নিজে থেকেই ব্যাকআপ নেয় (`/var/backups/olt-panel`)।
  - ডাটা, সেটিংস আর ব্র্যান্ডিং যেমন আছে তেমনই থাকবে।
  - নির্দিষ্ট ভার্সনে যেতে: `--version=v1.1.0`
- **সমস্যা হলে আগের অবস্থায় ফেরাতে:** `sudo bash deploy/rollback.sh <ব্যাকআপ ফাইল>`
- **অন্য সার্ভারে সব ডাটাসহ সরাতে:**
  1. পুরনো সার্ভারে চালান: `sudo bash deploy/backup.sh`
  2. যে ফাইল তৈরি হবে সেটা নতুন সার্ভারে কপি করুন।
  3. নতুন সার্ভারে ইনস্টলার চালান `--restore=ফাইলের-পাথ` দিয়ে।
  
  সব ইউজার, OLT, সুইচ, হিসাব আর হাজিরা আগের মতোই থাকবে।
- **ZKTeco মেশিন:** *Menu → COMM → Cloud Server Setting*-এ সার্ভারের IP আর পোর্ট 80 দিন।
- **পাসওয়ার্ড ভুলে গেলে:** `sudo -u www-data php artisan app:create-admin --username=admin`
