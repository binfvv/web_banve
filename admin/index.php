<?php
require_once __DIR__ . '/_bootstrap.php';
$page_title = 'Dashboard'; 
$page_h1 = 'Tổng quan hệ thống'; 
$active = 'dashboard';

// ===== 1. TÍNH TOÁN KPI (THỐNG KÊ) =====

// Tổng người dùng
$kpi_users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Tổng doanh thu (Chỉ tính đơn đã thanh toán để chính xác hơn, hoặc bỏ điều kiện nếu muốn tính tổng tất cả)
$kpi_revenue = (int)$pdo->query("SELECT COALESCE(SUM(tong_tien),0) FROM thanh_toan WHERE trang_thai = 'da_thanh_toan'")->fetchColumn();

// Vé đã bán (Trạng thái 'da_dat')
$kpi_sold = (int)$pdo->query("SELECT COUNT(*) FROM ghe WHERE trang_thai = 'da_dat'")->fetchColumn();

// Doanh thu hôm nay
$kpi_today = (int)$pdo->query("SELECT COALESCE(SUM(tong_tien),0) FROM thanh_toan WHERE DATE(ngay_giao_dich) = CURDATE() AND trang_thai = 'da_thanh_toan'")->fetchColumn();


// ===== 2. BIỂU ĐỒ DOANH THU (7 NGÀY GẦN NHẤT) =====
$revRows = $pdo->query("
  SELECT DATE(ngay_giao_dich) as d, SUM(tong_tien) as total
  FROM thanh_toan
  WHERE ngay_giao_dich >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
  AND trang_thai = 'da_thanh_toan' -- Chỉ tính đơn đã thanh toán
  GROUP BY DATE(ngay_giao_dich)
  ORDER BY d ASC
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Fill dữ liệu cho đủ 7 ngày (kể cả ngày không có doanh thu)
$labels = []; 
$series = [];
for ($i = 6; $i >= 0; $i--) {
    $d = (new DateTime())->modify("-$i day")->format('Y-m-d');
    $labels[] = date('d/m', strtotime($d)); // Format ngày/tháng
    $series[] = (int)($revRows[$d] ?? 0);
}


// ===== 3. DỮ LIỆU BẢNG =====

// 5 Giao dịch mới nhất
$payments = $pdo->query("
  SELECT id, ma_don_hang, ho_ten_khach, tong_tien, trang_thai, ngay_giao_dich
  FROM thanh_toan
  ORDER BY ngay_giao_dich DESC
  LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// 5 Chuyến tàu sắp khởi hành
$upcoming = $pdo->query("
  SELECT id, ten_tau, ga_di, ga_den, ngay_di, gio_di, gia_ve,
         (SELECT COUNT(*) FROM ghe WHERE id_tau = chuyen_tau.id AND trang_thai='trong') as ghe_trong
  FROM chuyen_tau
  WHERE CONCAT(ngay_di,' ',gio_di) >= NOW()
  ORDER BY ngay_di ASC, gio_di ASC
  LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/_layout_top.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 border-start border-4 border-primary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Doanh thu tổng</div>
                        <div class="fs-4 fw-bold text-primary"><?= number_format($kpi_revenue/1000000, 1) ?> Tr</div>
                        <div class="small text-muted"><?= number_format($kpi_revenue) ?> đ</div>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-3 rounded text-primary">
                        <i class="ti ti-wallet fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 border-start border-4 border-success h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Hôm nay</div>
                        <div class="fs-4 fw-bold text-success"><?= number_format($kpi_today) ?> đ</div>
                        <div class="small text-muted">Doanh thu trong ngày</div>
                    </div>
                    <div class="bg-success bg-opacity-10 p-3 rounded text-success">
                        <i class="ti ti-calendar-event fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 border-start border-4 border-warning h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Vé đã bán</div>
                        <div class="fs-4 fw-bold text-warning"><?= number_format($kpi_sold) ?></div>
                        <div class="small text-muted">Tổng số ghế đã đặt</div>
                    </div>
                    <div class="bg-warning bg-opacity-10 p-3 rounded text-warning">
                        <i class="ti ti-ticket fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 border-start border-4 border-info h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Người dùng</div>
                        <div class="fs-4 fw-bold text-info"><?= number_format($kpi_users) ?></div>
                        <div class="small text-muted">Tài khoản đăng ký</div>
                    </div>
                    <div class="bg-info bg-opacity-10 p-3 rounded text-info">
                        <i class="ti ti-users fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 card-title"><i class="ti ti-chart-line text-primary"></i> Biểu đồ doanh thu (7 ngày)</h5>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 card-title">Giao dịch mới nhất</h5>
                <a href="thanh_toan.php" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Mã đơn</th>
                                <th>Khách hàng</th>
                                <th>Số tiền</th>
                                <th>Trạng thái</th>
                                <th class="text-end pe-3">Thời gian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!$payments): ?>
                                <tr><td colspan="5" class="text-center py-3 text-muted">Chưa có giao dịch nào.</td></tr>
                            <?php else: foreach($payments as $p): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-primary">#<?= h($p['ma_don_hang']) ?></td>
                                    <td><?= h($p['ho_ten_khach']) ?></td>
                                    <td class="fw-bold"><?= number_format($p['tong_tien'],0,',','.') ?> đ</td>
                                    <td>
                                        <?php if($p['trang_thai']=='da_thanh_toan'): ?>
                                            <span class="badge bg-success">Thành công</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Chờ xử lý</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3 small text-muted">
                                        <?= date('H:i d/m', strtotime($p['ngay_giao_dich'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 card-title">Sắp khởi hành</h5>
                <a href="chuyen_tau.php" class="btn btn-sm btn-outline-secondary"><i class="ti ti-settings"></i></a>
            </div>
            <div class="card-body p-0">
                <?php if(!$upcoming): ?>
                    <div class="p-4 text-center text-muted">Không có chuyến nào sắp chạy.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                    <?php foreach($upcoming as $c): ?>
                        <li class="list-group-item p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-primary"><?= h($c['ten_tau']) ?></span>
                                <small class="fw-bold text-danger"><?= date('H:i', strtotime($c['gio_di'])) ?></small>
                            </div>
                            <div class="fw-bold mb-1">
                                <?= h($c['ga_di']) ?> <i class="ti ti-arrow-right small text-muted"></i> <?= h($c['ga_den']) ?>
                            </div>
                            <div class="d-flex justify-content-between small text-muted">
                                <span><?= date('d/m/Y', strtotime($c['ngay_di'])) ?></span>
                                <span>Còn <?= $c['ghe_trong'] ?> chỗ</span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('revenueChart');
    if(ctx){
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [{
                    label: 'Doanh thu (VNĐ)',
                    data: <?= json_encode($series) ?>,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: { callback: function(value) { return value.toLocaleString('vi-VN') + ' đ'; } }
                    }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/_layout_bottom.php'; ?>