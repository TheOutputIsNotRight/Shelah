<?php
// Quick diagnostics — visit /api/setup/info.php to see loaded PHP extensions
// DELETE this file after confirming setup works.
header('Content-Type: text/html; charset=utf-8');
echo '<h2>PHP Version: ' . PHP_VERSION . '</h2>';
echo '<h3>PDO Drivers:</h3><pre>';
print_r(PDO::getAvailableDrivers());
echo '</pre>';
echo '<h3>Extensions:</h3><pre>';
echo implode("\n", get_loaded_extensions());
echo '</pre>';
echo '<h3>DATABASE_URL set: </h3>';
echo getenv('DATABASE_URL') ? '✅ YES' : '❌ NO — check Vercel env vars';
