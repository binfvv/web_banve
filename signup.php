<?php
// signup.php — Đăng ký an toàn với PDO + password_hash + CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => isset($_SERVER['HTTPS']),
        'samesite' => 'Lax',
    ]);
    session_start();
}
require_once __DIR__ . '/includes/db.php'; // phải tạo $pdo (PDO)

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// CSRF token
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

$register_message = '';
$success = isset($_GET['success']) && $_GET['success'] === 'true';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Kiểm tra CSRF
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $register_message = 'Phiên không hợp lệ. Vui lòng tải lại trang.';
    } else {
        // 2) Lấy & validate input
        $new_username = trim($_POST['username'] ?? '');
        $new_password = $_POST['password'] ?? '';
        $confirm_password = $_POST['cf_password'] ?? '';
        $email = trim($_POST['email'] ?? '');

        if ($new_username === '' || $new_password === '' || $confirm_password === '' || $email === '') {
            $register_message = 'Vui lòng nhập đủ các trường.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $register_message = 'Email không hợp lệ.';
        } elseif (strlen($new_username) < 3 || strlen($new_username) > 50) {
            $register_message = 'Username phải từ 3–50 ký tự.';
        } elseif (strlen($new_password) < 6) {
            $register_message = 'Mật khẩu phải từ 6 ký tự trở lên.';
        } elseif ($new_password !== $confirm_password) {
            $register_message = 'Mật khẩu và xác nhận mật khẩu không khớp!';
        } else {
            try {
                // 3) Kiểm tra trùng username/email
                $stmt = $pdo->prepare("SELECT username, email FROM users WHERE username = :u OR email = :e LIMIT 1");
                $stmt->execute([':u' => $new_username, ':e' => $email]);
                $dup = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($dup) {
                    if (strcasecmp($dup['username'], $new_username) === 0) {
                        $register_message = 'Tên đăng nhập đã tồn tại!';
                    } elseif (strcasecmp($dup['email'], $email) === 0) {
                        $register_message = 'Email đã được sử dụng!';
                    }
                } else {
                    // 4) Hash mật khẩu và tạo tài khoản (role=khach_hang)
                    $hash = password_hash($new_password, PASSWORD_DEFAULT);

                    // Nếu bảng users của bạn có cột role (login đang dùng), insert kèm role.
                    // Nếu KHÔNG có cột role, đổi SQL bên dưới thành (username, password, email)
                    $sql = "INSERT INTO users (username, password, email, role) VALUES (:u, :p, :e, 'khach_hang')";
                    $ins = $pdo->prepare($sql);
                    $ok = $ins->execute([':u'=>$new_username, ':p'=>$hash, ':e'=>$email]);

                    if ($ok) {
                        // Chuyển sang trang login với thông báo success
                        header("Location: login.php?signup=1");
                        exit;
                    } else {
                        $register_message = 'Không thể đăng ký. Vui lòng thử lại.';
                    }
                }
            } catch (Throwable $e) {
                // Gợi ý lỗi ràng buộc unique, v.v.
                $register_message = 'Lỗi hệ thống: ' . h($e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Signup</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style_login.css">
</head>
<body>
    <div class="container">
        <form action="signup.php" method="POST" autocomplete="off">
            <h1>Signup</h1>

            <?php if ($register_message): ?>
                <p style="color: red;"><?= h($register_message) ?></p>
            <?php endif; ?>

            <?php if ($success): ?>
                <p style="color: green;">Đăng ký thành công! Vui lòng đăng nhập.</p>
            <?php endif; ?>

            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

            <input type="text" id="username" name="username" placeholder="Username" required>
            <br>
            <input type="password" id="password" name="password" placeholder="Create password" required><br>
            <input type="password" id="cf_password" name="cf_password" placeholder="Confirm password" required><br>
            <input type="email" id="email" name="email" placeholder="Email" required><br><br>
            <input type="submit" value="Signup" class="btn"><br>
            <label>Already have an account? </label><a href="login.php" class="forgot"> Login</a>
        </form>
    </div>
</body>
</html>
