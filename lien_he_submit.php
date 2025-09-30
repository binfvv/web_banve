<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php'; // $pdo hoặc $conn

// CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')
) {
    http_response_code(400);
    exit('Yêu cầu không hợp lệ.');
}

// Honeypot (bot sẽ điền)
if (!empty($_POST['website'])) {
    header('Location: lien_he.php?sent=1'); exit;
}

// Lấy dữ liệu
$name  = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$topic = trim($_POST['topic'] ?? '');
$msg   = trim($_POST['message'] ?? '');

if ($name==='' || $email==='' || $topic==='' || strlen($msg) < 10) {
    header('Location: lien_he.php?sent=0'); exit;
}

// Lưu database
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("INSERT INTO contact_messages(name,email,phone,topic,message,created_at,ip_addr)
                               VALUES(?,?,?,?,?,NOW(),?)");
        $stmt->execute([$name,$email,$phone,$topic,$msg,$_SERVER['REMOTE_ADDR'] ?? '']);
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $stmt = $conn->prepare("INSERT INTO contact_messages(name,email,phone,topic,message,created_at,ip_addr)
                                VALUES(?,?,?,?,?,NOW(),?)");
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt->bind_param('ssssss',$name,$email,$phone,$topic,$msg,$ip);
        $stmt->execute(); $stmt->close();
    }
    // TODO (tuỳ chọn): gửi email thông báo đến bộ phận CSKH
    header('Location: lien_he.php?sent=1'); exit;
} catch (Throwable $e) {
    // error_log($e->getMessage());
    header('Location: lien_he.php?sent=0'); exit;
}
