<?php
// ==== Session ====
if (session_status() === PHP_SESSION_NONE) session_start();

// ==== DB (yêu cầu includes/db.php tạo $pdo là PDO) ====
require_once __DIR__ . '/includes/db.php';
$connected = isset($pdo) && $pdo instanceof PDO;

// ==== Helpers ====
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function money_vi($n){ return number_format((int)$n, 0, ',', '.') . ' VNĐ'; }

// ==== Cart count ====
$cart_count = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

// ==== Menus: fallback mặc định, sau đó cố đọc từ DB để ghi đè ====
$header_menus = [
  ['label' => 'TRANG CHỦ',     'url' => 'index.php'],
  ['label' => 'TÌM CHUYẾN TÀU', 'url' => 'tim_kiem.php'],
  ['label' => 'LỊCH TRÌNH',    'url' => 'lich_trinh.php'],
  ['label' => 'LIÊN HỆ',       'url' => 'lien_he.php'],
];
$footer_menus = [];

if ($connected) {
  try {
    $stm = $pdo->query("
      SELECT location, label, url
      FROM menus
      WHERE visible = 1
      ORDER BY location, position, id
    ");
    $rows = $stm->fetchAll();
    if ($rows) {
      $header_menus = []; // chỉ ghi đè nếu có dữ liệu
      foreach ($rows as $m) {
        $item = ['label' => $m['label'], 'url' => $m['url']];
        if ($m['location'] === 'footer') $footer_menus[] = $item;
        else $header_menus[] = $item;
      }
    }
  } catch (Throwable $e) {
    // Bảng menus chưa có -> giữ fallback
  }
}

// ==== Featured: lấy từ chuyen_tau (90 ngày gần nhất, tối đa 5 dòng) ====
$featured = [];
if ($connected) {
  try {
    $sql = "
      SELECT id,
             ten_tau AS train_name,
             ga_di   AS depart_name,
             ga_den  AS arrive_name,
             CONCAT(ngay_di, ' ', gio_di) AS depart_time,
             gia_ve  AS price
      FROM chuyen_tau
      WHERE CONCAT(ngay_di,' ',gio_di) >= NOW() - INTERVAL 90 DAY
      ORDER BY ngay_di ASC, gio_di ASC
      LIMIT 5
    ";
    $featured = $pdo->query($sql)->fetchAll();
  } catch (Throwable $e) { $featured = []; }
}
?>
<!doctype html>
<html lang="vi" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Web bán vé – Trang chủ</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    
    :root {
      --brand: #0d6efd;
      --bg-soft: #f8f9fb;
      --card-radius: 16px;
    }
    [data-theme="dark"] { --bg-soft: #0b0e12; }
    body { background: var(--bg-soft); }
    .navbar-brand img { height: 44px; }
    .card { border-radius: var(--card-radius); }
    .hero {
      background: linear-gradient(135deg, #0d6efd 0%, #3b8aff 100%);
      color: #fff;
      border-radius: var(--card-radius);
    }
    .hero .btn { border-radius: 999px; }
    .table thead th { background: #0d6efd; color: #fff; }
    footer { border-top: 1px solid #e9ecef; }
  </style>
</head>
<body>
  <!-- NAVBAR -->
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
          <?php foreach ($header_menus as $m): ?>
            <li class="nav-item">
              <a class="nav-link" href="<?= h($m['url']) ?>"><?= h($m['label']) ?></a>
            </li>
          <?php endforeach; ?>
          <li class="nav-item"><a class="nav-link" href="dat_ve_thanh_cong.php">VÉ ĐÃ ĐẶT</a></li>
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

  <!-- ALERT nếu không kết nối DB -->
  <?php if (!$connected): ?>
    <div class="container mt-3">
      <div class="alert alert-warning">
        Không kết nối được cơ sở dữ liệu. Đang hiển thị một phần dữ liệu demo.
      </div>
    </div>
  <?php endif; ?>

  <!-- HERO -->
  <div class="container my-4">
    <div class="hero p-4 p-md-5 shadow-sm">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h1 class="h3 h1-md fw-bold mb-2">Đặt vé tàu nhanh – an toàn – tiện lợi</h1>
          <p class="mb-3">Tìm chuyến tàu phù hợp, xem lịch trình, đặt & quản lý vé trực tuyến.</p>
          <a class="btn btn-light btn-lg me-2" href="tim_kiem.php"><i class="ti ti-search"></i> Tìm chuyến tàu</a>
          <a class="btn btn-outline-light btn-lg" href="lich_trinh.php"><i class="ti ti-calendar-time"></i> Xem lịch trình</a>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
          <div class="bg-white text-dark rounded-4 p-3 shadow-sm">
            <div class="small text-muted">Giỏ hàng</div>
            <div class="display-6"><?= $cart_count ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- MAIN GRID -->
  <div class="container mb-5">
    <div class="row g-4">
      <div class="col-lg-8">
        <!-- Điều kiện đi tàu -->
        <div class="card shadow-sm">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Điều kiện hành khách đi tàu</h5>
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#ruleBox">
              <i class="ti ti-chevron-down"></i>
            </button>
          </div>
          <div id="ruleBox" class="collapse show">
            <div class="card-body">
              <p><strong>1. Đi từ địa phương/khu vực cấp độ dịch 1–2:</strong></p>
              <ul class="mb-3">
                <li>Tuân thủ “Thông điệp 5K”; khai báo y tế trên ứng dụng PC-Covid.</li>
                <li>Thực hiện nghiêm biện pháp phòng chống dịch theo hướng dẫn của Bộ Y tế.</li>
              </ul>
              <p><strong>2. Đi từ khu vực cấp độ 3:</strong></p>
              <ul class="mb-3">
                <li>Thực hiện như mục (1); xét nghiệm khi có triệu chứng hoặc theo điều tra dịch tễ.</li>
              </ul>
              <p><strong>3. Đi từ khu vực cấp độ 4:</strong></p>
              <ul class="mb-0">
                <li>Cần kết quả xét nghiệm âm tính trong 72 giờ trước khi lên tàu; mua vé/toa theo hướng dẫn.</li>
              </ul>
              <div class="alert alert-danger mt-3 mb-0">
                <strong>Lưu ý:</strong> Không tuân thủ các điều kiện đi tàu, ngành đường sắt từ chối chuyên chở và không hoàn tiền vé.
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Lịch trình nổi bật -->
      <div class="col-lg-4">
        <div class="card shadow-sm">
          <div class="card-header"><h5 class="mb-0">Lịch trình nổi bật</h5></div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table mb-0 align-middle">
                <thead>
                  <tr>
                    <th>Ga đi</th>
                    <th>Ga đến</th>
                    <th>Khởi hành</th>
                  </tr>
                </thead>
                <tbody>
                <?php if ($featured && count($featured)): ?>
                  <?php foreach ($featured as $it): ?>
                    <tr>
                      <td><?= h($it['depart_name']) ?></td>
                      <td><?= h($it['arrive_name']) ?></td>
                      <td>
                        <div class="small text-nowrap"><?= h(date('H:i d/m/Y', strtotime($it['depart_time']))) ?></div>
                        <div class="text-muted small"><?= money_vi($it['price']) ?></div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <!-- Fallback demo nếu chưa có dữ liệu -->
                  <tr>
                    <td>Hà Nội</td>
                    <td>TP.HCM</td>
                    <td><div class="small">08:00 12/11/2024</div><div class="text-muted small">1,200,000 VNĐ</div></td>
                  </tr>
                  <tr>
                    <td>Hải Phòng</td>
                    <td>Hà Nội</td>
                    <td><div class="small">09:00 13/11/2024</div><div class="text-muted small">200,000 VNĐ</div></td>
                  </tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="card-footer text-end">
            <a class="btn btn-sm btn-outline-primary" href="lich_trinh.php"><i class="ti ti-arrow-right"></i> Xem tất cả</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- FOOTER -->
  <footer class="py-4">
    <div class="container">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
        <div class="text-muted small">© <?= date('Y') ?> Đoàn Đức Bình IT4.K23</div>
        <ul class="nav">
          <?php foreach ($footer_menus as $m): ?>
            <li class="nav-item">
              <a class="nav-link small" href="<?= h($m['url']) ?>"><?= h($m['label']) ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Theme switcher (light/dark) – lưu LocalStorage
    const html = document.documentElement;
    const btn  = document.getElementById('themeBtn');
    const saved = localStorage.getItem('theme');
    if (saved) html.dataset.theme = saved;

    const setIcon = () => {
      btn.innerHTML = html.dataset.theme === 'dark' ? '<i class="ti ti-moon-stars"></i>' : '<i class="ti ti-sun"></i>';
    };
    setIcon();

    btn?.addEventListener('click', () => {
      html.dataset.theme = (html.dataset.theme === 'dark' ? 'light' : 'dark');
      localStorage.setItem('theme', html.dataset.theme);
      setIcon();
    });
  </script>
</body>
</html>
