<?php
require __DIR__ . '/config/db.php';
$pdo = getDB();
$h1 = password_hash('password', PASSWORD_DEFAULT);
$h2 = password_hash('super123', PASSWORD_DEFAULT);
$h3 = password_hash('agent123', PASSWORD_DEFAULT);
$pdo->prepare('UPDATE users SET password=? WHERE username=?')->execute([$h1,'admin']);
$pdo->prepare('UPDATE users SET password=? WHERE username=?')->execute([$h2,'supervisor1']);
$pdo->prepare('UPDATE users SET password=? WHERE username=?')->execute([$h3,'agent1']);
echo 'Passwords updated OK' . PHP_EOL;
$users = $pdo->query('SELECT username,role,status FROM users')->fetchAll();
foreach($users as $u) echo $u['username'] . ' | ' . $u['role'] . ' | ' . $u['status'] . PHP_EOL;
echo PHP_EOL . 'Tables:' . PHP_EOL;
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach($tables as $t) echo '  ' . $t . PHP_EOL;
