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
    <style>
      h3 {
            text-align: left;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border: 2px solid black;
        }
        table, th, td {
            border: 1px solid #000000;
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
        .trang_chu{
            width: 100%;
            margin-left: 80px;
            margin-right: 80px;
            /* padding: 20px; */
        }
    </style>
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
        <article>
            <div class="trang_chu">
                <div class="panel-heading">
                    <h3 class="panel-title">ĐIỀU KIỆN HÀNH KHÁCH ĐI TÀU TỪ NGÀY 27/10/2021</h3>
                </div>
                <div class="panel-body">
                    <p><strong>1. Đối với hành khách đi từ địa phương/khu vực cấp độ dịch là cấp 1,2:</strong></p>
                    <p>- Tuân thủ “Thông điệp 5K”; khai báo y tế trên ứng dụng PC-Covid.</p>
                    <p>- Thực hiện nghiêm các biện pháp phòng, chống dịch COVID-19 theo hướng dẫn của Ban Chỉ đạo Quốc gia phòng, chống dịch COVID-19 và Bộ Y tế.</p>

                    <p><strong>2. Đối với hành khách đi từ địa phương/khu vực cấp độ dịch là cấp 3:</strong></p>
                    <p>- Thực hiện theo nội dung mục (1.) nêu trên;</p>
                    <p>- Xét nghiệm các trường hợp có một trong các biểu hiện triệu chứng sốt, ho, mệt mỏi, đau họng, mất vị giác và khứu giác, khó thở… hoặc có chỉ định điều tra dịch tễ.</p>

                    <p><strong>3. Đối với hành khách đi từ địa phương/khu vực cấp độ dịch là cấp 4:</strong></p>
                    <p>- Ngoài việc thực hiện theo nội dung mục 1 nêu trên hành khách phải có kết quả xét nghiệm SARS-CoV-2 bằng phương pháp RT-PCR hoặc xét nghiệm nhanh kháng nguyên âm tính trong vòng 72 giờ trước khi lên tàu.</p>
                    <p>- Chỉ đặt mua vé, đi tàu trên toa dành riêng của đoàn tàu.</p>

                    <div class="alert alert-danger" style="margin-bottom:0px;">
                        <strong>Lưu ý: Hành khách không tuân thủ các điều kiện đi tàu như trên Ngành đường sắt từ chối chuyên chở và không trả lại tiền vé.</strong>
                    </div>
                </div>
            </div>
        </article>
        <aside>
              <!-- Danh sách các chuyến tàu tìm kiếm được -->
        <div class="train-list-asile">
            <h3>Lịch trình nổi bật</h3>
            <table>
                <thead>
                    <tr>
                        <th>Ga đi</th>
                        <th>Ga đến</th>
                        <th>Thời gian khởi hành</th>
                        <th>Thời gian đến</th>
                        <th>Giá vé</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Hà Nội</td>
                        <td>TP. Hồ Chí Minh</td>
                        <td>08:00 12/11/2024</td>
                        <td>18:00 13/11/2024</td>
                        <td>1,200,000 VND</td>
                    </tr>
                    <tr>
                        <td>TP. Hồ Chí Minh</td>
                        <td>Hà Nội</td>
                        <td>09:00 13/11/2024</td>
                        <td>19:00 14/11/2024</td>
                        <td>1,200,000 VND</td>
                    </tr>
                    <tr>
                        <td>Quảng Ninh</td>
                        <td>Hà Nội</td>
                        <td>09:00 13/11/2024</td>
                        <td>19:00 14/11/2024</td>
                        <td>200,000 VND</td>
                    </tr>
                    <tr>
                        <td>Hải Phòng</td>
                        <td>Hà Nội</td>
                        <td>09:00 13/11/2024</td>
                        <td>19:00 14/11/2024</td>
                        <td>200,000 VND</td>
                    </tr>
                    <tr>
                        <td>Hà Nội</td>
                        <td>Hải Phòng</td>
                        <td>09:00 13/11/2024</td>
                        <td>19:00 14/11/2024</td>
                        <td>200,000 VND</td>
                    </tr>
                </tbody>
            </table>
        </div>
        </aside>
       
        <footer>
            <p>Đoàn Đức Bình IT4.K23 </p>
        </footer>
    </div>
</body>
</html>
