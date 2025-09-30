<?php
// gio_hang.php — Chỉ thanh toán ONLINE + Thu thập thông tin thanh toán (PDO + CSRF + Transaction)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php'; // phải có $pdo (PDO)

// ===== Helpers =====
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function money_vi($n){ return number_format((int)$n,0,',','.') . ' VNĐ'; }

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf'];

// Bắt buộc đăng nhập
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];

// Cart
$_SESSION['cart'] = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? array_values(array_unique($_SESSION['cart'])) : [];
$cart = $_SESSION['cart'];
$cart_count = count($cart);

// ====== Remove seat ======
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['remove_seat'])) {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) { http_response_code(400); exit('CSRF không hợp lệ.'); }
  $sid = (int)$_POST['remove_seat'];
  $pos = array_search($sid, $cart, true);
  if ($pos !== false) {
    array_splice($cart, $pos, 1);
    $_SESSION['cart'] = $cart;
  }
  header('Location: gio_hang.php'); exit;
}

// ====== Lấy thông tin ghế trong giỏ ======
$items = []; $tong_tien = 0;
if ($cart_count > 0) {
  $ph = implode(',', array_fill(0, $cart_count, '?'));
  $sql = "
    SELECT 
      g.id AS ghe_id, g.so_ghe, g.trang_thai,
      ct.id AS ma_chuyen, ct.ten_tau, ct.ga_di, ct.ga_den,
      ct.ngay_di, ct.gio_di, ct.ngay_den, ct.gio_den,
      COALESCE(g.gia, ct.gia_ve) AS gia_ve
    FROM ghe g
    JOIN chuyen_tau ct ON ct.id = g.id_tau
    WHERE g.id IN ($ph)
    ORDER BY ct.ngay_di ASC, ct.gio_di ASC, ct.id ASC, g.so_ghe ASC
  ";
  $stm = $pdo->prepare($sql); $stm->execute($cart);
  $items = $stm->fetchAll(PDO::FETCH_ASSOC);
  foreach ($items as $r) $tong_tien += (int)$r['gia_ve'];
}

// ====== Thanh toán ONLINE ======
$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['confirm_online'])) {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) { http_response_code(400); exit('CSRF không hợp lệ.'); }
  if ($cart_count === 0) $errors[] = 'Giỏ hàng trống.';

  // Lấy thông tin form
  $payer_name  = trim($_POST['payer_name'] ?? '');
  $payer_email = trim($_POST['payer_email'] ?? '');
  $payer_phone = trim($_POST['payer_phone'] ?? '');
  $gateway     = trim($_POST['gateway'] ?? 'vnpay');    // vnpay | momo | bank
  $txn_code    = trim($_POST['txn_code'] ?? '');        // mã giao dịch / 6 số cuối thẻ
  $note        = trim($_POST['note'] ?? '');

  // Validate
  if ($payer_name === '') $errors[] = 'Vui lòng nhập họ tên người thanh toán.';
  if (!filter_var($payer_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';
  if ($payer_phone === '' || !preg_match('/^[0-9+\-\s]{8,16}$/', $payer_phone)) $errors[] = 'Số điện thoại không hợp lệ.';
  if (!in_array($gateway, ['vnpay','momo','bank'], true)) $errors[] = 'Cổng thanh toán không hợp lệ.';
  if ($txn_code === '') $errors[] = 'Vui lòng nhập mã giao dịch / 6 số cuối thẻ.';

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // Lock ghế để tránh double-book: chỉ nhận ghế còn 'trong'
      $ph = implode(',', array_fill(0, $cart_count, '?'));
      $stmLock = $pdo->prepare("SELECT id, trang_thai FROM ghe WHERE id IN ($ph) FOR UPDATE");
      $stmLock->execute($cart);
      $nowStates = $stmLock->fetchAll(PDO::FETCH_KEY_PAIR);
      $conflicts = [];
      foreach ($cart as $sid) { if (!isset($nowStates[$sid]) || $nowStates[$sid] !== 'trong') $conflicts[] = $sid; }
      if ($conflicts) {
        $pdo->rollBack();
        $errors[] = 'Một số ghế đã được người khác đặt. Vui lòng cập nhật giỏ hàng.';
      } else {
        // Tính tổng tiền lại từ DB
        $stmSum = $pdo->prepare("
          SELECT SUM(COALESCE(g.gia, ct.gia_ve))
          FROM ghe g JOIN chuyen_tau ct ON ct.id = g.id_tau
          WHERE g.id IN ($ph)
        ");
        $stmSum->execute($cart);
        $tong_db = (int)$stmSum->fetchColumn();
        if ($tong_db <= 0) {
          $pdo->rollBack();
          $errors[] = 'Tổng tiền không hợp lệ.';
        } else {
          // Cập nhật ghế
          $stmUpd = $pdo->prepare("UPDATE ghe SET trang_thai='da_dat', user_id=:uid WHERE id=:sid AND trang_thai='trong'");
          foreach ($cart as $sid) {
            $stmUpd->execute([':uid'=>$user_id, ':sid'=>(int)$sid]);
          }

          // Lưu thanh_toan
          $ghi_chu = json_encode([
            'payer_name'  => $payer_name,
            'payer_email' => $payer_email,
            'payer_phone' => $payer_phone,
            'gateway'     => $gateway,
            'txn_code'    => $txn_code,
            'note'        => $note,
            'seats'       => $cart,
          ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

          $stmPay = $pdo->prepare("
            INSERT INTO thanh_toan (user_id, phuong_thuc, tong_tien, ngay_giao_dich, ghi_chu)
            VALUES (:uid, 'online', :total, NOW(), :note)
          ");
          $stmPay->execute([':uid'=>$user_id, ':total'=>$tong_db, ':note'=>$ghi_chu]);

          $pdo->commit();
          // Clear giỏ + sang trang vé thành công
          $_SESSION['cart'] = [];
          header('Location: dat_ve_thanh_cong.php'); exit;
        }
      }
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = 'Có lỗi khi xử lý thanh toán: ' . $e->getMessage();
    }
  }
}

// ===== Menu động (fallback) =====
$header_menus = [
  ['label'=>'TRANG CHỦ','url'=>'index.php'],
  ['label'=>'TÌM CHUYẾN TÀU','url'=>'tim_kiem.php'],
  ['label'=>'LỊCH TRÌNH','url'=>'lich_trinh.php'],
  ['label'=>'LIÊN HỆ','url'=>'lien_he.php'],
];
try {
  $rows = $pdo->query("SELECT location,label,url FROM menus WHERE visible=1 ORDER BY location,position,id")->fetchAll(PDO::FETCH_ASSOC);
  if ($rows) { $header_menus=[]; foreach($rows as $x){ if($x['location']!=='footer') $header_menus[]=['label'=>$x['label'],'url'=>$x['url']]; } }
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
  <style>
    :root { --r:16px; }
    body{background:#f6f8fb}
    .navbar-brand img{height:44px}
    .card{border-radius:var(--r)}
    .table thead th{background:#0d6efd;color:#fff}
    .pill{border-radius:999px}
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
        <?php foreach($header_menus as $m): ?>
          <li class="nav-item"><a class="nav-link" href="<?= h($m['url']) ?>"><?= h($m['label']) ?></a></li>
        <?php endforeach; ?>
        <li class="nav-item"><a class="nav-link active" href="gio_hang.php">GIỎ HÀNG (<?= $cart_count ?>)</a></li>
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

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <div class="fw-semibold">Không thể xử lý:</div>
      <ul class="mb-0"><?php foreach($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Bảng ghế -->
    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-body p-0">
          <?php if (!$items): ?>
            <div class="p-4 text-muted">Giỏ hàng trống. <a href="tim_kiem.php">Chọn vé ngay</a>.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead>
                  <tr>
                    <th style="width:56px">#</th>
                    <th>Thông tin</th>
                    <th style="width:150px">Giá vé</th>
                    <th style="width:90px">Xoá</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $i=1; foreach($items as $r): ?>
                    <tr>
                      <td><?= $i++ ?></td>
                      <td>
                        <div class="fw-semibold">Ghế số <?= h($r['so_ghe']) ?><?= $r['trang_thai']!=='trong'?' <span class="badge bg-warning text-dark ms-1">đã giữ</span>':'' ?></div>
                        <div class="small text-muted">
                          Tàu: <?= h($r['ten_tau']) ?> | Tuyến: <?= h($r['ga_di']) ?> → <?= h($r['ga_den']) ?><br>
                          Thời gian: <?= h($r['ngay_di'].' '.$r['gio_di']) ?> — <?= h($r['ngay_den'].' '.$r['gio_den']) ?>
                        </div>
                      </td>
                      <td class="fw-semibold"><?= money_vi($r['gia_ve']) ?></td>
                      <td>
                        <form method="post" class="m-0" onsubmit="return confirm('Xoá ghế này khỏi giỏ?')">
                          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                          <button class="btn btn-sm btn-outline-danger pill" name="remove_seat" value="<?= (int)$r['ghe_id'] ?>"><i class="ti ti-x"></i> Xoá</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="2" class="text-end fw-semibold">Tổng tiền</td>
                    <td colspan="2" class="fw-bold"><?= money_vi($tong_tien) ?></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Form thanh toán ONLINE -->
    <div class="col-lg-5">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="mb-3">Thanh toán online</h5>
          <?php if (!$items): ?>
            <div class="alert alert-secondary mb-0">Bạn cần chọn vé trước khi thanh toán.</div>
          <?php else: ?>
            <form method="post" id="payForm" novalidate>
              <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
              <div class="mb-2">
                <label class="form-label">Họ tên người thanh toán <span class="text-danger">*</span></label>
                <input class="form-control" name="payer_name" required>
              </div>
              <div class="mb-2">
                <label class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" name="payer_email" required>
              </div>
              <div class="mb-2">
                <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                <input class="form-control" name="payer_phone" placeholder="VD: 09xxxxxxxx" required>
              </div>
              <div class="mb-2">
                <label class="form-label">Cổng thanh toán <span class="text-danger">*</span></label>
                <select class="form-select" name="gateway" required>
                  <option value="vnpay">VNPay</option>
                  <option value="momo">MoMo</option>
                  <option value="bank">Thẻ/Chuyển khoản ngân hàng</option>
                </select>
                <div class="form-text">Demo: Không gọi cổng thật, chỉ lưu thông tin.</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Mã giao dịch / 6 số cuối thẻ <span class="text-danger">*</span></label>
                <input class="form-control" name="txn_code" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Ghi chú</label>
                <textarea class="form-control" name="note" rows="2" placeholder="Ghi chú thêm (nếu có)"></textarea>
              </div>
              <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="small text-muted">Số ghế: <span class="badge bg-secondary"><?= $cart_count ?></span></div>
                <div class="fw-bold"><?= money_vi($tong_tien) ?></div>
              </div>
              <button class="btn btn-primary w-100" name="confirm_online" value="1" type="submit">
                Xác nhận thanh toán online
              </button>
            </form>
          <?php endif; ?>
          <div class="small text-muted mt-3">
            Sau khi xác nhận, hệ thống sẽ giữ ghế và tạo giao dịch trong bảng <code>thanh_toan</code> (cột <code>ghi_chu</code> lưu JSON thông tin thanh toán).
          </div>
        </div>
      </div>
      <div class="mt-2 small text-muted">
        * Tích hợp cổng thật (VNPay/MoMo) có thể gắn ở nút trên: chuyển hướng tới gateway, nhận callback → xác thực → chèn thanh toán → đánh dấu ghế.
      </div>
    </div>
  </div>

  <div class="my-4">
    <a class="btn btn-outline-secondary" href="tim_kiem.php"><i class="ti ti-arrow-left"></i> Tiếp tục chọn vé</a>
  </div>
</div>

<footer class="py-4">
  <div class="container"><div class="text-muted small">© <?= date('Y') ?> Đoàn Đức Bình IT4.K23</div></div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Validate client-side đơn giản
document.getElementById('payForm')?.addEventListener('submit', function(e){
  const form = this, req = form.querySelectorAll('[required]');
  let ok = true;
  req.forEach(el => { if(!el.value.trim()){ el.classList.add('is-invalid'); ok=false; } else el.classList.remove('is-invalid'); });
  if(!ok){ e.preventDefault(); alert('Vui lòng điền đầy đủ các trường bắt buộc.'); }
});
</script>
</body>
</html>
