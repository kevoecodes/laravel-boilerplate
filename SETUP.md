# Setup guide: Windows 10 / 11 (fresh machine)

This guide assumes a brand-new Windows PC with nothing installed. Every command is meant to be copied into
**PowerShell**, in order.

- **Admin PowerShell:** Start menu → type `PowerShell` → right-click **Windows PowerShell** → **Run as administrator**.
  Steps marked **(Admin)** need it.
- **Normal PowerShell:** the same, without "Run as administrator". Fine for everything else.
- **Editing files:** we use **vim** throughout. Vim basics:

  | Key | What it does |
  | --- | --- |
  | `i` | start typing (insert mode) |
  | `Esc` | stop typing (back to normal mode) |
  | `/text` then `Enter` | search for `text` (`n` = next match) |
  | `:wq` then `Enter` | save and quit |
  | `:q!` then `Enter` | quit **without** saving |

  Lines below that start with `:` are vim commands. Press `Esc` first, paste the line, then press `Enter`.

> **Refreshing PATH:** installers update PATH for *new* terminals only. After each install, either close and reopen
> PowerShell or run this line, which reloads PATH in the current window:
>
> ```powershell
> $env:Path = [Environment]::GetEnvironmentVariable('Path','Machine') + ';' + [Environment]::GetEnvironmentVariable('Path','User')
> ```
>
> The rest of this guide calls this line **"refresh PATH"**.

---

## 1. winget, Git and vim

### 1.1 Confirm winget works

winget ships with Windows 11 and up-to-date Windows 10 (1809+) as part of **App Installer**.

```powershell
winget --version
```

If you get *"winget is not recognized"*: open the Microsoft Store, search for **App Installer**, click **Update**
(or **Get**), then open a new PowerShell window and try again. The first run may ask you to accept the source
agreements. Type `Y`.

### 1.2 Install Git (Admin)

```powershell
winget install --id Git.Git -e --source winget --accept-package-agreements --accept-source-agreements
```

Refresh PATH, then verify:

```powershell
git --version
```

Keep line endings as stored in the repo (this project pins LF via `.gitattributes`). Run this **before** cloning:

```powershell
git config --global core.autocrlf false
```

Optional, but needed before you commit:

```powershell
git config --global user.name "Your Name"
```

```powershell
git config --global user.email "you@example.com"
```

### 1.3 Install vim (Admin)

```powershell
winget install --id vim.vim -e --source winget --accept-package-agreements --accept-source-agreements
```

The vim installer does not add itself to PATH. This adds the newest `C:\Program Files\Vim\vim9x` folder to the
machine PATH:

```powershell
$vimDir = (Get-ChildItem 'C:\Program Files\Vim' -Directory -Filter 'vim*' | Sort-Object Name -Descending | Select-Object -First 1).FullName; [Environment]::SetEnvironmentVariable('Path', [Environment]::GetEnvironmentVariable('Path','Machine') + ";$vimDir", 'Machine'); $vimDir
```

Refresh PATH, then verify:

```powershell
vim --version | Select-Object -First 1
```

---

## 2. PHP 8.3 (8.2+ works)

### 2.1 Install (Admin)

```powershell
winget install --id PHP.PHP.8.3 -e --source winget --accept-package-agreements --accept-source-agreements
```

Refresh PATH, then verify:

```powershell
php -v
```

> **No winget package?** Use the official Windows build instead: download the **VS16 x64 Non Thread Safe** zip
> from <https://windows.php.net/download/>, extract it to `C:\php`, then add it to PATH (Admin):
>
> ```powershell
> [Environment]::SetEnvironmentVariable('Path', [Environment]::GetEnvironmentVariable('Path','Machine') + ';C:\php', 'Machine')
> ```
>
> Refresh PATH and continue. The next steps work the same way.

### 2.2 Create php.ini

PHP ships with templates but no active `php.ini`. Find the PHP folder and create one from the development template:

```powershell
$env:PHPDIR = Split-Path (php -r "echo PHP_BINARY;"); $env:PHPDIR
```

```powershell
if (-not (Test-Path "$env:PHPDIR\php.ini")) { Copy-Item "$env:PHPDIR\php.ini-development" "$env:PHPDIR\php.ini" }
```

### 2.3 Enable the required extensions with vim

Open the file from the **same** PowerShell window (vim reads `$PHPDIR` from it):

```powershell
vim "$env:PHPDIR\php.ini"
```

In vim, paste these lines **one at a time** (press `Esc` first, then paste, then `Enter`).

Point `extension_dir` at PHP's `ext` folder with an absolute path. A relative path breaks when you run PHP from
another folder:

```vim
:%s/^;\?\s*extension_dir\s*=\s*"ext"/\='extension_dir = "' . $PHPDIR . '\ext"'/
```

Uncomment the extensions the project needs:

```vim
:%s/^;\(extension=\(pdo_pgsql\|pgsql\|mbstring\|openssl\|curl\|fileinfo\|zip\|intl\)\)\s*$/\1/
```

Save and quit:

```vim
:wq
```

> **Doing it by hand instead:** search with `/extension=pdo_pgsql`, put the cursor on the `;` at the start of the
> line, press `x` to delete it. Repeat for `pgsql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `zip`, `intl`.
> Then search `/extension_dir`, delete the `;` on the `extension_dir = "ext"` line, and change `"ext"` to the full
> path, e.g. `"C:\php\ext"`.
>
> **bcmath** is compiled into the official Windows builds, so it has no `extension=` line to enable.

### 2.4 Verify

```powershell
php --ini
```

`Loaded Configuration File` must show your `php.ini` path, not `(none)`.

```powershell
php -m | Select-String -Pattern '^(pdo_pgsql|pgsql|mbstring|openssl|curl|fileinfo|zip|intl|bcmath)$'
```

All 9 names must be listed. If one is missing, see [Troubleshooting](#7-troubleshooting).

---

## 3. Composer

### 3.1 Install (Admin)

The official installer finds `php.exe` from PATH and adds Composer to PATH for all users:

```powershell
Invoke-WebRequest -Uri 'https://getcomposer.org/Composer-Setup.exe' -OutFile "$env:TEMP\Composer-Setup.exe"
```

```powershell
Start-Process -FilePath "$env:TEMP\Composer-Setup.exe" -ArgumentList '/VERYSILENT', '/SUPPRESSMSGBOXES', '/ALLUSERS' -Wait
```

Refresh PATH, then verify:

```powershell
composer -V
```

---

## 4. PostgreSQL 16

### 4.1 Install (Admin)

Choose a **superuser (postgres) password** and write it down. You need it below. Replace `ChangeMe123!` in the
first line:

```powershell
$PgSuperPassword = 'ChangeMe123!'
```

```powershell
winget install --id PostgreSQL.PostgreSQL.16 -e --source winget --accept-package-agreements --accept-source-agreements --override "--mode unattended --unattendedmodeui minimal --superpassword `"$PgSuperPassword`" --serverport 5432"
```

> Older winget catalogs call the package `PostgreSQL.PostgreSQL`. Run `winget search PostgreSQL` to see the IDs
> available to you. If you install without `--override`, the graphical installer opens. Accept the defaults
> (port **5432**) and set the superuser password when it asks.

### 4.2 Make sure the service is running

```powershell
Get-Service -Name 'postgresql*'
```

`Status` should be `Running`. If it isn't (Admin):

```powershell
Get-Service -Name 'postgresql*' | Set-Service -StartupType Automatic -PassThru | Start-Service
```

### 4.3 Add PostgreSQL's `bin` to PATH (Admin)

```powershell
$pgBin = (Get-ChildItem 'C:\Program Files\PostgreSQL' -Directory | Sort-Object { [int]$_.Name } -Descending | Select-Object -First 1).FullName + '\bin'; [Environment]::SetEnvironmentVariable('Path', [Environment]::GetEnvironmentVariable('Path','Machine') + ";$pgBin", 'Machine'); $pgBin
```

Refresh PATH, then verify:

```powershell
psql --version
```

### 4.4 Create the app user and databases

Let `psql` use the superuser password for this window only, so it doesn't prompt every time:

```powershell
$env:PGPASSWORD = $PgSuperPassword
```

(If you opened a new window, use `$env:PGPASSWORD = 'ChangeMe123!'` with your real password.)

```powershell
psql -h 127.0.0.1 -U postgres -c "CREATE ROLE laravel WITH LOGIN PASSWORD 'secret';"
```

```powershell
psql -h 127.0.0.1 -U postgres -c "CREATE DATABASE laravel_boilerplate OWNER laravel;"
```

```powershell
psql -h 127.0.0.1 -U postgres -c "CREATE DATABASE laravel_boilerplate_test OWNER laravel;"
```

`laravel_boilerplate_test` is used only by `php artisan test`. Use a stronger password than `secret` on anything
that isn't your local machine.

### 4.5 Confirm the app user can connect

```powershell
$env:PGPASSWORD = 'secret'; psql -h 127.0.0.1 -U laravel -d laravel_boilerplate -c "SELECT current_user, current_database(), version();"
```

You should see `laravel | laravel_boilerplate | PostgreSQL 16...`. Clear the variable afterwards:

```powershell
Remove-Item Env:PGPASSWORD
```

---

## 5. The project

> **Shortcut:** after cloning (5.1), `.\setup.ps1` does 5.2–5.6 for you. See [setup.ps1](#setupps1).

### 5.1 Clone

```powershell
mkdir $HOME\code -Force; cd $HOME\code
```

```powershell
git clone https://github.com/kevoecodes/laravel-boilerplate.git
```

```powershell
cd laravel-boilerplate
```

### 5.2 Install PHP dependencies

```powershell
composer install
```

### 5.3 Create `.env`

```powershell
Copy-Item .env.example .env
```

### 5.4 Edit `.env` with vim

```powershell
vim .env
```

Check or change these lines. The defaults already match step 4, so usually you only change `DB_PASSWORD` if you
picked a different one:

```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laravel_boilerplate
DB_USERNAME=laravel
DB_PASSWORD=secret

FRONTEND_URL=http://localhost:5173
```

Edit them with vim commands (`Esc`, paste, `Enter`), e.g.:

```vim
:%s/^DB_PASSWORD=.*/DB_PASSWORD=secret/
```

```vim
:%s#^FRONTEND_URL=.*#FRONTEND_URL=http://localhost:5173#
```

```vim
:wq
```

`FRONTEND_URL` is the browser origin allowed by CORS. List several origins separated by commas, e.g.
`http://localhost:5173,http://127.0.0.1:5173`.

### 5.5 App key, migrations and seed data

```powershell
php artisan key:generate
```

```powershell
php artisan migrate --seed
```

### 5.6 Run it

```powershell
php artisan serve
```

The API is now at <http://localhost:8000/api/v1>. Leave this window open; press `Ctrl+C` to stop.
Quick check from a **second** PowerShell window:

```powershell
Invoke-RestMethod -Method Post -Uri http://localhost:8000/api/v1/auth/login -ContentType 'application/json' -Body '{"email":"admin@example.com","password":"password"}'
```

You should get back a `token` and the `user`.

### 5.7 Run the tests (optional)

```powershell
php artisan test
```

---

## 6. Default seeded logins

| Email | Password | Role |
| --- | --- | --- |
| `admin@example.com` | `password` | admin |
| `user@example.com` | `password` | user |

The seeder also creates 28 random users, 50 products and 80 orders. Reset everything with
`php artisan migrate:fresh --seed`.

---

## 7. Troubleshooting

### "php / composer / psql / vim is not recognized"

PATH changes only reach **new** terminals. Close and reopen PowerShell, or refresh PATH (see the top of this guide).
Check what the machine PATH contains:

```powershell
[Environment]::GetEnvironmentVariable('Path','Machine') -split ';'
```

### `php --ini` shows `Loaded Configuration File: (none)`

`php.ini` doesn't exist next to `php.exe`. Redo [2.2](#22-create-phpini). Make sure the file is called `php.ini`,
not `php.ini.txt`. Show file extensions with `Get-ChildItem "$env:PHPDIR\php.ini*"`.

### An extension is missing from `php -m`

Open `php.ini` (`vim "$env:PHPDIR\php.ini"`) and check:

- the `extension=...` line has no leading `;`
- `extension_dir` points to the real `ext` folder, e.g. `extension_dir = "C:\php\ext"`, and that folder contains
  `php_pdo_pgsql.dll`

Run `php -m` again. `php --ini` shows the exact file PHP loads. Editing a different `php.ini` has no effect.

### `could not find driver (Connection: pgsql ...)`

`pdo_pgsql` isn't loaded. Fix it as above, then confirm:

```powershell
php -m | Select-String pgsql
```

You must see both `pdo_pgsql` and `pgsql`. If they load but fail with *"Unable to load dynamic library"*, the
`libpq.dll` that ships in the PHP folder isn't found. Make sure the PHP folder itself is on PATH.

### `password authentication failed for user "laravel"` / `role "laravel" does not exist`

- `DB_USERNAME` / `DB_PASSWORD` in `.env` must match what you created in 4.4.
- Reset the password as superuser:

  ```powershell
  psql -h 127.0.0.1 -U postgres -c "ALTER ROLE laravel WITH PASSWORD 'secret';"
  ```

- After editing `.env`, run `php artisan config:clear`. A cached config ignores `.env`.

### `connection refused` on 127.0.0.1:5432

PostgreSQL isn't running, or it's on another port. Run `Get-Service postgresql*` (start it as in 4.2). Look up the
port in `C:\Program Files\PostgreSQL\16\data\postgresql.conf` (`port = 5432`).

### Forgot the postgres superuser password

As Admin, open `C:\Program Files\PostgreSQL\16\data\pg_hba.conf` in vim. Temporarily change `scram-sha-256` to
`trust` on the `127.0.0.1/32` line, then restart:

```powershell
Restart-Service postgresql-x64-16
```

Then set a new password:

```powershell
psql -h 127.0.0.1 -U postgres -c "ALTER ROLE postgres WITH PASSWORD 'NewPassword!';"
```

**Change `pg_hba.conf` back to `scram-sha-256`** and restart the service again.

### Port 8000 already in use

Use another port:

```powershell
php artisan serve --port=8001
```

Then point the frontend's `VITE_API_URL` at `http://localhost:8001/api/v1`. To see what holds port 8000:

```powershell
Get-NetTCPConnection -LocalPort 8000 -State Listen | ForEach-Object { Get-Process -Id $_.OwningProcess }
```

### CORS errors in the browser ("No 'Access-Control-Allow-Origin' header")

- `FRONTEND_URL` in `.env` must match the browser's address bar exactly: scheme, host **and** port,
  e.g. `http://localhost:5173`. `localhost` and `127.0.0.1` count as different origins. List both if you use both.
- After changing `.env`, run `php artisan config:clear` and restart `php artisan serve`.
- CORS applies only to `api/*` paths (`config/cors.php`). Make sure the frontend calls `/api/v1/...`.
- A 500 error can show up as a CORS error in the browser. Check `storage\logs\laravel.log`.

### Sanctum: always `401 Unauthenticated`

- This API uses **bearer tokens**, not cookies. Send `Authorization: Bearer <token>` (the frontend does this
  automatically).
- Tokens are deleted on logout and by `migrate:fresh`. Log in again.
- If you later switch to cookie-based SPA auth, set `SANCTUM_STATEFUL_DOMAINS=localhost:5173` and
  `SESSION_DOMAIN=localhost`, and enable `supports_credentials` in `config/cors.php`. The token setup used here
  needs none of that.

### Line endings (`^M`, odd diffs, or a script that won't run)

This repo stores files with LF endings (`.gitattributes`), except `*.ps1`, which uses CRLF. If you cloned with
`core.autocrlf=true` and see whole-file diffs:

```powershell
git config --global core.autocrlf false
```

```powershell
git rm --cached -r . -q; git reset --hard
```

(`reset --hard` discards uncommitted changes. Commit or stash them first.)

### `running scripts is disabled on this system` when running `setup.ps1`

Allow local scripts for your user, once:

```powershell
Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned
```

Or run a single script without changing the policy:

```powershell
powershell -ExecutionPolicy Bypass -File .\setup.ps1
```

### Other errors

```powershell
Get-Content storage\logs\laravel.log -Tail 50
```

---

## setup.ps1

Automates step 5 (after cloning): checks PHP, its extensions and Composer, then runs `composer install`, creates and
fills in `.env`, generates the key, and migrates and seeds.

```powershell
.\setup.ps1
```

Override the defaults:

```powershell
.\setup.ps1 -DbPassword 'secret' -DbName 'laravel_boilerplate' -FrontendUrl 'http://localhost:5173'
```

Rebuild the database from scratch, run the tests, then start the server:

```powershell
.\setup.ps1 -Fresh -RunTests -Serve
```

Run `Get-Help .\setup.ps1 -Detailed` to see every option.

---

## Appendix: WSL2 (Ubuntu) instead of native Windows

Use this if you'd rather run the stack on Linux inside Windows.

**1. Install WSL2 + Ubuntu (Admin PowerShell), then reboot:**

```powershell
wsl --install -d Ubuntu
```

After the reboot, Ubuntu opens and asks for a Linux username and password. Run the rest **inside Ubuntu**.

**2. Packages (PHP 8.3, PostgreSQL, Git, vim, Composer):**

```bash
sudo apt update && sudo apt install -y software-properties-common ca-certificates curl unzip git vim
```

```bash
sudo add-apt-repository -y ppa:ondrej/php && sudo apt update
```

```bash
sudo apt install -y php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-curl php8.3-xml php8.3-zip php8.3-intl php8.3-bcmath postgresql
```

```bash
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
```

```bash
php -v && php -m | grep -E 'pdo_pgsql|pgsql|mbstring|intl|bcmath|zip' && composer -V
```

(On Ubuntu 24.04 the stock `php8.3-*` packages exist, so you can skip the `ondrej/php` PPA.)

**3. PostgreSQL:**

```bash
sudo service postgresql start
```

```bash
sudo -u postgres psql -c "CREATE ROLE laravel WITH LOGIN PASSWORD 'secret';"
```

```bash
sudo -u postgres psql -c "CREATE DATABASE laravel_boilerplate OWNER laravel;"
```

```bash
sudo -u postgres psql -c "CREATE DATABASE laravel_boilerplate_test OWNER laravel;"
```

```bash
PGPASSWORD=secret psql -h 127.0.0.1 -U laravel -d laravel_boilerplate -c "select current_user;"
```

**4. Project.** Keep it in the Linux filesystem (`~/code`), not `/mnt/c`, which is much slower:

```bash
mkdir -p ~/code && cd ~/code && git clone https://github.com/kevoecodes/laravel-boilerplate.git && cd laravel-boilerplate
```

```bash
composer install && cp .env.example .env && vim .env
```

```bash
php artisan key:generate && php artisan migrate --seed
```

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

WSL2 forwards `localhost`, so <http://localhost:8000> works from the Windows browser and from a frontend running
on Windows. PostgreSQL doesn't start automatically in WSL. Run `sudo service postgresql start` after each reboot.
