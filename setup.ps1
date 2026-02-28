<#
.SYNOPSIS
    TeachTech one-command setup for Windows.
    Installs XAMPP (PHP + MySQL), Composer, imports DB schema.

.USAGE
    Run PowerShell as Administrator, then:
        Set-ExecutionPolicy RemoteSigned -Scope CurrentUser -Force
        .\setup.ps1
#>

# ---- Config -----------------------------------------------------------------
# Direct CDN URL (no SourceForge redirects)
$XAMPP_URL = "https://downloads.sourceforge.net/project/xampp/XAMPP%20Windows/8.2.12/xampp-windows-x64-8.2.12-0-VS16-installer.exe"
$XAMPP_DIR = "C:\xampp"
$XAMPP_PHP = "$XAMPP_DIR\php"
$XAMPP_MYSQL = "$XAMPP_DIR\mysql\bin"
$COMPOSER_URL = "https://getcomposer.org/Composer-Setup.exe"
$DOWNLOADS = "$env:TEMP\tt_setup"
$PROJECT_DIR = $PSScriptRoot

# ---- Helpers ----------------------------------------------------------------
function Log-Step { param($m) Write-Host "" ; Write-Host "[STEP] $m" -ForegroundColor Cyan }
function Log-OK { param($m) Write-Host "  [OK] $m" -ForegroundColor Green }
function Log-Warn { param($m) Write-Host "  [!!] $m" -ForegroundColor Yellow }
function Log-Error { param($m) Write-Host " [ERR] $m" -ForegroundColor Red ; exit 1 }

function Add-ToPath {
    param($dir)
    $parts = $env:Path -split ';'
    if ($parts -notcontains $dir) {
        [Environment]::SetEnvironmentVariable("Path", ($env:Path + ";" + $dir), "Machine")
        $env:Path = $env:Path + ";" + $dir
        Log-OK "Added to PATH: $dir"
    }
    else {
        Log-Warn "Already in PATH: $dir"
    }
}

function Get-File {
    param($url, $out)
    Log-Warn "Downloading (may take a few minutes)..."
    # Use WebClient for better redirect support than Invoke-WebRequest
    $wc = New-Object System.Net.WebClient
    $wc.Headers.Add("User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64)")
    $wc.DownloadFile($url, $out)
    $size = [math]::Round((Get-Item $out).Length / 1MB, 1)
    Log-OK "Downloaded: $out ($size MB)"
    if ($size -lt 1) {
        Log-Error "Downloaded file is too small ($size MB) - URL may have returned an error page. Check your internet connection."
    }
}

# ---- 0. Require Admin -------------------------------------------------------
Log-Step "Checking privileges"
$id = [Security.Principal.WindowsIdentity]::GetCurrent()
$ok = ([Security.Principal.WindowsPrincipal]$id).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $ok) { Log-Error "Must run as Administrator. Right-click PowerShell > Run as Administrator." }
Log-OK "Administrator confirmed."

# ---- 1. Temp directory ------------------------------------------------------
Log-Step "Preparing download folder"
New-Item -ItemType Directory -Force -Path $DOWNLOADS | Out-Null
Log-OK "Folder: $DOWNLOADS"

# ---- 2. XAMPP ---------------------------------------------------------------
Log-Step "Checking XAMPP"
if (Test-Path "$XAMPP_PHP\php.exe") {
    Log-OK "XAMPP already installed at $XAMPP_DIR"
}
else {
    $exe = "$DOWNLOADS\xampp.exe"

    # Try primary URL first, fallback to alternate
    try {
        Get-File -url $XAMPP_URL -out $exe
    }
    catch {
        Log-Warn "Primary URL failed. Trying alternate URL..."
        $altUrl = "https://www.apachefriends.org/xampp-files/8.2.12/xampp-windows-x64-8.2.12-0-VS16-installer.exe"
        try {
            Get-File -url $altUrl -out $exe
        }
        catch {
            Log-Warn "Auto-download failed. Please download XAMPP manually:"
            Write-Host ""
            Write-Host "  1. Go to: https://www.apachefriends.org/download.html" -ForegroundColor Yellow
            Write-Host "  2. Download the Windows 64-bit installer" -ForegroundColor Yellow
            Write-Host "  3. Install it to C:\xampp" -ForegroundColor Yellow
            Write-Host "  4. Re-run this script" -ForegroundColor Yellow
            Write-Host ""
            exit 1
        }
    }

    Log-Step "Running XAMPP installer... (1-3 minutes, do not close)"
    Start-Process -FilePath $exe -ArgumentList "--mode unattended --unattendedmodeui none" -Wait
    if (-not (Test-Path "$XAMPP_PHP\php.exe")) {
        Log-Warn "Silent install may have failed. Trying GUI installer..."
        Start-Process -FilePath $exe -Wait
    }
    if (-not (Test-Path "$XAMPP_PHP\php.exe")) {
        Log-Error "XAMPP install failed. See https://www.apachefriends.org/"
    }
    Log-OK "XAMPP installed at $XAMPP_DIR"
}

# ---- 3. PATH ----------------------------------------------------------------
Log-Step "Configuring PATH"
Add-ToPath -dir $XAMPP_PHP
Add-ToPath -dir $XAMPP_MYSQL
$v = & "$XAMPP_PHP\php.exe" --version 2>&1 | Select-Object -First 1
Log-OK "PHP ready: $v"

# ---- 4. Composer ------------------------------------------------------------
Log-Step "Checking Composer"
$cc = Get-Command composer -ErrorAction SilentlyContinue
if ($cc) {
    Log-OK "Composer: $($cc.Source)"
}
else {
    $cs = "$DOWNLOADS\ComposerSetup.exe"
    Get-File -url $COMPOSER_URL -out $cs
    Start-Process -FilePath $cs -ArgumentList "/VERYSILENT /SUPPRESSMSGBOXES" -Wait
    # Refresh PATH in current session
    $env:Path = [System.Environment]::GetEnvironmentVariable("Path", "Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path", "User")
    $cc = Get-Command composer -ErrorAction SilentlyContinue
    if (-not $cc) { Add-ToPath -dir "C:\ProgramData\ComposerSetup\bin" }
    Log-OK "Composer installed."
}

# ---- 5. PHP dependencies ----------------------------------------------------
Log-Step "Running: composer install"
Set-Location $PROJECT_DIR
& composer install --no-interaction --prefer-dist
if ($LASTEXITCODE -ne 0) { Log-Warn "composer install had issues. Check output above." }
else { Log-OK "PHP dependencies ready." }

# ---- 6. .env ----------------------------------------------------------------
Log-Step "Setting up .env"
if (Test-Path "$PROJECT_DIR\.env") {
    Log-Warn ".env already exists - skipping."
}
else {
    Copy-Item "$PROJECT_DIR\.env.example" "$PROJECT_DIR\.env"
    Log-OK ".env created from .env.example"
    Log-Warn "Open .env and set DB_PASS and SMTP credentials."
}

# ---- 7. Database schema -----------------------------------------------------
Log-Step "Importing database schema"
$mysql = "$XAMPP_MYSQL\mysql.exe"
$schema = "$PROJECT_DIR\database\schema.sql"

if (-not (Test-Path $mysql)) {
    Log-Warn "MySQL not found at $mysql"
    Log-Warn "Start XAMPP MySQL first, then run manually:"
    Write-Host "    Get-Content database\schema.sql | C:\xampp\mysql\bin\mysql.exe -u root" -ForegroundColor White
}
else {
    $null = & $mysql -u root --connect-timeout=5 -e "SELECT 1;" 2>&1
    if ($LASTEXITCODE -eq 0) {
        Log-OK "MySQL connected."
        Get-Content $schema | & $mysql -u root
        Log-OK "Schema imported."
    }
    else {
        Log-Warn "MySQL not running or needs password. Start XAMPP Apache+MySQL then run:"
        Write-Host "    Get-Content database\schema.sql | C:\xampp\mysql\bin\mysql.exe -u root" -ForegroundColor White
    }
}

# ---- 8. Directories ---------------------------------------------------------
Log-Step "Creating required directories"
foreach ($d in @("$PROJECT_DIR\uploads\materials", "$PROJECT_DIR\logs")) {
    New-Item -ItemType Directory -Force -Path $d | Out-Null
    Log-OK "Created: $d"
}

# ---- Done -------------------------------------------------------------------
Write-Host ""
Write-Host "================================================" -ForegroundColor Green
Write-Host "  TeachTech Setup Complete!" -ForegroundColor Green
Write-Host "================================================" -ForegroundColor Green
Write-Host ""
Write-Host " MANUAL STEPS REMAINING:" -ForegroundColor Yellow
Write-Host "  1. Open XAMPP Control Panel (C:\xampp\xampp-control.exe)" -ForegroundColor White
Write-Host "     Start: Apache + MySQL" -ForegroundColor White
Write-Host ""
Write-Host "  2. Import database (run in this terminal):" -ForegroundColor White
Write-Host "     Get-Content database\schema.sql | C:\xampp\mysql\bin\mysql.exe -u root" -ForegroundColor Cyan
Write-Host ""
Write-Host "  3. Edit .env file (add your Gmail App Password)" -ForegroundColor White
Write-Host ""
Write-Host "  4. Start dev server:" -ForegroundColor White
Write-Host "     php -S localhost:8000" -ForegroundColor Cyan
Write-Host ""
Write-Host "  5. Open: http://localhost:8000/register.php" -ForegroundColor White
Write-Host ""
