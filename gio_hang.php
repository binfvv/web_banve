<?php
session_start();
include 'includes/db.php';

// Kiểm tra nếu người dùng đã đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Xóa ghế khỏi giỏ hàng
if (isset($_POST['remove_seat'])) {
    $seat_id = $_POST['remove_seat'];
    if (($key = array_search($seat_id, $_SESSION['cart'])) !== false) {
        unset($_SESSION['cart'][$key]);
    }
    $_SESSION['cart'] = array_values($_SESSION['cart']); // Sắp xếp lại chỉ số
    header('Location: gio_hang.php');
    exit;
}

if (isset($_POST['confirm_booking'])) {
    $user_id = $_SESSION['user_id'];
    $payment_method = $_POST['payment_method']; // Lấy phương thức thanh toán

    // Tính tổng tiền từ các ghế trong giỏ hàng
    $tong_gia = 0; 
    foreach ($_SESSION['cart'] as $seat_id) {
        // Truy vấn để lấy giá vé từ bảng chuyen_tau
        $stmt = $conn->prepare("
            SELECT chuyen_tau.gia_ve 
            FROM ghe 
            JOIN chuyen_tau ON ghe.id_tau = chuyen_tau.id 
            WHERE ghe.id = ?
        ");
        $stmt->bind_param("i", $seat_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $seat = $result->fetch_assoc();
        if ($seat) {
            $tong_gia += $seat['gia_ve']; // Cộng giá vé vào tổng tiền
        }
        $stmt->close();
    }

    // Cập nhật trạng thái ghế thành 'da_dat'
    $stmt = $conn->prepare("UPDATE ghe SET trang_thai = 'da_dat', user_id = ? WHERE id = ? AND trang_thai = 'trong'");
    foreach ($_SESSION['cart'] as $seat_id) {
        $stmt->bind_param("ii", $user_id, $seat_id);
        $stmt->execute();
    }
    $stmt->close();

    // Lưu thông tin thanh toán
    if ($tong_gia > 0) {
        $stmt = $conn->prepare("INSERT INTO thanh_toan (user_id, phuong_thuc, tong_tien, ngay_giao_dich) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("isi", $user_id, $payment_method, $tong_gia);
        $stmt->execute();
        $stmt->close();

        // Xóa giỏ hàng sau khi đặt vé thành công
        $_SESSION['cart'] = [];
        header('Location: dat_ve_thanh_cong.php');
        exit;
    } else {
        // Nếu tổng tiền không hợp lệ
        echo "Lỗi: Tổng tiền không hợp lệ.";
    }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng</title>
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

        table,
        th,
        td {
            border: 1px solid #ddd;
        }

        th,
        td {
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
            if (isset($_SESSION['user_id'])) {
                echo '<a href="logout.php">ĐĂNG XUẤT</a>';
            } else {
                echo '<a href="login.php">ĐĂNG NHẬP</a>';
            }
            ?>
        </nav>

        <div class="train-list">
            <h2>Giỏ hàng của bạn</h2>
            <?php if (!empty($_SESSION['cart'])): ?>
                <table>
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Thông tin ghế</th>
                            <th>Giá vé</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $tong_gia = 0;
                    $stt = 1;
                    foreach ($_SESSION['cart'] as $seat_id):
                        // Truy vấn thông tin ghế và chuyến tàu
                        $stmt = $conn->prepare("
                        SELECT ghe.id AS ghe_id, ghe.so_ghe, chuyen_tau.ten_tau, chuyen_tau.ga_di, chuyen_tau.ga_den, 
                            chuyen_tau.ngay_di, chuyen_tau.gio_di, chuyen_tau.ngay_den, chuyen_tau.gio_den, chuyen_tau.gia_ve 
                        FROM ghe 
                        JOIN chuyen_tau ON ghe.id_tau = chuyen_tau.id 
                        WHERE ghe.id = ?
                    ");

                        $stmt->bind_param("i", $seat_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $seat = $result->fetch_assoc();
                        if ($seat) {
                            $tong_gia += $seat['gia_ve']; // Cộng giá vé vào tổng giá
                        }
                    ?>
                    <tr>
                        <td><?php echo $stt++; ?></td>
                        <td>
                            Ghế số: <?php echo $seat['so_ghe']; ?><br>
                            Tên tàu: <?php echo $seat['ten_tau']; ?><br>
                            Ga đi: <?php echo $seat['ga_di']; ?><br>
                            Ga đến: <?php echo $seat['ga_den']; ?><br>
                            Thời gian: <?php echo $seat['ngay_di'] . ' ' . $seat['gio_di'] . ' --- ' . $seat['ngay_den'] . ' ' . $seat['gio_den']; ?></>
                        </td>
                        <td>
                        <?php echo number_format($seat['gia_ve'], 0, ',', '.'); ?> VNĐ
                        </td>
                        <td>
                            <form method="POST" action="">
                                <button class="confirm-btn" type="submit" name="remove_seat" value="<?php echo $seat_id; ?>">Xóa</button>
                            </form>
                        </td>
                    </tr>
                    <?php
                        $stmt->close();
                    endforeach;
                    ?>

                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" style="text-align: right; font-weight: bold;" >Tổng tiền:</td>
                            <td colspan="2" style="font-weight: bold;">
                                <?php echo number_format($tong_gia, 0, ',', '.'); ?> VNĐ
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <br>

            <?php else: ?>
                <p>Giỏ hàng của bạn trống. <a href="tim_kiem.php">Quay lại để chọn vé</a>.</p>
            <?php endif; ?>
        </div>
        <div class="train-list">
        <form method="POST" action="">
            <h3>Phương thức thanh toán</h3>
            <label>
                <input type="radio" name="payment_method" value="online" required> Thanh toán online
            </label>
            <br>
            <label>
                <input type="radio" name="payment_method" value="offline" required> Thanh toán khi nhận vé (offline)
            </label>
            <br><br>
            <button class="confirm-btn" type="submit" name="confirm_booking">Xác nhận đặt vé</button>
        </form>

        </div>

        <footer>
            <p>Đoàn Đức Bình IT4.K23</p>
        </footer>
    </div>
</body>

</html>