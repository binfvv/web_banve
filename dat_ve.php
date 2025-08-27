<?php
include 'includes/db.php';
session_start();

// Lấy ID chuyến tàu từ query string
$train_id = isset($_GET['id']) ? $_GET['id'] : null;

// Khởi tạo giỏ hàng nếu chưa có
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$seats = [];
// Kiểm tra nếu ID chuyến tàu tồn tại
if ($train_id) {
    // Truy vấn để lấy thông tin ghế của chuyến tàu
    $sql = "SELECT * FROM ghe WHERE id_tau = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $train_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $seats = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $seats[] = $row;
        }
    }
    $stmt->close();
}

// Kiểm tra nếu người dùng đã đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Xử lý khi thêm ghế vào giỏ hàng
if (isset($_POST['selected_seats'])) {
    $selected_seats = json_decode($_POST['selected_seats'], true); // Mảng ghế được chọn

    // Thêm các ghế vào session giỏ hàng
    foreach ($selected_seats as $seat_id) {
        if (!in_array($seat_id, $_SESSION['cart'])) {
            $_SESSION['cart'][] = $seat_id;
        }
    }
    echo json_encode(['status' => 'success', 'message' => 'Ghế đã được thêm vào giỏ hàng!']);
    exit;
}


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt vé</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .toa_tau_container {
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }
        .toa_tau {
            width: 5vw;
            height: 4vw;
        }
    </style>
</head>
<script>
   
let selectedSeats = [];

function selectSeat(seatElement) {
    if (seatElement.classList.contains('booked')) return;

    const seatId = seatElement.getAttribute('data-seat-id');
    if (seatElement.classList.contains('selected')) {
        seatElement.classList.remove('selected');
        selectedSeats = selectedSeats.filter(id => id !== seatId);
    } else {
        seatElement.classList.add('selected');
        selectedSeats.push(seatId);
    }
}

function addToCart() {
    if (selectedSeats.length === 0) {
        alert('Vui lòng chọn ít nhất một ghế!');
        return;
    }

    console.log("Ghế đã chọn:", selectedSeats); // Kiểm tra danh sách ghế đã chọn

    const formData = new FormData();
    formData.append('selected_seats', JSON.stringify(selectedSeats));

    fetch('dat_ve.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log("Kết quả từ server:", data); // Kiểm tra phản hồi từ server
        if (data.status === 'success') {
            alert(data.message);
            window.location.href = 'gio_hang.php'; // Chuyển hướng đến giỏ hàng
        } else {
            alert('Lỗi khi thêm vào giỏ hàng.');
        }
    })
    .catch(error => console.error('Error:', error));
}

</script>
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
        <div class="dat_ve">
            <h2>Chọn ghế</h2>
            <div class="toa_tau_container">
                <img class="toa_tau" src="assets/toa_sau.png" alt="">
                <img class="toa_tau" src="assets/toa_sau.png" alt="">
                <img class="toa_tau" src="assets/toa_sau.png" alt="">
                <img class="toa_tau" src="assets/toa_sau.png" alt="">
                <img class="toa_tau" src="assets/toa_dau.png" alt="">
            </div>
            <br>
            <div class="seat-selection">
                <div class="seats">
                <?php
                if (!empty($seats)) {
                    $count = 0;
                    foreach ($seats as $seat):
                        if ($count % 5 == 0) {
                            if ($count != 0) echo '</div>';
                            echo '<div class="seat-row">';
                        }
                        ?>
                        <div class="seat <?php echo ($seat['trang_thai'] == 'da_dat') ? 'booked' : 'available'; ?>"
                            data-seat-id="<?php echo $seat['id']; ?>"
                            onclick="selectSeat(this)">
                            <?php echo $seat['so_ghe']; ?>
                        </div>
                        <?php
                        $count++;
                        if ($count % 5 == 0) echo '</div>';
                    endforeach;
                    if ($count % 5 != 0) echo '</div>';
                } else {
                    echo "Không có ghế nào để chọn.";
                }
                ?>
                </div>

                <button class="confirm-btn" onclick="addToCart()">Thêm vào giỏ hàng</button>
            </div>
            
        </div>
    </div>
</body>
</html>
