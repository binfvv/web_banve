<?php
// dat_ve.php — Chọn ghế tàu hỏa (Horizontal Layout)
if (session_status() === PHP_SESSION_NONE) session_start();

// --- 1. KẾT NỐI DATABASE (Tự động dò tìm file) ---
$db_paths = [
    __DIR__ . '/assets/includes/db.php',
    __DIR__ . '/includes/db.php',
    __DIR__ . '/db.php'
];
$db_found = false;
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_found = true;
        break;
    }
}
if (!$db_found) {
    die("<h1>Lỗi cấu hình:</h1> Không tìm thấy file <code>db.php</code>. Vui lòng kiểm tra lại cấu trúc thư mục.");
}
// -------------------------------------------------

// ===== Helpers =====
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Kiểm tra đăng nhập (Bỏ comment dòng dưới nếu bắt buộc đăng nhập mới được xem ghế)
// if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
$cart_count = count($_SESSION['cart']);

// CSRF Token
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf'];

$train_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ======================= MINI APIs ======================= */
/* A) Kiểm tra trạng thái ghế realtime (Dùng cho AJAX JS bên dưới) */
if (isset($_GET['action']) && $_GET['action'] === 'check') {
    header('Content-Type: application/json; charset=utf-8');
    $ids = json_decode($_GET['ids'] ?? '[]', true);
    $ids = is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
    
    if (!$ids) { echo json_encode(['ok'=>true,'seats'=>[]]); exit; }
    
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stm = $pdo->prepare("SELECT id, trang_thai FROM ghe WHERE id IN ($ph)");
    $stm->execute($ids);
    
    // Trả về dạng { "101": "da_dat", "102": "trong" }
    echo json_encode(['ok'=>true, 'seats'=>$stm->fetchAll(PDO::FETCH_KEY_PAIR)]);
    exit;
}

/* ======================= LOAD DATA ======================= */
$trip = null; $coaches = []; $coach_id = null; $seats = [];

if ($train_id > 0) {
    // 1. Thông tin chuyến tàu
    $stm = $pdo->prepare("SELECT id, ten_tau, ga_di, ga_den, ngay_di, gio_di, ngay_den, gio_den, gia_ve FROM chuyen_tau WHERE id=:id");
    $stm->execute([':id'=>$train_id]);
    $trip = $stm->fetch(PDO::FETCH_ASSOC);

    if ($trip) {
        // 2. Danh sách toa
        $coachesStmt = $pdo->prepare("
            SELECT t.id, t.ten_toa, t.loai_toa, t.thu_tu,
                   COALESCE(t.gia_tu, MIN(COALESCE(g.gia, :gve_low)))  AS gia_tu,
                   COALESCE(t.gia_den, MAX(COALESCE(g.gia, :gve_high))) AS gia_den,
                   SUM(CASE WHEN g.trang_thai <> 'da_dat' THEN 1 ELSE 0 END) AS con_cho
            FROM toa_tau t
            LEFT JOIN ghe g ON g.id_toa = t.id
            WHERE t.id_tau = :tau
            GROUP BY t.id
            ORDER BY t.thu_tu, t.id
        ");
        $coachesStmt->execute([
            ':gve_low'  => $trip['gia_ve'],
            ':gve_high' => $trip['gia_ve'],
            ':tau'      => $train_id
        ]);
        $coaches = $coachesStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Xác định toa đang chọn
        $coach_id = isset($_GET['toa']) ? (int)$_GET['toa'] : ($coaches[0]['id'] ?? null);

        if ($coach_id) {
            // 4. Lấy ghế của toa (Sắp xếp theo Hàng -> Cột -> Số ghế)
            $stmSeats = $pdo->prepare("
                SELECT id, so_ghe, trang_thai, hang, cot, co_ban,
                       COALESCE(gia, :gve) AS gia
                FROM ghe
                WHERE id_toa = :id_toa
                ORDER BY hang ASC, cot ASC, so_ghe ASC
            ");
            $stmSeats->execute([
                ':gve'    => $trip['gia_ve'],
                ':id_toa' => $coach_id
            ]);
            $seats = $stmSeats->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

/* ======================= UI HEADER ======================= */
$header_menus = [
    ['label'=>'TRANG CHỦ','url'=>'index.php'],
    ['label'=>'TÌM CHUYẾN TÀU','url'=>'tim_kiem.php'],
    ['label'=>'LỊCH TRÌNH','url'=>'lich_trinh.php'],
    ['label'=>'LIÊN HỆ','url'=>'lien_he.php'],
];
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chọn ghế - <?= $trip ? h($trip['ten_tau']) : 'Đặt vé tàu' ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
<style>
  body{background:#f6f8fb}
  .navbar-brand img{height:44px}
  .card{border-radius:16px}
  .coach-pill{min-width:220px}
  .coach-pill .small{opacity:.85}

  /* === CSS TOA TÀU NGANG (Horizontal Layout) === */
  .train-coach-map {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    overflow-x: auto;
    justify-content: center;
    gap: 15px;
    padding: 40px 25px;
    border: 3px solid #2d5a86;
    border-radius: 16px;
    background: #fff;
    align-items: center;
    min-height: 280px;
    scrollbar-width: thin;
    scrollbar-color: #2d5a86 #e9ecef;
  }
  .train-coach-map::-webkit-scrollbar { height: 8px; }
  .train-coach-map::-webkit-scrollbar-track { background: #e9ecef; border-radius: 4px; }
  .train-coach-map::-webkit-scrollbar-thumb { background: #2d5a86; border-radius: 4px; }

  /* Một hàng ghế (thực tế là 1 cột dọc gồm 4 ghế) */
  .coach-row {
    display: flex;
    flex-direction: column;
    gap: 25px; /* Lối đi giữa */
    position: relative;
    padding: 0 4px;
    flex-shrink: 0;
  }

  /* Kẻ vạch vàng nâu (cái bàn/vách) */
  .coach-row::before {
    content: ''; position: absolute; left: -8px; top: 0; bottom: 0;
    width: 6px; background-color: #a68b55; border-radius: 3px; z-index: 1;
  }
  .coach-row:first-child::before { display: none; }

  .seat-pair { display: flex; flex-direction: column; gap: 6px; }

  .seat {
    width: 44px; height: 44px;
    border-radius: 8px; border: 1px solid #adb5bd;
    background: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 13px; color: #495057;
    cursor: pointer; z-index: 2; transition: all 0.2s;
    user-select: none; box-shadow: 0 2px 4px rgba(0,0,0,0.05);
  }
  .seat.available:hover { border-color: #0d6efd; transform: scale(1.05); box-shadow: 0 4px 8px rgba(13,110,253,0.15); }
  .seat.selected { background: #0d6efd; color: white; border-color: #0d6efd; }
  .seat.booked { background: #e9ecef; color: #adb5bd; border-color: #dee2e6; cursor: not-allowed; text-decoration: line-through; }
  
  /* Legend & Summary */
  .legend .dot{width:14px;height:14px;border-radius:4px;display:inline-block;margin-right:6px;vertical-align:middle}
  .dot-available{background:#fff;border:2px solid #e5e7eb}
  .dot-selected{background:#0d6efd}
  .dot-booked{background:#e9ecef}
  .ban-chip{background:#f1f5ff;border:1px dashed #9db8ff;border-radius:10px;padding:1px 8px;font-size:.8rem}
  
  .sticky-summary{
      position:sticky; bottom:0; z-index:20;
      background:#fff; border-top:1px solid #e9ecef;
      padding:.75rem; box-shadow:0 -8px 20px rgba(0,0,0,.04)
  }
  .toast-container{position:fixed;top:12px;right:12px;z-index:1080}
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark" style="background:#0d6efd;">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
        <img src="assets/LOGO_n.png" alt="DSVN" onerror="this.style.display='none'">
        <span class="fw-bold">Vé Tàu</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMain">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <?php foreach ($header_menus as $m): ?>
            <li class="nav-item"><a class="nav-link" href="<?= h($m['url']) ?>"><?= h($m['label']) ?></a></li>
          <?php endforeach; ?>
          </ul>
        <div class="d-flex align-items-center gap-2">
          <?php if (isset($_SESSION['user_id'])): ?>
            <a class="btn btn-sm btn-light" href="logout.php">ĐĂNG XUẤT</a>
          <?php else: ?>
            <a class="btn btn-sm btn-outline-light" href="login.php">ĐĂNG NHẬP</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
</nav>

<div class="container my-4">
  <h1 class="h4 mb-3">Chọn ghế</h1>

  <?php if(!$trip): ?>
    <div class="alert alert-warning">Không tìm thấy chuyến tàu hoặc mã tàu không hợp lệ. <a href="index.php">Quay lại trang chủ</a></div>
  <?php else: ?>
    
    <div class="card shadow-sm mb-3">
      <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <span class="badge text-bg-primary fs-6"><?=h($trip['ten_tau'])?></span>
        <div><strong>Tuyến:</strong> <?=h($trip['ga_di'])?> → <?=h($trip['ga_den'])?></div>
        <div><strong>Khởi hành:</strong> <?=h($trip['ngay_di'].' '.$trip['gio_di'])?></div>
        <div><strong>Đến nơi:</strong> <?=h($trip['ngay_den'].' '.$trip['gio_den'])?></div>
      </div>
      
      <?php if($coaches): ?>
      <div class="card-footer bg-white">
        <ul class="nav nav-pills gap-2 flex-nowrap overflow-auto pb-2">
          <?php foreach($coaches as $c): ?>
          <li class="nav-item">
            <a class="nav-link coach-pill <?= $c['id']==$coach_id?'active':'' ?>"
               href="?id=<?= (int)$trip['id'] ?>&toa=<?= (int)$c['id'] ?>">
              <div class="fw-semibold"><?=h($c['ten_toa'])?></div>
              <div class="small"><?=h($c['loai_toa'])?></div>
              <div class="small mt-1">
                Còn <?= (int)$c['con_cho'] ?> chỗ | 
                <?= number_format((int)$c['gia_tu'],0,',','.') ?> đ
              </div>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>

    <div class="card shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
          <?php 
            $cur=null; 
            foreach($coaches as $c){ if($c['id']==$coach_id){ $cur=$c; break; } }
            echo $cur ? h($cur['ten_toa']) : 'Sơ đồ ghế';
          ?>
          <?php if($seats && array_sum(array_column($seats,'co_ban'))>0): ?>
            <span class="ms-2 ban-chip">Có bàn</span>
          <?php endif; ?>
        </h5>
        <div class="legend small">
          <span class="me-3"><span class="dot dot-available border"></span> Trống</span>
          <span class="me-3"><span class="dot dot-selected"></span> Đang chọn</span>
          <span><span class="dot dot-booked"></span> Đã bán</span>
        </div>
      </div>
      
      <div class="card-body">
        <?php if(!$seats): ?>
          <div class="text-muted p-4 text-center">Không có dữ liệu ghế cho toa này.</div>
        <?php else: ?>
          
          <div id="seatMapArea" class="train-coach-map">
            <div style="writing-mode: vertical-lr; font-weight:bold; color:#ccc; transform: rotate(180deg); margin-right:10px; flex-shrink:0;">ĐẦU TOA</div>

            <?php
            // Group ghế theo [Hàng][Cột]
            $map = []; 
            $maxR = 0;
            foreach($seats as $s){ 
                $r = (int)$s['hang']; 
                $c = (int)$s['cot']; 
                $map[$r][$c] = $s; 
                if($r > $maxR) $maxR = $r;
            }

            // Render từng hàng (cột dọc)
            for($r = 1; $r <= $maxR; $r++): 
                if(empty($map[$r])) continue; 
            ?>
            <div class="coach-row" data-row="<?= $r ?>">
                <div class="seat-pair">
                    <?php foreach([2, 1] as $p): ?> 
                        <?php if(isset($map[$r][$p])): 
                            $s = $map[$r][$p];
                            $isBooked = ($s['trang_thai'] === 'da_dat');
                            $cls = $isBooked ? 'booked' : 'available';
                            // Nếu muốn giữ trạng thái đã chọn khi reload, check session ở đây (tuỳ chọn)
                        ?>
                            <button type="button" class="seat <?= $cls ?>" 
                                    data-seat-id="<?= (int)$s['id'] ?>"
                                    title="Ghế <?= h($s['so_ghe']) ?> - Giá: <?= number_format((int)$s['gia'],0,',','.') ?> VNĐ">
                                <?= h($s['so_ghe']) ?>
                            </button>
                        <?php else: ?><div class="seat" style="visibility:hidden"></div><?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="seat-pair">
                    <?php foreach([3, 4] as $p): ?>
                        <?php if(isset($map[$r][$p])): 
                            $s = $map[$r][$p];
                            $isBooked = ($s['trang_thai'] === 'da_dat');
                            $cls = $isBooked ? 'booked' : 'available';
                        ?>
                            <button type="button" class="seat <?= $cls ?>" 
                                    data-seat-id="<?= (int)$s['id'] ?>"
                                    title="Ghế <?= h($s['so_ghe']) ?> - Giá: <?= number_format((int)$s['gia'],0,',','.') ?> VNĐ">
                                <?= h($s['so_ghe']) ?>
                            </button>
                        <?php else: ?><div class="seat" style="visibility:hidden"></div><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endfor; ?>
            
            <div style="writing-mode: vertical-lr; font-weight:bold; color:#ccc; transform: rotate(180deg); margin-left:10px; flex-shrink:0;">CUỐI TOA</div>
          </div>
        <?php endif; ?>
      </div>

      <div class="sticky-summary">
        <div class="d-flex flex-wrap align-items-center gap-3">
          <div><strong>Đã chọn:</strong> <span id="selCount">0</span> chỗ</div>
          <div><strong>Tạm tính:</strong> <span id="selPrice">0 VNĐ</span></div>
          <div class="ms-auto d-flex gap-2">
            <button id="btnClear" class="btn btn-outline-secondary btn-sm"><i class="ti ti-eraser"></i> Bỏ chọn</button>
            <button id="btnAdd" class="btn btn-primary fw-bold"><i class="ti ti-ticket"></i> Mua vé ngay</button>
          </div>
        </div>
      </div>
    </div>
    
    <div class="mt-3">
       <a class="btn btn-outline-secondary" href="javascript:history.back()"><i class="ti ti-arrow-left"></i> Quay lại</a>
    </div>

  <?php endif; ?>
</div>

<div class="toast-container" id="toasts"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
  const MAX_SELECT = 6;
  const seatGrid = document.getElementById('seatMapArea');
  const selCount = document.getElementById('selCount');
  const selPrice = document.getElementById('selPrice');
  const btnAdd   = document.getElementById('btnAdd');
  const btnClear = document.getElementById('btnClear');
  const toasts   = document.getElementById('toasts');

  // Helper lấy giá tiền từ title
  const priceOf = el => {
    const t = el.getAttribute('title')||'';
    const m = t.match(/Giá:\s*([\d\.]+)\s*VNĐ/);
    if(m) return parseInt(m[1].replace(/\./g,''),10);
    return 0;
  };

  // Helper Toast thông báo
  const toast = (msg, type='secondary') => {
    const el = document.createElement('div');
    el.className = `toast text-bg-${type} border-0`;
    el.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div><button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    toasts.appendChild(el); new bootstrap.Toast(el,{delay:2500}).show();
    el.addEventListener('hidden.bs.toast', ()=>el.remove());
  };

  let selected = new Set();
  
  // Update UI khi chọn ghế
  const update = () => {
    let total = 0;
    selected.forEach(id => {
      const el = document.querySelector(`.seat[data-seat-id="${id}"]`);
      total += el ? priceOf(el) : 0;
    });
    selCount.textContent = selected.size;
    selPrice.textContent = total.toLocaleString('vi-VN') + ' VNĐ';
  };

  // Sự kiện Click ghế
  seatGrid?.addEventListener('click', e => {
    const el = e.target.closest('.seat'); 
    if(!el || el.classList.contains('booked') || el.style.visibility === 'hidden') return;
    
    const id = el.dataset.seatId;
    if (el.classList.contains('selected')) { 
      el.classList.remove('selected'); 
      selected.delete(id); 
    } else {
      if (selected.size >= MAX_SELECT){ toast('Chỉ được chọn tối đa '+MAX_SELECT+' vé.','danger'); return; }
      el.classList.add('selected'); 
      selected.add(id);
    }
    update();
  });

  // Nút xoá chọn
  btnClear?.addEventListener('click', ()=>{
    document.querySelectorAll('.seat.selected').forEach(el=>el.classList.remove('selected'));
    selected.clear(); update();
  });

  // --- LOGIC MỚI: CHUYỂN HƯỚNG SANG THANH_TOAN.PHP ---
  btnAdd?.addEventListener('click', async ()=>{
    if (selected.size === 0){ toast('Vui lòng chọn ít nhất một chỗ.','danger'); return; }
    
    const ids = [...selected];
    
    // 1. Check server lần cuối xem ghế còn không
    btnAdd.disabled = true;
    btnAdd.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang xử lý...';
    
    try {
        const res = await fetch(`dat_ve.php?action=check&ids=${encodeURIComponent(JSON.stringify(ids))}`);
        const chk = await res.json();
        
        if (!chk || !chk.ok) throw new Error('Lỗi kết nối');

        // Tìm ghế bị trùng (người khác đã mua)
        const conflicts = ids.filter(id => chk.seats[id] === 'da_dat');
        
        if (conflicts.length){
            toast('Một số ghế bạn chọn vừa có người mua mất rồi!', 'danger');
            // Update lại giao diện ghế đã mất
            conflicts.forEach(id => {
                const el = document.querySelector(`.seat[data-seat-id="${id}"]`);
                if(el) {
                    el.classList.remove('selected');
                    el.classList.add('booked');
                    selected.delete(id);
                }
            });
            update();
            btnAdd.disabled = false;
            btnAdd.innerHTML = '<i class="ti ti-ticket"></i> Mua vé ngay';
            return;
        }

        // 2. Nếu OK -> Chuyển hướng
        const seatString = ids.join(',');
        window.location.href = `thanh_toan.php?seats=${seatString}`;

    } catch (e) {
        toast('Có lỗi xảy ra, vui lòng thử lại.', 'danger');
        btnAdd.disabled = false;
        btnAdd.innerHTML = '<i class="ti ti-ticket"></i> Mua vé ngay';
    }
  });

  update();
})();
</script>
</body>
</html>