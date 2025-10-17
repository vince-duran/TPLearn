# 🔧 Fix MySQL Startup Error in XAMPP

## ❌ Error You're Seeing:
```
Error: MySQL shutdown unexpectedly.
This may be due to a blocked port, missing dependencies, 
improper privileges, a crash, or a shutdown by another method.
```

## 🎯 Quick Solutions (Try in Order):

### Solution 1: Stop Conflicting Services (Most Common)
MySQL port 3306 might be used by another service.

**Steps:**
1. Open Command Prompt as Administrator
2. Run: `netstat -ano | findstr :3306`
3. If you see a process using port 3306, note the PID
4. Stop it: `taskkill /PID [number] /F`
5. Start MySQL in XAMPP again

### Solution 2: Rename Corrupted Data Folder
The MySQL data folder might be corrupted.

**Steps:**
1. **STOP Apache in XAMPP first** (if running)
2. Navigate to: `C:\xampp\mysql\data`
3. Rename folder `data` to `data_old`
4. Copy folder `C:\xampp\mysql\backup` to `C:\xampp\mysql\data`
5. Copy these files from `data_old` to `data`:
   - All folders (your databases)
   - `ibdata1`
   - `ib_logfile*`
6. Start MySQL in XAMPP

### Solution 3: Fix Port Conflict
Change MySQL port if another service is using 3306.

**Steps:**
1. In XAMPP, click **Config** next to MySQL
2. Select **my.ini**
3. Find line: `port=3306`
4. Change to: `port=3307`
5. Save file
6. Start MySQL

### Solution 4: Check Error Log
See what exactly went wrong.

**Steps:**
1. Open: `C:\xampp\mysql\data\mysql_error.log`
2. Look at the last few lines
3. Search for the error online or share here

### Solution 5: Reinstall MySQL Data (Last Resort)
⚠️ **WARNING: This will delete all databases!**

**Steps:**
1. Backup `C:\xampp\mysql\data` folder
2. Stop XAMPP
3. Delete `C:\xampp\mysql\data`
4. Copy `C:\xampp\mysql\backup` to `C:\xampp\mysql\data`
5. Start XAMPP
6. Restore your databases from backup

## 🚀 Quick Fix Command (Run as Administrator):

```powershell
# Stop any process using port 3306
$process = Get-NetTCPConnection -LocalPort 3306 -ErrorAction SilentlyContinue
if ($process) {
    Stop-Process -Id $process.OwningProcess -Force
    Write-Host "Stopped process using port 3306" -ForegroundColor Green
} else {
    Write-Host "No process found using port 3306" -ForegroundColor Yellow
}
```

## 💡 For Your Assessment Submissions Issue:

**Good News:** The Assessment Submissions fix doesn't require MySQL to be running RIGHT NOW because all the diagnostic and fix files are already created.

**However:** You WILL need MySQL running to:
- Test the API endpoints
- Create test submissions
- View actual data in the tutor stream

## 📋 Recommended Action:

1. **First:** Fix MySQL using Solution 1 or 2 above
2. **Then:** Continue with the Assessment Submissions testing
3. **The files are ready:** All diagnostic tools are in place, just need MySQL running to test them

## 🔍 Check What's Using Port 3306:

Run this in PowerShell (as Admin):
```powershell
Get-Process -Id (Get-NetTCPConnection -LocalPort 3306).OwningProcess
```

This will show you which program is blocking MySQL.
