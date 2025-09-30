<?php
// dat_ve.php — Coach tabs + seat grid + price per seat (PDO, all named params)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php'; // phải tạo $pdo (PDO)

// ===== Helpers =====
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
$cart_count = count($_SESSION['cart']);
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf'];

$train_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ======================= MINI APIs ======================= */
/* A) Kiểm tra trạng thái ghế realtime */
if (isset($_GET['action']) && $_GET['action'] === 'check') {
  header('Content-Type: application/json; charset=utf-8');
  $ids = json_decode($_GET['ids'] ?? '[]', true);
  $ids = is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
  if (!$ids) { echo json_encode(['ok'=>true,'seats'=>[]]); exit; }
  $ph = implode(',', array_fill(0, count($ids), '?'));
  $stm = $pdo->prepare("SELECT id, trang_thai FROM ghe WHERE id IN ($ph)");
  $stm->execute($ids);
  echo json_encode(['ok'=>true, 'seats'=>$stm->fetchAll(PDO::FETCH_KEY_PAIR)]);
  exit;
}
/* B) Thêm ghế vào giỏ (AJAX POST) */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['selected_seats'])) {
  header('Content-Type: application/json; charset=utf-8');
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) { echo json_encode(['status'=>'error','message'=>'CSRF']); exit; }
  $ids = json_decode($_POST['selected_seats'] ?? '[]', true);
  $ids = is_array($ids) ? array_values(array_unique(array_map('intval',$ids))) : [];
  if (!$ids) { echo json_encode(['status'=>'error','message'=>'Chưa chọn ghế']); exit; }
  $ph = implode(',', array_fill(0, count($ids), '?'));
  $stm = $pdo->prepare("SELECT id, trang_thai FROM ghe WHERE id IN ($ph)");
  $stm->execute($ids);
  $ok=[]; $conf=[];
  foreach($stm->fetchAll(PDO::FETCH_ASSOC) as $r){
    if ($r['trang_thai'] === 'da_dat') $conf[] = (int)$r['id']; else $ok[] = (int)$r['id'];
  }
  foreach($ok as $sid){ if(!in_array($sid, $_SESSION['cart'], true)) $_SESSION['cart'][] = $sid; }
  echo json_encode(['status'=> $conf ? 'partial' : 'success', 'added'=>$ok, 'conflict'=>$conf, 'cart_count'=>count($_SESSION['cart'])]);
  exit;
}

/* ======================= LOAD DATA ======================= */
$trip = null; $coaches = []; $coach_id = null; $seats = [];

if ($train_id > 0) {
  // Thông tin chuyến tàu
  $stm = $pdo->prepare("SELECT id, ten_tau, ga_di, ga_den, ngay_di, gio_di, ngay_den, gio_den, gia_ve FROM chuyen_tau WHERE id=:id");
  $stm->execute([':id'=>$train_id]);
  $trip = $stm->fetch(PDO::FETCH_ASSOC);

  if ($trip) {
    // Danh sách toa (CHỈ DÙNG NAMED PARAMS)
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

    // Toa đang chọn (theo ?toa=ID hoặc mặc định toa đầu)
    $coach_id = isset($_GET['toa']) ? (int)$_GET['toa'] : ($coaches[0]['id'] ?? null);

    if ($coach_id) {
      // Lấy ghế của toa (CHỈ DÙNG NAMED PARAMS)
      $stmSeats = $pdo->prepare("
        SELECT id, so_ghe, trang_thai, hang, cot, co_ban,
               COALESCE(gia, :gve) AS gia
        FROM ghe
        WHERE id_toa = :id_toa
        ORDER BY
          CASE WHEN hang IS NULL THEN 0 ELSE 1 END DESC,
          hang ASC, cot ASC, so_ghe ASC
      ");
      $stmSeats->execute([
        ':gve'    => $trip['gia_ve'],
        ':id_toa' => $coach_id
      ]);
      $seats = $stmSeats->fetchAll(PDO::FETCH_ASSOC);
    }
  }
}

/* ======================= UI ======================= */
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
<title>Chọn ghế</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
<style>
  body{background:#f6f8fb}
  .navbar-brand img{height:44px}
  .card{border-radius:16px}
  .coach-pill{min-width:220px}
  .coach-pill .small{opacity:.85}
  .seat-grid{display:grid;grid-template-columns:repeat(6,64px);gap:12px;justify-content:center}
  @media(max-width:576px){.seat-grid{grid-template-columns:repeat(4,56px);gap:10px}}
  .seat{width:64px;height:64px;border-radius:12px;border:2px solid #e5e7eb;background:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;cursor:pointer;user-select:none}
  .seat.available:hover{border-color:#0d6efd;box-shadow:0 2px 14px rgba(13,110,253,.15)}
  .seat.selected{background:#0d6efd;border-color:#0d6efd;color:#fff}
  .seat.booked{background:#e9ecef;border-color:#e9ecef;color:#8a8f98;text-decoration:line-through;cursor:not-allowed}
  .legend .dot{width:14px;height:14px;border-radius:4px;display:inline-block;margin-right:6px;vertical-align:middle}
  .dot-available{background:#fff;border:2px solid #e5e7eb}
  .dot-selected{background:#0d6efd}
  .dot-booked{background:#e9ecef}
  .ban-chip{background:#f1f5ff;border:1px dashed #9db8ff;border-radius:10px;padding:1px 8px;font-size:.8rem}
  .sticky-summary{position:sticky;bottom:0;z-index:20;background:#fff;border-top:1px solid #e9ecef;padding:.75rem;box-shadow:0 -8px 20px rgba(0,0,0,.04)}
  .toast-container{position:fixed;top:12px;right:12px;z-index:1080}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background:#0d6efd;">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
      <img src="assets/LOGO_n.png" alt=""><span class="fw-bold">Vé tàu</span>
    </a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <?php foreach($header_menus as $m): ?>
          <li class="nav-item"><a class="nav-link" href="<?=h($m['url'])?>"><?=h($m['label'])?></a></li>
        <?php endforeach; ?>
        <li class="nav-item"><a class="nav-link" href="gio_hang.php">GIỎ HÀNG (<?= $cart_count ?>)</a></li>
      </ul>
      <a class="btn btn-sm btn-light" href="logout.php">ĐĂNG XUẤT</a>
    </div>
  </div>
</nav>

<div class="container my-4">
  <h1 class="h4 mb-3">Chọn ghế</h1>

  <?php if(!$trip): ?>
    <div class="alert alert-warning">Không tìm thấy chuyến tàu.</div>
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
        <ul class="nav nav-pills gap-2 flex-nowrap overflow-auto">
          <?php foreach($coaches as $c): ?>
          <li class="nav-item">
            <a class="nav-link coach-pill <?= $c['id']==$coach_id?'active':'' ?>"
               href="?id=<?= (int)$trip['id'] ?>&toa=<?= (int)$c['id'] ?>">
              <div class="fw-semibold"><?=h($c['ten_toa'])?>: <?=h($c['loai_toa'])?></div>
              <div class="small">
                Còn <?= (int)$c['con_cho'] ?> chỗ |
                Giá từ <?= number_format((int)$c['gia_tu'],0,',','.') ?>
                <?= $c['gia_den'] && $c['gia_den']!=$c['gia_tu'] ? ' - '.number_format((int)$c['gia_den'],0,',','.') : '' ?> VNĐ
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
          <?php $cur=null; foreach($coaches as $c){ if($c['id']==$coach_id){ $cur=$c; break; } }
            echo $cur ? h($cur['ten_toa'].': '.$cur['loai_toa']) : 'Sơ đồ ghế';
          ?>
          <?php if($seats && array_sum(array_column($seats,'co_ban'))>0): ?>
            <span class="ms-2 ban-chip">Bàn</span>
          <?php endif; ?>
        </h5>
        <div class="legend small">
          <span class="me-3"><span class="dot dot-available border"></span> Chỗ trống</span>
          <span class="me-3"><span class="dot dot-selected"></span> Chỗ đang chọn</span>
          <span><span class="dot dot-booked"></span> Chỗ đã bán</span>
        </div>
      </div>
      <div class="card-body">
        <?php if(!$seats): ?>
          <div class="text-muted">Không có ghế nào để chọn.</div>
        <?php else: ?>
          <div id="seatGrid" class="seat-grid" role="grid" aria-label="Sơ đồ ghế">
            <?php
              // Nếu có hang/cot thì render đúng layout
              $haveGrid=false; foreach($seats as $s){ if($s['hang']!==null && $s['cot']!==null){ $haveGrid=true; break; } }
              if ($haveGrid){
                $map=[]; $maxR=0; $maxC=0;
                foreach($seats as $s){ $r=(int)$s['hang']; $c=(int)$s['cot']; $map[$r][$c]=$s; $maxR=max($maxR,$r); $maxC=max($maxC,$c); }
                for($r=1;$r<=$maxR;$r++){
                  for($c=1;$c<=$maxC;$c++){
                    if (!isset($map[$r][$c])) { echo '<div class="seat" style="visibility:hidden"></div>'; continue; }
                    $s=$map[$r][$c]; $cls=$s['trang_thai']==='da_dat'?'booked':'available';
                    echo '<button type="button" class="seat '.$cls.'" data-seat-id="'.(int)$s['id'].'"'.
                         ' title="Ghế '.h($s['so_ghe']).' • '.number_format((int)$s['gia'],0,',','.').' VNĐ">'.
                         h($s['so_ghe']).'</button>';
                  }
                }
              } else {
                foreach($seats as $s){
                  $cls=$s['trang_thai']==='da_dat'?'booked':'available';
                  echo '<button type="button" class="seat '.$cls.'" data-seat-id="'.(int)$s['id'].'"'.
                       ' title="Ghế '.h($s['so_ghe']).' • '.number_format((int)$s['gia'],0,',','.').' VNĐ">'.
                       h($s['so_ghe']).'</button>';
                }
              }
            ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="sticky-summary">
        <div class="d-flex flex-wrap align-items-center gap-3">
          <div><strong>Đã chọn:</strong> <span id="selCount">0</span> chỗ</div>
          <div><strong>Tạm tính:</strong> <span id="selPrice">0 VNĐ</span></div>
          <div class="ms-auto d-flex gap-2">
            <button id="btnClear" class="btn btn-outline-secondary btn-sm"><i class="ti ti-eraser"></i> Bỏ chọn</button>
            <button id="btnAdd" class="btn btn-primary"><i class="ti ti-shopping-cart-plus"></i> Đặt vé</button>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-3">
      <a class="btn btn-outline-secondary" href="lich_trinh.php"><i class="ti ti-arrow-left"></i> Quay lại</a>
      <a class="btn btn-outline-primary ms-2" href="gio_hang.php"><i class="ti ti-basket"></i> Giỏ hàng (<?= $cart_count ?>)</a>
    </div>
  <?php endif; ?>
</div>

<div class="toast-container" id="toasts"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
  const MAX_SELECT = 6;
  const seatGrid = document.getElementById('seatGrid');
  const selCount = document.getElementById('selCount');
  const selPrice = document.getElementById('selPrice');
  const btnAdd   = document.getElementById('btnAdd');
  const btnClear = document.getElementById('btnClear');
  const toasts   = document.getElementById('toasts');

  const priceOf = el => {
    const t = el.getAttribute('title')||'';
    const m = t.match(/([\d\.]+)\s*VNĐ$/);
    return m ? parseInt(m[1].replace(/\./g,''),10) : 0;
  };
  const toast = (msg,type='secondary') => {
    const el = document.createElement('div');
    el.className = `toast text-bg-${type} border-0`;
    el.innerHTML = `<div class="d-flex">
      <div class="toast-body">${msg}</div>
      <button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>`;
    toasts.appendChild(el); new bootstrap.Toast(el,{delay:2200}).show();
    el.addEventListener('hidden.bs.toast', ()=>el.remove());
  };

  let selected = new Set();
  const update = () => {
    let total = 0;
    selected.forEach(id => {
      const el = document.querySelector(`.seat[data-seat-id="${id}"]`);
      total += el ? priceOf(el) : 0;
    });
    selCount.textContent = selected.size;
    selPrice.textContent = total.toLocaleString('vi-VN') + ' VNĐ';
  };

  seatGrid?.addEventListener('click', e=>{
    const el = e.target.closest('.seat'); if(!el || el.classList.contains('booked')) return;
    const id = el.dataset.seatId;
    if (el.classList.contains('selected')) { el.classList.remove('selected'); selected.delete(id); }
    else {
      if (selected.size >= MAX_SELECT){ toast('Bạn chỉ chọn tối đa '+MAX_SELECT+' chỗ.','danger'); return; }
      el.classList.add('selected'); selected.add(id);
    }
    update();
  });

  btnClear?.addEventListener('click', ()=>{
    document.querySelectorAll('.seat.selected').forEach(el=>el.classList.remove('selected'));
    selected.clear(); update();
  });

  btnAdd?.addEventListener('click', async ()=>{
    if (selected.size===0){ toast('Vui lòng chọn ít nhất một chỗ.','danger'); return; }
    const ids = [...selected];
    // Kiểm tra realtime
    const chk = await fetch(`dat_ve.php?action=check&ids=${encodeURIComponent(JSON.stringify(ids))}`).then(r=>r.json()).catch(()=>null);
    if (!chk || !chk.ok){ toast('Không kiểm tra được trạng thái chỗ.','danger'); return; }
    const conflicts = ids.filter(id => chk.seats[id]==='da_dat');
    if (conflicts.length){
      conflicts.forEach(id=>{
        const el = document.querySelector(`.seat[data-seat-id="${id}"]`);
        if (el){ el.classList.remove('selected'); el.classList.add('booked'); selected.delete(id); }
      });
      update();
      toast('Một số chỗ đã được bán trước, vui lòng chọn chỗ khác.','danger');
      return;
    }
    // Gửi thêm vào giỏ
    const fd = new FormData();
    fd.append('selected_seats', JSON.stringify(ids));
    fd.append('csrf', <?= json_encode($CSRF) ?>);
    const res = await fetch('dat_ve.php', {method:'POST', body:fd}).then(r=>r.json()).catch(()=>null);
    if (res && (res.status==='success' || res.status==='partial')){
      if (res.conflict?.length) toast('Một số chỗ bị giữ trước, phần còn lại đã thêm.','warning');
      else toast('Đã thêm vào giỏ hàng.','success');
      setTimeout(()=>location.href='gio_hang.php', 500);
    } else toast(res?.message || 'Không thể thêm vào giỏ.','danger');
  });

  update();
})();
</script>
</body>
</html>
