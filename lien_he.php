<?php 
 session_start()
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web bán vé</title>
    <link rel="stylesheet" href="assets/style.css">

</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <!-- Logo -->
                <div class="logo">
                    <img src="assets/LOGO_n.png" alt="Đường sắt Việt Nam" />
                </div>
            </div>
        </header>
        <nav>
            <a href="index.php">TRANG CHỦ</a>
            <a href="tim_kiem.php">TÌM CHUYẾN TÀU</a>
            <a href="lich_trinh.php">LỊCH TRÌNH</a>
            <a href="lien_he.php">LIÊN HỆ</a>
            <a href="gio_hang.php">GIỎ HÀNG 
    (<?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?>) </a>
            <a href="dat_ve_thanh_cong.php">VÉ ĐÃ ĐẶT</a>
            <?php 
                if(isset($_SESSION['user_id'])){
                    ?>
                    <a href="logout.php">ĐĂNG XUẤT</a>
                    <?php
                }else{
                ?>
                    <a href="login.php">ĐĂNG NHẬP</a>
                <?php
                }
            ?>
        </nav>  
            <div class="train-list">
                <div class="panel-heading">
                    <h3 class="panel-title">THÔNG TIN LIÊN HỆ</h3>
                </div>
                <div class="panel-body">
                    <p><strong>Tổng công ty đường sắt Việt Nam</strong></p>
                    <p>Số 118 Lê Duẩn, Hoàn Kiếm, Hà Nội.</p>

                    <p><strong>Tổng đài hỗ trợ và chăm sóc khách hàng</strong></p>
                    <p>Hỗ trợ tra cứu giờ tàu, giá vé, quy định đổi và trả vé, các chương trình khuyến mại, mua vé qua số điện thoại</p>
                    <p>Khu vực miền Bắc: <strong>1900 0109</strong></p>
                    <p>Khu vực miền Nam: <strong>1900 1520</strong></p>

                    <p><strong>Tổng đài hỗ trợ thanh toán và hoàn tiền online</strong></p>
                    <p>Điện thoại: <strong>1900 6469</strong></p>
                    <p>Email: support1@dsvn.vn</p>
                </div>
            </div>
    </div>
</body>
</html>
