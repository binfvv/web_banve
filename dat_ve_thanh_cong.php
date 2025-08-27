<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Kiểm tra kết nối cơ sở dữ liệu
if (!$conn) {
    die("Kết nối tới cơ sở dữ liệu thất bại: " . $conn->connect_error);
}

$sql = "SELECT DISTINCT chuyen_tau.id AS ma_chuyen, chuyen_tau.ten_tau, chuyen_tau.ga_di, chuyen_tau.ga_den, 
               chuyen_tau.ngay_di, chuyen_tau.gio_di, chuyen_tau.ngay_den, chuyen_tau.gio_den, 
               chuyen_tau.gia_ve, ghe.so_ghe
        FROM chuyen_tau
        JOIN ghe ON chuyen_tau.id = ghe.id_tau
        WHERE ghe.user_id = ? AND ghe.trang_thai = 'da_dat'";

$stmt = $conn->prepare($sql);

// Kiểm tra xem truy vấn có chuẩn bị thành công hay không
if (!$stmt) {
    die("Chuẩn bị truy vấn thất bại: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Kiểm tra xem truy vấn có trả về dữ liệu hay không
if (!$result) {
    die("Truy vấn thất bại: " . $stmt->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vé đã đặt</title>
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
<body>
    <div class="container">
        <header>
            <div class="header-content">
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
                    echo '<a href="logout.php">ĐĂNG XUẤT</a>';
                } else {
                    echo '<a href="login.php">ĐĂNG NHẬP</a>';
                }
            ?>
        </nav>
        <div class="train-list">
            <h2>Đặt vé thành công</h2>
            <p>Dưới đây là thông tin vé bạn đã đặt:</p>
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
                        <th>Số ghế</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>
                                    <td>{$row['ma_chuyen']}</td>
                                    <td>{$row['ten_tau']}</td>
                                    <td>{$row['ga_di']}</td>
                                    <td>{$row['ga_den']}</td>
                                    <td>{$row['ngay_di']} <br> {$row['gio_di']}</td>
                                    <td>{$row['ngay_den']} <br> {$row['gio_den']}</td>
                                    <td>{$row['gia_ve']} VNĐ</td>
                                    <td>{$row['so_ghe']}</td>
                                </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8'>Không có ghế đã đặt nào</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

<?php 
$stmt->close();
?>
