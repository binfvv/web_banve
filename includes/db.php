<?php
// Thông tin kết nối cơ sở dữ liệu
$servername = "localhost"; // Địa chỉ máy chủ
$username = "root";        // Tên người dùng MySQL
$password = "";            // Mật khẩu MySQL
$dbname = "web_ban_ve";    // Tên cơ sở dữ liệu

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
//echo "Kết nối thành công!";
?>