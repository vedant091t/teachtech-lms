# Quick Setup with XAMPP

## Download & Install XAMPP

1. **Download XAMPP**: https://www.apachefriends.org/download.html
2. **Install** to default location: `C:\xampp`
3. **Start** XAMPP Control Panel

## Setup PHP in PATH

After XAMPP installation:

```powershell
# Add PHP to PATH (run PowerShell as Administrator)
[Environment]::SetEnvironmentVariable("Path", $env:Path + ";C:\xampp\php", "Machine")
```

**OR manually:**
1. Search Windows: "Environment Variables"
2. Click "Environment Variables"
3. Under "System variables", select "Path"
4. Click "Edit"
5. Click "New"
6. Add: `C:\xampp\php`
7. Click "OK" on all dialogs
8. **Restart PowerShell**

## Verify PHP Installation

```powershell
# Close and reopen PowerShell, then test
php --version
```

Expected output:
```
PHP 8.x.x (cli) ...
```

## Install Composer (After PHP is working)

```powershell
# Download Composer installer for Windows
Invoke-WebRequest -Uri https://getcomposer.org/Composer-Setup.exe -OutFile composer-setup.exe

# Run installer
.\composer-setup.exe

# Remove installer
Remove-Item composer-setup.exe
```

## Alternative: Use XAMPP's PHP Directly

If you don't want to add to PATH:

```powershell
# Use full path to PHP
C:\xampp\php\php.exe --version

# Install Composer with full path
C:\xampp\php\php.exe -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
C:\xampp\php\php.exe composer-setup.php
C:\xampp\php\php.exe -r "unlink('composer-setup.php');"

# Then use composer.phar with full PHP path
C:\xampp\php\php.exe composer.phar install
```

## Quick Check: Is PHP Already Installed?

```powershell
# Check common PHP locations
Test-Path "C:\xampp\php\php.exe"
Test-Path "C:\php\php.exe"
Test-Path "C:\Program Files\PHP\php.exe"

# Search for PHP
Get-ChildItem -Path C:\ -Filter php.exe -Recurse -ErrorAction SilentlyContinue | Select-Object FullName
```

## After PHP is Working

1. Verify: `php --version`
2. Install Composer: Download from https://getcomposer.org/Composer-Setup.exe
3. Verify: `composer --version`
4. Then run in your project:
   ```powershell
   cd d:\Vedant_Tandel\project\teachtech
   composer install
   ```
