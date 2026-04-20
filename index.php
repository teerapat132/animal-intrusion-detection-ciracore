<?php
// เชื่อมต่อฐานข้อมูล
$con = mysqli_connect("localhost", "root", "", "cira_db");
if (!$con) { die("Connection failed: " . mysqli_connect_error()); }

function translateName($name) {
    $th_names = ["DOG" => "สุนัข", "CAT" => "แมว", "CHICKEN" => "ไก่", "BUFFALO" => "ควาย"];
    return isset($th_names[$name]) ? $th_names[$name] : $name;
}

// ดึงยอดรวม
$total_all = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(id) AS total FROM tbl_object"))['total'];
$total_today = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(id) AS total FROM tbl_object WHERE DATE(activedatetime) = CURDATE()"))['total'];

// ดึงข้อมูลกราฟ
$res_group = mysqli_query($con, "SELECT name, COUNT(id) as count FROM tbl_object GROUP BY name");
$labels = []; $data = [];
while($row = mysqli_fetch_assoc($res_group)) {
    $labels[] = translateName($row['name']);
    $data[] = $row['count'];
}

// ดึงข้อมูลประวัติทั้งหมด (เพื่อลงตาราง)
$res_history = mysqli_query($con, "SELECT name, activedatetime FROM tbl_object ORDER BY activedatetime DESC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Dashboard | ระบบตรวจจับสัตว์บุกรุก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; }
        .stat-card { color: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .bg-primary-custom { background: linear-gradient(45deg, #007bff, #00c6ff); }
        .bg-warning-custom { background: linear-gradient(45deg, #ff9900, #ffc107); }
        canvas { cursor: pointer; } /* ให้รู้ว่ากราฟคลิกได้ */
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container"><span class="navbar-brand mb-0 h1"> AI Intrusion Detection Dashboard</span></div>
</nav>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-6"><div class="stat-card bg-primary-custom"><h4>การบุกรุกทั้งหมด</h4><h1 class="display-4 fw-bold"><?php echo $total_all; ?> <span class="fs-4">ครั้ง</span></h1></div></div>
        <div class="col-md-6"><div class="stat-card bg-warning-custom"><h4>การบุกรุกวันนี้</h4><h1 class="display-4 fw-bold"><?php echo $total_today; ?> <span class="fs-4">ครั้ง</span></h1></div></div>
    </div>

    <div class="row mb-4">
        <div class="col-md-7"><div class="card p-3 h-100"><h5 class="text-center">สถิติแยกตามชนิดสัตว์</h5><canvas id="barChart"></canvas></div></div>
        <div class="col-md-5"><div class="card p-3 h-100"><h5 class="text-center">สัดส่วนสัตว์ที่พบบ่อย (คลิกที่สีเพื่อค้นหา)</h5><canvas id="pieChart"></canvas></div></div>
    </div>

    <div class="row"><div class="col-12"><div class="card p-4 mb-5 shadow-sm">
        <h5 class="mb-3"> ประวัติการบุกรุก (Smart Table)</h5>
        <table id="historyTable" class="table table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr><th>ชนิดสัตว์</th><th>วัน-เวลา ที่ตรวจพบ</th><th>หลักฐาน</th></tr>
            </thead>
            <tbody>
                <?php 
                while($row = mysqli_fetch_assoc($res_history)) {
                    $raw_name = $row['name'];
                    $animal_th = translateName($raw_name);
                    $time_format = date('d/m/Y H:i:s', strtotime($row['activedatetime']));
                    
                    // 🔍 ระบบสแกนหาไฟล์แบบฉลาด: หาเฉพาะ "เวลา" ไม่สนชื่อสัตว์
                    $file_prefix = date('Ymd_His', strtotime($row['activedatetime']));
                    $img_url = "";
                    
                    // ค้นหาไฟล์ .jpg (เล็ก) หรือ .JPG (ใหญ่) ที่ขึ้นต้นด้วยเวลาที่กำหนด
                    $files_in_dir = glob("images/" . $file_prefix . "*.jpg"); 
                    if(empty($files_in_dir)) {
                        $files_in_dir = glob("images/" . $file_prefix . "*.JPG"); 
                    }

                    // ถ้าเจอรูป ให้เอารูปแรกมาโชว์ ถ้าไม่เจอให้ใส่รูป Default
                    if(!empty($files_in_dir)) {
                        $img_url = $files_in_dir[0]; 
                    } else {
                        $img_url = "https://via.placeholder.com/600x400?text=No+Image+Found";
                    }

                    echo "<tr>
                            <td><h5><span class='badge bg-danger'>{$animal_th}</span></h5></td>
                            <td>{$time_format}</td>
                            <td><button class='btn btn-sm btn-info text-white fw-bold' onclick='showImage(\"{$img_url}\", \"{$animal_th}\", \"{$time_format}\")'>👁️ ดูภาพ</button></td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>
    </div></div></div>
</div>

<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title" id="modalTitle">ภาพหลักฐาน</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center bg-light">
        <img id="modalImage" src="" class="img-fluid rounded shadow" alt="Evidence Image" onerror="this.src='https://via.placeholder.com/600x400?text=No+Image+Found';">
      </div>
    </div>
  </div>
</div>

<script>
    const labels = <?php echo json_encode($labels); ?>;
    const dataValues = <?php echo json_encode($data); ?>;
    const chartColors = ['#ff6384', '#36a2eb', '#ffce56', '#4bc0c0'];

    new Chart(document.getElementById('barChart'), { type: 'bar', data: { labels: labels, datasets: [{ label: 'จำนวน', data: dataValues, backgroundColor: chartColors }] }});

    const pieCtx = document.getElementById('pieChart');
    const pieChart = new Chart(pieCtx, { type: 'doughnut', data: { labels: labels, datasets: [{ data: dataValues, backgroundColor: chartColors }] }});

    $(document).ready(function() {
        // เปิดระบบ Smart Table
        var table = $('#historyTable').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json" },
            "pageLength": 5,
            "lengthMenu": [5, 10, 20, 50],
            "order": [[1, 'desc']] // ให้เรียงวันที่ล่าสุดขึ้นก่อนเสมอ
        });

        // ระบบกราฟสั่งการตาราง (คลิกกราฟแล้วกรองข้อมูล)
        pieCtx.onclick = function(evt) {
            var activePoints = pieChart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
            if (activePoints.length > 0) {
                var label = pieChart.data.labels[activePoints[0].index];
                table.search(label).draw(); // สั่งค้นหาชื่อสัตว์ในตารางทันที
            }
        };
    });

    // ฟังก์ชันเปิด Modal รูปภาพ
    function showImage(url, name, time) {
        document.getElementById('modalImage').src = url;
        document.getElementById('modalTitle').innerText = '📸 หลักฐาน: พบ' + name + ' (' + time + ')';
        new bootstrap.Modal(document.getElementById('imageModal')).show();
    }
</script>

</body>
</html>


