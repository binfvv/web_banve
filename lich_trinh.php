<?php
include __DIR__ . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function money_vi($n){ return number_format((int)$n,0,',','.') . ' VNĐ'; }
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$cart_count = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

$use_pdo    = (isset($pdo)  && $pdo  instanceof PDO);
$use_mysqli = (isset($conn) && $conn instanceof mysqli);

// ===== Menu (fallback nếu chưa có bảng menus)
$header_menus = [
  ['label'=>'TRANG CHỦ','url'=>'index.php'],
  ['label'=>'TÌM CHUYẾN TÀU','url'=>'tim_kiem.php'],
  ['label'=>'LỊCH TRÌNH','url'=>'lich_trinh.php'],
  ['label'=>'LIÊN HỆ','url'=>'lien_he.php'],
];
try {
  if ($use_pdo) {
    $m = $pdo->query("SELECT location,label,url FROM menus WHERE visible=1 ORDER BY location,position,id")->fetchAll(PDO::FETCH_ASSOC);
    if ($m){ $header_menus=[]; foreach($m as $x) if($x['location']!=='footer') $header_menus[]=['label'=>$x['label'],'url'=>$x['url']]; }
  } elseif ($use_mysqli) {
    if ($res=$conn->query("SELECT location,label,url FROM menus WHERE visible=1 ORDER BY location,position,id")){
      $m=$res->fetch_all(MYSQLI_ASSOC); if($m){ $header_menus=[]; foreach($m as $x) if($x['location']!=='footer') $header_menus[]=['label'=>$x['label'],'url'=>$x['url']]; }
    }
  }
} catch(Throwable $e){}

// ===== Filters
$ga_di   = trim($_GET['ga_di']   ?? '');
$ga_den  = trim($_GET['ga_den']  ?? '');
$ngay_di = trim($_GET['ngay_di'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 10; $offset = ($page-1)*$limit;

$where=[]; $params=[]; $types='';
if($ga_di!==''){ $where[]="ga_di=?";  $params[]=$ga_di;  $types.='s'; }
if($ga_den!==''){ $where[]="ga_den=?"; $params[]=$ga_den; $types.='s'; }
if($ngay_di!==''){ $where[]="ngay_di=?"; $params[]=$ngay_di; $types.='s'; }
$where_sql = $where?(' WHERE '.implode(' AND ',$where)):'';

// ===== Total
$total=0;
if($use_pdo){
  $st=$pdo->prepare("SELECT COUNT(*) FROM chuyen_tau".$where_sql);
  $st->execute($params); $total=(int)$st->fetchColumn();
}else{
  $st=$conn->prepare("SELECT COUNT(*) FROM chuyen_tau".$where_sql);
  if($types) $st->bind_param($types, ...$params);
  $st->execute(); $st->bind_result($total); $st->fetch(); $st->close();
}

// ===== Rows
$rows=[];
if($use_pdo){
  $sql="SELECT id,ten_tau,ga_di,ga_den,ngay_di,gio_di,ngay_den,gio_den,gia_ve
        FROM chuyen_tau $where_sql
        ORDER BY ngay_di ASC, gio_di ASC, id ASC
        LIMIT $limit OFFSET $offset";
  $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC);
}else{
  $sql="SELECT id,ten_tau,ga_di,ga_den,ngay_di,gio_di,ngay_den,gio_den,gia_ve
        FROM chuyen_tau $where_sql
        ORDER BY ngay_di ASC, gio_di ASC, id ASC
        LIMIT ? OFFSET ?";
  $types2=$types.'ii'; $params2=$params; $params2[]=$limit; $params2[]=$offset;
  $st=$conn->prepare($sql); if($types2) $st->bind_param($types2, ...$params2);
  $st->execute(); $res=$st->get_result(); $rows=$res?$res->fetch_all(MYSQLI_ASSOC):[]; $st->close();
}

// ===== Station options
$stations=[];
if($use_pdo){
  try{$stations=$pdo->query("SELECT DISTINCT ga_di name FROM chuyen_tau UNION SELECT DISTINCT ga_den FROM chuyen_tau ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);}catch(Throwable $e){}
}else{
  if($r=$conn->query("SELECT DISTINCT ga_di name FROM chuyen_tau UNION SELECT DISTINCT ga_den FROM chuyen_tau ORDER BY name")){
    $stations=array_map(fn($x)=>$x['name'],$r->fetch_all(MYSQLI_ASSOC));
  }
}

// ===== Pagination + View mode
$pages=max(1,(int)ceil($total/$limit));
function page_url($p){ $qs=$_GET; $qs['page']=$p; return '?'.http_build_query($qs); }
$view = $_GET['view'] ?? 'card'; // <<== mặc định CARD
$view = in_array($view,['table','card'])?$view:'card';
function view_url($v){ $qs=$_GET; $qs['view']=$v; unset($qs['page']); return '?'.http_build_query($qs); }
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lịch trình sẵn có</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    :root { --card-r:14px; }
    body { background:#f5f7fb; }
    .navbar-brand img{ height:44px; }
    .card{ border-radius:var(--card-r); }
    .table thead th{ background:#0674ff; color:#fff; }
    .pill{ border-radius:999px; }
  </style>
</head>
<body>
  <!-- NAV -->
  <nav class="navbar navbar-expand-lg navbar-dark" style="background:#0d6efd;">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
        <img src="assets/LOGO_n.png" alt="Đường sắt Việt Nam"><span class="fw-bold">Vé tàu</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMain">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <?php foreach($header_menus as $m): ?>
            <li class="nav-item"><a class="nav-link<?= (basename($m['url'])==='lich_trinh.php'?' active':'') ?>" href="<?= h($m['url']) ?>"><?= h($m['label']) ?></a></li>
          <?php endforeach; ?>
          <li class="nav-item"><a class="nav-link" href="gio_hang.php">GIỎ HÀNG (<?= $cart_count ?>)</a></li>
          <li class="nav-item"><a class="nav-link" href="dat_ve_thanh_cong.php">VÉ ĐÃ ĐẶT</a></li>
        </ul>
        <div class="d-flex">
          <?php if(isset($_SESSION['user_id'])): ?>
            <a class="btn btn-sm btn-light" href="logout.php">ĐĂNG XUẤT</a>
          <?php else: ?>
            <a class="btn btn-sm btn-outline-light" href="login.php">ĐĂNG NHẬP</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <!-- HEADER + VIEW SWITCH -->
  <div class="container my-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h1 class="h4 mb-0">Lịch trình sẵn có</h1>
      <div class="d-flex gap-2">
        <a href="<?= h(view_url('table')) ?>" class="btn btn-sm <?= $view==='table'?'btn-primary':'btn-outline-primary' ?>"><i class="ti ti-table"></i> Bảng</a>
        <a href="<?= h(view_url('card'))  ?>" class="btn btn-sm <?= $view==='card'?'btn-primary':'btn-outline-primary' ?>"><i class="ti ti-layout-grid"></i> Thẻ</a>
        <a class="btn btn-sm btn-success" href="tim_kiem.php"><i class="ti ti-search"></i> Tìm chuyến</a>
      </div>
    </div>
  </div>

  <!-- FILTERS -->
  <div class="container mb-3">
    <div class="card shadow-sm">
      <div class="card-body">
        <form class="row g-2">
          <div class="col-md-4">
            <label class="form-label">Ga đi</label>
            <select name="ga_di" class="form-select">
              <option value="">-- Tất cả --</option>
              <?php foreach($stations as $st): ?>
                <option value="<?= h($st) ?>" <?= ($ga_di===$st?'selected':'') ?>><?= h($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Ga đến</label>
            <select name="ga_den" class="form-select">
              <option value="">-- Tất cả --</option>
              <?php foreach($stations as $st): ?>
                <option value="<?= h($st) ?>" <?= ($ga_den===$st?'selected':'') ?>><?= h($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Ngày đi</label>
            <input type="date" name="ngay_di" class="form-control" value="<?= h($ngay_di) ?>">
          </div>
          <div class="col-12 d-flex gap-2 mt-2">
            <button class="btn btn-primary"><i class="ti ti-filter"></i> Lọc</button>
            <a class="btn btn-outline-secondary" href="lich_trinh.php"><i class="ti ti-eraser"></i> Xóa lọc</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- CARD VIEW -->
  <?php if ($view === 'card'): ?>
  <div class="container mb-5">
    <?php $grouped=[]; foreach($rows as $r){ $grouped[$r['ngay_di']][]=$r; } ksort($grouped); ?>
    <?php if (!$rows): ?>
      <div class="card shadow-sm"><div class="card-body text-center text-muted py-4">Không có chuyến tàu nào phù hợp.</div></div>
    <?php endif; ?>

    <?php foreach($grouped as $day => $items): ?>
      <h5 class="mb-3 mt-4"><i class="ti ti-calendar"></i> <?= h(date('d/m/Y', strtotime($day))) ?></h5>
      <div class="row g-3">
        <?php foreach($items as $r): ?>
          <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 shadow-sm">
              <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-1">
                  <span class="badge text-bg-primary"><?= h($r['ten_tau']) ?></span>
                  <span class="text-muted small">#<?= h($r['id']) ?></span>
                </div>
                <div class="mb-2">
                  <div class="fw-semibold"><?= h($r['ga_di']) ?> → <?= h($r['ga_den']) ?></div>
                  <div class="text-muted small">Khởi hành: <?= h($r['gio_di']) ?> • Đến: <?= h($r['gio_den']) ?></div>
                </div>
                <div class="mt-auto d-flex justify-content-between align-items-end">
                  <div>
                    <div class="text-muted small">Giá từ</div>
                    <div class="h5 mb-0"><?= money_vi($r['gia_ve']) ?></div>
                  </div>
                  <a class="btn btn-primary pill" href="dat_ve.php?id=<?= urlencode($r['id']) ?>"><i class="ti ti-ticket"></i> Đặt vé</a>
                </div>
              </div>
              <div class="card-footer d-flex justify-content-between align-items-center small bg-light-subtle">
                <span><i class="ti ti-clock"></i> <?= h($r['ngay_di']) ?></span>
                <span class="text-muted"><i class="ti ti-route"></i> trực tiếp</span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <?php if ($pages > 1): ?>
      <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-muted small">Tổng: <?= $total ?> chuyến • Trang <?= $page ?>/<?= $pages ?></div>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= h(page_url(1)) ?>">&laquo;</a></li>
            <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= h(page_url($page-1)) ?>">&lsaquo;</a></li>
            <?php $st=max(1,$page-2); $en=min($pages,$page+2); for($p=$st;$p<=$en;$p++): ?>
              <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="<?= h(page_url($p)) ?>"><?= $p ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $page>=$pages?'disabled':'' ?>"><a class="page-link" href="<?= h(page_url($page+1)) ?>">&rsaquo;</a></li>
            <li class="page-item <?= $page>=$pages?'disabled':'' ?>"><a class="page-link" href="<?= h(page_url($pages)) ?>">&raquo;</a></li>
          </ul>
        </nav>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- TABLE VIEW -->
  <?php if ($view === 'table'): ?>
  <div class="container mb-5">
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped align-middle mb-0">
            <thead>
              <tr>
                <th style="width:110px">Mã chuyến</th>
                <th>Tên tàu</th>
                <th>Ga đi</th>
                <th>Ga đến</th>
                <th>Khởi hành</th>
                <th>Đến nơi</th>
                <th>Giá vé</th>
                <th style="width:110px">Đặt vé</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($rows): foreach($rows as $r): ?>
                <tr>
                  <td><?= h($r['id']) ?></td>
                  <td><?= h($r['ten_tau']) ?></td>
                  <td><?= h($r['ga_di']) ?></td>
                  <td><?= h($r['ga_den']) ?></td>
                  <td><div class="small"><?= h($r['ngay_di']) ?></div><div class="text-muted small"><?= h($r['gio_di']) ?></div></td>
                  <td><div class="small"><?= h($r['ngay_den']) ?></div><div class="text-muted small"><?= h($r['gio_den']) ?></div></td>
                  <td><?= money_vi($r['gia_ve']) ?></td>
                  <td><a class="btn btn-sm btn-primary pill" href="dat_ve.php?id=<?= urlencode($r['id']) ?>">Đặt vé</a></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Không có chuyến tàu nào phù hợp.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if ($pages > 1): ?>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="text-muted small">Tổng: <?= $total ?> chuyến • Trang <?= $page ?>/<?= $pages ?></div>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= h(page_url(1)) ?>">&laquo;</a></li>
            <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= h(page_url($page-1)) ?>">&lsaquo;</a></li>
            <?php $st=max(1,$page-2); $en=min($pages,$page+2); for($p=$st;$p<=$en;$p++): ?>
              <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="<?= h(page_url($p)) ?>"><?= $p ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $page>=$pages?'disabled':'' ?>"><a class="page-link" href="<?= h(page_url($page+1)) ?>">&rsaquo;</a></li>
            <li class="page-item <?= $page>=$pages?'disabled':'' ?>"><a class="page-link" href="<?= h(page_url($pages)) ?>">&raquo;</a></li>
          </ul>
        </nav>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <footer class="py-4">
    <div class="container">
      <div class="text-muted small">© <?= date('Y') ?> Đoàn Đức Bình IT4.K23</div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
