<?php
// thanh_toan.php
if (session_status() === PHP_SESSION_NONE) session_start();

// --- 1. KẾT NỐI DB ---
$db_paths = [
    __DIR__ . '/assets/includes/db.php',
    __DIR__ . '/includes/db.php',
    __DIR__ . '/db.php'
];
$db_found = false;
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_found = true;
        break;
    }
}
if (!$db_found) {
    die("<h1>Lỗi hệ thống:</h1> Không tìm thấy file <code>db.php</code>.");
}
// ---------------------

// ===== Helpers & Navbar Data =====
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$header_menus = [
    ['label'=>'TRANG CHỦ','url'=>'index.php'],
    ['label'=>'TÌM CHUYẾN TÀU','url'=>'tim_kiem.php'],
    ['label'=>'LỊCH TRÌNH','url'=>'lich_trinh.php'],
    ['label'=>'LIÊN HỆ','url'=>'lien_he.php'],
];

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
$cart_count = count($_SESSION['cart']);

// ===== 2. LẤY DỮ LIỆU GHẾ =====
$seat_ids_str = $_GET['seats'] ?? '';
$seat_ids = array_filter(explode(',', $seat_ids_str), 'is_numeric');

if (empty($seat_ids)) { header('Location: index.php'); exit; }

$ph = implode(',', array_fill(0, count($seat_ids), '?'));
$sql = "
    SELECT g.id, g.so_ghe, g.gia, g.trang_thai, 
           t.ten_toa, 
           ct.ten_tau, ct.ngay_di, ct.gio_di, ct.ga_di, ct.ga_den, ct.id as id_tau
    FROM ghe g
    JOIN toa_tau t ON g.id_toa = t.id
    JOIN chuyen_tau ct ON t.id_tau = ct.id
    WHERE g.id IN ($ph)
";
$stmt = $pdo->prepare($sql);
$stmt->execute($seat_ids);
$seats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_price = 0;
$valid_ids = [];
$train_info = null;

if (empty($seats)) { die('<div class="alert alert-warning m-4">Dữ liệu ghế không tồn tại.</div>'); }

foreach ($seats as $s) {
    if ($s['trang_thai'] === 'da_dat') {
        die("<div class='container mt-5 alert alert-danger'>Ghế <b>{$s['so_ghe']}</b> ({$s['ten_toa']}) vừa có người khác đặt. <a href='javascript:history.back()'>Quay lại</a></div>");
    }
    $total_price += $s['gia'];
    $valid_ids[] = $s['id'];
    
    if (!$train_info) {
        $train_info = [
            'ten_tau' => $s['ten_tau'],
            'lich' => $s['ga_di'] . ' → ' . $s['ga_den'] . ' (' . date('H:i d/m/Y', strtotime($s['ngay_di'].' '.$s['gio_di'])) . ')',
            'id_tau' => $s['id_tau']
        ];
    }
}

// ===== 3. XỬ LÝ POST (TẠO ĐƠN & CHUYỂN QUA TRANG QR) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ho_ten = $_POST['ho_ten'] ?? '';
    $sdt    = $_POST['sdt'] ?? '';
    $email  = $_POST['email'] ?? '';
    $cccd   = $_POST['cccd'] ?? '';
    $pt_tt  = 'qr_code'; 
    $ghi_chu= $_POST['ghi_chu'] ?? '';
    $user_id = $_SESSION['user_id'] ?? 0; 

    $ma_don_hang = 'DH-' . strtoupper(substr(md5(uniqid()), 0, 8)); 
    $ngay_giao_dich = date('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();

        // A. Tạo đơn hàng với trạng thái 'cho_thanh_toan'
        $sqlOrder = "INSERT INTO thanh_toan (ma_don_hang, user_id, ho_ten_khach, sdt_khach, email_khach, cccd_khach, phuong_thuc, tong_tien, trang_thai, ngay_giao_dich, ghi_chu) 
                     VALUES (:ma, :uid, :ten, :sdt, :email, :cccd, :pt, :tien, 'cho_thanh_toan', :ngay, :gc)";
        $stmtOrder = $pdo->prepare($sqlOrder);
        $stmtOrder->execute([
            ':ma' => $ma_don_hang, ':uid' => $user_id, ':ten' => $ho_ten, ':sdt' => $sdt, ':email' => $email,
            ':cccd' => $cccd, ':pt' => $pt_tt, ':tien' => $total_price, ':ngay' => $ngay_giao_dich, ':gc' => $ghi_chu
        ]);

        // B. Cập nhật ghế sang trạng thái 'giu_cho'
        $sqlGhe = "UPDATE ghe SET trang_thai = 'giu_cho', user_id = :uid WHERE id = :id";
        $stmtGhe = $pdo->prepare($sqlGhe);

        // C. Tạo lịch sử
        $sqlHist = "INSERT INTO lich_su_dat_ve (id_user, id_tau, id_ghe, thoi_gian_dat) VALUES (:uid, :tau, :ghe, NOW())";
        $stmtHist = $pdo->prepare($sqlHist);

        foreach ($valid_ids as $gid) {
            $stmtGhe->execute([':uid' => $user_id, ':id' => $gid]);
            $stmtHist->execute([':uid' => $user_id, ':tau' => $train_info['id_tau'], ':ghe' => $gid]);
        }

        $pdo->commit();
        
        // --- CHUYỂN HƯỚNG SANG TRANG QUÉT QR ---
        header("Location: thanh_toan_qr.php?ma_don=$ma_don_hang");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Lỗi hệ thống: " . $e->getMessage();
    }
}
?> 
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Thanh toán - Vé Tàu</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
<style>
  body{background:#f6f8fb} 
  .navbar-brand img{height:44px}
  .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
  .header-step { background: #0d6efd; color: white; padding: 15px; border-radius: 12px 12px 0 0; }
  .qr-option { border: 2px solid #0d6efd; background-color: #f0f7ff; border-radius: 8px; }
  .last-no-border:last-child { border-bottom: 0 !important; margin-bottom: 0 !important; padding-bottom: 0 !important; }
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

<div class="container py-4">
    <div class="row g-4">
        <div class="col-md-7">
            <div class="card h-100">
                <div class="header-step">
                    <h5 class="mb-0"><i class="ti ti-user-edit"></i> Thông tin khách hàng</h5>
                </div>
                <div class="card-body p-4">
                    <?php if(isset($error)): ?>
                        <div class="alert alert-danger"><?= h($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Họ và tên <span class="text-danger">*</span></label>
                                <input type="text" name="ho_ten" class="form-control" required placeholder="Ví dụ: Nguyễn Văn A"
                                       value="<?= isset($_SESSION['user_name']) ? h($_SESSION['user_name']) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Số điện thoại <span class="text-danger">*</span></label>
                                <input type="text" name="sdt" class="form-control" required placeholder="09xxxxxxx">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="example@email.com"
                                   value="<?= isset($_SESSION['user_email']) ? h($_SESSION['user_email']) : '' ?>">
                            <div class="form-text">Vé điện tử sẽ được gửi qua email này.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">CCCD / CMND <span class="text-danger">*</span></label>
                            <input type="text" name="cccd" class="form-control" required placeholder="Số căn cước công dân">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Ghi chú</label>
                            <textarea name="ghi_chu" class="form-control" rows="2"></textarea>
                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3"><i class="ti ti-credit-card"></i> Phương thức thanh toán</h5>
                        
                        <div class="qr-option p-3 d-flex align-items-center gap-3">
                            <div class="bg-white p-2 rounded border">
                                <i class="ti ti-qrcode fs-1 text-dark"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Thanh toán bằng mã QR (VietQR)</h6>
                                <p class="mb-0 small text-muted">Hệ thống sẽ hiển thị mã QR để bạn quét sau khi bấm xác nhận.</p>
                            </div>
                        </div>
                        <input type="hidden" name="phuong_thuc" value="qr_code">

                        <button type="submit" class="btn btn-primary w-100 py-3 mt-4 fw-bold text-uppercase">
                            XÁC NHẬN & QUÉT MÃ QR
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card bg-white">
                <div class="header-step bg-secondary text-white">
                    <h5 class="mb-0"><i class="ti ti-ticket"></i> Chi tiết vé đặt</h5>
                </div>
                <div class="card-body p-4">
                    <h5 class="text-primary fw-bold"><?= h($train_info['ten_tau']) ?></h5>
                    <p class="text-muted mb-3"><i class="ti ti-clock"></i> <?= h($train_info['lich']) ?></p>
                    
                    <div class="border rounded p-3 mb-3 bg-light">
                        <ul class="list-unstyled mb-0">
                            <?php foreach($seats as $s): ?>
                            <li class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom last-no-border">
                                <div>
                                    <span class="badge bg-primary">Toa <?= h($s['ten_toa']) ?></span>
                                    <span class="fw-bold ms-1">Ghế <?= h($s['so_ghe']) ?></span>
                                </div>
                                <span class="fw-bold text-dark"><?= number_format($s['gia'], 0, ',', '.') ?> đ</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <span class="h5 mb-0 text-secondary">Tổng cộng:</span>
                        <span class="h3 text-danger mb-0 fw-bold"><?= number_format($total_price, 0, ',', '.') ?> đ</span>
                    </div>
                </div>
            </div>
            
            <div class="mt-4 text-center">
                <a href="javascript:history.back()" class="text-decoration-none text-secondary">
                    <i class="ti ti-arrow-left"></i> Quay lại chọn ghế
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>