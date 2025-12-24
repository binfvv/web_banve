<?php
require_once __DIR__ . '/_bootstrap.php';
$page_title = 'Quản lý Thanh toán'; 
$page_h1 = 'Quản lý Thanh toán'; 
$active = 'pay';

// --- XỬ LÝ FORM (Cập nhật trạng thái / Xóa) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $act = $_POST['act'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);

    if ($act === 'update_status') {
        $status = $_POST['trang_thai'] ?? 'cho_thanh_toan';
        
        // Cập nhật trạng thái đơn hàng
        $pdo->prepare("UPDATE thanh_toan SET trang_thai = ? WHERE id = ?")->execute([$status, $id]);

        // [NÂNG CAO] Nếu Hủy đơn -> Nhả ghế (chuyển ghế về trạng thái 'trong')
        // Logic này cần join bảng lich_su hoặc lưu ghế vào thanh_toan. 
        // Ở mức cơ bản quản lý đơn, ta chỉ update trạng thái đơn.
        
        $msg = "Đã cập nhật trạng thái đơn #$id thành công.";
    } 
    elseif ($act === 'delete') {
        // Xóa đơn hàng
        $pdo->prepare("DELETE FROM thanh_toan WHERE id = ?")->execute([$id]);
        $msg = "Đã xóa đơn hàng #$id.";
    }

    if (isset($msg)) {
        header('Location: thanh_toan.php?msg='.urlencode($msg));
        exit;
    }
}

// --- TÌM KIẾM & LỌC ---
$keyword = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM thanh_toan";
$params = [];

if ($keyword) {
    $sql .= " WHERE ma_don_hang LIKE ? OR ho_ten_khach LIKE ? OR sdt_khach LIKE ?";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

$sql .= " ORDER BY ngay_giao_dich DESC, id DESC";

$rows = $pdo->prepare($sql);
$rows->execute($params);
$orders = $rows->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/_layout_top.php';
?>

<?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="ti ti-check"></i> <?= h($_GET['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">Lịch sử giao dịch</h5>
        
        <form method="get" class="d-flex" style="max-width: 300px;">
            <input type="text" name="q" class="form-control form-control-sm me-2" placeholder="Mã đơn, tên, sđt..." value="<?= h($keyword) ?>">
            <button class="btn btn-sm btn-primary"><i class="ti ti-search"></i></button>
            <?php if($keyword): ?>
                <a href="thanh_toan.php" class="btn btn-sm btn-outline-secondary ms-1">Xóa lọc</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">#ID</th>
                        <th>Mã đơn</th>
                        <th>Khách hàng</th>
                        <th>Tổng tiền</th>
                        <th>Phương thức</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th class="text-end pe-3">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">Không tìm thấy đơn hàng nào</td></tr>
                <?php else: ?>
                    <?php foreach($orders as $r): ?>
                    <tr>
                        <td class="ps-3 text-muted"><?= (int)$r['id'] ?></td>
                        <td><span class="badge bg-light text-dark border"><?= h($r['ma_don_hang']) ?></span></td>
                        <td>
                            <div class="fw-bold"><?= h($r['ho_ten_khach']) ?></div>
                            <div class="small text-muted"><?= h($r['sdt_khach']) ?></div>
                        </td>
                        <td class="fw-bold text-danger"><?= number_format($r['tong_tien'], 0, ',', '.') ?> đ</td>
                        <td>
                            <?php if($r['phuong_thuc']=='qr_code'): ?>
                                <span class="badge bg-primary bg-opacity-10 text-primary"><i class="ti ti-qrcode"></i> QR Code</span>
                            <?php elseif($r['phuong_thuc']=='tien_mat'): ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="ti ti-cash"></i> Tiền mặt</span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark"><?= h($r['phuong_thuc']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($r['trang_thai'] === 'da_thanh_toan'): ?>
                                <span class="badge bg-success">Đã thanh toán</span>
                            <?php elseif($r['trang_thai'] === 'cho_thanh_toan'): ?>
                                <span class="badge bg-warning text-dark">Chờ xử lý</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Hủy / Khác</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted">
                            <?= date('d/m/Y', strtotime($r['ngay_giao_dich'])) ?><br>
                            <?= date('H:i', strtotime($r['ngay_giao_dich'])) ?>
                        </td>
                        <td class="text-end pe-3">
                            <button class="btn btn-sm btn-outline-primary border-0" data-bs-toggle="modal" data-bs-target="#modal<?= $r['id'] ?>" title="Xem chi tiết">
                                <i class="ti ti-eye"></i>
                            </button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa lịch sử đơn này?')">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="act" value="delete">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger border-0" title="Xóa"><i class="ti ti-trash"></i></button>
                            </form>
                        </td>
                    </tr>

                    <div class="modal fade" id="modal<?= $r['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Chi tiết đơn: <?= h($r['ma_don_hang']) ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <ul class="list-group list-group-flush mb-3">
                                        <li class="list-group-item d-flex justify-content-between">
                                            <span>Khách hàng:</span> <strong><?= h($r['ho_ten_khach']) ?></strong>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between">
                                            <span>SĐT:</span> <span><?= h($r['sdt_khach']) ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between">
                                            <span>Email:</span> <span><?= h($r['email_khach']) ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between">
                                            <span>CCCD:</span> <span><?= h($r['cccd_khach']) ?></span>
                                        </li>
                                        <li class="list-group-item">
                                            <span class="d-block mb-1">Ghi chú:</span>
                                            <div class="bg-light p-2 rounded small text-break"><?= nl2br(h($r['ghi_chu'])) ?: '<em>Không có</em>' ?></div>
                                        </li>
                                    </ul>
                                    
                                    <hr>
                                    <h6 class="mb-2">Cập nhật trạng thái:</h6>
                                    <form method="post" class="d-flex gap-2">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="act" value="update_status">
                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                        
                                        <select name="trang_thai" class="form-select">
                                            <option value="cho_thanh_toan" <?= $r['trang_thai']=='cho_thanh_toan'?'selected':'' ?>>Chờ thanh toán</option>
                                            <option value="da_thanh_toan" <?= $r['trang_thai']=='da_thanh_toan'?'selected':'' ?>>Đã thanh toán</option>
                                            <option value="huy" <?= $r['trang_thai']=='huy'?'selected':'' ?>>Hủy bỏ</option>
                                        </select>
                                        <button class="btn btn-primary">Lưu</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>