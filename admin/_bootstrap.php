<?php
// admin/_bootstrap.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php'; // tạo $pdo (PDO)

// only admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header('Location: /login.php'); exit;
}

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function csrf_field(){
  $t = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');
  echo '<input type="hidden" name="csrf" value="'.$t.'">';
}
function check_csrf(){
  $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
  if (!$ok) { http_response_code(400); exit('Yêu cầu không hợp lệ (CSRF).'); }
}

// Helpers
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function money_vi($n){ return number_format((int)$n,0,',','.') . ' VNĐ'; }
