<?php

$phpIniPath = 'C:\xampp\php\php.ini';
$backupPath = 'C:\xampp\php\php.ini.backup';

// Read the file
$lines = file($phpIniPath, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    exit("Failed to read php.ini\n");
}

// Make a backup
if (! copy($phpIniPath, $backupPath)) {
    echo "Warning: Could not create backup\n";
}

$newCurlLine = 'curl.cainfo="C:\xampp\php\extras\ssl\cacert.pem"';
$newOpensslLine = 'openssl.cafile="C:\xampp\php\extras\ssl\cacert.pem"';

$modified = false;
foreach ($lines as $i => $line) {
    if (strpos($line, 'curl.cainfo=') === 0) {
        if ($lines[$i] !== $newCurlLine) {
            $lines[$i] = $newCurlLine;
            $modified = true;
            echo "Updated curl.cainfo\n";
        }
    }
    if (strpos($line, 'openssl.cafile=') === 0) {
        if ($lines[$i] !== $newOpensslLine) {
            $lines[$i] = $newOpensslLine;
            $modified = true;
            echo "Updated openssl.cafile\n";
        }
    }
}

if ($modified) {
    // Write back
    $result = file_put_contents($phpIniPath, implode("\n", $lines));
    if ($result === false) {
        exit("Failed to write php.ini\n");
    }
    echo "php.ini updated successfully\n";
} else {
    echo "No changes needed\n";
}

// Verify the new values
$ini = parse_ini_file($phpIniPath);
echo 'Current curl.cainfo: '.($ini['curl.cainfo'] ?? 'NOT SET')."\n";
echo 'Current openssl.cafile: '.($ini['openssl.cafile'] ?? 'NOT SET')."\n";
