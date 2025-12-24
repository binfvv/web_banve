<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hàm escape (NAV đang dùng)
if (!function_exists('h')) {
    function h($s){
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// Menu header
$header_menus = [
    ['label' => 'TRANG CHỦ',     'url' => 'index.php'],
    ['label' => 'TÌM CHUYẾN TÀU','url' => 'tim_kiem.php'],
    ['label' => 'LỊCH TRÌNH',    'url' => 'lich_trinh.php'],
    ['label' => 'LIÊN HỆ',       'url' => 'lien_he.php'],
    ['label' => 'GIỎ VÉ',       'url' => 'gio_ve.php'],
    ['label' => 'VÉ ĐÃ ĐẶT',       'url' => 'dat_ve_thanh_cong.php'],
];

// Giỏ hàng
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cart_count = count($_SESSION['cart']);
