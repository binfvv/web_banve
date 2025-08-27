<?php
include 'includes/db.php'; // Kết nối tới cơ sở dữ liệu

$register_message = ""; // Biến lưu thông báo đăng ký

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_username = $_POST['username'];
    $new_password = $_POST['password'];
    $confirm_password = $_POST['cf_password'];
    $email = $_POST['email'];

    // Kiểm tra tên đăng nhập đã tồn tại
    $sql_check = "SELECT * FROM users WHERE username = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $new_username);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows > 0) {
        $register_message = "Tên đăng nhập đã tồn tại!";
    } else {
        // Kiểm tra mật khẩu và xác nhận mật khẩu có khớp không
        if ($new_password === $confirm_password) {
            // Lưu mật khẩu và tên đăng nhập vào cơ sở dữ liệu mà không mã hóa
            $sql = "INSERT INTO users (username, password, email) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $new_username, $new_password, $email); // Chỉnh sửa lại bind_param

            if ($stmt->execute()) {
                // Đăng ký thành công, chuyển hướng đến trang đăng nhập và thông báo thành công
                header("Location: signup.php?success=true");
                exit(); // Dừng script sau khi chuyển hướng
            } else {
                $register_message = "Lỗi: Không thể đăng ký. " . $stmt->error;
            }

            $stmt->close();
        } else {
            $register_message = "Mật khẩu và xác nhận mật khẩu không khớp!";
        }
    }

    $stmt_check->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Signup</title>
    <link rel="stylesheet" href="assets/style_login.css">
</head>

<body>
    <div class="container">
        <form action="signup.php" method="POST">
            <h1>Signup</h1>

            <!-- Hiển thị thông báo lỗi hoặc thành công -->
            <?php if (!empty($register_message)): ?>
                <p style="color: red;"><?php echo $register_message; ?></p>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && $_GET['success'] == 'true'): ?>
                <p style="color: green;">Đăng ký thành công! Vui lòng đăng nhập.</p>
            <?php endif; ?>

            <input type="text" id="username" name="username" placeholder="Username" required>
            <br>
            <input type="password" id="password" name="password" placeholder="Create password" required><br>
            <input type="password" id="cf_password" name="cf_password" placeholder="Confirm password" required><br>
            <input type="email" id="email" name="email" placeholder="Email" required><br><br>
            <input type="submit" value="Signup" class="btn"><br>
            <label for="">Already have an account? </label><a href="login.php" class="forgot"> Login</a>
        </form>
    </div>
</body>

</html>
