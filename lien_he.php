<?php
// lien_he.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php'; // $pdo (PDO) khuyên dùng; hoặc $conn (mysqli)

// ===== Helpers =====
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$cart_count = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
$use_pdo    = (isset($pdo)  && $pdo  instanceof PDO);
$use_mysqli = (isset($conn) && $conn instanceof mysqli);

// ===== CSRF
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$CSRF = $_SESSION['csrf'];

// ===== Menu động (fallback nếu chưa có)
$header_menus = [
  ['label'=>'TRANG CHỦ','url'=>'index.php'],
  ['label'=>'TÌM CHUYẾN TÀU','url'=>'tim_kiem.php'],
  ['label'=>'LỊCH TRÌNH','url'=>'lich_trinh.php'],
  ['label'=>'LIÊN HỆ','url'=>'lien_he.php'],
];
try {
  if ($use_pdo) {
    $m = $pdo->query("SELECT location,label,url FROM menus WHERE visible=1 ORDER BY location,position,id")->fetchAll(PDO::FETCH_ASSOC);
    if ($m){ $header_menus=[]; foreach($m as $x) if($x['location']!=='footer') $header_menus[]=['label'=>$x['label'],'url'=>$x['url']]; }
  } elseif ($use_mysqli) {
    if ($res=$conn->query("SELECT location,label,url FROM menus WHERE visible=1 ORDER BY location,position,id")){
      $m=$res->fetch_all(MYSQLI_ASSOC); if($m){ $header_menus=[]; foreach($m as $x) if($x['location']!=='footer') $header_menus[]=['label'=>$x['label'],'url'=>$x['url']]; }
    }
  }
} catch(Throwable $e){}

// ===== Thông tin công ty (lấy từ settings nếu có)
$company = [
  'name'   => 'Tổng công ty Đường sắt Việt Nam',
  'addr'   => 'Số 118 Lê Duẩn, Hoàn Kiếm, Hà Nội',
  'hotline_north' => '1900 0109',
  'hotline_south' => '1900 1520',
  'pay_support'   => '1900 6469',
  'email'  => 'support1@dsvn.vn',
];
try {
  if ($use_pdo) {
    $rows = $pdo->query("SELECT `key`,`value` FROM settings WHERE `key` IN ('company_name','company_addr','hotline_north','hotline_south','hotline_pay','support_email')")->fetchAll(PDO::FETCH_KEY_PAIR);
  } elseif ($use_mysqli) {
    $rows = [];
    if ($r = $conn->query("SELECT `key`,`value` FROM settings WHERE `key` IN ('company_name','company_addr','hotline_north','hotline_south','hotline_pay','support_email')")) {
      foreach ($r->fetch_all(MYSQLI_ASSOC) as $it) $rows[$it['key']] = $it['value'];
    }
  }
  if (!empty($rows)) {
    $company['name'] = $rows['company_name'] ?? $company['name'];
    $company['addr'] = $rows['company_addr'] ?? $company['addr'];
    $company['hotline_north'] = $rows['hotline_north'] ?? $company['hotline_north'];
    $company['hotline_south'] = $rows['hotline_south'] ?? $company['hotline_south'];
    $company['pay_support']   = $rows['hotline_pay']   ?? $company['pay_support'];
    $company['email'] = $rows['support_email'] ?? $company['email'];
  }
} catch (Throwable $e) {}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Liên hệ</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    :root { --card-r:16px; }
    body { background:#f8f9fb; }
    .navbar-brand img { height:44px; }
    .card { border-radius: var(--card-r); }
    .hero { background: linear-gradient(135deg,#0d6efd,#3b8aff); color:#fff; border-radius: var(--card-r); }
  </style>
</head>
<body>
  <!-- NAV -->
  <nav class="navbar navbar-expand-lg navbar-dark" style="background:#0d6efd;">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
        <img src="assets/LOGO_n.png" alt="Đường sắt Việt Nam">
        <span class="fw-bold">Vé tàu</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMain">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <?php foreach($header_menus as $m): ?>
            <li class="nav-item"><a class="nav-link<?= (basename($m['url'])==='lien_he.php'?' active':'') ?>" href="<?= h($m['url']) ?>"><?= h($m['label']) ?></a></li>
          <?php endforeach; ?>
          <li class="nav-item"><a class="nav-link" href="gio_hang.php">GIỎ HÀNG (<?= $cart_count ?>)</a></li>
          <li class="nav-item"><a class="nav-link" href="dat_ve_thanh_cong.php">VÉ ĐÃ ĐẶT</a></li>
        </ul>
        <div class="d-flex">
          <?php if(isset($_SESSION['user_id'])): ?>
            <a class="btn btn-sm btn-light" href="logout.php">ĐĂNG XUẤT</a>
          <?php else: ?>
            <a class="btn btn-sm btn-outline-light" href="login.php">ĐĂNG NHẬP</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <!-- HERO -->
  <div class="container my-4">
    <div class="hero p-4 p-md-5 shadow-sm">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h1 class="h3 h1-md fw-bold mb-2">Liên hệ hỗ trợ</h1>
          <p class="mb-0">Chúng tôi sẵn sàng trợ giúp về lịch trình, đặt vé, thanh toán, hoàn tiền và góp ý dịch vụ.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
          <div class="bg-white text-dark rounded-4 p-3 shadow-sm d-inline-block">
            <div class="small text-muted">Email hỗ trợ</div>
            <div class="fw-semibold"><?= h($company['email']) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- CONTENT -->
  <div class="container mb-5">
    <div class="row g-4">
      <!-- Info -->
      <div class="col-lg-5">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <h5 class="mb-3">Thông tin liên hệ</h5>
            <p class="mb-1 fw-semibold"><?= h($company['name']) ?></p>
            <p class="text-muted"><?= h($company['addr']) ?></p>
            <hr>
            <p class="mb-1 fw-semibold">Tổng đài hỗ trợ & CSKH</p>
            <ul class="list-unstyled">
              <li>Khu vực miền Bắc: <span class="fw-semibold"><?= h($company['hotline_north']) ?></span></li>
              <li>Khu vực miền Nam: <span class="fw-semibold"><?= h($company['hotline_south']) ?></span></li>
            </ul>
            <p class="mb-1 fw-semibold">Hỗ trợ thanh toán & hoàn tiền online</p>
            <ul class="list-unstyled">
              <li>Điện thoại: <span class="fw-semibold"><?= h($company['pay_support']) ?></span></li>
              <li>Email: <span class="fw-semibold"><?= h($company['email']) ?></span></li>
            </ul>
            <hr>
            <div class="text-muted small">Thời gian hỗ trợ: 08:00–21:00 (T2–CN, trừ ngày Lễ).</div>
          </div>
        </div>
      </div>

      <!-- Form -->
      <div class="col-lg-7">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="mb-3">Gửi yêu cầu hỗ trợ</h5>
            <?php if (!empty($_GET['sent']) && $_GET['sent']==='1'): ?>
              <div class="alert alert-success">Đã gửi yêu cầu. Chúng tôi sẽ phản hồi sớm nhất có thể.</div>
            <?php elseif (!empty($_GET['sent']) && $_GET['sent']==='0'): ?>
              <div class="alert alert-danger">Gửi yêu cầu thất bại. Vui lòng thử lại.</div>
            <?php endif; ?>

            <form action="lien_he_submit.php" method="post" class="row g-3" onsubmit="return validateForm()">
              <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
              <!-- honeypot -->
              <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off">
              <div class="col-md-6">
                <label class="form-label">Họ và tên</label>
                <input type="text" name="name" class="form-control" required maxlength="120">
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required maxlength="160">
              </div>
              <div class="col-md-6">
                <label class="form-label">Số điện thoại</label>
                <input type="tel" name="phone" class="form-control" pattern="[0-9+ ]{8,15}" placeholder="VD: 0901234567">
              </div>
              <div class="col-md-6">
                <label class="form-label">Chủ đề</label>
                <select name="topic" class="form-select" required>
                  <option value="" disabled selected>Chọn chủ đề</option>
                  <option value="Lịch trình">Lịch trình</option>
                  <option value="Đặt vé">Đặt vé</option>
                  <option value="Thanh toán/Hoàn tiền">Thanh toán/Hoàn tiền</option>
                  <option value="Góp ý dịch vụ">Góp ý dịch vụ</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Nội dung</label>
                <textarea name="message" class="form-control" rows="5" required minlength="10" maxlength="3000"></textarea>
              </div>
              <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary"><i class="ti ti-send"></i> Gửi</button>
                <button type="reset" class="btn btn-outline-secondary"><i class="ti ti-eraser"></i> Xóa</button>
              </div>
            </form>
          </div>
        </div>
      </div>

    </div>
  </div>

  <footer class="py-4">
    <div class="container">
      <div class="text-muted small">© <?= date('Y') ?> Đoàn Đức Bình IT4.K23</div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function validateForm(){
      const msg = document.querySelector('textarea[name="message"]').value.trim();
      if (msg.length < 10) { alert('Vui lòng mô tả chi tiết hơn (>= 10 ký tự)'); return false; }
      return true;
    }
  </script>
</body>
</html>
