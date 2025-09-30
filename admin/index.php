<?php
// admin/index.php — Dashboard quản trị
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php'; // tạo $pdo (PDO)

// ===== Middleware: chỉ cho phép admin =====
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header('Location: /login.php'); exit;
}

// ===== Helpers =====
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function money_vi($n){ return number_format((int)$n,0,',','.') . ' VNĐ'; }

// ===== KPIs =====
$kpi_users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

$kpi_revenue = (int)$pdo->query("
  SELECT COALESCE(SUM(tong_tien),0) FROM thanh_toan
")->fetchColumn();

$kpi_sold = (int)$pdo->query("
  SELECT COUNT(*) FROM ghe WHERE trang_thai = 'da_dat'
")->fetchColumn();

$kpi_available = (int)$pdo->query("
  SELECT COUNT(*) FROM ghe WHERE trang_thai = 'trong'
")->fetchColumn();

// ===== Doanh thu 7 ngày gần nhất =====
$revRows = $pdo->query("
  SELECT DATE(ngay_giao_dich) d, SUM(tong_tien) total
  FROM thanh_toan
  WHERE ngay_giao_dich >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
  GROUP BY DATE(ngay_giao_dich)
  ORDER BY d ASC
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Build labels & data cho 7 ngày (đủ ngày không thiếu)
$labels = []; $series = [];
for ($i=6; $i>=0; $i--) {
  $d = (new DateTime())->modify("-$i day")->format('Y-m-d');
  $labels[] = $d;
  $series[] = (int)($revRows[$d] ?? 0);
}

// ===== 5 giao dịch gần nhất =====
$payments = $pdo->query("
  SELECT id, user_id, phuong_thuc, tong_tien, ngay_giao_dich
  FROM thanh_toan
  ORDER BY ngay_giao_dich DESC, id DESC
  LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// ===== 5 chuyến sắp khởi hành =====
// Lưu ý schema: ngay_di (YYYY-MM-DD), gio_di (HH:MM:SS)
$upcoming = $pdo->query("
  SELECT id, ten_tau, ga_di, ga_den, ngay_di, gio_di, gia_ve
  FROM chuyen_tau
  WHERE CONCAT(ngay_di,' ',gio_di) >= NOW()
  ORDER BY ngay_di ASC, gio_di ASC
  LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>VNR Admin — Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    :root { --sidebar-w: 250px; }
    body { background:#f6f8fb; }
    .sidebar {
      position: fixed; inset: 0 auto 0 0; width: var(--sidebar-w);
      background:#0d6efd; color:#fff; padding:14px 10px; overflow-y:auto;
    }
    .sidebar a { color:#e7f1ff; text-decoration:none; display:flex; align-items:center; gap:10px;
      padding:10px 12px; border-radius:10px; }
    .sidebar a:hover, .sidebar a.active { background:#0b5ed7; color:#fff; }
    .brand { padding:10px 12px; font-weight:700; }
    .content { margin-left: var(--sidebar-w); min-height:100vh; }
    .topbar { position:sticky; top:0; z-index:20; background:#fff; border-bottom:1px solid #e9ecef; }
    .card{ border-radius:16px; }
  </style>
</head>
<body>
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="brand d-flex align-items-center gap-2"><i class="ti ti-train"></i> VNR Admin</div>
    <div class="small text-white-50 px-2">Điều hướng</div>
    <nav class="mt-1">
      <a class="active" href="#"><i class="ti ti-layout-dashboard"></i>Dashboard</a>
      <div class="small text-white-50 px-2 mt-3 mb-1">Dữ liệu</div>
      <a href="/admin/users.php"><i class="ti ti-users"></i>Người dùng</a>
      <a href="/admin/chuyen_tau.php"><i class="ti ti-calendar-time"></i>Chuyến tàu</a>
      <a href="/admin/toa_tau.php"><i class="ti ti-building-rail"></i>Toa & Ghế</a>
      <a href="/admin/thanh_toan.php"><i class="ti ti-credit-card"></i>Thanh toán</a>
      <a href="/admin/ve_da_dat.php"><i class="ti ti-ticket"></i>Vé đã đặt</a>
      <div class="small text-white-50 px-2 mt-3 mb-1">Nội dung</div>
      <a href="/admin/menus.php"><i class="ti ti-list-details"></i>Menu website</a>
      <a href="/admin/settings.php"><i class="ti ti-settings"></i>Cài đặt</a>
      <div class="small text-white-50 px-2 mt-3 mb-1">Hệ thống</div>
      <a href="/"><i class="ti ti-home"></i>Về trang người dùng</a>
      <a href="/logout.php"><i class="ti ti-logout"></i>Đăng xuất</a>
    </nav>
  </aside>

  <!-- CONTENT -->
  <div class="content">
    <!-- TOPBAR -->
    <div class="topbar">
      <div class="container-fluid py-2 px-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <span class="fw-semibold">Bảng điều khiển</span>
          <span class="text-muted">/ Dashboard</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div class="input-group input-group-sm" style="width:260px;">
            <span class="input-group-text bg-white"><i class="ti ti-search"></i></span>
            <input type="search" class="form-control" placeholder="Tìm nhanh (demo)…">
          </div>
          <span class="badge text-bg-light">Admin: <?= h($_SESSION['user_id']) ?></span>
        </div>
      </div>
    </div>

    <main class="container-fluid p-3 p-md-4">
      <!-- KPIs -->
      <div class="row g-3">
        <div class="col-sm-6 col-xl-3">
          <div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small">Người dùng</div>
            <div class="display-6"><?= $kpi_users ?></div>
          </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small">Doanh thu (tổng)</div>
            <div class="display-6"><?= money_vi($kpi_revenue) ?></div>
          </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small">Vé đã bán</div>
            <div class="display-6"><?= $kpi_sold ?></div>
          </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small">Ghế còn trống</div>
            <div class="display-6"><?= $kpi_available ?></div>
          </div></div>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <!-- Biểu đồ doanh thu -->
        <div class="col-lg-7">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Doanh thu 7 ngày gần nhất</h5>
              </div>
              <canvas id="chartRevenue" height="120"></canvas>
            </div>
          </div>
        </div>

        <!-- Giao dịch gần nhất -->
        <div class="col-lg-5">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Giao dịch gần đây</h5>
                <a class="btn btn-sm btn-outline-primary" href="/admin/thanh_toan.php">Xem tất cả</a>
              </div>
              <div class="table-responsive">
                <table class="table align-middle table-striped mb-0">
                  <thead><tr><th>#</th><th>Người dùng</th><th>PT</th><th>Tổng</th><th>Thời gian</th></tr></thead>
                  <tbody>
                    <?php if(!$payments): ?>
                      <tr><td colspan="5" class="text-muted">Chưa có giao dịch.</td></tr>
                    <?php else: foreach($payments as $p): ?>
                      <tr>
                        <td>#<?= (int)$p['id'] ?></td>
                        <td><?= h($p['user_id']) ?></td>
                        <td class="text-capitalize"><?= h($p['phuong_thuc']) ?></td>
                        <td class="fw-semibold"><?= money_vi($p['tong_tien']) ?></td>
                        <td class="small text-muted"><?= h($p['ngay_giao_dich']) ?></td>
                      </tr>
                    <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Chuyến sắp khởi hành -->
        <div class="col-12">
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Chuyến sắp khởi hành</h5>
                <a class="btn btn-sm btn-outline-primary" href="/admin/chuyen_tau.php">Quản lý chuyến</a>
              </div>
              <div class="table-responsive">
                <table class="table align-middle table-striped mb-0">
                  <thead><tr><th>#</th><th>Tàu</th><th>Tuyến</th><th>Khởi hành</th><th>Giá vé</th></tr></thead>
                  <tbody>
                    <?php if(!$upcoming): ?>
                      <tr><td colspan="5" class="text-muted">Chưa có chuyến sắp khởi hành.</td></tr>
                    <?php else: $i=1; foreach($upcoming as $c): ?>
                      <tr>
                        <td><?= $i++ ?></td>
                        <td class="fw-semibold"><?= h($c['ten_tau']) ?></td>
                        <td><?= h($c['ga_di']) ?> → <?= h($c['ga_den']) ?></td>
                        <td>
                          <?= h($c['ngay_di']) ?>
                          <div class="small text-muted"><?= h($c['gio_di']) ?></div>
                        </td>
                        <td class="fw-semibold"><?= money_vi($c['gia_ve']) ?></td>
                      </tr>
                    <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>

  <script>
    // Chart data từ PHP
    const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
    const data   = <?= json_encode($series, JSON_UNESCAPED_UNICODE) ?>;

    // Vẽ biểu đồ
    const ctx = document.getElementById('chartRevenue');
    new Chart(ctx, {
      type: 'line',
      data: { labels, datasets: [{ label: 'Doanh thu (VNĐ)', data, tension: .25 }] },
      options: {
        responsive: true,
        scales: { y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString('vi-VN') } } },
        plugins: { tooltip: { callbacks: { label: c => c.parsed.y.toLocaleString('vi-VN') + ' đ' } } }
      }
    });
  </script>
</body>
</html>
