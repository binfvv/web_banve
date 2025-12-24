<?php
// dat_ve_thanh_cong.php — Giao diện Ticket Card
if (session_status() === PHP_SESSION_NONE) session_start();

// --- 1. SETUP DB & AUTH ---
$db_paths = [__DIR__.'/assets/includes/db.php', __DIR__.'/includes/db.php', __DIR__.'/db.php'];
$db_found = false;
foreach($db_paths as $p) if(file_exists($p)){ require_once $p; $db_found=true; break; }
if(!$db_found) die("Lỗi: Không tìm thấy file db.php");

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function money_vi($n){ return number_format((int)$n, 0, ',', '.') . ' đ'; }
function date_vi($d){ return date('d/m/Y', strtotime($d)); }
function time_vi($t){ return date('H:i', strtotime($t)); }

$cart_count = (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) ? count($_SESSION['cart']) : 0;

// --- 2. LOGIC LẤY DỮ LIỆU (GIỮ NGUYÊN LOGIC CŨ) ---

/** A) Giao dịch gần nhất */
$sqlPay = "SELECT id, phuong_thuc, tong_tien, ngay_giao_dich, ghi_chu, trang_thai FROM thanh_toan WHERE user_id = :uid ORDER BY ngay_giao_dich DESC, id DESC LIMIT 1";
$stmtPay = $pdo->prepare($sqlPay);
$stmtPay->execute([':uid' => $user_id]);
$lastPayment = $stmtPay->fetch(PDO::FETCH_ASSOC);

/** B) Vé thuộc giao dịch gần nhất */
$latestTickets = [];
if ($lastPayment && !empty($lastPayment['ghi_chu'])) {
  $meta = json_decode($lastPayment['ghi_chu'], true); // Code cũ dùng meta['seats']
  // Nếu logic cũ lưu list ghế vào ghi_chu, ta parse ra:
  if (json_last_error() === JSON_ERROR_NONE && !empty($meta['seats']) && is_array($meta['seats'])) {
    $seatIds = array_values(array_unique(array_map('intval', $meta['seats'])));
    if ($seatIds) {
      $ph = implode(',', array_fill(0, count($seatIds), '?'));
      $sql = "SELECT ct.id AS ma_chuyen, ct.ten_tau, ct.ga_di, ct.ga_den, ct.ngay_di, ct.gio_di, ct.ngay_den, ct.gio_den, COALESCE(g.gia, ct.gia_ve) AS gia_ve, g.so_ghe, tt.ten_toa 
              FROM ghe g 
              JOIN chuyen_tau ct ON g.id_tau = ct.id 
              LEFT JOIN toa_tau tt ON g.id_toa = tt.id 
              WHERE g.id IN ($ph) ORDER BY ct.ngay_di, ct.gio_di, ct.id, g.so_ghe";
      $st = $pdo->prepare($sql);
      $st->execute($seatIds);
      $latestTickets = $st->fetchAll(PDO::FETCH_ASSOC);
    }
  } else {
     // Fallback: Nếu không lưu trong ghi_chu, thử tìm vé theo thời gian khớp với giao dịch (Optional)
     // Ở đây giữ nguyên logic bạn cung cấp là chỉ lấy từ ghi_chu['seats']
  }
}

/** C) Tất cả vé đã đặt (trạng thái 'da_dat') */
$sqlAll = "SELECT ct.id AS ma_chuyen, ct.ten_tau, ct.ga_di, ct.ga_den, ct.ngay_di, ct.gio_di, ct.ngay_den, ct.gio_den, COALESCE(g.gia, ct.gia_ve) AS gia_ve, g.so_ghe, tt.ten_toa 
           FROM ghe g 
           JOIN chuyen_tau ct ON g.id_tau = ct.id 
           LEFT JOIN toa_tau tt ON g.id_toa = tt.id 
           WHERE g.user_id = :uid AND g.trang_thai = 'da_dat' 
           ORDER BY ct.ngay_di DESC, ct.gio_di ASC, g.so_ghe ASC";
$stAll = $pdo->prepare($sqlAll);
$stAll->execute([':uid' => $user_id]);
$allTickets = $stAll->fetchAll(PDO::FETCH_ASSOC);

$header_menus = [
    ['label'=>'TRANG CHỦ','url'=>'index.php'],
    ['label'=>'TÌM CHUYẾN TÀU','url'=>'tim_kiem.php'],
    ['label'=>'LỊCH TRÌNH','url'=>'lich_trinh.php'],
    ['label'=>'LIÊN HỆ','url'=>'lien_he.php'],
];
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vé đã đặt</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
  <style>
    body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
    .navbar-brand img { height: 44px; }
    
    /* Ticket Card Design */
    .ticket-card {
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid #e1e4e8;
        transition: transform 0.2s, box-shadow 0.2s;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .ticket-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    }
    .ticket-header {
        background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
        color: white;
        padding: 12px 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .ticket-body {
        padding: 20px;
        flex-grow: 1;
        position: relative;
    }
    /* Đường đứt nét mô phỏng vé */
    .ticket-body::before {
        content: ''; position: absolute; top: -1px; left: 0; right: 0;
        border-top: 2px dashed #fff; opacity: 0.3;
    }
    .route-timeline {
        border-left: 2px dashed #cbd5e1;
        margin-left: 6px;
        padding-left: 20px;
        padding-bottom: 20px;
        position: relative;
    }
    .route-timeline::before {
        content: ''; position: absolute; left: -6px; top: 0;
        width: 10px; height: 10px; border-radius: 50%; background: #0d6efd;
    }
    .route-timeline.end { border-left: none; padding-bottom: 0; }
    .route-timeline.end::before { background: #dc3545; top: 5px; }
    
    .ticket-footer {
        background: #f8f9fa;
        padding: 12px 15px;
        border-top: 1px dashed #dee2e6;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .summary-card {
        background: #fff;
        border-radius: 12px;
        border-left: 5px solid #0d6efd;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark" style="background:#0d6efd;">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
        <img src="assets/LOGO_n.png" alt="DSVN" onerror="this.style.display='none'">
        <span class="fw-bold">Vé Tàu</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMain">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <?php foreach ($header_menus as $m): ?>
            <li class="nav-item">
              <a class="nav-link" href="<?= h($m['url']) ?>"><?= h($m['label']) ?></a>
            </li>
          <?php endforeach; ?>
          <li class="nav-item"><a class="nav-link active" href="dat_ve_thanh_cong.php">VÉ ĐÃ ĐẶT</a></li>
        </ul>
        <div class="d-flex align-items-center gap-2">
          <?php if (isset($_SESSION['user_id'])): ?>
            <a class="btn btn-sm btn-light" href="logout.php">ĐĂNG XUẤT</a>
          <?php else: ?>
            <a class="btn btn-sm btn-outline-light" href="login.php">ĐĂNG NHẬP</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
</nav>

<div class="container my-4">

  <?php if ($lastPayment): ?>
    <div class="summary-card p-4 mb-5">
        <div class="row align-items-center g-3">
            <div class="col-md-8">
                <h4 class="text-primary mb-1">
                    <i class="ti ti-circle-check-filled text-success"></i> Giao dịch gần nhất
                </h4>
                <div class="text-muted small">
                    Mã đơn: <span class="fw-bold text-dark"><?= h($lastPayment['ma_don_hang'] ?? 'GD-'.$lastPayment['id']) ?></span> 
                    &bull; Ngày: <?= date_vi($lastPayment['ngay_giao_dich']).' '.time_vi($lastPayment['ngay_giao_dich']) ?>
                </div>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="display-6 fw-bold text-danger"><?= money_vi($lastPayment['tong_tien']) ?></div>
                <span class="badge bg-success bg-opacity-10 text-success border border-success">
                    <?= $lastPayment['trang_thai'] === 'da_thanh_toan' ? 'ĐÃ THANH TOÁN' : 'CHỜ THANH TOÁN' ?>
                </span>
            </div>
        </div>
        
        <?php if($latestTickets): ?>
        <hr class="text-muted opacity-25">
        <h6 class="text-uppercase text-muted small fw-bold mb-3">Vé vừa đặt:</h6>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
            <?php foreach($latestTickets as $row): ?>
                <div class="col">
                    <div class="border rounded p-2 d-flex align-items-center bg-light">
                        <div class="bg-white border rounded p-2 text-center me-3" style="min-width: 60px;">
                            <div class="fw-bold text-primary"><?= h($row['ten_toa']) ?></div>
                            <div class="small">Ghế <?= h($row['so_ghe']) ?></div>
                        </div>
                        <div>
                            <div class="fw-bold"><?= h($row['ten_tau']) ?></div>
                            <div class="small text-muted"><?= h($row['ga_di']) ?> &rarr; <?= h($row['ga_den']) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="fw-bold m-0"><i class="ti ti-ticket"></i> Kho vé của tôi</h3>
      <a href="tim_kiem.php" class="btn btn-primary"><i class="ti ti-plus"></i> Đặt thêm vé</a>
  </div>

  <?php if (!$allTickets): ?>
    <div class="text-center py-5 text-muted">
        <i class="ti ti-ticket-off fs-1 d-block mb-3 opacity-50"></i>
        <p>Bạn chưa có lịch sử đặt vé nào.</p>
        <a href="tim_kiem.php" class="btn btn-outline-primary">Tìm chuyến tàu ngay</a>
    </div>
  <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
      <?php foreach($allTickets as $row): ?>
        <div class="col">
          <div class="ticket-card">
            <div class="ticket-header">
                <div>
                    <span class="fw-bold"><i class="ti ti-train"></i> <?= h($row['ten_tau']) ?></span>
                </div>
                <div class="badge bg-white text-primary fw-bold">
                    <?= date_vi($row['ngay_di']) ?>
                </div>
            </div>

            <div class="ticket-body">
                <div class="route-timeline">
                    <div class="fw-bold text-dark"><?= h($row['ga_di']) ?></div>
                    <div class="text-muted small">Khởi hành: <?= time_vi($row['gio_di']) ?></div>
                </div>
                <div class="route-timeline end">
                    <div class="fw-bold text-dark"><?= h($row['ga_den']) ?></div>
                    <div class="text-muted small">Đến nơi: <?= time_vi($row['gio_den']) ?></div>
                </div>
                
                <div class="mt-4 d-flex justify-content-center gap-3">
                    <div class="text-center px-3 py-2 bg-light rounded border">
                        <small class="text-muted d-block">Toa</small>
                        <strong class="text-primary"><?= h($row['ten_toa']) ?></strong>
                    </div>
                    <div class="text-center px-3 py-2 bg-light rounded border">
                        <small class="text-muted d-block">Ghế số</small>
                        <strong class="text-danger fs-5"><?= h($row['so_ghe']) ?></strong>
                    </div>
                </div>
            </div>

            <div class="ticket-footer">
                <div class="text-muted small">Giá vé</div>
                <div class="fw-bold text-primary fs-5"><?= money_vi($row['gia_ve']) ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>