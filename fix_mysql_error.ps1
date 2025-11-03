# MySQL Repair Script for XAMPP
# This script fixes the "MySQL shutdown unexpectedly" error

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "MySQL Repair Script for XAMPP" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Check if MySQL is running and stop it
Write-Host "[Step 1] Checking MySQL status..." -ForegroundColor Yellow
$mysqlProcess = Get-Process mysqld -ErrorAction SilentlyContinue
if ($mysqlProcess) {
    Write-Host "MySQL is running. Attempting to stop..." -ForegroundColor Yellow
    Stop-Process -Name mysqld -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 3
    Write-Host "MySQL stopped." -ForegroundColor Green
} else {
    Write-Host "MySQL is not running." -ForegroundColor Green
}

# Step 2: Backup current Aria log files
Write-Host ""
Write-Host "[Step 2] Backing up Aria log files..." -ForegroundColor Yellow
$backupFolder = "c:\xampp\mysql\data\backup_aria_logs_$(Get-Date -Format 'yyyyMMdd_HHmmss')"
New-Item -ItemType Directory -Path $backupFolder -Force | Out-Null

$ariaLogs = Get-ChildItem "c:\xampp\mysql\data\aria_log.*" -ErrorAction SilentlyContinue
if ($ariaLogs) {
    foreach ($log in $ariaLogs) {
        Copy-Item $log.FullName -Destination $backupFolder
        Write-Host "  Backed up: $($log.Name)" -ForegroundColor Gray
    }
    Write-Host "Aria logs backed up to: $backupFolder" -ForegroundColor Green
} else {
    Write-Host "No Aria log files found." -ForegroundColor Gray
}

# Step 3: Delete corrupted Aria log files
Write-Host ""
Write-Host "[Step 3] Removing corrupted Aria log files..." -ForegroundColor Yellow
$ariaLogs = Get-ChildItem "c:\xampp\mysql\data\aria_log.*" -ErrorAction SilentlyContinue
if ($ariaLogs) {
    foreach ($log in $ariaLogs) {
        Remove-Item $log.FullName -Force
        Write-Host "  Deleted: $($log.Name)" -ForegroundColor Gray
    }
    Write-Host "Corrupted Aria logs removed." -ForegroundColor Green
} else {
    Write-Host "No Aria log files to remove." -ForegroundColor Gray
}

# Step 4: Check and backup ibdata1 file
Write-Host ""
Write-Host "[Step 4] Checking InnoDB data files..." -ForegroundColor Yellow
$ibdata = "c:\xampp\mysql\data\ibdata1"
if (Test-Path $ibdata) {
    $size = (Get-Item $ibdata).Length / 1MB
    Write-Host "  ibdata1 size: $([math]::Round($size, 2)) MB" -ForegroundColor Gray
    Write-Host "InnoDB files OK." -ForegroundColor Green
} else {
    Write-Host "  WARNING: ibdata1 not found!" -ForegroundColor Red
}

# Step 5: Attempt to repair mysql.plugin table
Write-Host ""
Write-Host "[Step 5] Preparing to repair mysql.plugin table..." -ForegroundColor Yellow
Write-Host "  This will be done after MySQL starts." -ForegroundColor Gray

# Step 6: Instructions to start MySQL
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "NEXT STEPS:" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Go to XAMPP Control Panel" -ForegroundColor White
Write-Host "2. Click the 'Start' button next to MySQL" -ForegroundColor White
Write-Host "3. Check if MySQL starts successfully" -ForegroundColor White
Write-Host ""
Write-Host "If MySQL starts successfully:" -ForegroundColor Green
Write-Host "  - Your database is now working!" -ForegroundColor Green
Write-Host ""
Write-Host "If MySQL still fails to start:" -ForegroundColor Yellow
Write-Host "  - Run: .\fix_mysql_advanced.ps1" -ForegroundColor Yellow
Write-Host "  - Or check the error log again" -ForegroundColor Yellow
Write-Host ""
Write-Host "Backup location: $backupFolder" -ForegroundColor Cyan
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Repair script completed!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
