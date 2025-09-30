<?php
require_once __DIR__ . '/_bootstrap.php';
$page_title='Cài đặt'; $page_h1='Cài đặt'; $page_breadcrumb='Cài đặt'; $active='settings';
include __DIR__.'/_layout_top.php';
?>
<div class="card shadow-sm"><div class="card-body">
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Tên website</label>
      <input class="form-control" value="Đường Sắt Việt Nam – Demo">
    </div>
    <div class="col-md-6">
      <label class="form-label">Email liên hệ</label>
      <input class="form-control" value="support@example.com">
    </div>
  </div>
  <button class="btn btn-success mt-3" disabled>Lưu (demo)</button>
  <div class="small text-muted mt-2">Trang này là demo UI. Khi bạn xác định bảng cấu hình, mình sẽ nối vào DB.</div>
</div></div>
<?php include __DIR__.'/_layout_bottom.php'; ?>
