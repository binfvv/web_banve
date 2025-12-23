<?php
// includes/app.php
if (session_status() === PHP_SESSION_NONE) session_start();

$DOCROOT  = rtrim(str_replace('\\','/', $_SERVER['DOCUMENT_ROOT']), '/');
$APP_PATH = rtrim(str_replace('\\','/', realpath(__DIR__ . '/..')), '/'); // thư mục gốc dự án (web_banve)
$BASE_URL = rtrim(str_replace($DOCROOT, '', $APP_PATH), '/');             // ví dụ: /web_banve

function url(string $path = ''): string {
  global $BASE_URL;
  return $BASE_URL . '/' . ltrim($path, '/');
}
