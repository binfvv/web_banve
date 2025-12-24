<?php
// ===== Kết nối & Session =====
include __DIR__ . '/includes/db.php'; // $pdo (PDO) nếu bạn đã cấu hình ở đây
if (session_status() === PHP_SESSION_NONE) session_start();

// ===== CSRF (tối thiểu) =====
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$CSRF = $_SESSION['csrf'];

// ===== Giỏ hàng =====
$cart_count = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

// ===== Menu động (fallback nếu chưa có bảng menus) =====
$header_menus = [
  ['label'=>'TRANG CHỦ','url'=>'index.php'],
  ['label'=>'TÌM CHUYẾN TÀU','url'=>'tim_kiem.php'],
  ['label'=>'LỊCH TRÌNH','url'=>'lich_trinh.php'],
  ['label'=>'LIÊN HỆ','url'=>'lien_he.php'],
];

try {
  $rows = $pdo->query("SELECT location,label,url FROM menus WHERE visible=1 ORDER BY location, position, id")->fetchAll(PDO::FETCH_ASSOC);
  if ($rows) {
    $header_menus = [];
    foreach ($rows as $m) {
      if ($m['location'] !== 'footer') {
        $header_menus[] = ['label'=>$m['label'], 'url'=>$m['url']];
      }
    }
  }
} catch (Throwable $e) {
  // Chưa có bảng menus -> dùng fallback trên
}

// ===== Lấy danh sách ga từ DB (fallback nếu chưa có bảng) =====
$stations = [];
try {
  $stations = $pdo->query("SELECT name FROM stations ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
  $stations = ['Hải Phòng','Hà Nội','Hồ Chí Minh','Quảng Ninh','Đà Nẵng','Nha Trang'];
}

// ===== Giới hạn ngày (today) =====
$today = (new DateTime('today'))->format('Y-m-d');
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tìm kiếm chuyến tàu</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    :root { --card-r:16px; }
    body { background:#f8f9fb; }
    .navbar-brand img { height:44px; }
    .card { border-radius: var(--card-r); }
    .hero { background:linear-gradient(135deg,#0d6efd 0%,#3b8aff 100%); color:#fff; border-radius: var(--card-r); }
    .form-card { max-width: 860px; margin:auto; }
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
            <li class="nav-item"><a class="nav-link<?= (basename($m['url'])==='tim_kiem.php'?' active':'') ?>" href="<?= htmlspecialchars($m['url']) ?>"><?= htmlspecialchars($m['label']) ?></a></li>
          <?php endforeach; ?>
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
          <h1 class="h3 h1-md fw-bold mb-2">Tìm kiếm chuyến tàu</h1>
          <p class="mb-0">Chọn ga đi/đến và ngày khởi hành (có thể chọn khứ hồi). Hệ thống sẽ liệt kê các lịch trình phù hợp.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
          <div class="bg-white text-dark rounded-4 p-3 shadow-sm d-inline-block">
            <div class="small text-muted">Giỏ hàng</div>
            <div class="display-6"><?= $cart_count ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- SEARCH CARD -->
  <div class="container mb-5">
    <div class="card shadow-sm form-card">
      <div class="card-body p-4">
        <form action="ket_qua_tim_kiem.php" method="post" class="row g-3" onsubmit="return validateForm()">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
          <div class="col-12">
            <h2 class="h4 mb-0">Tìm kiếm chuyến tàu</h2>
          </div>

          <div class="col-md-6">
            <label for="ga_di" class="form-label">Ga đi</label>
            <select name="ga_di" id="ga_di" class="form-select" required>
              <option value="" disabled selected>Chọn ga đi</option>
              <?php foreach($stations as $st): ?>
                <option value="<?= htmlspecialchars($st) ?>"><?= htmlspecialchars($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label for="ga_den" class="form-label">Ga đến</label>
            <select name="ga_den" id="ga_den" class="form-select" required>
              <option value="" disabled selected>Chọn ga đến</option>
              <?php foreach($stations as $st): ?>
                <option value="<?= htmlspecialchars($st) ?>"><?= htmlspecialchars($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label for="ngay_di" class="form-label">Ngày đi</label>
            <input type="date" id="ngay_di" name="ngay_di" class="form-control" required min="<?= $today ?>">
          </div>

          <div class="col-md-6">
            <label for="ngay_ve" class="form-label">Ngày về</label>
            <input type="date" id="ngay_ve" name="ngay_ve" class="form-control" disabled>
          </div>

          <div class="col-12">
            <label class="form-label d-block">Loại vé</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="ticket_type" id="oneway" value="Một chiều" checked onclick="toggleNgayVe()">
              <label class="form-check-label" for="oneway">Một chiều</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="ticket_type" id="roundtrip" value="Khứ hồi" onclick="toggleNgayVe()">
              <label class="form-check-label" for="roundtrip">Khứ hồi</label>
            </div>
          </div>

          <div class="col-12 d-flex gap-2">
            <button type="submit" name="btn_tk" class="btn btn-primary">
              <i class="ti ti-search"></i> Tìm kiếm
            </button>
            <a class="btn btn-outline-secondary" href="tim_kiem.php"><i class="ti ti-eraser"></i> Xóa</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- FOOTER -->
  <footer class="py-4">
    <div class="container">
      <div class="text-muted small">© <?= date('Y') ?> Đoàn Đức Bình IT4.K23</div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function toggleNgayVe() {
      const ngayVeField = document.getElementById("ngay_ve");
      const isReturnTrip = document.getElementById('roundtrip').checked;
      ngayVeField.disabled = !isReturnTrip;
      if (!isReturnTrip) ngayVeField.value = "";
      if (isReturnTrip) {
        // ngày về không thể trước ngày đi
        const d = document.getElementById('ngay_di').value;
        ngayVeField.min = d || "<?= $today ?>";
      }
    }

    function validateForm() {
      const from = document.getElementById('ga_di').value;
      const to   = document.getElementById('ga_den').value;
      if (from && to && from === to) {
        alert('Ga đi và Ga đến phải khác nhau.');
        return false;
      }
      const ngayDi = document.getElementById('ngay_di').value;
      if (!ngayDi) return false;
      const isReturn = document.getElementById('roundtrip').checked;
      if (isReturn) {
        const ngayVe = document.getElementById('ngay_ve').value;
        if (!ngayVe) {
          alert('Vui lòng chọn Ngày về cho vé khứ hồi.');
          return false;
        }
        if (new Date(ngayVe) < new Date(ngayDi)) {
          alert('Ngày về không được trước Ngày đi.');
          return false;
        }
      }
      return true;
    }

    document.addEventListener("DOMContentLoaded", () => {
      toggleNgayVe();
      document.getElementById('ngay_di').addEventListener('change', toggleNgayVe);
    });
  </script>
</body>
</html>
