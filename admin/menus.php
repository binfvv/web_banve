<?php
require_once __DIR__ . '/_bootstrap.php';
$page_title='Menus'; $page_h1='Menu website'; $page_breadcrumb='Menus'; $active='menus';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  $act = $_POST['act'] ?? '';
  if ($act==='create' || $act==='update') {
    $id = (int)($_POST['id'] ?? 0);
    $location = $_POST['location']==='footer' ? 'footer' : 'header';
    $label = trim($_POST['label'] ?? '');
    $url   = trim($_POST['url'] ?? '');
    $pos   = (int)($_POST['position'] ?? 0);
    $vis   = isset($_POST['visible']) ? 1 : 0;
    if ($act==='create') {
      $pdo->prepare("INSERT INTO menus (location, label, url, position, visible) VALUES (?,?,?,?,?)")
          ->execute([$location,$label,$url,$pos,$vis]);
    } else {
      $pdo->prepare("UPDATE menus SET location=?, label=?, url=?, position=?, visible=? WHERE id=?")
          ->execute([$location,$label,$url,$pos,$vis,$id]);
    }
  } elseif ($act==='delete') {
    $id=(int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM menus WHERE id=?")->execute([$id]);
  }
  header('Location: menus.php'); exit;
}

$rows = $pdo->query("SELECT * FROM menus ORDER BY location, position, id")->fetchAll(PDO::FETCH_ASSOC);
include __DIR__.'/_layout_top.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Menus</h3>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalMenu" onclick="fillMenu()">+ Thêm menu</button>
</div>

<div class="card shadow-sm"><div class="card-body">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>#</th><th>Vị trí</th><th>Nhãn</th><th>URL</th><th>Thứ tự</th><th>Hiển thị</th><th>Thao tác</th></tr></thead>
      <tbody>
        <?php foreach($rows as $m): ?>
          <tr>
            <td><?= (int)$m['id'] ?></td>
            <td><?= h($m['location']) ?></td>
            <td><?= h($m['label']) ?></td>
            <td><code><?= h($m['url']) ?></code></td>
            <td><?= (int)$m['position'] ?></td>
            <td><?= $m['visible']?'✅':'🚫' ?></td>
            <td>
              <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#modalMenu"
                onclick='fillMenu(<?= json_encode($m, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>)'>Sửa</button>
              <form method="post" class="d-inline" onsubmit="return confirm('Xóa menu này?')">
                <?php csrf_field(); ?>
                <input type="hidden" name="act" value="delete">
                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Xóa</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div></div>

<div class="modal fade" id="modalMenu" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <div class="modal-header"><h5 class="modal-title">Menu</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <?php csrf_field(); ?>
        <input type="hidden" name="act" id="mAct" value="create">
        <input type="hidden" name="id" id="mId">
        <div class="row g-2">
          <div class="col-md-4"><label class="form-label">Vị trí</label>
            <select class="form-select" name="location" id="mLoc">
              <option value="header">header</option>
              <option value="footer">footer</option>
            </select>
          </div>
          <div class="col-md-5"><label class="form-label">Nhãn</label><input class="form-control" name="label" id="mLabel" required></div>
          <div class="col-md-3"><label class="form-label">Thứ tự</label><input type="number" class="form-control" name="position" id="mPos" value="0"></div>
          <div class="col-12"><label class="form-label">URL</label><input class="form-control" name="url" id="mUrl" placeholder="/index.php" required></div>
          <div class="col-12 form-check mt-2">
            <input class="form-check-input" type="checkbox" name="visible" id="mVis" checked>
            <label class="form-check-label" for="mVis">Hiển thị</label>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Hủy</button><button class="btn btn-primary">Lưu</button></div>
    </form>
  </div></div>
</div>

<script>
function fillMenu(row){
  document.getElementById('mAct').value = row ? 'update' : 'create';
  document.getElementById('mId').value  = row?.id ?? '';
  document.getElementById('mLoc').value = row?.location ?? 'header';
  document.getElementById('mLabel').value = row?.label ?? '';
  document.getElementById('mUrl').value   = row?.url ?? '';
  document.getElementById('mPos').value   = row?.position ?? 0;
  document.getElementById('mVis').checked = (row?.visible ?? 1) ? true : false;
}
</script>
<?php include __DIR__.'/_layout_bottom.php'; ?>
