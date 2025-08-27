<?php
session_start();

// Xóa tất cả dữ liệu trong session
session_unset();
session_destroy();

// Chuyển hướng về trang đăng nhập
header("Location: login.php");
exit();
?>