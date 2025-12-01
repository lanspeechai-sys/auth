# SchoolLink Africa - Deployment Preparation Script
# This script prepares your files for deployment to live server

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "SchoolLink Africa - Deployment Prep" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Set paths
$sourceDir = "c:\xampp1\htdocs\schoollink-africa"
$deployDir = "c:\xampp1\htdocs\schoollink-africa-deploy"

# Files to exclude from deployment
$excludeFiles = @(
    "ECOMMERCE_IMPLEMENTATION.md",
    "FEATURE_COMPLETION_REPORT.md",
    "PAYMENT_SETUP.md",
    "QUICK_START.md",
    "bug-analysis.md",
    "DEPLOYMENT_GUIDE.md",
    "deploy.ps1",
    "*-test.php",
    "*-debug.php",
    "auth_test.php",
    "brand_test.php",
    "ecommerce_test.php",
    "logout-test.html",
    "debug-events.php",
    "debug-opportunities.php",
    "logout-debug.php"
)

# Create deployment directory
Write-Host "Creating deployment directory..." -ForegroundColor Yellow
if (Test-Path $deployDir) {
    Write-Host "Removing old deployment directory..." -ForegroundColor Yellow
    Remove-Item -Path $deployDir -Recurse -Force
}
New-Item -ItemType Directory -Path $deployDir | Out-Null

# Copy all files
Write-Host "Copying files..." -ForegroundColor Yellow
Copy-Item -Path "$sourceDir\*" -Destination $deployDir -Recurse -Force

# Remove excluded files
Write-Host "Removing test and documentation files..." -ForegroundColor Yellow
foreach ($pattern in $excludeFiles) {
    Get-ChildItem -Path $deployDir -Filter $pattern -Recurse | Remove-Item -Force
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "Deployment package ready!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Location: $deployDir" -ForegroundColor Cyan
Write-Host ""
Write-Host "NEXT STEPS:" -ForegroundColor Yellow
Write-Host "1. Update config/database.php with live server credentials" -ForegroundColor White
Write-Host "2. Update config/paystack.php with live API keys" -ForegroundColor White
Write-Host "3. Upload files to: http://169.239.251.102:442/~splendour.kalu/" -ForegroundColor White
Write-Host "4. Import database/schoollink_africa.sql to live database" -ForegroundColor White
Write-Host "5. Set folder permissions (uploads/ = 755)" -ForegroundColor White
Write-Host ""
Write-Host "Use FTP/SFTP client (FileZilla, WinSCP) to upload files" -ForegroundColor Cyan
Write-Host ""

# Ask if user wants to create a ZIP file
$createZip = Read-Host "Create ZIP file for upload? (Y/N)"
if ($createZip -eq "Y" -or $createZip -eq "y") {
    $zipPath = "c:\xampp1\htdocs\schoollink-africa-deploy.zip"
    if (Test-Path $zipPath) {
        Remove-Item $zipPath -Force
    }
    
    Write-Host "Creating ZIP file..." -ForegroundColor Yellow
    Compress-Archive -Path "$deployDir\*" -DestinationPath $zipPath -Force
    
    Write-Host ""
    Write-Host "ZIP file created: $zipPath" -ForegroundColor Green
    Write-Host "You can upload this ZIP file via cPanel File Manager" -ForegroundColor Cyan
}

Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
