@echo off
REM AppChama — worker de filas (Redis). Nao usa portas TCP extras.
cd /d C:\xampp\htdocs\appchama\api
:loop
C:\xampp\php\php.exe artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
timeout /t 5 /nobreak >nul
goto loop
