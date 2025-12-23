<?php
// login.php — Đăng nhập an toàn (PDO + password_hash)
if (session_status() === PHP_SESSION_NONE) {
    // Bảo mật session cookie
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => isset($_SERVER['HTTPS']),
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/includes/db.php'; // tạo $pdo (PDO)

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kiểm tra CSRF
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $error = 'Phiên đăng nhập không hợp lệ. Vui lòng tải lại trang.';
    } else {
        // Lấy dữ liệu
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
        } else {
            // Truy vấn user
            $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $stored = $user['password'];

                // Nếu cột password đang lưu hash -> dùng password_verify
                $is_hashed = password_get_info($stored)['algo'] !== 0;
                $ok = $is_hashed ? password_verify($password, $stored) : hash_equals($stored, $password);

                if ($ok) {
                    // Nếu trước đây lưu plain text => tự động chuyển sang hash an toàn
                    if (!$is_hashed) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $up = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
                        $up->execute([':p' => $newHash, ':id' => $user['id']]);
                    }

                    // Lưu session
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['role']    = $user['role'];

                    // Điều hướng theo quyền
                    if ($user['role'] === 'admin') {
                        header('Location: /web_banve/admin/index.php'); // hoặc admin/index.php
                    } else {
                        header('Location: /web_banve/');
                    }
                    exit;
                }
            }

            // Sai thông tin đăng nhập
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/style_login.css">
</head>
<body>
  <div class="container">
    <form action="login.php" method="POST" autocomplete="off">
      <h1>Login</h1>

      <?php if ($error): ?>
        <p style="color:#d93025;margin-bottom:10px;"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>

      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

      <input type="text" id="username" name="username" placeholder="Username" required>
      <br>
      <input type="password" id="password" name="password" placeholder="Password" required>
      <br>
      <a href="#" class="forgot" target="_blank">Forgot password?</a>
      <br>
      <input type="submit" value="Login" class="btn"><br>
      <label>Don't have an account? </label><a href="signup.php" class="forgot"> Signup</a>
    </form>
  </div>
</body>
</html>
