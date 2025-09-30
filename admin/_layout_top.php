<?php
// admin/_layout_top.php
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= h($page_title ?? 'VNR Admin') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet" />
  <style>
    :root { --sidebar-w: 250px; }
    body { background:#f6f8fb; }
    .sidebar{ position:fixed; inset:0 auto 0 0; width:var(--sidebar-w); background:#0d6efd; color:#fff; padding:14px 10px; overflow-y:auto; }
    .sidebar a{ color:#e7f1ff; text-decoration:none; display:flex; gap:10px; align-items:center; padding:10px 12px; border-radius:10px; }
    .sidebar a:hover, .sidebar a.active{ background:#0b5ed7; color:#fff; }
    .brand{ padding:10px 12px; font-weight:700; }
    .content{ margin-left:var(--sidebar-w); min-height:100vh; }
    .topbar{ position:sticky; top:0; z-index:20; background:#fff; border-bottom:1px solid #e9ecef; }
    .card{ border-radius:16px; }
  </style>
</head>
<body>
  <aside class="sidebar">
    <div class="brand d-flex align-items-center gap-2"><i class="ti ti-train"></i> VNR Admin</div>
    <nav class="mt-1">
      <a href="/admin/index.php" class="<?= ($active ?? '')==='dashboard'?'active':'' ?>"><i class="ti ti-layout-dashboard"></i>Dashboard</a>
      <div class="small text-white-50 px-2 mt-3 mb-1">Dữ liệu</div>
      <a href="/admin/users.php" class="<?= ($active ?? '')==='users'?'active':'' ?>"><i class="ti ti-users"></i>Người dùng</a>
      <a href="/admin/chuyen_tau.php" class="<?= ($active ?? '')==='chuyen'?'active':'' ?>"><i class="ti ti-calendar-time"></i>Chuyến tàu</a>
      <a href="/admin/toa_tau.php" class="<?= ($active ?? '')==='toa'?'active':'' ?>"><i class="ti ti-building-rail"></i>Toa & Ghế</a>
      <a href="/admin/thanh_toan.php" class="<?= ($active ?? '')==='pay'?'active':'' ?>"><i class="ti ti-credit-card"></i>Thanh toán</a>
      <a href="/admin/ve_da_dat.php" class="<?= ($active ?? '')==='tickets'?'active':'' ?>"><i class="ti ti-ticket"></i>Vé đã đặt</a>
      <div class="small text-white-50 px-2 mt-3 mb-1">Nội dung</div>
      <a href="/admin/menus.php" class="<?= ($active ?? '')==='menus'?'active':'' ?>"><i class="ti ti-list-details"></i>Menu website</a>
      <a href="/admin/settings.php" class="<?= ($active ?? '')==='settings'?'active':'' ?>"><i class="ti ti-settings"></i>Cài đặt</a>
      <div class="small text-white-50 px-2 mt-3 mb-1">Khác</div>
      <a href="/"><i class="ti ti-home"></i>Trang người dùng</a>
      <a href="/logout.php"><i class="ti ti-logout"></i>Đăng xuất</a>
    </nav>
  </aside>

  <div class="content">
    <div class="topbar">
      <div class="container-fluid py-2 px-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <span class="fw-semibold"><?= h($page_h1 ?? 'Quản trị') ?></span>
          <span class="text-muted">/ <?= h($page_breadcrumb ?? '') ?></span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge text-bg-light">Admin: <?= h($_SESSION['user_id']) ?></span>
        </div>
      </div>
    </div>
    <main class="container-fluid p-3 p-md-4">
