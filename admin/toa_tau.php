<?php
require_once __DIR__ . '/_bootstrap.php';
$page_title = 'Quản lý Toa & Ghế'; 
$page_h1 = 'Quản lý Toa & Ghế'; 
$active = 'toa';

// --- XỬ LÝ FORM ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $act = $_POST['act'] ?? '';

    // 1. THÊM TOA TÀU MỚI (Kèm sinh ghế tự động)
    if ($act === 'create') {
        $id_tau      = (int)($_POST['id_tau'] ?? 0);
        $ten_toa     = trim($_POST['ten_toa'] ?? 'Toa mới');
        $loai_toa    = $_POST['loai_toa'] ?? 'Ghế mềm điều hòa';
        $so_ghe      = (int)($_POST['so_ghe'] ?? 32);
        $gia_ve      = (int)($_POST['gia_ve'] ?? 0);
        
        // Tính toán thứ tự toa (Toa số mấy trong đoàn)
        $stmCount = $pdo->prepare("SELECT COUNT(*) FROM toa_tau WHERE id_tau = ?");
        $stmCount->execute([$id_tau]);
        $thu_tu = $stmCount->fetchColumn() + 1;

        try {
            $pdo->beginTransaction();

            // A. Insert Toa
            $sqlToa = "INSERT INTO toa_tau (id_tau, ten_toa, loai_toa, thu_tu, gia_tu, gia_den) VALUES (?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sqlToa)->execute([$id_tau, $ten_toa, $loai_toa, $thu_tu, $gia_ve, $gia_ve]);
            $id_toa = $pdo->lastInsertId();

            // B. Insert Ghế (Có tính Hàng/Cột)
            $sqlGhe = "INSERT INTO ghe (id_tau, id_toa, so_ghe, hang, cot, gia, trang_thai, co_ban) VALUES (?, ?, ?, ?, ?, ?, 'trong', 1)";
            $stmtGhe = $pdo->prepare($sqlGhe);

            for ($j = 1; $j <= $so_ghe; $j++) {
                $hang = ceil($j / 4);       // Hàng 1, 1, 1, 1, 2, 2...
                $cot  = ($j - 1) % 4 + 1;   // Cột 1, 2, 3, 4, 1, 2...
                $stmtGhe->execute([$id_tau, $id_toa, $j, $hang, $cot, $gia_ve]);
            }

            $pdo->commit();
            $msg = "Đã thêm $ten_toa với $so_ghe ghế thành công!";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = "Lỗi: " . $e->getMessage();
        }
        
        header('Location: toa_tau.php?msg='.urlencode($msg));
        exit;

    // 2. XÓA TOA TÀU (Xử lý FK an toàn)
    } elseif ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->beginTransaction();
            // Xóa lịch sử đặt vé của các ghế thuộc toa này trước
            $pdo->prepare("DELETE ls FROM lich_su_dat_ve ls JOIN ghe g ON ls.id_ghe = g.id WHERE g.id_toa = ?")->execute([$id]);
            // Xóa ghế
            $pdo->prepare("DELETE FROM ghe WHERE id_toa = ?")->execute([$id]);
            // Xóa toa
            $pdo->prepare("DELETE FROM toa_tau WHERE id = ?")->execute([$id]);
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            die("Lỗi không thể xóa: " . $e->getMessage());
        }
        header('Location: toa_tau.php'); 
        exit;
    }
}

// --- LẤY DỮ LIỆU HIỂN THỊ ---
// Lấy danh sách chuyến tàu để đổ vào select box
$chuyens = $pdo->query("SELECT id, ten_tau, ga_di, ga_den, ngay_di, gio_di FROM chuyen_tau ORDER BY ngay_di DESC, gio_di DESC")->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách Toa
$sqlGetToa = "
  SELECT tt.id, tt.id_tau, tt.ten_toa, tt.loai_toa, tt.gia_tu,
         ct.ten_tau, ct.ngay_di, ct.gio_di,
         (SELECT COUNT(*) FROM ghe g WHERE g.id_toa = tt.id) AS so_ghe
  FROM toa_tau tt
  JOIN chuyen_tau ct ON ct.id = tt.id_tau
  ORDER BY ct.ngay_di DESC, ct.id DESC, tt.thu_tu ASC
";
$toas = $pdo->query($sqlGetToa)->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/_layout_top.php';
?>

<?php if (isset($_GET['msg'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <?= h($_GET['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-primary"><i class="ti ti-plus"></i> Thêm toa tàu vào chuyến có sẵn</h5>
    </div>
    <div class="card-body">
        <form method="post" class="row g-3">
            <?php csrf_field(); ?>
            <input type="hidden" name="act" value="create">
            
            <div class="col-md-4">
                <label class="form-label fw-bold">Chọn chuyến tàu</label>
                <select class="form-select" name="id_tau" required>
                    <?php foreach($chuyens as $c): ?>
                    <option value="<?= (int)$c['id'] ?>">
                        #<?= (int)$c['id'] ?>: <?= h($c['ten_tau']) ?> (<?= h($c['ga_di']) ?> - <?= h($c['ga_den']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold">Tên toa</label>
                <input type="text" class="form-control" name="ten_toa" placeholder="VD: Toa 4" required>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold">Loại toa</label>
                <select class="form-select" name="loai_toa">
                    <option value="Ghế mềm điều hòa">Ghế mềm điều hòa</option>
                    <option value="Giường nằm khoang 4">Giường nằm khoang 4</option>
                    <option value="Giường nằm VIP">Giường nằm VIP</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label fw-bold">Số ghế</label>
                <input type="number" class="form-control" name="so_ghe" value="32" min="1" max="100" required>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold">Giá vé toa này (VNĐ)</label>
                <input type="number" class="form-control" name="gia_ve" value="100000" min="0" required>
            </div>

            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100 fw-bold"><i class="ti ti-wand"></i> Sinh dữ liệu</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0"><i class="ti ti-list"></i> Danh sách Toa hiện có</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Thuộc chuyến</th>
                        <th>Tên toa</th>
                        <th>Loại & Giá</th>
                        <th>Số ghế</th>
                        <th class="text-end pe-3">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($toas as $t): ?>
                    <tr>
                        <td class="ps-3 text-muted">#<?= (int)$t['id'] ?></td>
                        <td>
                            <div class="fw-bold text-primary"><?= h($t['ten_tau']) ?></div>
                            <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($t['ngay_di'].' '.$t['gio_di'])) ?></div>
                        </td>
                        <td>
                            <span class="fw-bold"><?= h($t['ten_toa']) ?></span>
                        </td>
                        <td>
                            <div class="small"><?= h($t['loai_toa']) ?></div>
                            <div class="fw-bold text-success"><?= number_format($t['gia_tu'], 0, ',', '.') ?> đ</div>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?= (int)$t['so_ghe'] ?> ghế</span>
                        </td>
                        <td class="text-end pe-3">
                            <form method="post" class="d-inline" onsubmit="return confirm('CẢNH BÁO: Xóa toa sẽ xóa tất cả ghế và lịch sử đặt vé của toa này.\nBạn chắc chắn chứ?')">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="act" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="ti ti-trash"></i> Xóa</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>