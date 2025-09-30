<?php
// includes/db.php – Kết nối PDO dùng chung
$DB_HOST = 'localhost';
$DB_NAME = 'web_ban_ve';   // tên database của bạn
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Throwable $e) {
    die("Không thể kết nối CSDL: " . $e->getMessage());
}
?>
