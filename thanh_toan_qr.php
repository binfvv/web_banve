<?php
// thanh_toan_qr.php
session_start();

// --- CẤU HÌNH TÀI KHOẢN NHẬN TIỀN ---
$BANK_ID = 'VCB';       // Mã ngân hàng (VD: MB, VCB, ACB, TPB...)
$ACCOUNT_NO = '1018028736'; // Số tài khoản của bạn
$ACCOUNT_NAME = 'DOAN DUC BINH'; // Tên chủ tài khoản (tùy chọn hiển thị)
// -------------------------------------

// Kết nối DB
$db_paths = [__DIR__.'/assets/includes/db.php', __DIR__.'/includes/db.php', __DIR__.'/db.php'];
foreach($db_paths as $p) if(file_exists($p)){ require_once $p; break; }

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$ma_don = $_GET['ma_don'] ?? '';
if(!$ma_don) { header('Location: index.php'); exit; }

// 1. Lấy thông tin đơn hàng
$stm = $pdo->prepare("SELECT * FROM thanh_toan WHERE ma_don_hang = :ma");
$stm->execute([':ma' => $ma_don]);
$order = $stm->fetch(PDO::FETCH_ASSOC);

if (!$order) die("Đơn hàng không tồn tại.");
if ($order['trang_thai'] === 'da_thanh_toan') {
    header("Location: dat_ve_thanh_cong.php?ma_don=$ma_don");
    exit;
}

// 2. Xử lý khi bấm "Xác nhận đã thanh toán"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A. Cập nhật đơn hàng -> 'da_thanh_toan'
    $pdo->prepare("UPDATE thanh_toan SET trang_thai = 'da_thanh_toan' WHERE ma_don_hang = :ma")
        ->execute([':ma' => $ma_don]);
    
    // B. Cập nhật ghế -> 'da_dat' (chính thức)
    // Cần tìm các ghế thuộc đơn hàng này (Dựa vào user_id và trạng thái giu_cho, 
    // hoặc query bảng lich_su_dat_ve nếu cấu trúc DB cho phép join. 
    // Ở đây ta dùng user_id và thời gian gần nhất hoặc logic đơn giản cập nhật ghế đang giữ chỗ của user này)
    
    // Cách an toàn nhất: Lấy danh sách ghế đang 'giu_cho' của user này trong lich_su_dat_ve
    // Tuy nhiên để đơn giản theo DB hiện tại:
    // Update tất cả các ghế đang 'giu_cho' của user này thành 'da_dat'
    $pdo->prepare("UPDATE ghe SET trang_thai = 'da_dat' WHERE user_id = :uid AND trang_thai = 'giu_cho'")
        ->execute([':uid' => $order['user_id']]);

    // C. Chuyển hướng thành công
    header("Location: dat_ve_thanh_cong.php?ma_don=$ma_don");
    exit;
}

// 3. Xử lý Hủy đơn (Release ghế)
if (isset($_GET['action']) && $_GET['action'] === 'cancel') {
    $pdo->prepare("UPDATE ghe SET trang_thai = 'trong', user_id = 0 WHERE user_id = :uid AND trang_thai = 'giu_cho'")
        ->execute([':uid' => $order['user_id']]);
    $pdo->prepare("DELETE FROM thanh_toan WHERE ma_don_hang = :ma")->execute([':ma' => $ma_don]);
    header("Location: index.php");
    exit;
}

// Tạo link QR VietQR
// Format: https://img.vietqr.io/image/<BANK>-<ACC>-<TEMPLATE>.png?amount=<AMT>&addInfo=<CONTENT>
$qr_url = "https://img.vietqr.io/image/{$BANK_ID}-{$ACCOUNT_NO}-compact2.png?amount={$order['tong_tien']}&addInfo={$ma_don}&accountName={$ACCOUNT_NAME}";
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thanh toán QR - <?= h($ma_don) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
    <style>body{background:#f0f2f5; height: 100vh; display: flex; align-items: center; justify-content: center;}</style>
</head>
<body>

<div class="container" style="max-width: 500px;">
    <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-primary text-white text-center py-3">
            <h5 class="mb-0 fw-bold">THANH TOÁN ĐƠN HÀNG</h5>
            <div class="small opacity-75"><?= h($ma_don) ?></div>
        </div>
        <div class="card-body text-center p-4">
            
            <p class="text-muted mb-2">Quét mã QR để thanh toán</p>
            <h3 class="text-primary fw-bold mb-4"><?= number_format($order['tong_tien'], 0, ',', '.') ?> VNĐ</h3>

            <div class="border p-2 rounded-3 d-inline-block mb-4 shadow-sm">
                <img src="<?= $qr_url ?>" alt="QR Code" class="img-fluid" style="max-width: 250px;">
            </div>

            <div class="alert alert-info small text-start">
                <i class="ti ti-info-circle"></i> <strong>Hướng dẫn:</strong><br>
                1. Mở App ngân hàng, chọn Quét QR.<br>
                2. Quét mã trên. Nội dung và số tiền đã được nhập tự động.<br>
                3. Sau khi chuyển khoản thành công, bấm nút xác nhận bên dưới.
            </div>

            <form method="POST">
                <button type="submit" class="btn btn-success w-100 py-3 fw-bold shadow">
                    <i class="ti ti-check"></i> TÔI ĐÃ CHUYỂN KHOẢN
                </button>
            </form>

            <div class="mt-3">
                <a href="?ma_don=<?= $ma_don ?>&action=cancel" class="text-muted text-decoration-none small" onclick="return confirm('Bạn có chắc muốn hủy đơn và chọn lại ghế?')">
                    Hủy đơn hàng & Quay lại
                </a>
            </div>
        </div>
    </div>
</div>

</body>
</html>