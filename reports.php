<?php
require_once 'config/db.php';
if(!isAdmin()) redirect('dashboard.php');

// Helper function to check if table exists and truncate it
function truncateTableIfExists($conn, $tableName) {
    try {
        $check = $conn->query("SHOW TABLES LIKE '$tableName'");
        if($check->num_rows > 0) {
            $conn->query("TRUNCATE TABLE $tableName");
            return true;
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

// Handle reset actions
if(isset($_POST['reset_reports'])) {
    try {
        // Disable foreign key checks temporarily
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        
        // Reset sales data
        $conn->query("TRUNCATE TABLE sale_items");
        $conn->query("TRUNCATE TABLE sales_master");
        
        // Reset auto-increment
        $conn->query("ALTER TABLE sale_items AUTO_INCREMENT = 1");
        $conn->query("ALTER TABLE sales_master AUTO_INCREMENT = 1");
        
        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        
        $_SESSION['message'] = "Reports have been reset successfully!";
        $_SESSION['message_type'] = "success";
    } catch (Exception $e) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $_SESSION['message'] = "Error resetting reports: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
    redirect('reports.php');
}

// FIXED: Reset Medicines - Also resets categories
if(isset($_POST['reset_medicines'])) {
    try {
        // Disable foreign key checks temporarily
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        
        // First, delete all medicines
        $conn->query("TRUNCATE TABLE medicines");
        $conn->query("ALTER TABLE medicines AUTO_INCREMENT = 1");
        
        // FIXED: Also reset categories
        $conn->query("TRUNCATE TABLE categories");
        $conn->query("ALTER TABLE categories AUTO_INCREMENT = 1");
        
        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        
        $_SESSION['message'] = "Medicines and categories have been reset successfully!";
        $_SESSION['message_type'] = "success";
    } catch (Exception $e) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $_SESSION['message'] = "Error resetting medicines: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
    redirect('reports.php');
}

// FIXED: Reset Services
if(isset($_POST['reset_services'])) {
    try {
        // Disable foreign key checks temporarily
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        
        // Reset services data
        truncateTableIfExists($conn, 'services');
        $conn->query("ALTER TABLE services AUTO_INCREMENT = 1");
        
        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        
        $_SESSION['message'] = "Services have been reset successfully!";
        $_SESSION['message_type'] = "success";
    } catch (Exception $e) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $_SESSION['message'] = "Error resetting services: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
    redirect('reports.php');
}

// Get chemist name and admin name for sidebar
$chemist_name = getSetting('chemist_name', $conn);
if(empty($chemist_name)) $chemist_name = 'City Chemist & Medical Store';
$admin_name = $_SESSION['name'] ?? 'Super Admin';

// ========== DAILY SALES (last 14 days) ==========
$daily_labels = [];
$daily_values = [];
$daily_query = $conn->query("
    SELECT sale_date, SUM(net_amount) as total 
    FROM sales_master 
    WHERE is_returned=0 AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY sale_date 
    ORDER BY sale_date ASC
");
while($row = $daily_query->fetch_assoc()){
    $daily_labels[] = date('d M', strtotime($row['sale_date']));
    $daily_values[] = (float)$row['total'];
}
// Fill missing dates with 0
$start = strtotime('-13 days');
for($i = 0; $i < 14; $i++){
    $date = date('Y-m-d', strtotime("+$i days", $start));
    if(!in_array(date('d M', strtotime($date)), $daily_labels)){
        array_splice($daily_labels, $i, 0, date('d M', strtotime($date)));
        array_splice($daily_values, $i, 0, 0);
    }
}

// ========== WEEKLY SALES (last 12 weeks) ==========
$weekly_labels = [];
$weekly_values = [];
$weekly_query = $conn->query("
    SELECT 
        YEAR(sale_date) as yr, 
        WEEK(sale_date) as wk, 
        SUM(net_amount) as total 
    FROM sales_master 
    WHERE is_returned=0 AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 11 WEEK)
    GROUP BY YEAR(sale_date), WEEK(sale_date)
    ORDER BY yr DESC, wk DESC
    LIMIT 12
");
$weeks = [];
while($row = $weekly_query->fetch_assoc()){
    $weeks[] = $row;
}
$weeks = array_reverse($weeks);
foreach($weeks as $w){
    $weekly_labels[] = "Wk {$w['wk']} {$w['yr']}";
    $weekly_values[] = (float)$w['total'];
}

// ========== MONTHLY SALES (last 12 months) ==========
$monthly_labels = [];
$monthly_values = [];
$monthly_query = $conn->query("
    SELECT 
        DATE_FORMAT(sale_date, '%Y-%m') as month, 
        SUM(net_amount) as total 
    FROM sales_master 
    WHERE is_returned=0 AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY DATE_FORMAT(sale_date, '%Y-%m')
    ORDER BY month ASC
");
while($row = $monthly_query->fetch_assoc()){
    $monthly_labels[] = date('M Y', strtotime($row['month'] . '-01'));
    $monthly_values[] = (float)$row['total'];
}

// ========== YEARLY SALES (all years with data) ==========
$yearly_labels = [];
$yearly_values = [];
$yearly_query = $conn->query("
    SELECT YEAR(sale_date) as year, SUM(net_amount) as total 
    FROM sales_master 
    WHERE is_returned=0 
    GROUP BY YEAR(sale_date)
    ORDER BY year ASC
");
while($row = $yearly_query->fetch_assoc()){
    $yearly_labels[] = $row['year'];
    $yearly_values[] = (float)$row['total'];
}

// ========== TOP SELLING MEDICINES ==========
$top_medicines = $conn->query("
    SELECT 
        si.item_name,
        SUM(si.quantity) as total_qty,
        SUM(si.total_price) as total_sales,
        AVG(si.unit_price) as avg_price
    FROM sale_items si
    JOIN sales_master sm ON si.sale_id = sm.id
    WHERE sm.is_returned = 0
    GROUP BY si.item_name
    ORDER BY total_sales DESC
    LIMIT 10
");

// ========== MEDICINE SALES BREAKDOWN (Last 30 days) ==========
$medicine_breakdown = $conn->query("
    SELECT 
        si.item_name,
        si.quantity,
        si.unit_price,
        si.total_price,
        sm.invoice_no,
        sm.sale_date,
        u.name as cashier
    FROM sale_items si
    JOIN sales_master sm ON si.sale_id = sm.id
    JOIN users u ON sm.cashier_id = u.id
    WHERE sm.is_returned = 0 
    AND sm.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY sm.sale_date DESC, si.id DESC
    LIMIT 100
");

// Overall stats
$total_revenue = $conn->query("SELECT SUM(net_amount) as total FROM sales_master WHERE is_returned=0")->fetch_assoc()['total'];
$total_transactions = $conn->query("SELECT COUNT(*) as count FROM sales_master WHERE is_returned=0")->fetch_assoc()['count'];
$avg_sale = $total_transactions > 0 ? $total_revenue / $total_transactions : 0;

// Total medicines sold
$total_items_sold = $conn->query("SELECT SUM(quantity) as total FROM sale_items si JOIN sales_master sm ON si.sale_id = sm.id WHERE sm.is_returned=0")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reports - Chemist POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f4f6f9; margin: 0; padding: 0; }
        .sidebar {
            min-height: 100vh;
            background: #2c3e50;
            color: white;
        }
        .sidebar a {
            color: white;
            display: block;
            padding: 12px 20px;
            text-decoration: none;
        }
        .sidebar a:hover, .sidebar .active {
            background: #1a252f;
        }
        .content {
            background: #f4f6f9;
            padding: 20px;
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .table-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        canvas { max-height: 300px; width: 100% !important; }
        .medicine-detail-table {
            font-size: 14px;
        }
        .medicine-detail-table th {
            background: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .top-medicine-item {
            padding: 8px 12px;
            border-bottom: 1px solid #eee;
        }
        .top-medicine-item:last-child {
            border-bottom: none;
        }
        .med-rank {
            display: inline-block;
            width: 25px;
            height: 25px;
            background: #2c3e50;
            color: white;
            text-align: center;
            line-height: 25px;
            border-radius: 50%;
            font-size: 12px;
            font-weight: bold;
            margin-right: 10px;
        }
        .med-rank.gold { background: #FFD700; color: #333; }
        .med-rank.silver { background: #C0C0C0; color: #333; }
        .med-rank.bronze { background: #CD7F32; color: white; }
        
        /* Reset buttons styling */
        .reset-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .reset-section h5 {
            color: #dc3545;
            border-bottom: 2px solid #dc3545;
            padding-bottom: 10px;
        }
        .reset-btn {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: bold;
            min-width: 150px;
        }
        .reset-btn:hover {
            transform: scale(1.02);
            transition: transform 0.2s;
        }
        .modal-content {
            border-radius: 15px;
        }
        .modal-header {
            background: #dc3545;
            color: white;
            border-radius: 15px 15px 0 0;
        }
        .modal-footer .btn-secondary {
            background: #6c757d;
        }
        .modal-footer .btn-danger {
            background: #dc3545;
        }
        
        .modal-header-warning {
            background: #ffc107 !important;
            color: #333 !important;
        }
        
        .modal-header-info {
            background: #17a2b8 !important;
            color: white !important;
        }
        
        .btn-warning-custom {
            background: #ffc107;
            color: #333;
            border: none;
        }
        
        .btn-warning-custom:hover {
            background: #e0a800;
            color: #333;
        }
        
        @media print {
            .no-print { display: none !important; }
            .sidebar { display: none !important; }
            .col-md-10 { width: 100% !important; }
            .content { padding: 10px; }
            .reset-section { display: none !important; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- SIDEBAR -->
        <div class="col-md-2 p-0 sidebar no-print">
            <div class="text-white text-center py-4 bg-dark">
                <h6><?= htmlspecialchars($chemist_name) ?></h6>
                <small>Admin: <?= htmlspecialchars($admin_name) ?></small>
            </div>
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="medicines.php"><i class="fas fa-tablets"></i> Medicines</a>
            <a href="services.php"><i class="fas fa-concierge-bell"></i> Services</a>
            <a href="cashiers.php"><i class="fas fa-users"></i> Cashiers</a>
            <a href="reports.php" class="active"><i class="fas fa-chart-line"></i> Reports</a>
            <a href="customize.php"><i class="fas fa-palette"></i> Customize</a>
            <a href="returns.php"><i class="fas fa-undo-alt"></i> Returns</a>
            <a href="backup.php"><i class="fas fa-download"></i> Backup PDF</a>
            <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        
        <!-- MAIN CONTENT -->
        <div class="col-md-10 content">
            <h2 class="mb-4"><i class="fas fa-chart-pie"></i> Sales Analytics (KES)</h2>
            
            <!-- Display messages -->
            <?php if(isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= $_SESSION['message_type'] ?? 'success' ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= $_SESSION['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
            <?php endif; ?>
            
            <!-- Stats Row -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stats-card">
                        <h5>Total Revenue</h5>
                        <h2 class="text-success"><?= getCurrency() . number_format($total_revenue ?? 0, 2) ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h5>Transactions</h5>
                        <h2 class="text-primary"><?= number_format($total_transactions ?? 0) ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h5>Avg Sale Value</h5>
                        <h2 class="text-info"><?= getCurrency() . number_format($avg_sale ?? 0, 2) ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h5>Items Sold</h5>
                        <h2 class="text-warning"><?= number_format($total_items_sold ?? 0) ?></h2>
                    </div>
                </div>
            </div>
            
            <!-- RESET SECTION -->
            <div class="reset-section no-print">
                <h5><i class="fas fa-exclamation-triangle"></i> Reset Data</h5>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <button type="button" class="btn btn-danger reset-btn" data-bs-toggle="modal" data-bs-target="#resetReportsModal">
                            <i class="fas fa-undo-alt"></i> Reset Reports
                        </button>
                        <small class="d-block text-muted mt-1">Reset all sales and transaction data</small>
                    </div>
                    <div class="col-md-4 mb-2">
                        <button type="button" class="btn btn-warning reset-btn" data-bs-toggle="modal" data-bs-target="#resetMedicinesModal">
                            <i class="fas fa-tablets"></i> Reset Medicines
                        </button>
                        <small class="d-block text-muted mt-1">Remove all medicine entries and categories</small>
                    </div>
                    <div class="col-md-4 mb-2">
                        <button type="button" class="btn btn-info reset-btn" data-bs-toggle="modal" data-bs-target="#resetServicesModal">
                            <i class="fas fa-concierge-bell"></i> Reset Services
                        </button>
                        <small class="d-block text-muted mt-1">Remove all service entries</small>
                    </div>
                </div>
            </div>
            
            <!-- CHARTS ROW 1 -->
            <div class="row">
                <div class="col-md-6">
                    <div class="chart-card">
                        <h5><i class="fas fa-calendar-day"></i> Daily Sales (Last 14 Days)</h5>
                        <canvas id="dailyChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-card">
                        <h5><i class="fas fa-calendar-week"></i> Weekly Sales (Last 12 Weeks)</h5>
                        <canvas id="weeklyChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- CHARTS ROW 2 -->
            <div class="row">
                <div class="col-md-6">
                    <div class="chart-card">
                        <h5><i class="fas fa-calendar-alt"></i> Monthly Sales (Last 12 Months)</h5>
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-card">
                        <h5><i class="fas fa-chart-pie"></i> Yearly Sales (All Years)</h5>
                        <canvas id="yearlyChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- TOP SELLING MEDICINES -->
            <div class="row">
                <div class="col-md-6">
                    <div class="table-card">
                        <h5><i class="fas fa-trophy"></i> Top Selling Medicines</h5>
                        <div class="table-responsive">
                            <?php 
                            $rank = 1;
                            if($top_medicines->num_rows > 0):
                            while($med = $top_medicines->fetch_assoc()): 
                                $rank_class = '';
                                if($rank == 1) $rank_class = 'gold';
                                elseif($rank == 2) $rank_class = 'silver';
                                elseif($rank == 3) $rank_class = 'bronze';
                            ?>
                            <div class="top-medicine-item d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="med-rank <?= $rank_class ?>"><?= $rank ?></span>
                                    <strong><?= htmlspecialchars($med['item_name']) ?></strong>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary">Qty: <?= number_format($med['total_qty']) ?></span>
                                    <br>
                                    <small><?= getCurrency() . number_format($med['total_sales'], 2) ?></small>
                                </div>
                            </div>
                            <?php 
                            $rank++;
                            endwhile; 
                            else: ?>
                            <div class="text-center text-muted py-3">No sales data available</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="table-card">
                        <h5><i class="fas fa-list"></i> Medicine Sales Breakdown</h5>
                        <small class="text-muted">Last 30 days - showing medicine, quantity, price, and total</small>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-striped table-hover medicine-detail-table">
                                <thead>
                                    <tr>
                                        <th>Medicine</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-center">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($medicine_breakdown->num_rows > 0): ?>
                                    <?php while($row = $medicine_breakdown->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['item_name']) ?></td>
                                        <td class="text-center"><?= $row['quantity'] ?></td>
                                        <td class="text-end"><?= getCurrency() . number_format($row['unit_price'], 2) ?></td>
                                        <td class="text-end fw-bold"><?= getCurrency() . number_format($row['total_price'], 2) ?></td>
                                        <td class="text-center"><small><?= date('d/m/Y', strtotime($row['sale_date'])) ?></small></td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">No sales in the last 30 days</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-3 no-print">
                <button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Print Report</button>
                <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
            </div>
        </div>
    </div>
</div>

<!-- Reset Reports Modal -->
<div class="modal fade" id="resetReportsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Reset All Reports</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <i class="fas fa-undo-alt" style="font-size: 60px; color: #dc3545;"></i>
                    <h4 class="mt-3">Are you sure?</h4>
                    <p class="text-muted">This action will permanently delete all sales records, transactions, and related data. This cannot be undone!</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> All sales history, invoices, and transaction records will be removed.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="reset_reports" class="btn btn-danger" onclick="return confirm('Are you absolutely sure? This cannot be undone!')">
                        <i class="fas fa-trash"></i> Yes, Reset All Reports
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reset Medicines Modal -->
<div class="modal fade" id="resetMedicinesModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-warning">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Reset All Medicines &amp; Categories</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <i class="fas fa-tablets" style="font-size: 60px; color: #ffc107;"></i>
                    <h4 class="mt-3">Are you sure?</h4>
                    <p class="text-muted">This action will permanently delete all medicine entries <strong>AND categories</strong> from the system. This cannot be undone!</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> All medicines, stock, prices, and categories will be removed.
                    </div>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> This will also remove all stock history and purchase records related to medicines.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="reset_medicines" class="btn btn-warning-custom" onclick="return confirm('Are you absolutely sure? This cannot be undone!')">
                        <i class="fas fa-trash"></i> Yes, Reset Medicines &amp; Categories
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reset Services Modal -->
<div class="modal fade" id="resetServicesModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-info">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Reset All Services</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <i class="fas fa-concierge-bell" style="font-size: 60px; color: #17a2b8;"></i>
                    <h4 class="mt-3">Are you sure?</h4>
                    <p class="text-muted">This action will permanently delete all service entries from the system. This cannot be undone!</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> All services, pricing, and related data will be removed.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="reset_services" class="btn btn-info" onclick="return confirm('Are you absolutely sure? This cannot be undone!')">
                        <i class="fas fa-trash"></i> Yes, Reset Services
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Daily Chart (Bar)
new Chart(document.getElementById('dailyChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($daily_labels) ?>,
        datasets: [{
            label: 'Sales (KES)',
            data: <?= json_encode($daily_values) ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.6)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: { responsive: true, maintainAspectRatio: true }
});

// Weekly Chart (Line)
new Chart(document.getElementById('weeklyChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($weekly_labels) ?>,
        datasets: [{
            label: 'Sales (KES)',
            data: <?= json_encode($weekly_values) ?>,
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 2,
            tension: 0.3,
            fill: true
        }]
    },
    options: { responsive: true, maintainAspectRatio: true }
});

// Monthly Chart (Bar)
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($monthly_labels) ?>,
        datasets: [{
            label: 'Sales (KES)',
            data: <?= json_encode($monthly_values) ?>,
            backgroundColor: 'rgba(153, 102, 255, 0.6)',
            borderColor: 'rgba(153, 102, 255, 1)',
            borderWidth: 1
        }]
    },
    options: { responsive: true, maintainAspectRatio: true }
});

// Yearly Chart (Pie)
new Chart(document.getElementById('yearlyChart'), {
    type: 'pie',
    data: {
        labels: <?= json_encode($yearly_labels) ?>,
        datasets: [{
            data: <?= json_encode($yearly_values) ?>,
            backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40']
        }]
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>