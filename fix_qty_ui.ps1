$files = Get-ChildItem -Path "c:\xampp\htdocs\egglandbd" -Recurse -Include *.php, *.html

foreach ($f in $files) {
    $content = Get-Content $f.FullName
    $changed = $false
    for ($i = 0; $i -lt $content.Length; $i++) {
        # Fix step="0.01" to step="1" for qty inputs
        if ($content[$i] -match "qty" -and $content[$i] -match 'step="0.01"') {
            # Only if it does not contain 'price' or 'amount' (to avoid changing price steps accidentally if 'qty' is also on the line)
            # Wait, what if they are on the same line?
            # E.g. in operation.php we split them so it's safe.
            # Let's do a targeted replace using regex
            if ($content[$i] -match "type=`"number`".*?qty") {
                $content[$i] = $content[$i] -replace 'step="0.01"', 'step="1"'
                $changed = $true
            } elseif ($content[$i] -match "qty.*?type=`"number`"") {
                $content[$i] = $content[$i] -replace 'step="0.01"', 'step="1"'
                $changed = $true
            } elseif ($content[$i] -match "prod-qty" -and $content[$i] -match 'step="0.01"') {
                $content[$i] = $content[$i] -replace 'step="0.01"', 'step="1"'
                $changed = $true
            }
        }
        
        # In JS: replace ${item.qty} with ${parseInt(item.qty||0)} to drop any decimals
        if ($content[$i] -match '\$\{item\.qty\}') {
            $content[$i] = $content[$i] -replace '\$\{item\.qty\}', '${parseInt(item.qty||0)}'
            $changed = $true
        }
        if ($content[$i] -match '\$\{qty\}' -and $content[$i] -notmatch 'price') {
            # Wait, ${qty} might be used. Let's replace only in inputs.
            $content[$i] = $content[$i] -replace 'value="\$\{qty\}"', 'value="${parseInt(qty||0)}"'
            $changed = $true
        }
    }
    
    if ($changed) {
        Set-Content -Path $f.FullName -Value $content
        Write-Host "Updated $($f.FullName)"
    }
}
