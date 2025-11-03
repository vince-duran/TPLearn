# Advanced MySQL Repair Script for XAMPP
# Use this if the basic repair didn't work

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Advanced MySQL Repair Script" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Stop MySQL if running
Write-Host "[Step 1] Stopping MySQL..." -ForegroundColor Yellow
$mysqlProcess = Get-Process mysqld -ErrorAction SilentlyContinue
if ($mysqlProcess) {
    Stop-Process -Name mysqld -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 3
}
Write-Host "MySQL stopped." -ForegroundColor Green

# Create comprehensive backup
Write-Host ""
Write-Host "[Step 2] Creating comprehensive backup..." -ForegroundColor Yellow
$backupFolder = "c:\xampp\mysql\data\backup_full_$(Get-Date -Format 'yyyyMMdd_HHmmss')"
New-Item -ItemType Directory -Path $backupFolder -Force | Out-Null

# Backup mysql system database
Write-Host "  Backing up mysql system database..." -ForegroundColor Gray
$mysqlDir = "c:\xampp\mysql\data\mysql"
if (Test-Path $mysqlDir) {
    Copy-Item $mysqlDir -Destination "$backupFolder\mysql" -Recurse -Force
    Write-Host "  MySQL system database backed up" -ForegroundColor Green
}

# Delete all Aria log files
Write-Host ""
Write-Host "[Step 3] Removing all Aria log files..." -ForegroundColor Yellow
Get-ChildItem "c:\xampp\mysql\data\aria_log*" -ErrorAction SilentlyContinue | Remove-Item -Force
Get-ChildItem "c:\xampp\mysql\data\aria_control*" -ErrorAction SilentlyContinue | Remove-Item -Force
Write-Host "Aria files removed." -ForegroundColor Green

# Try to repair mysql.plugin table
Write-Host ""
Write-Host "[Step 4] Repairing mysql.plugin table..." -ForegroundColor Yellow
$pluginFile = "c:\xampp\mysql\data\mysql\plugin.frm"
$pluginMYD = "c:\xampp\mysql\data\mysql\plugin.MYD"
$pluginMYI = "c:\xampp\mysql\data\mysql\plugin.MYI"

if (Test-Path $pluginFile) {
    Write-Host "  plugin table files found" -ForegroundColor Gray
    
    # Backup plugin files
    if (Test-Path $pluginMYD) { Copy-Item $pluginMYD "$backupFolder\plugin.MYD.bak" }
    if (Test-Path $pluginMYI) { Copy-Item $pluginMYI "$backupFolder\plugin.MYI.bak" }
    
    # Try using myisamchk
    Write-Host "  Running myisamchk repair..." -ForegroundColor Gray
    $myisamchk = "c:\xampp\mysql\bin\myisamchk.exe"
    if (Test-Path $myisamchk) {
        & $myisamchk -r -f "c:\xampp\mysql\data\mysql\plugin"
        Write-Host "  Repair attempted" -ForegroundColor Green
    }
} else {
    Write-Host "  plugin table not found - will be recreated" -ForegroundColor Yellow
}

# Check for port conflicts
Write-Host ""
Write-Host "[Step 5] Checking for port 3306 conflicts..." -ForegroundColor Yellow
$port3306 = Get-NetTCPConnection -LocalPort 3306 -ErrorAction SilentlyContinue
if ($port3306) {
    Write-Host "  WARNING: Port 3306 is in use by another process!" -ForegroundColor Red
    Write-Host "  Process ID: $($port3306.OwningProcess)" -ForegroundColor Red
} else {
    Write-Host "  Port 3306 is available" -ForegroundColor Green
}

# Check my.ini configuration
Write-Host ""
Write-Host "[Step 6] Checking MySQL configuration..." -ForegroundColor Yellow
$myini = "c:\xampp\mysql\bin\my.ini"
if (Test-Path $myini) {
    Write-Host "  my.ini found" -ForegroundColor Green
    
    # Check for skip-grant-tables (for emergency access)
    $content = Get-Content $myini -Raw
    if ($content -notmatch "skip-grant-tables") {
        Write-Host "  Configuration looks normal" -ForegroundColor Green
    }
} else {
    Write-Host "  WARNING: my.ini not found!" -ForegroundColor Red
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "NEXT STEPS:" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Try starting MySQL from XAMPP Control Panel" -ForegroundColor White
Write-Host ""
Write-Host "If it still fails, try these options:" -ForegroundColor Yellow
Write-Host ""
Write-Host "OPTION A - Check the error log:" -ForegroundColor Cyan
Write-Host "  Get-Content 'c:\xampp\mysql\data\mysql_error.log' -Tail 20" -ForegroundColor Gray
Write-Host ""
Write-Host "OPTION B - Start MySQL in safe mode (skip grant tables):" -ForegroundColor Cyan
Write-Host "  1. Open: c:\xampp\mysql\bin\my.ini" -ForegroundColor Gray
Write-Host "  2. Add under [mysqld]: skip-grant-tables" -ForegroundColor Gray
Write-Host "  3. Start MySQL" -ForegroundColor Gray
Write-Host "  4. Run: mysql_upgrade.exe" -ForegroundColor Gray
Write-Host ""
Write-Host "OPTION C - Reinstall MySQL (preserves data):" -ForegroundColor Cyan
Write-Host "  1. Backup c:\xampp\mysql\data\ folder" -ForegroundColor Gray
Write-Host "  2. Reinstall XAMPP MySQL component" -ForegroundColor Gray
Write-Host "  3. Restore data folder" -ForegroundColor Gray
Write-Host ""
Write-Host "Backup location: $backupFolder" -ForegroundColor Cyan
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Advanced repair completed!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
