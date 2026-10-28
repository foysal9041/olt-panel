# OLT Panel

A web panel for running an ISP's network and back office in one place: OLT, switch and router monitoring with LibreNMS-style graphs, alerts, NTTN and latency checks, billing with emailed PDF invoices, accounts, inventory, tickets, tasks, and HR with attendance straight from ZKTeco fingerprint devices. The interface is in **English and বাংলা**.

Built with Laravel 12 (PHP 8.2+), MariaDB/MySQL, AdminLTE 3 and Chart.js.

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

---

## Install — one command

On a fresh **Ubuntu 22.04 / 24.04** or **Debian 12** server, as a user with sudo:

```bash
curl -fsSL https://raw.githubusercontent.com/foysal9041/olt-panel/master/install.sh | sudo bash
```

The installer asks three things: the domain or IP for the panel, the admin username, and the admin password. Leave the password blank to get a random one. Then it sets up everything:

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
| `--domain=noc.example.com` | Address the panel is opened on (default: the server's IP) |
| `--admin-user=admin` `--admin-pass=…` | The first admin (default: asked; a blank password gets a random one) |
| `--ssl --email=you@example.com` | HTTPS from Let's Encrypt. The domain must already point to the server, with port 80 open. |
| `--restore=/path/backup.tar.gz` | Move an existing panel here (see [Moving to another server](#moving-to-another-server)) |
| `--dir=/var/www/olt-panel` | Where the app is installed |
| `--branch=master` `--repo=URL` | Which code to install |
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
2. **Settings → Email**: the SMTP mailbox used to send invoices and receipts to clients. It has a button for a test mail.
3. **Settings → Telegram** and **WhatsApp**: where alerts go, and the alert thresholds.
4. Add **zones**, then **OLTs**, **switches and routers** (SNMP v2c or v1 community), **NTTN links** and **latency targets**.
5. **HR & Attendance**:
   - Add employees with their device user ID.
   - Set **Duty Shifts** (start, end and grace period).
   - Set the weekly off day and the **Office Time** under Devices.
6. **ZKTeco F18 devices**: on the device, open *Menu → COMM → Cloud Server Setting*. Set *Enable Domain Name* to Off, the server address to this server's IP, and the port to **80**. Then add the device under **HR & Attendance → Devices** with its serial number.
7. **Users**: create accounts for staff and tick the modules each one can use.

---

## Updating

Run the installer again:

```bash
sudo bash /var/www/olt-panel/install.sh
```

It pulls the latest code, installs packages and runs new database migrations. Your `.env`, data, uploaded files and nginx settings are kept. If the folder has local code changes, it doesn't pull, and says so.

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
- **Behind Cloudflare** with an Origin Certificate: see `deploy/enable-https.sh` and `deploy/nginx/` for an example (written for `noc.sunlitnetwork.com`; change the domain).

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

## Useful commands

```bash
cd /var/www/olt-panel
sudo -u www-data php artisan app:create-admin --username=admin   # new admin, or reset a forgotten password
sudo -u www-data php artisan schedule:list                       # what runs, and when
sudo -u www-data php artisan app:poll-switches                   # poll switches/routers now
tail -f storage/logs/laravel-$(date +%F).log                     # app errors
sudo -u www-data php artisan optimize                            # after editing .env
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

---

## বাংলায় সংক্ষেপে

**নতুন সার্ভারে ইনস্টল** (Ubuntu 22.04/24.04 বা Debian 12):

```bash
curl -fsSL https://raw.githubusercontent.com/foysal9041/olt-panel/master/install.sh | sudo bash
```

ইনস্টলার ডোমেইন/IP, অ্যাডমিন ইউজারনেম আর পাসওয়ার্ড জিজ্ঞেস করবে, বাকি সব নিজেই করে নেবে:

- nginx, PHP, MariaDB
- ডাটাবেস আর অ্যাডমিন ইউজার
- প্রতি মিনিটের শিডিউলার
- চাইলে HTTPS

শেষে লগইনের ঠিকানা আর পাসওয়ার্ড দেখাবে। এগুলো `/root/olt-panel-install.txt`-এও সেভ থাকবে।

- **আপডেট:** `sudo bash /var/www/olt-panel/install.sh`। ডাটা আর সেটিংস যেমন আছে তেমনই থাকবে।
- **অন্য সার্ভারে সব ডাটাসহ সরাতে:**
  1. পুরনো সার্ভারে চালান: `sudo bash deploy/backup.sh`
  2. যে ফাইল তৈরি হবে সেটা নতুন সার্ভারে কপি করুন।
  3. নতুন সার্ভারে ইনস্টলার চালান `--restore=ফাইলের-পাথ` দিয়ে।
  
  সব ইউজার, OLT, সুইচ, হিসাব আর হাজিরা আগের মতোই থাকবে।
- **ZKTeco মেশিন:** *Menu → COMM → Cloud Server Setting*-এ সার্ভারের IP আর পোর্ট 80 দিন।
- **পাসওয়ার্ড ভুলে গেলে:** `sudo -u www-data php artisan app:create-admin --username=admin`
