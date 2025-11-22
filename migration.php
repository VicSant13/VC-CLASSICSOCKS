<?php
$host = 'classic_socks_db';
$db   = 'classicdb';
$user = 'classicuser';
$pass = 'classicpass123';
$port = 3307;

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "Connected to database.\n";
    
    // Check if column exists
    $stmt = $pdo->query("SELECT count(*) FROM information_schema.columns WHERE table_name = 'sales' AND column_name = 'product_id' AND table_schema = '$db'");
    $exists = $stmt->fetchColumn();
    
    if (!$exists) {
        echo "Adding product_id column...\n";
        $pdo->exec("ALTER TABLE sales ADD COLUMN product_id INT NULL");
        echo "Column added successfully.\n";
    } else {
        echo "Column product_id already exists.\n";
    }
    
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    echo "Please run the following SQL manually inside your database container:\n";
    echo "ALTER TABLE sales ADD COLUMN product_id INT NULL;\n";
    exit(1);
}
