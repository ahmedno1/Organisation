$content = Get-Content "app/Http/Controllers/FamilyImportController.php" -Raw
$enc = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText("app/Http/Controllers/FamilyImportController.php", $content, $enc)
