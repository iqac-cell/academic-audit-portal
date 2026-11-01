<#
    Updates the Academic Audit Portal on the Windows Server straight from GitHub
    (https://github.com/iqac-cell/academic-audit-portal). Same steps as "Updating the server" in README.md.
    Run in an Administrator PowerShell on the server:

        powershell -ExecutionPolicy Bypass -File C:\sites\academic-audit-portal\deploy\server\update-from-git.ps1
        # a branch, tag or commit other than main
        powershell -ExecutionPolicy Bypass -File C:\sites\academic-audit-portal\deploy\server\update-from-git.ps1 -Ref sso-client

    Steps:
      1. stop if the checkout has local edits (other than .env, storage and vendor, which git ignores);
      2. git fetch, check out origin/<Ref> (a branch is checked out and fast-forwarded; a tag/commit is detached);
      3. composer install --no-dev --optimize-autoloader;
      4. php artisan down, migrate --force, optimize:clear, config:cache, route:cache, view:cache, up;
      5. Restart-WebAppPool <AppPool>.
    The database (connection, instance, port) comes from .env only; this script never names a SQL Server instance.
#>
param(
    [string] $Ref = 'main',
    [string] $Path = 'C:\sites\academic-audit-portal',
    [string] $AppPool = 'AcademicAudit',
    [string] $Php = 'C:\php\php.exe',
    [switch] $SkipMigrate
)
$ErrorActionPreference = 'Stop'
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) { throw 'Run this in an Administrator PowerShell.' }
function Step([string] $t) { Write-Host "==> $t" -ForegroundColor Cyan }
function RunGit { & git.exe -C $Path @args; if ($LASTEXITCODE -ne 0) { throw "git $($args -join ' ') failed (exit $LASTEXITCODE)" } }
function Artisan { & $Php (Join-Path $Path 'artisan') @args; if ($LASTEXITCODE -ne 0) { throw "php artisan $($args -join ' ') failed (exit $LASTEXITCODE)" } }

if (-not (Test-Path $Php)) {
    $found = Get-Command php -ErrorAction SilentlyContinue
    if (-not $found) { throw "PHP not found at $Php (and not on PATH). Pass -Php <path to php.exe>." }
    $Php = $found.Source
}
if (-not (Get-Command git -ErrorAction SilentlyContinue)) { throw 'git is not installed (or not on PATH).' }
$composer = Get-Command composer -ErrorAction SilentlyContinue
if (-not $composer) { throw 'composer is not installed (or not on PATH).' }
if (-not (Test-Path (Join-Path $Path '.git'))) { throw "$Path is not a Git checkout." }
if (-not (Test-Path (Join-Path $Path '.env'))) { throw "$Path\.env is missing. Create it first (see docs/SSO.md and README.md)." }

Step 'Check for local edits'
$dirty = & git.exe -C $Path status --porcelain --untracked-files=no
if ($dirty) { throw "The checkout has local edits; commit or discard them first:`n$($dirty -join "`n")" }

Step "Fetch and check out $Ref"
$before = (& git.exe -C $Path rev-parse --short HEAD).Trim()
RunGit fetch --prune --tags origin
$isBranch = & git.exe -C $Path rev-parse --verify --quiet "refs/remotes/origin/$Ref"
if ($isBranch) {
    RunGit checkout $Ref
    RunGit merge --ff-only "origin/$Ref"
} else {
    $target = & git.exe -C $Path rev-parse --verify --quiet "$Ref^{commit}"
    if (-not $target) { throw "'$Ref' is not a branch, tag or commit on GitHub." }
    RunGit checkout --detach $target
}
$after = (& git.exe -C $Path rev-parse --short HEAD).Trim()
Write-Host ("    $before -> " + (& git.exe -C $Path log -1 --format='%h %s (%cd)' --date=format:'%d-%m-%Y %H:%M'))

Step 'composer install --no-dev'
Push-Location $Path
try {
    & $composer.Source install --no-dev --optimize-autoloader --no-interaction
    if ($LASTEXITCODE -ne 0) { throw "composer install failed (exit $LASTEXITCODE). The code is at $after; run this script again after fixing." }
} finally { Pop-Location }

Step 'Migrate and rebuild caches'
Artisan down --retry=30
try {
    if (-not $SkipMigrate) { Artisan migrate --force }
    Artisan optimize:clear
    Artisan config:cache
    Artisan route:cache
    Artisan view:cache
} finally {
    Artisan up
}

Step "Restart application pool $AppPool"
Import-Module WebAdministration
Restart-WebAppPool -Name $AppPool

Write-Host "Academic Audit Portal $after is live." -ForegroundColor Green
