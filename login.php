<?php 
include 'includes/db.php'; // Kết nối tới cơ sở dữ liệu
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Lấy dữ liệu từ form đăng nhập
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Sử dụng prepared statement để bảo vệ chống SQL Injection
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    // Kiểm tra mật khẩu
    if ($result && $result['password'] == $password) {
        // Lưu thông tin vào session
        $_SESSION['user_id'] = $result['id'];
        $_SESSION['role'] = $result['role'];
        
        // Kiểm tra role và chuyển hướng dựa trên role
        if ($result['role'] === 'khach_hang') {
            header('Location: http://localhost/web_banve/');
        } elseif ($result['role'] === 'admin') {
            header('Location: http://localhost/web_banve/admin/admin.html');
        }
        exit();
    } else {
        // Nếu mật khẩu sai, chuyển hướng về trang đăng nhập với thông báo lỗi
        header('Location: login.php?error=invalid');
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="assets/style_login.css">
</head>
<body>
    <!-- Hiển thị thông báo lỗi nếu có lỗi -->
    <div class="container">
    <form action="<?php echo $_SERVER['PHP_SELF'];?>" method="POST">
        <h1>Login</h1>
        <?php 
        if (isset($_GET['error']) && $_GET['error'] == 'invalid'): ?>
            <p style="color: red;">Tên đăng nhập hoặc mật khẩu không đúng. Vui lòng thử lại.</p>
        <?php endif; 
        ?>
        <input type="text" id="username" name="username" placeholder="Username" required>
        <br>
        <input type="password" id="password" name="password" placeholder="Password" required><br>
        <a href="#" class="forgot" target="_blank">Forgot password?</a>
        <br>
        <input type="submit" value="Login" class="btn"><br>
        <label for="">Don't have an account? </label><a href="signup.php" class="forgot"> Signup</a>
    </form>
    </div>
</body>
</html>

