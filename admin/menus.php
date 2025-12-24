<?php
require_once __DIR__ . '/_bootstrap.php';
$page_title = 'Quản lý Menu'; 
$page_h1 = 'Cấu hình Menu'; 
$active = 'menus';

// --- XỬ LÝ FORM ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $act = $_POST['act'] ?? '';
    $msg = '';
    $error = '';

    try {
        if ($act === 'create' || $act === 'update') {
            $id       = (int)($_POST['id'] ?? 0);
            $location = ($_POST['location'] === 'footer') ? 'footer' : 'header';
            $label    = trim($_POST['label'] ?? '');
            $url      = trim($_POST['url'] ?? '');
            $pos      = (int)($_POST['position'] ?? 0);
            $vis      = isset($_POST['visible']) ? 1 : 0;

            if ($label === '') throw new Exception("Tên menu không được để trống.");
            if ($url === '')   throw new Exception("Đường dẫn (URL) không được để trống.");

            if ($act === 'create') {
                $sql = "INSERT INTO menus (location, label, url, position, visible) VALUES (?,?,?,?,?)";
                $pdo->prepare($sql)->execute([$location, $label, $url, $pos, $vis]);
                $msg = "Thêm menu mới thành công.";
            } else {
                $sql = "UPDATE menus SET location=?, label=?, url=?, position=?, visible=? WHERE id=?";
                $pdo->prepare($sql)->execute([$location, $label, $url, $pos, $vis, $id]);
                $msg = "Cập nhật menu thành công.";
            }
        } 
        elseif ($act === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM menus WHERE id=?")->execute([$id]);
            $msg = "Đã xóa menu.";
        }
    } catch (Exception $e) {
        $error = "Lỗi: " . $e->getMessage();
    }

    // Redirect để tránh resubmit form
    $redirectUrl = 'menus.php';
    if ($msg) $redirectUrl .= '?msg=' . urlencode($msg);
    if ($error) $redirectUrl .= '?err=' . urlencode($error);
    header("Location: $redirectUrl");
    exit;
}

// --- LẤY DỮ LIỆU ---
$rows = $pdo->query("SELECT * FROM menus ORDER BY location DESC, position ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/_layout_top.php';
?>

<?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="ti ti-check"></i> <?= h($_GET['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($_GET['err'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="ti ti-alert-triangle"></i> <?= h($_GET['err']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Quản lý Menu</h3>
        <small class="text-muted">Điều chỉnh thanh điều hướng và chân trang</small>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalMenu" onclick="fillMenu()">
        <i class="ti ti-plus"></i> Thêm menu
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Vị trí</th>
                        <th>Nhãn hiển thị</th>
                        <th>Đường dẫn (URL)</th>
                        <th class="text-center">Thứ tự</th>
                        <th class="text-center">Trạng thái</th>
                        <th class="text-end pe-3">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($rows)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">Chưa có menu nào.</td></tr>
                <?php else: ?>
                    <?php foreach($rows as $m): ?>
                    <tr>
                        <td class="ps-3">
                            <?php if($m['location'] === 'header'): ?>
                                <span class="badge bg-primary">Header</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Footer</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold"><?= h($m['label']) ?></td>
                        <td>
                            <code class="text-primary bg-light px-2 py-1 rounded"><?= h($m['url']) ?></code>
                        </td>
                        <td class="text-center"><?= (int)$m['position'] ?></td>
                        <td class="text-center">
                            <?php if($m['visible']): ?>
                                <span class="badge bg-success bg-opacity-10 text-success"><i class="ti ti-eye"></i> Hiện</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="ti ti-eye-off"></i> Ẩn</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-3">
                            <button class="btn btn-sm btn-light text-primary me-1" 
                                    data-bs-toggle="modal" data-bs-target="#modalMenu"
                                    onclick='fillMenu(<?= json_encode($m, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>)'>
                                <i class="ti ti-edit"></i> Sửa
                            </button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa menu này không?')">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="act" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                <button class="btn btn-sm btn-light text-danger"><i class="ti ti-trash"></i></button>
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

<div class="modal fade" id="modalMenu" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Thêm menu mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="act" id="mAct" value="create">
                    <input type="hidden" name="id" id="mId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Vị trí hiển thị</label>
                        <select class="form-select" name="location" id="mLoc">
                            <option value="header">Header (Thanh điều hướng trên cùng)</option>
                            <option value="footer">Footer (Chân trang)</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Tên nhãn (Label)</label>
                            <input class="form-control" name="label" id="mLabel" placeholder="VD: Trang chủ" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Thứ tự</label>
                            <input type="number" class="form-control" name="position" id="mPos" value="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Đường dẫn (URL)</label>
                        <input class="form-control" name="url" id="mUrl" placeholder="VD: index.php hoặc https://..." required>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="visible" id="mVis" checked>
                        <label class="form-check-label" for="mVis">Hiển thị công khai</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function fillMenu(row){
    const isEdit = !!row;
    document.getElementById('modalTitle').innerText = isEdit ? 'Cập nhật Menu' : 'Thêm menu mới';
    document.getElementById('mAct').value   = isEdit ? 'update' : 'create';
    document.getElementById('mId').value    = row?.id ?? '';
    document.getElementById('mLoc').value   = row?.location ?? 'header';
    document.getElementById('mLabel').value = row?.label ?? '';
    document.getElementById('mUrl').value   = row?.url ?? '';
    document.getElementById('mPos').value   = row?.position ?? 0;
    document.getElementById('mVis').checked = (row?.visible == 0) ? false : true;
}
</script>

<?php include __DIR__ . '/_layout_bottom.php'; ?>