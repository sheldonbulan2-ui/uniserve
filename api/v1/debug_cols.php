<?php
require __DIR__ . '/config/database.php';
$pdo = Database::getConnection();
$cols = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'users'")->fetchAll();
echo json_encode($cols, JSON_PRETTY_PRINT);
