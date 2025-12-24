<?php
require_once __DIR__ . '/_bootstrap.php';
$page_title = 'Quản lý Vé đã đặt'; 
$page_h1 = 'Danh sách Vé đã đặt'; 
$page_breadcrumb = 'Vé đã đặt'; 
$active = 'tickets';

// --- XỬ LÝ: HỦY VÉ (TRẢ GHẾ) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $act = $_POST['act'] ?? '';
    
    if ($act === 'cancel_ticket') {
        $ghe_id = (int)($_POST['ghe_id'] ?? 0);
        
        // Trả ghế về trạng thái 'trong' và xóa user_id
        $stm = $pdo->prepare("UPDATE ghe SET trang_thai = 'trong', user_id = 0 WHERE id = ?");
        $stm->execute([$ghe_id]);
        
        // (Tùy chọn) Xóa lịch sử đặt vé liên quan nếu muốn sạch data
        // $pdo->prepare("DELETE FROM lich_su_dat_ve WHERE id_ghe = ?")->execute([$ghe_id]);

        $msg = "Đã hủy vé (ghế ID: $ghe_id) thành công. Ghế đã mở lại.";
        header('Location: ve_da_dat.php?msg='.urlencode($msg));
        exit;
    }
}

// --- LỌC DỮ LIỆU ---
$filter_chuyen = (int)($_GET['chuyen'] ?? 0);
$keyword       = trim($_GET['q'] ?? '');

$params = [];
$sql = "
    SELECT 
        g.id AS ghe_id, g.so_ghe, g.user_id,
        ct.id AS ma_chuyen, ct.ten_tau, ct.ga_di, ct.ga_den, ct.ngay_di, ct.gio_di,
        COALESCE(g.gia, ct.gia_ve) AS gia_ve,
        tt.ten_toa,
        u.username, u.email, u.role
    FROM ghe g
    JOIN chuyen_tau ct ON ct.id = g.id_tau
    LEFT JOIN toa_tau tt ON tt.id = g.id_toa
    LEFT JOIN users u ON u.id = g.user_id
    WHERE g.trang_thai = 'da_dat'
";

// Lọc theo chuyến
if ($filter_chuyen > 0) { 
    $sql .= " AND ct.id = :cid"; 
    $params[':cid'] = $filter_chuyen; 
}

// Lọc theo tên người dùng hoặc số ghế
if ($keyword) {
    $sql .= " AND (u.username LIKE :kw OR u.email LIKE :kw OR g.so_ghe LIKE :kw)";
    $params[':kw'] = "%$keyword%";
}

$sql .= " ORDER BY ct.ngay_di DESC, ct.gio_di DESC, g.id DESC";

$stm = $pdo->prepare($sql);
$stm->execute($params);
$rows = $stm->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách chuyến để nạp vào Select box
$chuyens = $pdo->query("SELECT id, ten_tau, ga_di, ga_den, ngay_di, gio_di FROM chuyen_tau ORDER BY ngay_di DESC, gio_di DESC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__.'/_layout_top.php';
?>

<?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="ti ti-check"></i> <?= h($_GET['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form class="row g-3" method="GET">
            <div class="col-md-5">
                <label class="form-label fw-bold">Lọc theo chuyến tàu</label>
                <select class="form-select" name="chuyen" onchange="this.form.submit()">
                    <option value="0">— Tất cả các chuyến —</option>
                    <?php foreach($chuyens as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $filter_chuyen===(int)$c['id']?'selected':'' ?>>
                            #<?= (int)$c['id'] ?> — <?= h($c['ten_tau']) ?> (<?= h($c['ga_di']) ?> → <?= h($c['ga_den']) ?>) - <?= date('d/m/Y', strtotime($c['ngay_di'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label fw-bold">Tìm kiếm</label>
                <input type="text" class="form-control" name="q" value="<?= h($keyword) ?>" placeholder="Nhập số ghế, tên username hoặc email...">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100"><i class="ti ti-filter"></i> Lọc</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Ghế / Toa</th>
                        <th>Thông tin Khách hàng</th>
                        <th>Chuyến tàu & Lộ trình</th>
                        <th>Thời gian</th>
                        <th>Giá vé</th>
                        <th class="text-end pe-3">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($rows)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">Chưa có vé nào được đặt.</td></tr>
                <?php else: ?>
                    <?php foreach($rows as $r): ?>
                    <tr>
                        <td class="ps-3">
                            <div class="fw-bold text-primary fs-5"><?= h($r['so_ghe']) ?></div>
                            <span class="badge bg-secondary"><?= h($r['ten_toa'] ?? 'N/A') ?></span>
                        </td>
                        <td>
                            <?php if ($r['user_id'] > 0): ?>
                                <div class="fw-bold"><i class="ti ti-user"></i> <?= h($r['username']) ?></div>
                                <div class="small text-muted"><?= h($r['email']) ?></div>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Khách vãng lai / Tại quầy</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-bold"><?= h($r['ten_tau']) ?> <small class="text-muted">(#<?= $r['ma_chuyen'] ?>)</small></div>
                            <div class="small">
                                <?= h($r['ga_di']) ?> <i class="ti ti-arrow-right small"></i> <?= h($r['ga_den']) ?>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold"><?= date('d/m/Y', strtotime($r['ngay_di'])) ?></div>
                            <div class="small text-muted"><?= date('H:i', strtotime($r['gio_di'])) ?></div>
                        </td>
                        <td class="fw-bold text-success"><?= money_vi($r['gia_ve']) ?></td>
                        <td class="text-end pe-3">
                            <form method="post" onsubmit="return confirm('CẢNH BÁO: Bạn có chắc muốn HỦY vé này?\nGhế sẽ chuyển sang trạng thái TRỐNG để người khác mua.')">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="act" value="cancel_ticket">
                                <input type="hidden" name="ghe_id" value="<?= $r['ghe_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Hủy vé / Trả ghế">
                                    <i class="ti ti-x"></i> Hủy vé
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__.'/_layout_bottom.php'; ?>