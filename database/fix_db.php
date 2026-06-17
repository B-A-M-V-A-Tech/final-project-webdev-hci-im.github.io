<?php
$mysqladmin = 'C:/xampp/mysql/bin/mysqladmin.exe';
$mysqld = 'C:/xampp/mysql/bin/mysqld.exe';
$mysql = 'C:/xampp/mysql/bin/mysql.exe';
$myini = 'C:/xampp/mysql/bin/my.ini';
$dataDir = 'C:/xampp/mysql/data/sip_and_pulse_db';

function run($cmd) {
    exec($cmd . ' 2>&1', $out, $code);
    return ['out' => implode("\n", $out), 'code' => $code];
}

echo "Stopping MySQL...\n";
run("\"$mysqladmin\" -u root shutdown");
sleep(4);

if (is_dir($dataDir)) {
    echo "Removing broken database folder...\n";
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dataDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $file->isDir() ? rmdir($file) : unlink($file);
    }
    rmdir($dataDir);
}

echo "Starting MySQL...\n";
pclose(popen('start /B "" "' . $mysqld . '" --defaults-file="' . $myini . '" --standalone', 'r'));

for ($i = 0; $i < 12; $i++) {
    sleep(1);
    $ping = run("\"$mysqladmin\" -u root ping");
    if ($ping['code'] === 0) {
        break;
    }
}

$result = run("\"$mysql\" -u root -e \"DROP DATABASE IF EXISTS sip_and_pulse_db; CREATE DATABASE sip_and_pulse_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;\"");
if ($result['code'] !== 0) {
    echo "MySQL setup failed:\n" . $result['out'] . "\n";
    exit(1);
}

chdir(dirname(__DIR__));
include __DIR__ . '/db_connect.php';

$conn = new mysqli('localhost', 'root', '', 'sip_and_pulse_db');
$tables = $conn->query('SHOW TABLES');
$list = [];
while ($tables && ($row = $tables->fetch_row())) {
    $list[] = $row[0];
}
echo 'Tables: ' . (count($list) ? implode(', ', $list) : '(none)') . "\n";

foreach (['menu_items', 'device_access_platforms', 'analytics_config', 'admin_users'] as $t) {
    if (!in_array($t, $list, true)) {
        echo "MISSING: $t\n";
        continue;
    }
    $c = $conn->query("SELECT COUNT(*) AS c FROM `$t`")->fetch_assoc();
    echo "$t rows: {$c['c']}\n";
}

$conn->close();
echo "Done.\n";
