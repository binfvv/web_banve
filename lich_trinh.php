<?php
include 'includes/db.php'; // Kết nối tới cơ sở dữ liệu
session_start()
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh Sách chuyến tàu</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
      h2 {
            text-align: left;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #0085c4;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .btn-book {
            background-color: #0085c4;
            color: white;
            padding: 8px 16px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-book:hover {
            background-color: #016290;
        }
    </style>
</head>
<script>
function bookTicket(id) {
    // Chuyển hướng đến trang đặt vé với tham số chuyến tàu
    window.location.href = "dat_ve.php?id=" + id;
}
</script>

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
        <!-- Danh sách các chuyến tàu tìm kiếm được -->
        <div class="train-list">
            <h2>Lịch trình sẵn có</h2>
            <table>
                <thead>
                    <tr>
                        <th>Mã chuyến tàu</th>
                        <th>Tên tàu</th>
                        <th>Ga đi</th>
                        <th>Ga đến</th>
            
                        <th>Thời gian khởi hành</th>
                        <th>Thời gian đến</th>
                        <th>Giá vé</th>
                        <th>Đặt vé</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                        
                        $sql = "SELECT * FROM chuyen_tau ";
                        $result = $conn->query($sql);

                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>
                                        <td>{$row['id']}</td>
                                        <td>{$row['ten_tau']}</td>
                                        <td>{$row['ga_di']}</td>
                                        <td>{$row['ga_den']}</td>
                                        <td>
                                        {$row['ngay_di']} <br>
                                        {$row['gio_di']}
                                        </td>
                                        <td>
                                        {$row['ngay_den']}<br>
                                        {$row['gio_den']} 
                                        </td>
                                        <td>{$row['gia_ve']} VNĐ</td>
                                        <td><button class='btn-book' onclick=\"bookTicket('{$row['id']}')\">Đặt vé</button></td>
                                    </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='8'>Không có chuyến tàu nào</td></tr>";
                        }

                        $conn->close(); 
                        ?>
                    </tr>
                </tbody>
            </table>
        </div>

        <footer>
            <p>Đoàn Đức Bình IT4.K23 </p>
        </footer>
    </div>
</body>
</html>