<?php
// ket_qua_tim_kiem.php (Card UI)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php'; // must provide $pdo (PDO)

// Helpers
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function money_vi($n){ return number_format((int)$n, 0, ',', '.') . ' VNĐ'; }
function dt($date,$time){ return trim($date.' '.$time); }
function duration_str($start,$end){
  $a = strtotime($start); $b = strtotime($end);
  if(!$a || !$b || $b<$a) return '';
  $mins = (int)(($b-$a)/60);
  $h = intdiv($mins,60); $m = $mins%60;
  return ($h>0? $h.'h' : '').($m>0? ' '.$m.'m' : '');
}

$cart_count = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

// Input
$ga_di   = trim($_POST['ga_di']   ?? '');
$ga_den  = trim($_POST['ga_den']  ?? '');
$ngay_di = trim($_POST['ngay_di'] ?? '');
$ngay_ve = trim($_POST['ngay_ve'] ?? '');
$loai_ve = $_POST['ticket_type'] ?? 'Một chiều';

// CSRF (optional)
if (isset($_POST['csrf']) && (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']))) {
  http_response_code(400); exit('CSRF token không hợp lệ.');
}

// Validate
$errors=[];
if ($ga_di===''||$ga_den===''||$ngay_di==='') $errors[]='Vui lòng chọn đủ Ga đi, Ga đến và Ngày đi.';
if ($ga_di!=='' && $ga_den!=='' && $ga_di===$ga_den) $errors[]='Ga đi và Ga đến phải khác nhau.';
if ($loai_ve==='Khứ hồi' && $ngay_ve!=='' && strtotime($ngay_ve)<strtotime($ngay_di)) $errors[]='Ngày về không được trước Ngày đi.';

// Menu (fallback)
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

// Query
$di_rows = $ve_rows = [];
if (!$errors){
  $sql = "SELECT id,ten_tau,ga_di,ga_den,ngay_di,gio_di,ngay_den,gio_den,gia_ve
          FROM chuyen_tau WHERE ga_di=? AND ga_den=? AND ngay_di=? ORDER BY gio_di ASC, id ASC";
  $st = $pdo->prepare($sql);
  $st->execute([$ga_di,$ga_den,$ngay_di]);
  $di_rows = $st->fetchAll(PDO::FETCH_ASSOC);

  if ($loai_ve==='Khứ hồi' && $ngay_ve!==''){
    $st2 = $pdo->prepare($sql);
    $st2->execute([$ga_den,$ga_di,$ngay_ve]);
    $ve_rows = $st2->fetchAll(PDO::FETCH_ASSOC);
  }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kết quả tìm kiếm</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    :root { --r:16px; }
    body { background:#f6f8fb; }
    .navbar-brand img{ height:44px; }
    .chip { display:inline-flex; gap:.5rem; align-items:center; background:#fff; border:1px solid #e9ecef; padding:.4rem .75rem; border-radius:999px; }
    .card { border-radius:var(--r); }
    .trip-card .line { height:2px; background:#0d6efd; opacity:.4; }
    .price { font-size:1.1rem; font-weight:700; }
    .muted { color:#6c757d; }
  </style>
  <script>
    function bookTicket(id){ window.location.href = "dat_ve.php?id=" + encodeURIComponent(id); }
  </script>
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
            <li class="nav-item"><a class="nav-link" href="<?= h($m['url']) ?>"><?= h($m['label']) ?></a></li>
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

  <div class="container my-4">
    <h1 class="h3 mb-3">Kết quả tìm kiếm</h1>

    <!-- Summary chips -->
    <div class="card shadow-sm mb-4">
      <div class="card-body d-flex flex-wrap gap-2">
        <span class="chip"><i class="ti ti-map-pin"></i> Ga đi: <strong><?= h($ga_di) ?></strong></span>
        <span class="chip"><i class="ti ti-flag-3"></i> Ga đến: <strong><?= h($ga_den) ?></strong></span>
        <span class="chip"><i class="ti ti-calendar"></i> Ngày đi: <strong><?= h($ngay_di) ?></strong></span>
        <span class="chip"><i class="ti ti-ticket"></i> Loại vé: <strong><?= h($loai_ve) ?></strong></span>
        <?php if ($loai_ve==='Khứ hồi' && $ngay_ve!==''): ?>
          <span class="chip"><i class="ti ti-calendar"></i> Ngày về: <strong><?= h($ngay_ve) ?></strong></span>
        <?php endif; ?>
        <div class="ms-auto d-flex gap-2">
          <a class="btn btn-outline-primary btn-sm" href="tim_kiem.php"><i class="ti ti-adjustments"></i> Đổi tiêu chí</a>
          <a class="btn btn-primary btn-sm" href="lich_trinh.php?ga_di=<?= h($ga_di) ?>&ga_den=<?= h($ga_den) ?>&ngay_di=<?= h($ngay_di) ?>"><i class="ti ti-list"></i> Xem tất cả lịch trình</a>
        </div>
      </div>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <?php foreach($errors as $e): ?><div>• <?= h($e) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Section render helper -->
    <?php
      function render_cards($rows, $title){
        if (!$rows){
          echo '<div class="card shadow-sm mb-4"><div class="card-header"><h5 class="mb-0">'.h($title).'</h5></div><div class="card-body text-center text-muted py-4">Không có chuyến phù hợp.</div></div>';
          return;
        }
        echo '<div class="card shadow-sm mb-4"><div class="card-header"><h5 class="mb-0">'.h($title).'</h5></div><div class="card-body"><div class="row g-3">';
        foreach($rows as $r){
          $start = dt($r['ngay_di'],$r['gio_di']);
          $end   = dt($r['ngay_den'],$r['gio_den']);
          $dur   = duration_str($start,$end);
          echo '<div class="col-12 col-md-6 col-xl-4">
                  <div class="card h-100 trip-card">
                    <div class="card-body d-flex flex-column">
                      <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="badge text-bg-primary">'.h($r['ten_tau']).'</span>
                        <span class="text-muted small">#'.h($r['id']).'</span>
                      </div>
                      <div class="fw-semibold">'.h($r['ga_di']).' → '.h($r['ga_den']).'</div>
                      <div class="d-flex align-items-center my-2">
                        <div class="me-3">
                          <div class="small muted">Khởi hành</div>
                          <div>'.h(date('H:i', strtotime($start))).'</div>
                          <div class="muted small">'.h(date('d/m/Y', strtotime($start))).'</div>
                        </div>
                        <div class="flex-grow-1 line mx-2"></div>
                        <div class="ms-3 text-end">
                          <div class="small muted">Đến nơi</div>
                          <div>'.h(date('H:i', strtotime($end))).'</div>
                          <div class="muted small">'.h(date('d/m/Y', strtotime($end))).'</div>
                        </div>
                      </div>';
          if ($dur) echo '<div class="muted small mb-2"><i class="ti ti-clock"></i> Thời gian dự kiến: '.$dur.'</div>';
          echo        '<div class="mt-auto d-flex justify-content-between align-items-end">
                          <div class="price">'.money_vi($r['gia_ve']).'</div>
                          <button class="btn btn-primary" onclick="bookTicket(\''.h($r['id']).'\')"><i class="ti ti-ticket"></i> Đặt vé</button>
                        </div>
                    </div>
                  </div>
                </div>';
        }
        echo '</div></div></div>';
      }
    ?>

    <?php if (!$errors): ?>
      <?php render_cards($di_rows, 'Chuyến đi'); ?>
      <?php if ($loai_ve==='Khứ hồi' && $ngay_ve!=='') render_cards($ve_rows, 'Chuyến về'); ?>
    <?php endif; ?>

    <div class="mb-5"><a class="btn btn-outline-secondary" href="tim_kiem.php"><i class="ti ti-arrow-left"></i> Quay lại tìm kiếm</a></div>
  </div>

  <footer class="py-4">
    <div class="container">
      <div class="text-muted small">© <?= date('Y') ?> Đoàn Đức Bình IT4.K23</div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
