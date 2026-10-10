$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

Start-Process powershell -ArgumentList @(
    '-NoExit',
    '-Command',
    'php artisan serve --host=127.0.0.1 --port=8000'
)

Start-Process powershell -ArgumentList @(
    '-NoExit',
    '-Command',
    'nvm use 20.19.0; npm run dev'
)

Write-Host "Started Laravel and Vite. Open http://127.0.0.1:8000"
