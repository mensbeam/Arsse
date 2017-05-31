@echo off
setlocal
set base=%~dp0
start /b php -n -S localhost:8000 "%base%\server.php" >nul 2>nul
timeout /nobreak /t 1 >nul
php "%base%\..\vendor\phpunit\phpunit\phpunit" -c "%base%\phpunit.xml" %*
timeout /nobreak /t 1 >nul
for /f "usebackq tokens=5" %%a in (`netstat -aon ^| find "LISTENING" ^| find ":8000"`) do (
    taskkill /f /pid %%a >nul
    goto :eof
)