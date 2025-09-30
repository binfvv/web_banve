<?php
require_once __DIR__ . '/_bootstrap.php';
$page_title='Vé đã đặt'; $page_h1='Vé đã đặt'; $page_breadcrumb='Vé đã đặt'; $active='tickets';

// filter theo chuyến
$filter = (int)($_GET['chuyen'] ?? 0);
$params = [];
$sql = "
SELECT g.id AS ghe_id, g.so_ghe, g.user_id,
       ct.id AS ma_chuyen, ct.ten_tau, ct.ga_di, ct.ga_den, ct.ngay_di, ct.gio_di, ct.ngay_den, ct.gio_den,
       COALESCE(g.gia, ct.gia_ve) AS gia_ve,
       tt.ten_toa
FROM ghe g
JOIN chuyen_tau ct ON ct.id = g.id_tau
LEFT JOIN toa_tau tt ON tt.id = g.id_toa
WHERE g.trang_thai = 'da_dat'
";
if ($filter>0) { $sql .= " AND ct.id = :cid"; $params[':cid']=$filter; }
$sql .= " ORDER BY ct.ngay_di DESC, ct.gio_di DESC, ct.id DESC, g.so_ghe ASC";

$stm = $pdo->prepare($sql);
$stm->execute($params);
$rows = $stm->fetchAll(PDO::FETCH_ASSOC);

$chuyens = $pdo->query("SELECT id, ten_tau, ga_di, ga_den, ngay_di, gio_di FROM chuyen_tau ORDER BY ngay_di DESC, gio_di DESC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__.'/_layout_top.php';
?>
<div class="card shadow-sm mb-3"><div class="card-body">
  <form class="row g-2">
    <div class="col-md-6">
      <label class="form-label">Lọc theo chuyến</label>
      <select class="form-select" name="chuyen" onchange="this.form.submit()">
        <option value="0">— Tất cả —</option>
        <?php foreach($chuyens as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $filter===(int)$c['id']?'selected':'' ?>>
            #<?= (int)$c['id'] ?> — <?= h($c['ten_tau']) ?> (<?= h($c['ga_di']) ?>→<?= h($c['ga_den']) ?>, <?= h($c['ngay_di'].' '.$c['gio_di']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div></div>

<div class="card shadow-sm"><div class="card-body">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr>
        <th>Ghế</th><th>Toa</th><th>Người đặt</th><th>Chuyến</th><th>Khởi hành</th><th>Đến nơi</th><th>Giá</th>
      </tr></thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= h($r['so_ghe']) ?></td>
            <td><?= h($r['ten_toa'] ?? '—') ?></td>
            <td>#<?= (int)$r['user_id'] ?></td>
            <td>#<?= (int)$r['ma_chuyen'] ?> — <?= h($r['ten_tau']) ?><div class="small text-muted"><?= h($r['ga_di']) ?> → <?= h($r['ga_den']) ?></div></td>
            <td><?= h($r['ngay_di']) ?><div class="small text-muted"><?= h($r['gio_di']) ?></div></td>
            <td><?= h($r['ngay_den']) ?><div class="small text-muted"><?= h($r['gio_den']) ?></div></td>
            <td class="fw-semibold"><?= money_vi($r['gia_ve']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div></div>
<?php include __DIR__.'/_layout_bottom.php'; ?>
