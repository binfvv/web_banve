<?php
require_once __DIR__ . '/_bootstrap.php';
$page_title='Toa & Ghế'; $page_h1='Toa & Ghế'; $page_breadcrumb='Toa & Ghế'; $active='toa';

// Sinh ghế hàng loạt cho một chuyến: nhập số toa & số ghế / toa
if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  $act = $_POST['act'] ?? '';
  if ($act==='gen') {
    $id_tau = (int)($_POST['id_tau'] ?? 0);
    $so_toa = max(1, (int)($_POST['so_toa'] ?? 1));
    $ghe_moi_toa = max(1, (int)($_POST['ghe_moi_toa'] ?? 1));

    // tạo toa_tau và ghế nếu chưa tồn tại
    $pdo->beginTransaction();
    try {
      for ($t=1; $t <= $so_toa; $t++) {
        // tạo toa
        $stm = $pdo->prepare("INSERT INTO toa_tau (id_tau, ten_toa) VALUES (?, ?)");
        $ten_toa = "Toa $t";
        $stm->execute([$id_tau, $ten_toa]);
        $id_toa = (int)$pdo->lastInsertId();

        // tạo ghế
        $ins = $pdo->prepare("INSERT INTO ghe (id_tau, id_toa, so_ghe, trang_thai) VALUES (?, ?, ?, 'trong')");
        for ($g=1; $g <= $ghe_moi_toa; $g++) {
          $ins->execute([$id_tau, $id_toa, "T{$t}-".str_pad($g,2,'0',STR_PAD_LEFT)]);
        }
      }
      $pdo->commit();
      $msg = "Đã sinh $so_toa toa x $ghe_moi_toa ghế/toa cho chuyến #$id_tau";
    } catch(Throwable $e){
      if ($pdo->inTransaction()) $pdo->rollBack();
      $msg = "Lỗi: " . $e->getMessage();
    }
    header('Location: toa_tau.php?msg='.urlencode($msg)); exit;
  } elseif ($act==='del_toa') {
    $id = (int)($_POST['id'] ?? 0);
    // xóa ghế thuộc toa rồi xóa toa
    $pdo->prepare("DELETE FROM ghe WHERE id_toa=?")->execute([$id]);
    $pdo->prepare("DELETE FROM toa_tau WHERE id=?")->execute([$id]);
    header('Location: toa_tau.php'); exit;
  }
}

// dữ liệu
$chuyens = $pdo->query("SELECT id, ten_tau, ga_di, ga_den, ngay_di, gio_di FROM chuyen_tau ORDER BY ngay_di DESC, gio_di DESC")->fetchAll(PDO::FETCH_ASSOC);
$toas = $pdo->query("
  SELECT tt.id, tt.id_tau, tt.ten_toa, ct.ten_tau, ct.ngay_di, ct.gio_di,
         (SELECT COUNT(*) FROM ghe g WHERE g.id_toa = tt.id) AS so_ghe
  FROM toa_tau tt
  JOIN chuyen_tau ct ON ct.id = tt.id_tau
  ORDER BY tt.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/_layout_top.php';
?>
<?php if (isset($_GET['msg'])): ?>
  <div class="alert alert-info"><?= h($_GET['msg']) ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-3"><div class="card-body">
  <h5 class="mb-3">Sinh ghế hàng loạt cho chuyến</h5>
  <form method="post" class="row g-2 align-items-end">
    <?php csrf_field(); ?>
    <input type="hidden" name="act" value="gen">
    <div class="col-md-5">
      <label class="form-label">Chọn chuyến</label>
      <select class="form-select" name="id_tau" required>
        <?php foreach($chuyens as $c): ?>
          <option value="<?= (int)$c['id'] ?>">#<?= (int)$c['id'] ?> — <?= h($c['ten_tau']) ?> (<?= h($c['ga_di']) ?>→<?= h($c['ga_den']) ?>, <?= h($c['ngay_di'].' '.$c['gio_di']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Số toa</label>
      <input type="number" class="form-control" name="so_toa" value="4" min="1" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Ghế / toa</label>
      <input type="number" class="form-control" name="ghe_moi_toa" value="20" min="1" required>
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100">Sinh ghế</button>
    </div>
  </form>
</div></div>

<div class="card shadow-sm"><div class="card-body">
  <h5 class="mb-3">Danh sách Toa</h5>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>#</th><th>Chuyến</th><th>Toa</th><th>Ghế</th><th>Thao tác</th></tr></thead>
      <tbody>
        <?php foreach($toas as $t): ?>
          <tr>
            <td><?= (int)$t['id'] ?></td>
            <td>#<?= (int)$t['id_tau'] ?> — <?= h($t['ten_tau']) ?><div class="small text-muted"><?= h($t['ngay_di'].' '.$t['gio_di']) ?></div></td>
            <td><?= h($t['ten_toa']) ?></td>
            <td><?= (int)$t['so_ghe'] ?></td>
            <td>
              <form method="post" class="d-inline" onsubmit="return confirm('Xóa toa này và toàn bộ ghế thuộc toa?')">
                <?php csrf_field(); ?>
                <input type="hidden" name="act" value="del_toa">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Xóa toa</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div></div>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
