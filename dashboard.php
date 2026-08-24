<?php require_once 'config/db.php';
if(!isLoggedIn()) redirect('index.php');
$isAdmin = isAdmin();
$today = date('Y-m-d');
$total_sales_today = 0;
$low_stock_count = 0;
$expired_count = 0;
$total_medicines = 0;

if($isAdmin) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(net_amount),0) as total FROM sales_master WHERE sale_date = ? AND is_returned=0");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $total_sales_today = $stmt->get_result()->fetch_assoc()['total'];
    
    $total_medicines = $conn->query("SELECT COUNT(*) as c FROM medicines")->fetch_assoc()['c'];
    $low_stock_count = $conn->query("SELECT COUNT(*) as c FROM medicines WHERE stock_quantity <= 10")->fetch_assoc()['c'];
    $expired_count = $conn->query("SELECT COUNT(*) as c FROM medicines WHERE expiry_date < CURDATE()")->fetch_assoc()['c'];
} else {
    $uid = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COALESCE(SUM(net_amount),0) as total FROM sales_master WHERE cashier_id = ? AND sale_date = ? AND is_returned=0");
    $stmt->bind_param("is", $uid, $today);
    $stmt->execute();
    $total_sales_today = $stmt->get_result()->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f4f6f9; }
        .sidebar { min-height: 100vh; background: #2c3e50; }
        .sidebar a { color: white; display: block; padding: 12px 20px; text-decoration: none; }
        .sidebar a:hover { background: #1a252f; }
        .card-stats { border-left: 5px solid #007bff; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 p-0 sidebar">
            <div class="text-white text-center py-4 bg-dark">
                <h6><?= htmlspecialchars(getSetting('chemist_name', $conn)) ?></h6>
                <small><?= ucfirst($_SESSION['role']) ?>: <?= $_SESSION['name'] ?></small>
            </div>
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <?php if($isAdmin): ?>
                <a href="medicines.php"><i class="fas fa-tablets"></i> Medicines</a>
                <a href="services.php"><i class="fas fa-concierge-bell"></i> Services</a>
                <a href="cashiers.php"><i class="fas fa-users"></i> Cashiers</a>
                <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
                <a href="customize.php"><i class="fas fa-palette"></i> Customize</a>
                <a href="returns.php"><i class="fas fa-undo-alt"></i> Returns</a>
                <a href="backup.php"><i class="fas fa-database"></i> Backup PDF</a>
            <?php else: ?>
                <a href="pos.php"><i class="fas fa-shopping-cart"></i> POS Sale</a>
                <a href="my_sales.php"><i class="fas fa-receipt"></i> My Sales</a>
            <?php endif; ?>
            <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-10 content p-4">
            <h2>Dashboard</h2>
            <div class="row mt-3">
                <div class="col-md-3">
                    <div class="card card-stats p-3"><h5>Today's Sales</h5><h3><?= getCurrency().number_format($total_sales_today,2) ?></h3></div>
                </div>
                <?php if($isAdmin): ?>
                <div class="col-md-3">
                    <div class="card card-stats p-3"><h5>Total Medicines</h5><h3><?= $total_medicines ?></h3></div>
                </div>
                <div class="col-md-3">
                    <div class="card card-stats p-3 border-left-warning"><h5>Low Stock (<10)</h5><h3 class="text-warning"><?= $low_stock_count ?></h3></div>
                </div>
                <div class="col-md-3">
                    <div class="card card-stats p-3"><h5>Expired</h5><h3 class="text-danger"><?= $expired_count ?></h3></div>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card"><div class="card-header bg-warning">⚠️ Low Stock Alerts</div>
                        <ul class="list-group"><?php $low = $conn->query("SELECT name,stock_quantity FROM medicines WHERE stock_quantity<=10 LIMIT 5"); while($r=$low->fetch_assoc()){ echo "<li class='list-group-item'>{$r['name']} - Stock: {$r['stock_quantity']}</li>"; } ?></ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card"><div class="card-header bg-danger text-white">📅 Expired Medicines</div>
                        <ul class="list-group"><?php $exp = $conn->query("SELECT name,expiry_date FROM medicines WHERE expiry_date<CURDATE() LIMIT 5"); while($r=$exp->fetch_assoc()){ echo "<li class='list-group-item'>{$r['name']} - Expired: {$r['expiry_date']}</li>"; } ?></ul>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>