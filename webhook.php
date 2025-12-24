<?php
// webhook.php - File này nhận thông báo từ SePay/Casso
require_once __DIR__ . '/includes/db.php';

// Nhận dữ liệu JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// SePay gửi về một mảng các giao dịch, thường nằm trong key 'transactions' hoặc gửi trực tiếp object tùy cấu hình
// Ví dụ xử lý đơn giản:
if (isset($data['content']) && isset($data['transferAmount'])) {
    
    $noi_dung_ck = $data['content']; // VD: "VETAU1709999 SEPAY..."
    $so_tien_ck  = $data['transferAmount'];

    // 1. Tìm mã đơn hàng trong nội dung chuyển khoản bằng Regex
    // Tìm chuỗi bắt đầu bằng VETAU theo sau là số
    if (preg_match('/VETAU\d+/', $noi_dung_ck, $matches)) {
        $ma_don_hang_tim_thay = $matches[0];

        // 2. Tìm đơn hàng trong Database
        $stmt = $pdo->prepare("SELECT * FROM thanh_toan WHERE ma_don_hang = ? AND trang_thai = 'cho_thanh_toan'");
        $stmt->execute([$ma_don_hang_tim_thay]);
        $order = $stmt->fetch();

        if ($order) {
            // 3. Kiểm tra số tiền (Nên cho phép sai số nhỏ hoặc yêu cầu chính xác)
            if ($so_tien_ck >= $order['tong_tien']) {
                
                // 4. UPDATE trạng thái thành công
                $update = $pdo->prepare("UPDATE thanh_toan SET trang_thai = 'da_thanh_toan' WHERE id = ?");
                $update->execute([$order['id']]);

                // 5. (Tùy chọn) Cập nhật trạng thái ghế trong bảng 'ghe' từ 'giu_cho' sang 'da_dat'
                // ... code update bảng ghe ...

                echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
                exit;
            }
        }
    }
}

echo json_encode(['success' => false, 'message' => 'Không xử lý được']);
?>