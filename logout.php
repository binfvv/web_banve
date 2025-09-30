<?php
// logout.php — Đăng xuất an toàn
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Xóa toàn bộ dữ liệu trong session
$_SESSION = [];

// Nếu cookie session tồn tại thì xóa luôn
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Hủy session hoàn toàn
session_destroy();

// Xác định đường dẫn tuyệt đối đến login.php
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$redirect = $basePath . '/login.php';

// Chuyển hướng về trang đăng nhập
header("Location: $redirect");
exit;
?>
