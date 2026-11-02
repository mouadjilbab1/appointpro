# AppointPro Commercial Packaging Script
$ErrorActionPreference = "Stop"

$distDir = "dist/appointpro"
$zipOutput = "appointpro.zip"

Write-Host "Creating clean staging directory: $distDir..."
if (Test-Path "dist") {
    Remove-Item -Recurse -Force "dist"
}
if (Test-Path $zipOutput) {
    Remove-Item -Force $zipOutput
}

New-Item -ItemType Directory -Force -Path $distDir | Out-Null

Write-Host "Copying plugin production files..."
$itemsToCopy = @(
    "appointpro.php",
    "uninstall.php",
    "readme.txt",
    "composer.json",
    "includes",
    "admin",
    "frontend",
    "assets",
    "languages",
    "templates",
    "docs"
)

foreach ($item in $itemsToCopy) {
    $src = "appointpro/$item"
    if (Test-Path $src) {
        Copy-Item -Recurse -Force -Path $src -Destination "$distDir/$item"
    }
}

Write-Host "Generating optimized production Composer autoloader..."
Push-Location $distDir
& composer dump-autoload --no-dev --optimize --no-interaction
Pop-Location

Write-Host "Creating installable plugin ZIP archive: $zipOutput..."
Push-Location "dist"
Compress-Archive -Path "appointpro" -DestinationPath "../$zipOutput" -Force
Pop-Location

Write-Host "Verifying ZIP archive..."
$zipItem = Get-Item $zipOutput
Write-Host "Successfully generated: $($zipItem.FullName) ($([math]::Round($zipItem.Length / 1KB, 2)) KB)"
