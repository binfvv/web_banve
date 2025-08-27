<?php
include 'includes/db.php'; // Kết nối tới cơ sở dữ liệu
session_start()
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tìm kiếm chuyến tàu</title>
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
          <div class="tim_kiem">
            <form action="ket_qua_tim_kiem.php" method="post">
              <h2>Tìm kiếm chuyến tàu</h2>

              <div class="form-group">
                <div class="input-group">
                  <div>
                    <label for="ga_di">Ga đi</label>
                    <select name="ga_di" id="ga_di">
                      <option value="" disabled selected>Chọn ga đi</option>
                      <option value="Hải Phòng">Hải Phòng</option>
                      <option value="Hà Nội">Hà Nội</option>
                      <option value="Hồ Chí Minh">Hồ Chí Minh</option>
                      <option value="Quảng Ninh">Quảng Ninh</option>
                      <option value="Đà Nẵng">Đà Nẵng</option>
                      <option value="Nha Trang">Nha Trang</option>
                    </select>
                  </div>
                  <div>
                    <label for="ga_den">Ga đến</label>
                    <select name="ga_den" id="ga_den">
                      <option value="" disabled selected>Chọn ga đến</option>
                      <option value="Hải Phòng">Hải Phòng</option>
                      <option value="Hà Nội">Hà Nội</option>
                      <option value="Hồ Chí Minh">Hồ Chí Minh</option>
                      <option value="Quảng Ninh">Quảng Ninh</option>
                      <option value="Đà Nẵng">Đà Nẵng</option>
                      <option value="Nha Trang">Nha Trang</option>
                    </select>
                  </div>
                </div>
              </div>

              <div class="form-group">
                <div class="input-group">
                  <div>
                    <label for="ngay_di">Ngày đi</label>
                    <input type="date" id="ngay_di" name="ngay_di" required>
                  </div>
                  <div>
                    <label for="ngay_ve">Ngày về</label>
                    <input type="date" id="ngay_ve" name="ngay_ve" disabled>
                  </div>
                </div>
              </div>
              <br>
              <div class="form-group">
                <label>Loại vé</label> 
                <div class="radio-group">
                  <label><input type="radio" name="ticket_type" value="Một chiều" checked required onclick="toggleNgayVe()"> Một chiều</label>
                  <label><input type="radio" name="ticket_type" value="Khứ hồi" onclick="toggleNgayVe()"> Khứ hồi</label>
                </div>
              </div>
              <br>
              <input type="submit" name="btn_tk" value="Tìm kiếm">
            </form>
          </div>

<script>
  function toggleNgayVe() {
    const ngayVeField = document.getElementById("ngay_ve");
    const isReturnTrip = document.querySelector('input[name="ticket_type"]:checked').value === "Khứ hồi";

    ngayVeField.disabled = !isReturnTrip;
    if (!isReturnTrip) {
      ngayVeField.value = ""; // Xóa giá trị nếu không phải là khứ hồi
    }
  }

  // Gọi hàm khi trang tải để đảm bảo trạng thái ban đầu đúng
  document.addEventListener("DOMContentLoaded", toggleNgayVe);
</script>

          
          
          

        <footer>
            <p>Đoàn Đức Bình IT4.K23 </p>
        </footer>
    </div>
</body>
</html>