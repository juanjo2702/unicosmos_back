<?php

$phpIniPath = 'C:\xampp\php\php.ini';

$lines = file($phpIniPath, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    exit("Failed to read php.ini\n");
}

$modified = false;
foreach ($lines as $i => $line) {
    $trimmed = trim($line);
    if (strpos($trimmed, 'curl.cainfo=') === 0) {
        $lines[$i] = ';'.$line;
        $modified = true;
        echo "Commented curl.cainfo\n";
    }
    if (strpos($trimmed, 'openssl.cafile=') === 0) {
        $lines[$i] = ';'.$line;
        $modified = true;
        echo "Commented openssl.cafile\n";
    }
}

if ($modified) {
    file_put_contents($phpIniPath, implode("\n", $lines));
    echo "php.ini updated (commented)\n";
} else {
    echo "No lines to comment\n";
}
