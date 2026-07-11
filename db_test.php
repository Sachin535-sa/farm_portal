<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Diagnostics</h2>";

echo "<h3>Environment Variables Seen by PHP:</h3>";
echo "DB_HOST: " . var_export(getenv('DB_HOST'), true) . "<br>";
echo "DB_PORT: " . var_export(getenv('DB_PORT'), true) . "<br>";
echo "DB_USER: " . var_export(getenv('DB_USER'), true) . "<br>";
echo "DB_NAME: " . var_export(getenv('DB_NAME'), true) . "<br>";
echo "DB_PASS exists: " . (getenv('DB_PASS') !== false ? 'Yes (Length: ' . strlen(getenv('DB_PASS')) . ')' : 'No') . "<br>";

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db   = getenv('DB_NAME') ?: 'farm_portal';
$port = getenv('DB_PORT') !== false ? intval(getenv('DB_PORT')) : null;

echo "<h3>Connection Attempt:</h3>";
$display_port = $port !== null ? $port : 'default (tries 3307 then 3306)';
echo "Connecting to host: <b>$host</b> on port: <b>$display_port</b>...<br>";

$start = microtime(true);
if ($port !== null) {
    $conn = @mysqli_connect($host, $user, $pass, $db, $port);
} else {
    $conn = @mysqli_connect($host, $user, $pass, $db, 3307);
    if (!$conn) {
        $conn = @mysqli_connect($host, $user, $pass, $db, 3306);
    }
}
$end = microtime(true);

echo "Connection attempt took: " . round($end - $start, 4) . " seconds<br>";

if (!$conn) {
    echo "<span style='color: red; font-weight: bold;'>Connection Failed:</span> " . mysqli_connect_error() . "<br>";
} else {
    echo "<span style='color: green; font-weight: bold;'>Connection Successful!</span><br>";
    mysqli_close($conn);
}
