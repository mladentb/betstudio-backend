@echo off
title BetStudio Development Server
color 0A

echo.
echo  ╔══════════════════════════════════════════════════════════════╗
echo  ║                    BetStudio Development                     ║
echo  ║                      Starting Server...                      ║
echo  ╚══════════════════════════════════════════════════════════════╝
echo.

cd /d C:\Projects\BetStudio

:: Check if PHP is available
where php >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [ERROR] PHP is not installed or not in PATH!
    echo Please install PHP and add it to your PATH.
    pause
    exit /b 1
)

:: Check if composer dependencies are installed
if not exist "vendor\autoload.php" (
    echo [INFO] Installing Composer dependencies...
    composer install
    echo.
)

:: Run migrations if needed
echo [INFO] Running database migrations...
php artisan migrate --force
echo.

:: Clear and cache config for performance
echo [INFO] Optimizing application...
php artisan config:cache
php artisan route:cache
echo.

:: Start the server in background and open browser
echo [INFO] Starting Laravel development server on http://127.0.0.1:8000
echo [INFO] Opening browser...
echo.
echo ══════════════════════════════════════════════════════════════════
echo   API Endpoints:
echo   - GET  http://127.0.0.1:8000/api/sports
echo   - GET  http://127.0.0.1:8000/api/leagues
echo   - GET  http://127.0.0.1:8000/api/games
echo   - GET  http://127.0.0.1:8000/api/games-upcoming
echo   - GET  http://127.0.0.1:8000/api/games-live
echo   - GET  http://127.0.0.1:8000/api/games-today
echo ══════════════════════════════════════════════════════════════════
echo.
echo Press Ctrl+C to stop the server
echo.

:: Open browser after 2 seconds
start /b cmd /c "timeout /t 2 /nobreak >nul && start http://127.0.0.1:8000/api"

:: Start Laravel server
php artisan serve --host=127.0.0.1 --port=8000
