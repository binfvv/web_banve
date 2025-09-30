<?php
require_once __DIR__ . '/_bootstrap.php';
$page_title='Thanh toán'; $page_h1='Thanh toán'; $page_breadcrumb='Thanh toán'; $active='pay';

$rows = $pdo->query("
  SELECT id, user_id, phuong_thuc, tong_tien, ngay_giao_dich, ghi_chu
  FROM thanh_toan
  ORDER BY ngay_giao_dich DESC, id DESC
")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__.'/_layout_top.php';
?>
<div class="card shadow-sm"><div class="card-body">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>#</th><th>User</th><th>Phương thức</th><th>Tổng</th><th>Thời gian</th><th>Chi tiết</th></tr></thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= (int)$r['user_id'] ?></td>
            <td class="text-capitalize"><?= h($r['phuong_thuc']) ?></td>
            <td class="fw-semibold"><?= money_vi($r['tong_tien']) ?></td>
            <td class="small text-muted"><?= h($r['ngay_giao_dich']) ?></td>
            <td>
              <?php if (!empty($r['ghi_chu'])): ?>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#m<?= (int)$r['id'] ?>">Xem</button>
                <div class="modal fade" id="m<?= (int)$r['id'] ?>" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
                  <div class="modal-header"><h5 class="modal-title">Chi tiết ghi chú</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                  <div class="modal-body">
                    <pre class="small mb-0"><?= h($r['ghi_chu']) ?></pre>
                  </div>
                </div></div></div>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div></div>
<?php include __DIR__.'/_layout_bottom.php'; ?>
