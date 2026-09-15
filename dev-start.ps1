# BetStudio Development Server Starter
# Run: .\dev-start.ps1

$Host.UI.RawUI.WindowTitle = "BetStudio Dev Server"

Write-Host ""
Write-Host "  ╔══════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "  ║                    BetStudio Development                     ║" -ForegroundColor Cyan
Write-Host "  ║                      Starting Server...                      ║" -ForegroundColor Cyan
Write-Host "  ╚══════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

Set-Location "C:\Projects\BetStudio"

# Check PHP
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host "[ERROR] PHP is not installed or not in PATH!" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

# Check vendor folder
if (-not (Test-Path "vendor\autoload.php")) {
    Write-Host "[INFO] Installing Composer dependencies..." -ForegroundColor Yellow
    composer install
    Write-Host ""
}

# Run migrations
Write-Host "[INFO] Running database migrations..." -ForegroundColor Yellow
php artisan migrate --force
Write-Host ""

# Optimize
Write-Host "[INFO] Optimizing application..." -ForegroundColor Yellow
php artisan config:cache
php artisan route:cache
Write-Host ""

# Display info
Write-Host "══════════════════════════════════════════════════════════════════" -ForegroundColor Green
Write-Host "  API Endpoints:" -ForegroundColor White
Write-Host "  - GET  http://127.0.0.1:8000/api/sports" -ForegroundColor Gray
Write-Host "  - GET  http://127.0.0.1:8000/api/leagues" -ForegroundColor Gray
Write-Host "  - GET  http://127.0.0.1:8000/api/games" -ForegroundColor Gray
Write-Host "  - GET  http://127.0.0.1:8000/api/games-upcoming" -ForegroundColor Gray
Write-Host "  - GET  http://127.0.0.1:8000/api/games-live" -ForegroundColor Gray
Write-Host "  - GET  http://127.0.0.1:8000/api/games-today" -ForegroundColor Gray
Write-Host "══════════════════════════════════════════════════════════════════" -ForegroundColor Green
Write-Host ""
Write-Host "[INFO] Starting Laravel server on http://127.0.0.1:8000" -ForegroundColor Green
Write-Host "[INFO] Press Ctrl+C to stop" -ForegroundColor Yellow
Write-Host ""

# Open browser after delay
Start-Job -ScriptBlock {
    Start-Sleep -Seconds 2
    Start-Process "http://127.0.0.1:8000/api"
} | Out-Null

# Start server
php artisan serve --host=127.0.0.1 --port=8000
