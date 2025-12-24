<?php
require_once __DIR__ . '/_bootstrap.php';
$page_title = 'Quản lý Chuyến tàu'; 
$page_h1 = 'Quản lý Chuyến tàu'; 
$page_breadcrumb = 'Chuyến tàu'; 
$active = 'chuyen';

// --- CẤU HÌNH TỰ ĐỘNG SINH DỮ LIỆU ---
const AUTO_GEN_COACHES = 3; // Tạo tự động 3 toa
const SEATS_PER_COACH  = 32; // Mỗi toa 32 ghế (8 hàng x 4 cột)

// XỬ LÝ FORM (Create/Update/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $act = $_POST['act'] ?? '';

    try {
        // === BẮT ĐẦU XỬ LÝ ===
        if ($act === 'create' || $act === 'update') {
            $id       = (int)($_POST['id'] ?? 0);
            $ten_tau  = trim($_POST['ten_tau'] ?? '');
            $ga_di    = trim($_POST['ga_di'] ?? '');
            $ga_den   = trim($_POST['ga_den'] ?? '');
            $ngay_di  = $_POST['ngay_di'] ?? '';
            $gio_di   = $_POST['gio_di'] ?? '';
            $ngay_den = $_POST['ngay_den'] ?? '';
            $gio_den  = $_POST['gio_den'] ?? '';
            $gia_ve   = (int)($_POST['gia_ve'] ?? 0);

            if ($act === 'create') {
                // 1. TẠO MỚI (Có Transaction để sinh Toa/Ghế)
                $pdo->beginTransaction();

                $sql = "INSERT INTO chuyen_tau (ten_tau, ga_di, ga_den, ngay_di, gio_di, ngay_den, gio_den, gia_ve) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$ten_tau, $ga_di, $ga_den, $ngay_di, $gio_di, $ngay_den, $gio_den, $gia_ve]);
                $id_tau = $pdo->lastInsertId();

                // Tự động tạo Toa & Ghế
                for ($i = 1; $i <= AUTO_GEN_COACHES; $i++) {
                    $is_vip = ($i === 1); 
                    $ten_toa = "Toa " . $i;
                    $loai_toa = $is_vip ? "Giường nằm VIP" : "Ghế mềm điều hòa";
                    $gia_toa = $is_vip ? ($gia_ve * 1.2) : $gia_ve;

                    // Insert Toa
                    $sqlToa = "INSERT INTO toa_tau (id_tau, ten_toa, loai_toa, thu_tu, gia_tu, gia_den) VALUES (?, ?, ?, ?, ?, ?)";
                    $pdo->prepare($sqlToa)->execute([$id_tau, $ten_toa, $loai_toa, $i, $gia_toa, $gia_toa]);
                    $id_toa = $pdo->lastInsertId();

                    // Insert Ghế
                    $sqlGhe = "INSERT INTO ghe (id_tau, id_toa, so_ghe, hang, cot, gia, trang_thai, co_ban) VALUES (?, ?, ?, ?, ?, ?, 'trong', 1)";
                    $stmtGhe = $pdo->prepare($sqlGhe);

                    for ($j = 1; $j <= SEATS_PER_COACH; $j++) {
                        $hang = ceil($j / 4);
                        $cot  = ($j - 1) % 4 + 1;
                        $stmtGhe->execute([$id_tau, $id_toa, $j, $hang, $cot, $gia_toa]);
                    }
                }
                $pdo->commit(); 

            } else {
                // 2. CẬP NHẬT (Không sửa ghế để bảo toàn dữ liệu đặt vé)
                $sql = "UPDATE chuyen_tau SET ten_tau=?, ga_di=?, ga_den=?, ngay_di=?, gio_di=?, ngay_den=?, gio_den=?, gia_ve=? WHERE id=?";
                $pdo->prepare($sql)->execute([$ten_tau, $ga_di, $ga_den, $ngay_di, $gio_di, $ngay_den, $gio_den, $gia_ve, $id]);
            }

        } elseif ($act === 'delete') {
            // 3. XÓA (Transaction xóa theo thứ tự Cha-Con)
            $id = (int)($_POST['id'] ?? 0);
            
            $pdo->beginTransaction();
            // B1: Xóa Lịch sử đặt vé (Con của Ghe)
            $pdo->prepare("DELETE FROM lich_su_dat_ve WHERE id_tau = ?")->execute([$id]);
            // B2: Xóa Ghế (Con của Toa)
            $pdo->prepare("DELETE FROM ghe WHERE id_tau = ?")->execute([$id]);
            // B3: Xóa Toa (Con của Chuyến)
            $pdo->prepare("DELETE FROM toa_tau WHERE id_tau = ?")->execute([$id]);
            // B4: Xóa Chuyến tàu
            $pdo->prepare("DELETE FROM chuyen_tau WHERE id = ?")->execute([$id]);
            $pdo->commit();
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Hiển thị lỗi rõ ràng và dừng script
        die('<div class="alert alert-danger m-3">
                <h4>Lỗi xử lý dữ liệu:</h4>
                <p>'. htmlspecialchars($e->getMessage()) .'</p>
                <a href="chuyen_tau.php" class="btn btn-secondary">Quay lại</a>
             </div>');
    }

    header('Location: chuyen_tau.php'); 
    exit;
}

// Lấy danh sách hiển thị
$rows = $pdo->query("SELECT * FROM chuyen_tau ORDER BY ngay_di DESC, gio_di DESC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/_layout_top.php';
?>



<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
      <h3 class="mb-0">Quản lý Chuyến tàu</h3>
      <small class="text-muted">Tự động sinh <?= AUTO_GEN_COACHES ?> toa và <?= AUTO_GEN_COACHES * SEATS_PER_COACH ?> ghế khi tạo mới.</small>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEdit" onclick="fillForm()">
      <i class="ti ti-plus"></i> Thêm chuyến
  </button>
</div>

<div class="card shadow-sm border-0">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">#</th>
            <th>Tên tàu</th>
            <th>Lộ trình</th>
            <th>Ngày đi</th>
            <th>Ngày đến</th>
            <th>Giá gốc</th>
            <th class="text-end pe-3">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td class="ps-3 fw-bold text-muted"><?= (int)$r['id'] ?></td>
              <td><span class="badge bg-primary bg-opacity-10 text-primary"><?= h($r['ten_tau']) ?></span></td>
              <td>
                  <div class="d-flex flex-column">
                      <span class="fw-bold"><?= h($r['ga_di']) ?></span>
                      <i class="ti ti-arrow-down small text-muted"></i>
                      <span class="fw-bold"><?= h($r['ga_den']) ?></span>
                  </div>
              </td>
              <td>
                  <div class="fw-bold"><?= date('d/m/Y', strtotime($r['ngay_di'])) ?></div>
                  <div class="small text-muted"><?= date('H:i', strtotime($r['gio_di'])) ?></div>
              </td>
              <td>
                  <div class="fw-bold"><?= date('d/m/Y', strtotime($r['ngay_den'])) ?></div>
                  <div class="small text-muted"><?= date('H:i', strtotime($r['gio_den'])) ?></div>
              </td>
              <td class="fw-bold text-success"><?= number_format($r['gia_ve'], 0, ',', '.') ?> đ</td>
              <td class="text-end pe-3">
                <button class="btn btn-sm btn-light text-primary me-1"
                        data-bs-toggle="modal" data-bs-target="#modalEdit"
                        onclick='fillForm(<?= json_encode($r, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>)'>
                    <i class="ti ti-edit"></i> Sửa
                </button>
                <form method="post" class="d-inline" onsubmit="return confirm('CẢNH BÁO: Xóa chuyến tàu sẽ xóa toàn bộ TOA, GHẾ và LỊCH SỬ liên quan.\nBạn có chắc chắn không?')">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="act" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-light text-danger"><i class="ti ti-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" class="needs-validation" novalidate>
        <div class="modal-header bg-light">
          <h5 class="modal-title" id="modalTitle">Thêm chuyến mới</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php csrf_field(); ?>
          <input type="hidden" name="act" id="fAct" value="create">
          <input type="hidden" name="id" id="fId">
          
          <div class="mb-3">
              <label class="form-label fw-bold">Tên chuyến tàu / Số hiệu</label>
              <input class="form-control" name="ten_tau" id="fTen" placeholder="VD: SE1, TN2..." required>
          </div>
          
          <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">Ga đi</label>
                <input class="form-control" name="ga_di" id="fDi" placeholder="Hà Nội" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Ga đến</label>
                <input class="form-control" name="ga_den" id="fDen" placeholder="Sài Gòn" required>
            </div>
          </div>
          
          <div class="row g-3 mb-3 p-3 bg-light rounded mx-0">
            <div class="col-md-6">
                <label class="form-label small text-muted text-uppercase">Khởi hành</label>
                <div class="input-group">
                    <input type="date" class="form-control" name="ngay_di" id="fNgDi" required>
                    <input type="time" class="form-control" name="gio_di" id="fGioDi" required>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-muted text-uppercase">Đến nơi (Dự kiến)</label>
                <div class="input-group">
                    <input type="date" class="form-control" name="ngay_den" id="fNgDen" required>
                    <input type="time" class="form-control" name="gio_den" id="fGioDen" required>
                </div>
            </div>
          </div>
          
          <div class="mb-2">
              <label class="form-label fw-bold">Giá vé cơ bản (VND)</label>
              <input type="number" class="form-control fw-bold text-success" name="gia_ve" id="fGia" placeholder="VD: 500000" required>
              <div class="form-text">Hệ thống sẽ tự động tạo Toa và Ghế dựa trên giá này (Toa VIP sẽ đắt hơn 20%).</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
          <button type="submit" class="btn btn-primary px-4">Lưu dữ liệu</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function fillForm(row){
  const isEdit = !!row;
  document.getElementById('modalTitle').textContent = isEdit ? 'Cập nhật chuyến tàu' : 'Thêm chuyến mới';
  document.getElementById('fAct').value = isEdit ? 'update' : 'create';
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

(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms).forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!form.checkValidity()) {
        event.preventDefault()
        event.stopPropagation()
      }
      form.classList.add('was-validated')
    }, false)
  })
})()
</script>

<?php include __DIR__ . '/_layout_bottom.php'; ?>