<?php
require_once __DIR__ . '/_bootstrap.php';
$page_title='Chuyến tàu'; $page_h1='Quản lý Chuyến tàu'; $page_breadcrumb='Chuyến tàu'; $active='chuyen';

// Create/Update/Delete
if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  $act = $_POST['act'] ?? '';
  if ($act==='create' || $act==='update') {
    $id      = (int)($_POST['id'] ?? 0);
    $ten_tau = trim($_POST['ten_tau'] ?? '');
    $ga_di   = trim($_POST['ga_di'] ?? '');
    $ga_den  = trim($_POST['ga_den'] ?? '');
    $ngay_di = $_POST['ngay_di'] ?? '';
    $gio_di  = $_POST['gio_di'] ?? '';
    $ngay_den= $_POST['ngay_den'] ?? '';
    $gio_den = $_POST['gio_den'] ?? '';
    $gia_ve  = (int)($_POST['gia_ve'] ?? 0);

    if ($act==='create') {
      $sql = "INSERT INTO chuyen_tau (ten_tau,ga_di,ga_den,ngay_di,gio_di,ngay_den,gio_den,gia_ve)
              VALUES (?,?,?,?,?,?,?,?)";
      $pdo->prepare($sql)->execute([$ten_tau,$ga_di,$ga_den,$ngay_di,$gio_di,$ngay_den,$gio_den,$gia_ve]);
    } else {
      $sql = "UPDATE chuyen_tau SET ten_tau=?,ga_di=?,ga_den=?,ngay_di=?,gio_di=?,ngay_den=?,gio_den=?,gia_ve=? WHERE id=?";
      $pdo->prepare($sql)->execute([$ten_tau,$ga_di,$ga_den,$ngay_di,$gio_di,$ngay_den,$gio_den,$gia_ve,$id]);
    }
  } elseif ($act==='delete') {
    $id=(int)($_POST['id'] ?? 0);
    // cảnh báo: có liên kết với ghe/thanh_toan → cần cân nhắc FK
    $pdo->prepare("DELETE FROM chuyen_tau WHERE id=?")->execute([$id]);
  }
  header('Location: chuyen_tau.php'); exit;
}

$rows = $pdo->query("SELECT * FROM chuyen_tau ORDER BY ngay_di DESC, gio_di DESC")->fetchAll(PDO::FETCH_ASSOC);
include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Chuyến tàu</h3>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEdit" onclick="fillForm()">+ Thêm chuyến</button>
</div>

<div class="card shadow-sm"><div class="card-body">
  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead><tr>
        <th>#</th><th>Tên tàu</th><th>Tuyến</th><th>Khởi hành</th><th>Đến</th><th>Giá vé</th><th style="width:160px">Thao tác</th>
      </tr></thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= h($r['ten_tau']) ?></td>
            <td><?= h($r['ga_di']) ?> → <?= h($r['ga_den']) ?></td>
            <td><?= h($r['ngay_di']) ?> <div class="small text-muted"><?= h($r['gio_di']) ?></div></td>
            <td><?= h($r['ngay_den']) ?> <div class="small text-muted"><?= h($r['gio_den']) ?></div></td>
            <td class="fw-semibold"><?= money_vi($r['gia_ve']) ?></td>
            <td>
              <button class="btn btn-sm btn-outline-primary me-1"
                      data-bs-toggle="modal" data-bs-target="#modalEdit"
                      onclick='fillForm(<?= json_encode($r, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>)'>Sửa</button>
              <form method="post" class="d-inline" onsubmit="return confirm('Xóa chuyến này?')">
                <?php csrf_field(); ?>
                <input type="hidden" name="act" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Xóa</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div></div>

<!-- Modal create/update -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post" class="needs-validation" novalidate>
      <div class="modal-header"><h5 class="modal-title">Chuyến tàu</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <?php csrf_field(); ?>
        <input type="hidden" name="act" id="fAct" value="create">
        <input type="hidden" name="id" id="fId">
        <div class="mb-2"><label class="form-label">Tên tàu</label><input class="form-control" name="ten_tau" id="fTen" required></div>
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label">Ga đi</label><input class="form-control" name="ga_di" id="fDi" required></div>
          <div class="col-md-6"><label class="form-label">Ga đến</label><input class="form-control" name="ga_den" id="fDen" required></div>
        </div>
        <div class="row g-2 mt-1">
          <div class="col-md-6"><label class="form-label">Ngày đi</label><input type="date" class="form-control" name="ngay_di" id="fNgDi" required></div>
          <div class="col-md-6"><label class="form-label">Giờ đi</label><input type="time" class="form-control" name="gio_di" id="fGioDi" required></div>
        </div>
        <div class="row g-2 mt-1">
          <div class="col-md-6"><label class="form-label">Ngày đến</label><input type="date" class="form-control" name="ngay_den" id="fNgDen" required></div>
          <div class="col-md-6"><label class="form-label">Giờ đến</label><input type="time" class="form-control" name="gio_den" id="fGioDen" required></div>
        </div>
        <div class="mt-2"><label class="form-label">Giá vé (đ)</label><input type="number" class="form-control" name="gia_ve" id="fGia" required></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Hủy</button>
        <button class="btn btn-primary">Lưu</button>
      </div>
    </form>
  </div></div>
</div>

<script>
function fillForm(row){
  document.getElementById('fAct').value = row ? 'update' : 'create';
  document.getElementById('fId').value  = row?.id ?? '';
  document.getElementById('fTen').value = row?.ten_tau ?? '';
  document.getElementById('fDi').value  = row?.ga_di ?? '';
  document.getElementById('fDen').value = row?.ga_den ?? '';
  document.getElementById('fNgDi').value= row?.ngay_di ?? '';
  document.getElementById('fGioDi').value= row?.gio_di ?? '';
  document.getElementById('fNgDen').value= row?.ngay_den ?? '';
  document.getElementById('fGioDen').value= row?.gio_den ?? '';
  document.getElementById('fGia').value = row?.gia_ve ?? '';
}
</script>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
