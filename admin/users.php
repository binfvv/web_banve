<?php
require_once __DIR__ . '/_bootstrap.php';
$page_title='Users'; $page_h1='Người dùng'; $page_breadcrumb='Users'; $active='users';

// Update role
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['act'], $_POST['id'])) {
  check_csrf();
  $id=(int)$_POST['id'];
  if ($_POST['act']==='role' && isset($_POST['role'])) {
    $role = $_POST['role']==='admin' ? 'admin' : 'khach_hang';
    $stm = $pdo->prepare("UPDATE users SET role=? WHERE id=?");
    $stm->execute([$role,$id]);
  } elseif ($_POST['act']==='del') {
    if ($id !== (int)$_SESSION['user_id']) { // không xóa chính mình
      // Lưu ý: nếu có FK, cần ON DELETE SET NULL/RESTRICT
      $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    }
  }
  header('Location: users.php'); exit;
}

$rows = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
include __DIR__.'/_layout_top.php';
?>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead><tr><th>#</th><th>Username</th><th>Email</th><th>Role</th><th>Tạo lúc</th><th>Thao tác</th></tr></thead>
        <tbody>
        <?php foreach($rows as $u): ?>
          <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><?= h($u['username']) ?></td>
            <td><?= h($u['email']) ?></td>
            <td>
              <form method="post" class="d-inline">
                <?php csrf_field(); ?>
                <input type="hidden" name="act" value="role">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <select name="role" class="form-select form-select-sm d-inline-block" style="width:140px" onchange="this.form.submit()">
                  <option value="khach_hang" <?= $u['role']==='khach_hang'?'selected':'' ?>>khach_hang</option>
                  <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>admin</option>
                </select>
              </form>
            </td>
            <td class="small text-muted"><?= h($u['created_at'] ?? '') ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Xóa user này?')">
                <?php csrf_field(); ?>
                <input type="hidden" name="act" value="del">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" <?= (int)$u['id']===(int)$_SESSION['user_id']?'disabled':'' ?>>Xóa</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__.'/_layout_bottom.php'; ?>
