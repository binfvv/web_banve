<?php
// checkout.php — demo thanh toán giỏ hàng (PDO)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php'; // $pdo (PDO)

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
$cart = array_values(array_unique(array_map('intval', $_SESSION['cart'])));
$cart_count = count($cart);

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf'];

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Lấy chi tiết ghế trong giỏ
$items = [];
$total = 0;

if ($cart_count > 0) {
  $ph = implode(',', array_fill(0, $cart_count, '?'));
  $sql = "
    SELECT g.id AS ghe_id, g.so_ghe,
           ct.id AS chuyen_id, ct.ten_tau, ct.ga_di, ct.ga_den,
           ct.ngay_di, ct.gio_di, ct.ngay_den, ct.gio_den,
           COALESCE(g.gia, ct.gia_ve) AS gia_ve
    FROM ghe g
    JOIN chuyen_tau ct ON g.id_tau = ct.id
    WHERE g.id IN ($ph)
    ORDER BY ct.ngay_di, ct.gio_di, ct.id, g.so_ghe
  ";
  $stm = $pdo->prepare($sql);
  $stm->execute($cart);
  $items = $stm->fetchAll(PDO::FETCH_ASSOC);
  foreach ($items as $it) $total += (int)$it['gia_ve'];
}

/* ====== POST: Confirm thanh toán (demo) ====== */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['confirm_checkout'])) {
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    die('CSRF token không hợp lệ.');
  }
  if ($cart_count === 0) {
    header('Location: gio_hang.php'); exit;
  }

  $payment_method = $_POST['payment_method'] ?? 'online'; // 'online' | 'offline'

  // Transaction: khóa ghế + xác nhận đặt
  try {
    $pdo->beginTransaction();

    // Khóa các ghế trong giỏ (FOR UPDATE)
    $ph = implode(',', array_fill(0, $cart_count, '?'));
    $stmLock = $pdo->prepare("SELECT id, trang_thai FROM ghe WHERE id IN ($ph) FOR UPDATE");
    $stmLock->execute($cart);
    $locked = $stmLock->fetchAll(PDO::FETCH_KEY_PAIR); // id => trang_thai

    // Nếu có ghế đã bị đặt trước -> báo lỗi
    $conflicts = [];
    foreach ($locked as $sid => $st) {
      if ($st === 'da_dat') $conflicts[] = $sid;
    }
    if ($conflicts) {
      $pdo->rollBack();
      $_SESSION['checkout_error'] = 'Một số ghế đã có người đặt trước. Vui lòng bỏ ghế đó khỏi giỏ và thử lại.';
      header('Location: gio_hang.php'); exit;
    }

    // Tính lại tổng tiền từ DB (an toàn)
    $stmSum = $pdo->prepare("
      SELECT SUM(COALESCE(g.gia, ct.gia_ve)) AS tong
      FROM ghe g JOIN chuyen_tau ct ON g.id_tau = ct.id
      WHERE g.id IN ($ph)
    ");
    $stmSum->execute($cart);
    $real_total = (int)($stmSum->fetchColumn() ?? 0);

    // Cập nhật trạng thái ghế -> da_dat + gán user_id
    $stmUpd = $pdo->prepare("UPDATE ghe SET trang_thai='da_dat', user_id=:uid WHERE id=:id AND trang_thai<>'da_dat'");
    foreach ($cart as $sid) {
      $stmUpd->execute([':uid'=>$user_id, ':id'=>$sid]);
    }

    // Ghi thanh toán
    $stmPay = $pdo->prepare("
      INSERT INTO thanh_toan(user_id, phuong_thuc, tong_tien, ngay_giao_dich, ghi_chu)
      VALUES (:uid, :pm, :total, NOW(), :note)
    ");
    $stmPay->execute([
      ':uid'   => $user_id,
      ':pm'    => $payment_method,
      ':total' => $real_total,
      ':note'  => 'Demo checkout'
    ]);

    $pdo->commit();

    // Clear giỏ + chuyển trang
    $_SESSION['cart'] = [];
    header('Location: dat_ve_thanh_cong.php');
    exit;

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die('Lỗi thanh toán demo: '.$e->getMessage());
  }
}

?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Thanh toán (demo)</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#f6f8fb}
    .navbar-brand img{height:44px}
    .card{border-radius:16px}
    .table thead th{background:#0d6efd;color:#fff}
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark" style="background:#0d6efd;">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
      <img src="assets/LOGO_n.png" alt=""><span class="fw-bold">Vé tàu</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="index.php">TRANG CHỦ</a></li>
        <li class="nav-item"><a class="nav-link" href="tim_kiem.php">TÌM CHUYẾN TÀU</a></li>
        <li class="nav-item"><a class="nav-link" href="lich_trinh.php">LỊCH TRÌNH</a></li>
        <li class="nav-item"><a class="nav-link" href="gio_hang.php">GIỎ HÀNG (<?= $cart_count ?>)</a></li>
        <li class="nav-item"><a class="nav-link active" href="#">THANH TOÁN</a></li>
      </ul>
      <a class="btn btn-sm btn-light" href="logout.php">ĐĂNG XUẤT</a>
    </div>
  </div>
</nav>

<div class="container my-4">
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-body">
          <h1 class="h5 mb-3">Đơn hàng của bạn</h1>

          <?php if ($cart_count===0): ?>
            <div class="alert alert-info">
              Giỏ hàng trống. <a class="alert-link" href="tim_kiem.php">Quay lại tìm chuyến</a>.
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <th>Tàu</th>
                    <th>Tuyến</th>
                    <th>Khởi hành</th>
                    <th>Đến</th>
                    <th class="text-end">Giá</th>
                  </tr>
                </thead>
                <tbody class="table-group-divider">
                  <?php foreach($items as $it): ?>
                    <tr>
                      <td class="fw-semibold"><?= h($it['ten_tau']) ?> — Ghế <?= (int)$it['so_ghe'] ?></td>
                      <td><?= h($it['ga_di']) ?> → <?= h($it['ga_den']) ?></td>
                      <td><?= h($it['ngay_di']) ?> <div class="small text-muted"><?= h($it['gio_di']) ?></div></td>
                      <td><?= h($it['ngay_den']) ?> <div class="small text-muted"><?= h($it['gio_den']) ?></div></td>
                      <td class="text-end"><?= number_format((int)$it['gia_ve'],0,',','.') ?> VNĐ</td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                  <tr>
                    <td colspan="4" class="text-end fw-bold">Tổng tiền</td>
                    <td class="text-end fw-bold"><?= number_format($total,0,',','.') ?> VNĐ</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h2 class="h6 mb-3">Phương thức thanh toán (demo)</h2>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="payment_method" id="pmOnline" value="online" checked>
              <label class="form-check-label" for="pmOnline">Thanh toán online (demo)</label>
              <div class="form-text">Giả lập cổng thanh toán: nhấn “Thanh toán” là coi như thành công.</div>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="radio" name="payment_method" id="pmOffline" value="offline">
              <label class="form-check-label" for="pmOffline">Thanh toán khi nhận vé (offline)</label>
            </div>

            <button class="btn btn-primary w-100" type="submit" name="confirm_checkout"
              <?= $cart_count===0 ? 'disabled' : '' ?>>
              Thanh toán
            </button>
            <a class="btn btn-outline-secondary w-100 mt-2" href="gio_hang.php">Quay lại giỏ hàng</a>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
