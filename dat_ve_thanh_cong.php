<?php
// dat_ve_thanh_cong.php — HIỂN THỊ: (1) giao dịch gần nhất + (2) toàn bộ vé đã đặt (PDO)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php'; // $pdo (PDO)

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function money_vi($n){ return number_format((int)$n, 0, ',', '.') . ' VNĐ'; }

$cart_count = (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) ? count($_SESSION['cart']) : 0;

/** 1) Giao dịch gần nhất của user */
$sqlPay = "
  SELECT id, phuong_thuc, tong_tien, ngay_giao_dich, ghi_chu
  FROM thanh_toan
  WHERE user_id = :uid
  ORDER BY ngay_giao_dich DESC, id DESC
  LIMIT 1
";
$stmtPay = $pdo->prepare($sqlPay);
$stmtPay->execute([':uid' => $user_id]);
$lastPayment = $stmtPay->fetch(PDO::FETCH_ASSOC);

/** 2) Nếu ghi_chu có 'seats', lấy vé thuộc giao dịch gần nhất (section A) */
$latestTickets = [];
if ($lastPayment && !empty($lastPayment['ghi_chu'])) {
  $meta = json_decode($lastPayment['ghi_chu'], true);
  if (json_last_error() === JSON_ERROR_NONE && !empty($meta['seats']) && is_array($meta['seats'])) {
    $seatIds = array_values(array_unique(array_map('intval', $meta['seats'])));
    if ($seatIds) {
      $ph = implode(',', array_fill(0, count($seatIds), '?'));
      $sql = "
        SELECT 
          ct.id AS ma_chuyen, ct.ten_tau, ct.ga_di, ct.ga_den,
          ct.ngay_di, ct.gio_di, ct.ngay_den, ct.gio_den,
          COALESCE(g.gia, ct.gia_ve) AS gia_ve,
          g.so_ghe, tt.ten_toa
        FROM ghe g
        JOIN chuyen_tau ct ON g.id_tau = ct.id
        LEFT JOIN toa_tau tt ON g.id_toa = tt.id
        WHERE g.id IN ($ph)
        ORDER BY ct.ngay_di, ct.gio_di, ct.id, g.so_ghe
      ";
      $st = $pdo->prepare($sql);
      $st->execute($seatIds);
      $latestTickets = $st->fetchAll(PDO::FETCH_ASSOC);
    }
  }
}

/** 3) Luôn lấy tất cả vé đã đặt của user (section B) */
$sqlAll = "
  SELECT 
    ct.id AS ma_chuyen, ct.ten_tau, ct.ga_di, ct.ga_den,
    ct.ngay_di, ct.gio_di, ct.ngay_den, ct.gio_den,
    COALESCE(g.gia, ct.gia_ve) AS gia_ve,
    g.so_ghe, tt.ten_toa
  FROM ghe g
  JOIN chuyen_tau ct ON g.id_tau = ct.id
  LEFT JOIN toa_tau  tt ON g.id_toa = tt.id
  WHERE g.user_id = :uid AND g.trang_thai = 'da_dat'
  ORDER BY ct.ngay_di, ct.gio_di, ct.id, g.so_ghe
";
$stAll = $pdo->prepare($sqlAll);
$stAll->execute([':uid' => $user_id]);
$allTickets = $stAll->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vé đã đặt</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#f6f8fb}
    .navbar-brand img{height:44px}
    .card{border-radius:16px}
    .table thead th{background:#0d6efd;color:#fff}
    .badge-toa{background:#eef4ff;color:#3451d1;border:1px solid #cfe0ff}
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark" style="background:#0d6efd;">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
      <img src="assets/LOGO_n.png" alt=""><span class="fw-bold">Vé tàu</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="index.php">TRANG CHỦ</a></li>
        <li class="nav-item"><a class="nav-link" href="tim_kiem.php">TÌM CHUYẾN TÀU</a></li>
        <li class="nav-item"><a class="nav-link" href="lich_trinh.php">LỊCH TRÌNH</a></li>
        <li class="nav-item"><a class="nav-link" href="lien_he.php">LIÊN HỆ</a></li>
        <li class="nav-item"><a class="nav-link" href="gio_hang.php">GIỎ HÀNG (<?= (int)$cart_count ?>)</a></li>
        <li class="nav-item"><a class="nav-link active" href="dat_ve_thanh_cong.php">VÉ ĐÃ ĐẶT</a></li>
      </ul>
      <div class="d-flex">
        <a class="btn btn-sm btn-light" href="logout.php">ĐĂNG XUẤT</a>
      </div>
    </div>
  </div>
</nav>

<div class="container my-4">

  <?php if ($lastPayment): ?>
    <?php
      // tóm tắt ghi chú
      $notePreview = '—';
      if (!empty($lastPayment['ghi_chu'])) {
        $meta = json_decode($lastPayment['ghi_chu'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($meta)) {
          $who = $meta['payer_name'] ?? '';
          $gw  = $meta['gateway']     ?? '';
          $txn = $meta['txn_code']    ?? '';
          $notePreview = trim(($who ? 'Người TT: '.$who : '') . ($gw ? ' • Cổng: '.$gw : '') . ($txn ? ' • Mã: '.$txn : ''), " •");
          if ($notePreview==='') $notePreview = '—';
        }
      }
    ?>
    <div class="card shadow-sm mb-4">
      <div class="card-body d-flex flex-wrap align-items-center gap-4">
        <div>
          <div class="text-muted small">Mã giao dịch</div>
          <div class="fw-semibold">#<?= (int)$lastPayment['id'] ?></div>
        </div>
        <div>
          <div class="text-muted small">Phương thức</div>
          <div class="fw-semibold text-capitalize"><?= h($lastPayment['phuong_thuc']) ?></div>
        </div>
        <div>
          <div class="text-muted small">Tổng tiền (ghi nhận)</div>
          <div class="fw-semibold"><?= money_vi($lastPayment['tong_tien']) ?></div>
        </div>
        <div>
          <div class="text-muted small">Thời gian</div>
          <div class="fw-semibold"><?= h($lastPayment['ngay_giao_dich']) ?></div>
        </div>
        <div class="flex-grow-1">
          <div class="text-muted small">Ghi chú</div>
          <div class="fw-semibold"><?= h($notePreview) ?></div>
        </div>
      </div>
      <?php if ($latestTickets): ?>
        <div class="card-footer small text-muted">
          Dưới đây là các vé thuộc <strong>giao dịch gần nhất</strong>.
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($latestTickets): ?>
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h2 class="h5 mb-3">Vé trong giao dịch gần nhất</h2>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Mã chuyến</th><th>Tên tàu</th><th>Tuyến</th>
                <th>Khởi hành</th><th>Đến nơi</th><th>Giá vé</th><th>Toa</th><th>Ghế</th>
              </tr>
            </thead>
            <tbody class="table-group-divider">
              <?php foreach($latestTickets as $row): ?>
                <tr>
                  <td class="fw-semibold"><?= (int)$row['ma_chuyen'] ?></td>
                  <td><?= h($row['ten_tau']) ?></td>
                  <td><?= h($row['ga_di']) ?> → <?= h($row['ga_den']) ?></td>
                  <td><?= h($row['ngay_di']) ?><div class="small text-muted"><?= h($row['gio_di']) ?></div></td>
                  <td><?= h($row['ngay_den']) ?><div class="small text-muted"><?= h($row['gio_den']) ?></div></td>
                  <td class="fw-semibold"><?= money_vi($row['gia_ve']) ?></td>
                  <td><?= $row['ten_toa'] ? '<span class="badge badge-toa rounded-pill">'.h($row['ten_toa']).'</span>' : '<span class="text-muted">—</span>' ?></td>
                  <td>Ghế <?= h($row['so_ghe']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <h2 class="h5 mb-3">Tất cả vé đã đặt của bạn</h2>
      <?php if (!$allTickets): ?>
        <div class="alert alert-info mb-0">Chưa có vé nào được ghi nhận cho tài khoản này. <a class="alert-link" href="tim_kiem.php">Đặt vé ngay</a>.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Mã chuyến</th><th>Tên tàu</th><th>Tuyến</th>
                <th>Khởi hành</th><th>Đến nơi</th><th>Giá vé</th><th>Toa</th><th>Ghế</th>
              </tr>
            </thead>
            <tbody class="table-group-divider">
              <?php foreach($allTickets as $row): ?>
                <tr>
                  <td class="fw-semibold"><?= (int)$row['ma_chuyen'] ?></td>
                  <td><?= h($row['ten_tau']) ?></td>
                  <td><?= h($row['ga_di']) ?> → <?= h($row['ga_den']) ?></td>
                  <td><?= h($row['ngay_di']) ?><div class="small text-muted"><?= h($row['gio_di']) ?></div></td>
                  <td><?= h($row['ngay_den']) ?><div class="small text-muted"><?= h($row['gio_den']) ?></div></td>
                  <td class="fw-semibold"><?= money_vi($row['gia_ve']) ?></td>
                  <td><?= $row['ten_toa'] ? '<span class="badge badge-toa rounded-pill">'.h($row['ten_toa']).'</span>' : '<span class="text-muted">—</span>' ?></td>
                  <td>Ghế <?= h($row['so_ghe']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="mt-3 d-flex gap-2">
    <a class="btn btn-outline-primary" href="tim_kiem.php">Đặt thêm chuyến</a>
    <a class="btn btn-outline-secondary" href="gio_hang.php">Về giỏ hàng (<?= (int)$cart_count ?>)</a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
