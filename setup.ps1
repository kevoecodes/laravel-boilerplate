<#
.SYNOPSIS
    Sets up laravel-boilerplate after cloning (SETUP.md step 5).

.DESCRIPTION
    1. Checks that PHP 8.2+, the required PHP extensions and Composer are available
    2. composer install
    3. Creates .env from .env.example (if missing) and writes the DB_* / FRONTEND_URL values
    4. php artisan key:generate (only when APP_KEY is empty)
    5. Checks the database connection, then php artisan migrate --seed (or migrate:fresh --seed with -Fresh)
    6. Optionally runs the test suite and/or starts php artisan serve

    PostgreSQL must already be running and the database/user must exist (SETUP.md step 4).

.EXAMPLE
    .\setup.ps1

.EXAMPLE
    .\setup.ps1 -DbPassword 'secret' -FrontendUrl 'http://localhost:5173' -Serve

.EXAMPLE
    .\setup.ps1 -Fresh -RunTests
#>
[CmdletBinding()]
param(
    [string]$DbHost = '127.0.0.1',
    [int]$DbPort = 5432,
    [string]$DbName = 'laravel_boilerplate',
    [string]$DbUser = 'laravel',
    [string]$DbPassword = 'secret',
    [string]$FrontendUrl = 'http://localhost:5173',
    # Drop all tables and re-seed instead of a normal migrate
    [switch]$Fresh,
    # Run php artisan test after setup (needs the laravel_boilerplate_test database)
    [switch]$RunTests,
    # Start php artisan serve at the end
    [switch]$Serve,
    [int]$Port = 8000
)

$ErrorActionPreference = 'Stop'
Set-Location -Path $PSScriptRoot

function Write-Step([string]$Message) { Write-Host "`n==> $Message" -ForegroundColor Cyan }
function Write-Ok([string]$Message) { Write-Host "    OK  $Message" -ForegroundColor Green }
function Fail([string]$Message) { Write-Host "`n[x] $Message" -ForegroundColor Red; exit 1 }

function Invoke-Native {
    param([string]$File, [string[]]$Arguments)
    & $File @Arguments
    if ($LASTEXITCODE -ne 0) { Fail "'$File $($Arguments -join ' ')' failed (exit code $LASTEXITCODE)." }
}

# Reload PATH so tools installed in this session are visible.
$env:Path = [Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' + [Environment]::GetEnvironmentVariable('Path', 'User')

# --- 1. Prerequisites -------------------------------------------------------
Write-Step 'Checking prerequisites'

if (-not (Get-Command php -ErrorAction SilentlyContinue)) { Fail 'php not found on PATH. See SETUP.md step 2.' }
$phpVersion = (& php -r 'echo PHP_VERSION;')
if ([version]($phpVersion -replace '[^0-9.].*$', '') -lt [version]'8.2') { Fail "PHP 8.2+ required, found $phpVersion." }
Write-Ok "PHP $phpVersion"

$modules = (& php -m) | ForEach-Object { $_.Trim().ToLower() }
$required = 'pdo_pgsql', 'pgsql', 'mbstring', 'openssl', 'curl', 'fileinfo', 'zip', 'intl', 'bcmath'
$missing = $required | Where-Object { $modules -notcontains $_ }
if ($missing) {
    $ini = (& php -r 'echo php_ini_loaded_file() ?: "(none)";')
    Fail "Missing PHP extensions: $($missing -join ', '). Enable them in php.ini ($ini). See SETUP.md step 2.3."
}
Write-Ok "Extensions: $($required -join ', ')"

if (-not (Get-Command composer -ErrorAction SilentlyContinue)) { Fail 'composer not found on PATH. See SETUP.md step 3.' }
Write-Ok ((& composer -V --no-ansi 2>$null) | Select-Object -First 1)

# --- 2. Composer ------------------------------------------------------------
Write-Step 'Installing PHP dependencies (composer install)'
Invoke-Native composer @('install', '--no-interaction', '--prefer-dist')

# --- 3. .env ----------------------------------------------------------------
Write-Step 'Configuring .env'
if (-not (Test-Path .env)) {
    Copy-Item .env.example .env
    Write-Ok 'Created .env from .env.example'
}

$values = [ordered]@{
    DB_CONNECTION = 'pgsql'
    DB_HOST       = $DbHost
    DB_PORT       = "$DbPort"
    DB_DATABASE   = $DbName
    DB_USERNAME   = $DbUser
    DB_PASSWORD   = $DbPassword
    FRONTEND_URL  = $FrontendUrl
}

$envText = Get-Content .env -Raw
foreach ($key in $values.Keys) {
    $line = "$key=$($values[$key])"
    $pattern = "(?m)^#?\s*$key=.*$"
    if ($envText -match $pattern) {
        $envText = [regex]::Replace($envText, $pattern, [System.Text.RegularExpressions.MatchEvaluator] { param($m) $line })
    } else {
        $envText = $envText.TrimEnd() + "`n$line`n"
    }
}
# Write UTF-8 without BOM and with LF endings (PHP's dotenv parser chokes on a BOM).
[System.IO.File]::WriteAllText((Join-Path $PSScriptRoot '.env'), ($envText -replace "`r`n", "`n"), (New-Object System.Text.UTF8Encoding $false))
Write-Ok "DB $DbUser@${DbHost}:$DbPort/$DbName, FRONTEND_URL=$FrontendUrl"

# --- 4. App key -------------------------------------------------------------
if ($envText -match '(?m)^APP_KEY=\s*$') {
    Write-Step 'Generating application key'
    Invoke-Native php @('artisan', 'key:generate', '--force')
}

Invoke-Native php @('artisan', 'config:clear')

# --- 5. Database ------------------------------------------------------------
Write-Step 'Checking database connection'
& php artisan db:show --no-interaction *> $null
if ($LASTEXITCODE -ne 0) {
    Write-Host '    Could not connect. Details:' -ForegroundColor Yellow
    & php artisan db:show --no-interaction
    Fail "Cannot connect to PostgreSQL database '$DbName' as '$DbUser'. Is the service running and did you create the user/database? (SETUP.md step 4)"
}
Write-Ok 'Connected'

if ($Fresh) {
    Write-Step 'Rebuilding database (migrate:fresh --seed)'
    Invoke-Native php @('artisan', 'migrate:fresh', '--seed', '--force')
} else {
    Write-Step 'Migrating and seeding'
    # migrate:status fails when the migrations table doesn't exist yet, i.e. a brand-new database.
    & php artisan migrate:status --no-interaction *> $null
    $isNewDatabase = $LASTEXITCODE -ne 0
    Invoke-Native php @('artisan', 'migrate', '--force')
    if ($isNewDatabase) {
        Invoke-Native php @('artisan', 'db:seed', '--force')
    } else {
        Write-Ok 'Existing database. Seeders skipped (use -Fresh to rebuild with sample data).'
    }
}

# --- 6. Optional ------------------------------------------------------------
if ($RunTests) {
    Write-Step 'Running tests'
    Invoke-Native php @('artisan', 'test')
}

Write-Host "`nSetup complete." -ForegroundColor Green
Write-Host '  API:    ' -NoNewline; Write-Host "http://localhost:$Port/api/v1"
Write-Host '  Login:  ' -NoNewline; Write-Host 'admin@example.com / password'

if ($Serve) {
    Write-Step "Starting server on http://localhost:$Port (Ctrl+C to stop)"
    & php artisan serve --port=$Port
} else {
    Write-Host "`nStart the API with:  php artisan serve"
}
