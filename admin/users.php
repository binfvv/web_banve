<?php
require_once __DIR__ . '/_bootstrap.php';
$page_title = 'Quản lý Người dùng'; 
$page_h1 = 'Quản lý Người dùng'; 
$active = 'users';

// --- XỬ LÝ FORM ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $act = $_POST['act'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);

    // 1. CẬP NHẬT QUYỀN (ROLE)
    if ($act === 'role' && isset($_POST['role'])) {
        $role = ($_POST['role'] === 'admin') ? 'admin' : 'khach_hang';
        
        // Không cho phép tự hạ quyền admin của chính mình
        if ($id === (int)$_SESSION['user_id'] && $role !== 'admin') {
            $error = "Bạn không thể tự hạ quyền Admin của chính mình.";
        } else {
            $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role, $id]);
            $msg = "Đã cập nhật quyền thành công.";
        }
    } 
    // 2. XÓA NGƯỜI DÙNG
    elseif ($act === 'del') {
        if ($id === (int)$_SESSION['user_id']) {
            $error = "Không thể xóa tài khoản đang đăng nhập.";
        } else {
            try {
                // Chỉ thực hiện lệnh xóa, nếu DB có ràng buộc FK thì sẽ nhảy vào catch
                $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
                $msg = "Đã xóa người dùng thành công.";
            } catch (PDOException $e) {
                // Mã lỗi 23000 là lỗi ràng buộc khóa ngoại (Integrity constraint violation)
                if ($e->getCode() == '23000') {
                    $error = "<b>Không thể xóa:</b> Người dùng này đã có lịch sử đặt vé hoặc thanh toán. Dữ liệu cần được giữ lại để đối soát.";
                } else {
                    $error = "Lỗi hệ thống: " . $e->getMessage();
                }
            }
        }
    }
    
    if (empty($error)) {
        header('Location: users.php?msg='.urlencode($msg ?? ''));
        exit;
    }
}

// --- LỌC & TÌM KIẾM ---
$keyword = trim($_GET['q'] ?? '');

// SỬA LỖI Ở ĐÂY: Đã bỏ "created_at" ra khỏi câu lệnh SELECT
$sql = "SELECT id, username, email, role FROM users";
$params = [];

if ($keyword) {
    $sql .= " WHERE username LIKE ? OR email LIKE ?";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}
$sql .= " ORDER BY id DESC";

$rows = $pdo->prepare($sql);
$rows->execute($params);
$users = $rows->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/_layout_top.php';
?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="ti ti-alert-triangle"></i> <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="ti ti-check"></i> <?= h($_GET['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">Danh sách tài khoản</h5>
        
        <form method="get" class="d-flex" style="max-width: 300px;">
            <input type="text" name="q" class="form-control form-control-sm me-2" placeholder="Tìm tên hoặc email..." value="<?= h($keyword) ?>">
            <button class="btn btn-sm btn-primary"><i class="ti ti-search"></i></button>
            <?php if($keyword): ?>
                <a href="users.php" class="btn btn-sm btn-outline-secondary ms-1">Xóa lọc</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Tài khoản</th>
                        <th>Email</th>
                        <th>Quyền hạn (Role)</th>
                        <th class="text-end pe-3">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted">Không tìm thấy dữ liệu</td></tr>
                <?php else: ?>
                    <?php foreach($users as $u): ?>
                    <tr>
                        <td class="ps-3 text-muted">#<?= (int)$u['id'] ?></td>
                        <td class="fw-bold"><?= h($u['username']) ?></td>
                        <td><?= h($u['email']) ?></td>
                        <td>
                            <form method="post" class="d-inline">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="act" value="role">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <select name="role" class="form-select form-select-sm d-inline-block fw-bold 
                                    <?= $u['role'] === 'admin' ? 'text-danger border-danger bg-danger bg-opacity-10' : 'text-primary' ?>" 
                                    style="width:130px; cursor:pointer" 
                                    onchange="if(confirm('Đổi quyền user này?')) this.form.submit(); else this.value='<?= $u['role'] ?>'">
                                    <option value="khach_hang" <?= $u['role']==='khach_hang'?'selected':'' ?>>Khách hàng</option>
                                    <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
                                </select>
                            </form>
                        </td>
                        <td class="text-end pe-3">
                            <?php if((int)$u['id'] === (int)$_SESSION['user_id']): ?>
                                <span class="badge bg-secondary">Đang login</span>
                            <?php else: ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('CẢNH BÁO: Bạn có chắc muốn xóa user này không?\nHành động này không thể hoàn tác.')">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="act" value="del">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger border-0" title="Xóa">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>