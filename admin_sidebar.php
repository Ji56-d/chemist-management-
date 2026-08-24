<?php
// admin_sidebar.php - Reusable admin sidebar
$chemist_name = getSetting('chemist_name', $conn);
$admin_name = $_SESSION['name'] ?? 'Admin';
?>
<div class="col-md-2 p-0 sidebar">
    <div class="text-white text-center py-4 bg-dark">
        <h6><?= htmlspecialchars($chemist_name) ?></h6>
        <small>Admin: <?= htmlspecialchars($admin_name) ?></small>
    </div>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="medicines.php"><i class="fas fa-tablets"></i> Medicines</a>
    <a href="services.php"><i class="fas fa-concierge-bell"></i> Services</a>
    <a href="cashiers.php"><i class="fas fa-users"></i> Cashiers</a>
    <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="customize.php"><i class="fas fa-palette"></i> Customize</a>
    <a href="returns.php"><i class="fas fa-undo-alt"></i> Returns</a>
    <a href="backup.php"><i class="fas fa-download"></i> Backup PDF</a>
    <a href="upload_medicines.php"><i class="fas fa-file-pdf"></i> Upload PDF</a>
    <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>