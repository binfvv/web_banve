<?php
// gio_hang.php (PDO + Transaction + UI đẹp)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php'; // phải tạo $pdo (PDO)

// ===== Helpers =====
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function money_vi($n){ return number_format((int)$n,0,',','.') . ' VNĐ'; }

$cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? array_values(array_unique($_SESSION['cart'])) : [];
$cart_count = count($cart);

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf'];

// Bắt buộc đăng nhập
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
$user_id = (int)$_SESSION['user_id'];

// ====== Xử lý remove seat (POST) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_seat'])) {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) {
    http_response_code(400); exit('Yêu cầu không hợp lệ (CSRF).');
  }
  $seat_id = (int)$_POST['remove_seat'];
  $idx = array_search($seat_id, $cart, true);
  if ($idx !== false) {
    array_splice($cart, $idx, 1);
    $_SESSION['cart'] = $cart;
  }
  header('Location: gio_hang.php');
  exit;
}

// ====== Lấy thông tin các ghế trong giỏ (gộp query) ======
$seats = [];
$tong_gia = 0;

if ($cart_count > 0) {
  // tạo placeholders cho IN (...)
  $ph = implode(',', array_fill(0, $cart_count, '?'));
  $sql = "
    SELECT 
      ghe.id            AS ghe_id,
      ghe.so_ghe,
      ghe.trang_thai,
      chuyen_tau.id     AS tau_id,
      chuyen_tau.ten_tau,
      chuyen_tau.ga_di,
      chuyen_tau.ga_den,
      chuyen_tau.ngay_di,
      chuyen_tau.gio_di,
      chuyen_tau.ngay_den,
      chuyen_tau.gio_den,
      chuyen_tau.gia_ve
    FROM ghe
    JOIN chuyen_tau ON ghe.id_tau = chuyen_tau.id
    WHERE ghe.id IN ($ph)
    ORDER BY chuyen_tau.ngay_di, chuyen_tau.gio_di, ghe.so_ghe
  ";
  $stm = $pdo->prepare($sql);
  $stm->execute($cart);
  $seats = $stm->fetchAll(PDO::FETCH_ASSOC);
  foreach ($seats as $s) $tong_gia += (int)$s['gia_ve'];
}

// ====== Xác nhận đặt vé (POST) ======
$msg_success = $msg_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) {
    http_response_code(400); exit('Yêu cầu không hợp lệ (CSRF).');
  }
  $payment_method = $_POST['payment_method'] ?? '';
  if (!in_array($payment_method, ['online','offline'], true)) {
    $msg_error = 'Vui lòng chọn phương thức thanh toán hợp lệ.';
  } elseif ($cart_count === 0) {
    $msg_error = 'Giỏ hàng trống, không thể đặt vé.';
  } else {
    try {
      $pdo->beginTransaction();

      // Lấy lại danh sách ghế để kiểm tra trạng thái (FOR UPDATE tránh tranh chấp)
      $ph = implode(',', array_fill(0, $cart_count, '?'));
      $sqlLock = "SELECT id, trang_thai FROM ghe WHERE id IN ($ph) FOR UPDATE";
      $stm = $pdo->prepare($sqlLock);
      $stm->execute($cart);
      $current = $stm->fetchAll(PDO::FETCH_KEY_PAIR); // [id => trang_thai]

      // Nếu có ghế không còn 'trong' → hủy
      $conflicts = [];
      foreach ($cart as $sid) {
        if (!isset($current[$sid]) || $current[$sid] !== 'trong') $conflicts[] = $sid;
      }
      if ($conflicts) {
        $pdo->rollBack();
        $msg_error = 'Một số ghế đã được đặt trước bởi người khác. Vui lòng cập nhật giỏ hàng.';
      } else {
        // Tính tổng tiền (đảm bảo từ DB)
        $ph2 = implode(',', array_fill(0, $cart_count, '?'));
        $sqlSum = "
          SELECT SUM(chuyen_tau.gia_ve) 
          FROM ghe JOIN chuyen_tau ON ghe.id_tau = chuyen_tau.id
          WHERE ghe.id IN ($ph2)
        ";
        $stm2 = $pdo->prepare($sqlSum);
        $stm2->execute($cart);
        $tong_gia_db = (int)$stm2->fetchColumn();

        if ($tong_gia_db <= 0) {
          $pdo->rollBack();
          $msg_error = 'Tổng tiền không hợp lệ.';
        } else {
          // Cập nhật ghế -> da_dat + gắn user
          $sqlUpd = "UPDATE ghe SET trang_thai='da_dat', user_id=? WHERE id IN ($ph2)";
          $stm3 = $pdo->prepare($sqlUpd);
          $stm3->execute(array_merge([$user_id], $cart));

          // Tạo bản ghi thanh toán
          $sqlPay = "INSERT INTO thanh_toan(user_id, phuong_thuc, tong_tien, ngay_giao_dich) VALUES (?,?,?,NOW())";
          $stm4 = $pdo->prepare($sqlPay);
          $stm4->execute([$user_id, $payment_method, $tong_gia_db]);

          $pdo->commit();
          $_SESSION['cart'] = [];
          header('Location: dat_ve_thanh_cong.php');
          exit;
        }
      }
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $msg_error = 'Có lỗi xảy ra khi đặt vé. Vui lòng thử lại.';
      // error_log($e->getMessage());
    }
  }
}

// ===== Menu động (fallback nếu chưa có)
$header_menus = [
  ['label'=>'TRANG CHỦ','url'=>'index.php'],
  ['label'=>'TÌM CHUYẾN TÀU','url'=>'tim_kiem.php'],
  ['label'=>'LỊCH TRÌNH','url'=>'lich_trinh.php'],
  ['label'=>'LIÊN HỆ','url'=>'lien_he.php'],
];
try {
  $m = $pdo->query("SELECT location,label,url FROM menus WHERE visible=1 ORDER BY location,position,id")->fetchAll(PDO::FETCH_ASSOC);
  if ($m){ $header_menus=[]; foreach($m as $x) if($x['location']!=='footer') $header_menus[]=['label'=>$x['label'],'url'=>$x['url']]; }
} catch(Throwable $e){}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Giỏ hàng</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    :root { --r:16px; }
    body { background:#f6f8fb; }
    .navbar-brand img{ height:44px; }
    .card{ border-radius:var(--r); }
    .table thead th { background:#0085c4; color:#fff; }
    .pill{ border-radius:999px; }
  </style>
</head>
<body>
  <!-- NAV -->
  <nav class="navbar navbar-expand-lg navbar-dark" style="background:#0d6efd;">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
        <img src="assets/LOGO_n.png" alt="Đường sắt Việt Nam"><span class="fw-bold">Vé tàu</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMain">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <?php foreach($header_menus as $m): ?>
            <li class="nav-item"><a class="nav-link<?= (basename($m['url'])==='gio_hang.php'?' active':'') ?>" href="<?= h($m['url']) ?>"><?= h($m['label']) ?></a></li>
          <?php endforeach; ?>
          <li class="nav-item"><a class="nav-link" href="gio_hang.php">GIỎ HÀNG (<?= $cart_count ?>)</a></li>
          <li class="nav-item"><a class="nav-link" href="dat_ve_thanh_cong.php">VÉ ĐÃ ĐẶT</a></li>
        </ul>
        <div class="d-flex">
          <a class="btn btn-sm btn-light" href="logout.php">ĐĂNG XUẤT</a>
        </div>
      </div>
    </div>
  </nav>

  <div class="container my-4">
    <h1 class="h4 mb-3">Giỏ hàng của bạn</h1>

    <?php if ($msg_success): ?>
      <div class="alert alert-success"><?= h($msg_success) ?></div>
    <?php endif; ?>
    <?php if ($msg_error): ?>
      <div class="alert alert-danger"><?= h($msg_error) ?></div>
    <?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-8">
        <div class="card shadow-sm">
          <div class="card-body p-0">
            <?php if ($cart_count === 0): ?>
              <div class="p-4 text-muted">Giỏ hàng của bạn trống. <a href="tim_kiem.php">Quay lại để chọn vé</a>.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                  <thead>
                    <tr>
                      <th style="width:60px">#</th>
                      <th>Thông tin ghế / chuyến</th>
                      <th style="width:150px">Giá vé</th>
                      <th style="width:90px">Xóa</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php $stt=1; foreach($seats as $seat): ?>
                      <tr>
                        <td><?= $stt++ ?></td>
                        <td>
                          <div><strong>Ghế số:</strong> <?= h($seat['so_ghe']) ?> <?= $seat['trang_thai']!=='trong' ? '<span class="badge text-bg-warning ms-1">đã giữ</span>' : '' ?></div>
                          <div><strong>Tàu:</strong> <?= h($seat['ten_tau']) ?></div>
                          <div><strong>Tuyến:</strong> <?= h($seat['ga_di']) ?> → <?= h($seat['ga_den']) ?></div>
                          <div class="text-muted small">
                            <i class="ti ti-clock"></i>
                            <?= h($seat['ngay_di'].' '.$seat['gio_di']) ?> — <?= h($seat['ngay_den'].' '.$seat['gio_den']) ?>
                          </div>
                        </td>
                        <td><strong><?= money_vi($seat['gia_ve']) ?></strong></td>
                        <td>
                          <form method="post" class="m-0">
                            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                            <button class="btn btn-sm btn-outline-danger pill" type="submit" name="remove_seat" value="<?= (int)$seat['ghe_id'] ?>">
                              <i class="ti ti-x"></i> Xóa
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="2" class="text-end fw-semibold">Tổng tiền</td>
                      <td colspan="2" class="fw-bold"><?= money_vi($tong_gia) ?></td>
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
            <h5 class="mb-3">Phương thức thanh toán</h5>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
              <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="payment_method" id="pm1" value="online" required>
                <label class="form-check-label" for="pm1">Thanh toán online</label>
              </div>
              <div class="form-check mb-3">
                <input class="form-check-input" type="radio" name="payment_method" id="pm2" value="offline" required>
                <label class="form-check-label" for="pm2">Thanh toán khi nhận vé (offline)</label>
              </div>
              <button class="btn btn-primary w-100" type="submit" name="confirm_booking" <?= $cart_count===0?'disabled':'' ?>>
                <i class="ti ti-check"></i> Xác nhận đặt vé
              </button>
            </form>
            <div class="text-muted small mt-3">
              Khi xác nhận, hệ thống sẽ giữ ghế ngay lập tức. Nếu có ghế đã được người khác giữ trước, bạn sẽ nhận được thông báo để cập nhật giỏ hàng.
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="my-4">
      <a class="btn btn-outline-secondary" href="tim_kiem.php"><i class="ti ti-arrow-left"></i> Tiếp tục chọn vé</a>
    </div>
  </div>

  <footer class="py-4">
    <div class="container">
      <div class="text-muted small">© <?= date('Y') ?> Đoàn Đức Bình IT4.K23</div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
