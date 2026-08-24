<?php
require_once 'config/db.php';
if(!isAdmin()) redirect('dashboard.php');

// Check if we need to export the HTML report
if(isset($_GET['export_pdf'])){
    // ========== REPORT GENERATION CODE (unchanged) ==========
    // Create HTML content for the backup report
    $html_content = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>System Backup Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { color: #2c3e50; margin-bottom: 5px; }
            .header p { color: #7f8c8d; margin-top: 0; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th { background-color: #3498db; color: white; padding: 10px; text-align: left; border: 1px solid #ddd; }
            td { padding: 8px; border: 1px solid #ddd; }
            tr:nth-child(even) { background-color: #f2f2f2; }
            .section-title { background-color: #2c3e50; color: white; padding: 10px; margin-top: 20px; margin-bottom: 10px; }
            .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #7f8c8d; }
            .amount { text-align: right; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>' . htmlspecialchars(getSetting('chemist_name', $conn)) . '</h1>
            <p>System Backup Report - Generated: ' . date('Y-m-d H:i:s') . '</p>
            <p>Currency: Kenyan Shillings (KES)</p>
        </div>';
    
    // Sales Summary (last 50 transactions)
    $html_content .= '
        <div class="section-title">📊 Sales Summary (Last 50 Transactions)</div>
        <table>
            <thead>
                <tr><th>Invoice No</th><th>Date</th><th>Cashier</th><th>Total (KES)</th><th>Discount (KES)</th><th>Net (KES)</th><th>Payment</th></tr>
            </thead>
            <tbody>';
    $sales = $conn->query("SELECT sm.*, u.name as cashier FROM sales_master sm JOIN users u ON sm.cashier_id=u.id ORDER BY sm.id DESC LIMIT 50");
    while($row = $sales->fetch_assoc()){
        $html_content .= '<tr>
            <td>' . $row['invoice_no'] . '</td>
            <td>' . $row['sale_date'] . '</td>
            <td>' . htmlspecialchars($row['cashier']) . '</td>
            <td class="amount">' . number_format($row['total_amount'], 2) . '</td>
            <td class="amount">' . number_format($row['discount'], 2) . '</td>
            <td class="amount">' . number_format($row['net_amount'], 2) . '</td>
            <td>' . $row['payment_method'] . '</td>
        </tr>';
    }
    $html_content .= '</tbody>
    </table>';
    
    // Payment Methods Summary
    $payment_summary = $conn->query("SELECT payment_method, COUNT(*) as count, SUM(net_amount) as total FROM sales_master GROUP BY payment_method");
    $html_content .= '
        <div class="section-title">💳 Payment Methods Summary</div>
        <table>
            <thead><tr><th>Payment Method</th><th>Number of Transactions</th><th>Total Amount (KES)</th></thead>
            <tbody>';
    while($row = $payment_summary->fetch_assoc()){
        $html_content .= '<tr>
            <td>' . $row['payment_method'] . '</td>
            <td>' . $row['count'] . '</td>
            <td class="amount">' . number_format($row['total'], 2) . '</td>
        </tr>';
    }
    $html_content .= '</tbody>
    </table>';
    
    // Low Stock Items
    $html_content .= '
        <div class="section-title">⚠️ Low Stock Items (Stock ≤ 10)</div>
        <table>
            <thead>
                <tr><th>Medicine Name</th><th>Current Stock</th><th>Unit Type</th><th>Selling Price (KES)</th><th>Expiry Date</th><th>Supplier</th></tr>
            </thead>
            <tbody>';
    $lowStock = $conn->query("SELECT name, stock_quantity, unit_type, selling_price, expiry_date, supplier FROM medicines WHERE stock_quantity <= 10 ORDER BY stock_quantity ASC");
    if($lowStock->num_rows > 0){
        while($row = $lowStock->fetch_assoc()){
            $expiry_class = (strtotime($row['expiry_date']) < time()) ? 'style="color: red;"' : '';
            $html_content .= '<td>
                <td>' . htmlspecialchars($row['name']) . '</td>
                <td ' . $expiry_class . '>' . $row['stock_quantity'] . '</td>
                <td>' . $row['unit_type'] . '</td>
                <td class="amount">' . number_format($row['selling_price'], 2) . '</td>
                <td ' . $expiry_class . '>' . $row['expiry_date'] . '</td>
                <td>' . htmlspecialchars($row['supplier'] ?? 'N/A') . '</td>
            </tr>';
        }
    } else {
        $html_content .= '<tr><td colspan="6">No low stock items found</td></tr>';
    }
    $html_content .= '</tbody>
    </table>';
    
    // Expired Medicines
    $html_content .= '
        <div class="section-title">📅 Expired Medicines</div>
        <table>
            <thead>
                <tr><th>Medicine Name</th><th>Stock Quantity</th><th>Expiry Date</th><th>Cost Price (KES)</th><th>Selling Price (KES)</th><th>Supplier</th></tr>
            </thead>
            <tbody>';
    $expired = $conn->query("SELECT name, stock_quantity, expiry_date, cost_price, selling_price, supplier FROM medicines WHERE expiry_date < CURDATE() ORDER BY expiry_date ASC");
    if($expired->num_rows > 0){
        while($row = $expired->fetch_assoc()){
            $html_content .= '<tr>
                <td>' . htmlspecialchars($row['name']) . '</td>
                <td>' . $row['stock_quantity'] . '</td>
                <td style="color: red;">' . $row['expiry_date'] . '</td>
                <td class="amount">' . number_format($row['cost_price'], 2) . '</td>
                <td class="amount">' . number_format($row['selling_price'], 2) . '</td>
                <td>' . htmlspecialchars($row['supplier'] ?? 'N/A') . '</td>
            </tr>';
        }
    } else {
        $html_content .= '<table><td colspan="6">No expired medicines found</td></tr>';
    }
    $html_content .= '</tbody>
    </table>';
    
    // Complete Medicine Inventory
    $html_content .= '
        <div class="section-title">💊 Complete Medicine Inventory</div>
        <table>
            <thead>
                <tr><th>ID</th><th>Medicine Name</th><th>Category</th><th>Stock</th><th>Unit Type</th><th>Selling Price (KES)</th><th>Expiry Date</th></tr>
            </thead>
            <tbody>';
    $medicines = $conn->query("SELECT m.*, c.name as cat_name FROM medicines m LEFT JOIN categories c ON m.category_id=c.id ORDER BY m.name");
    while($row = $medicines->fetch_assoc()){
        $expiry_style = (strtotime($row['expiry_date']) < strtotime(date('Y-m-d'))) ? 'style="color: red;"' : '';
        $html_content .= '<tr>
            <td>' . $row['id'] . '</td>
            <td>' . htmlspecialchars($row['name']) . '</td>
            <td>' . ($row['cat_name'] ?? 'Uncategorized') . '</td>
            <td>' . $row['stock_quantity'] . '</td>
            <td>' . $row['unit_type'] . '</td>
            <td class="amount">' . number_format($row['selling_price'], 2) . '</td>
            <td ' . $expiry_style . '>' . $row['expiry_date'] . '</td>
        </tr>';
    }
    $html_content .= '</tbody>
    </table>';
    
    // Services
    $html_content .= '
        <div class="section-title">🛠️ Services Offered</div>
        <table>
            <thead><tr><th>Service Name</th><th>Price (KES)</th><th>Description</th><th>Status</th></tr></thead>
            <tbody>';
    $services = $conn->query("SELECT * FROM services ORDER BY name");
    while($row = $services->fetch_assoc()){
        $html_content .= '<tr>
            <td>' . htmlspecialchars($row['name']) . '</td>
            <td class="amount">' . number_format($row['price'], 2) . '</td>
            <td>' . htmlspecialchars($row['description'] ?? 'N/A') . '</td>
            <td>' . ($row['status'] ? 'Active' : 'Inactive') . '</td>
        </tr>';
    }
    $html_content .= '</tbody>
    </table>';
    
    // System Users
    $html_content .= '
        <div class="section-title">👥 System Users (Cashiers & Admins)</div>
        </table>
            <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Created Date</th></tr></thead>
            <tbody>';
    $users = $conn->query("SELECT name, username, role, created_at FROM users ORDER BY role, name");
    while($row = $users->fetch_assoc()){
        $html_content .= '<tr>
            <td>' . htmlspecialchars($row['name']) . '</td>
            <td>' . htmlspecialchars($row['username']) . '</td>
            <td>' . ucfirst($row['role']) . '</td>
            <td>' . $row['created_at'] . '</td>
        </tr>';
    }
    $html_content .= '</tbody>
    </table>';
    
    // Footer
    $html_content .= '
        <div class="footer">
            <hr>
            <p>' . nl2br(htmlspecialchars(getSetting('receipt_footer', $conn))) . '</p>
            <p>This report was generated automatically by Chemist POS System - Kenya<br>
            Date: ' . date('Y-m-d H:i:s') . ' | User: ' . htmlspecialchars($_SESSION['name']) . '</p>
        </div>
    </body>
    </html>';
    
    // Save HTML to a temporary file and force download
    $filename = 'backup_report_' . date('Ymd_His') . '.html';
    file_put_contents($filename, $html_content);
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    readfile($filename);
    unlink($filename);
    exit;
}

// ========== DASHBOARD VIEW (for backup.php) ==========
// Statistics for the dashboard view
$total_sales = $conn->query("SELECT COUNT(*) as count, SUM(net_amount) as total FROM sales_master")->fetch_assoc();
$total_medicines = $conn->query("SELECT COUNT(*) as count FROM medicines")->fetch_assoc()['count'];
$total_cashiers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='cashier'")->fetch_assoc()['count'];
$low_stock = $conn->query("SELECT COUNT(*) as count FROM medicines WHERE stock_quantity <= 10")->fetch_assoc()['count'];
$expired = $conn->query("SELECT COUNT(*) as count FROM medicines WHERE expiry_date < CURDATE()")->fetch_assoc()['count'];

// Get chemist name and admin name for sidebar
$chemist_name = getSetting('chemist_name', $conn);
if(empty($chemist_name)) $chemist_name = 'City Chemist & Medical Store';
$admin_name = $_SESSION['name'] ?? 'Super Admin';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Backup - PDF Export</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f4f6f9; margin: 0; padding: 0; }
        /* Sidebar styles – exactly as in the image */
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
        .sidebar a:hover {
            background: #1a252f;
        }
        .sidebar .active {
            background: #1a252f;
        }
        .content {
            background: #f4f6f9;
            padding: 20px;
        }
        .stats-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stats-card:hover { transform: translateY(-5px); }
        .border-primary { border-left-color: #3498db !important; }
        .border-success { border-left-color: #27ae60 !important; }
        .border-warning { border-left-color: #f39c12 !important; }
        .border-danger { border-left-color: #e74c3c !important; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- SIDEBAR – EXACTLY AS IN THE IMAGE -->
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
            <a href="backup.php" class="active"><i class="fas fa-download"></i> Backup PDF</a>
            <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        
        <!-- MAIN CONTENT AREA -->
        <div class="col-md-10 content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-database"></i> System Backup - PDF Export</h2>
                <span class="badge bg-primary">KES Currency</span>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card border-primary">
                        <div class="card-body">
                            <h6 class="text-muted">Total Sales</h6>
                            <h3 class="mb-0"><?= getCurrency() . number_format($total_sales['total'] ?? 0, 2) ?></h3>
                            <small class="text-muted"><?= $total_sales['count'] ?? 0 ?> transactions</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card border-success">
                        <div class="card-body">
                            <h6 class="text-muted">Total Medicines</h6>
                            <h3 class="mb-0"><?= $total_medicines ?></h3>
                            <small class="text-muted">in inventory</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card border-warning">
                        <div class="card-body">
                            <h6 class="text-muted">Low Stock Items</h6>
                            <h3 class="mb-0 text-warning"><?= $low_stock ?></h3>
                            <small class="text-muted">need attention</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card border-danger">
                        <div class="card-body">
                            <h6 class="text-muted">Expired Medicines</h6>
                            <h3 class="mb-0 text-danger"><?= $expired ?></h3>
                            <small class="text-muted">to be discarded</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Backup Options -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-file-pdf"></i> Export Backup Report</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>Backup Report Includes:</strong>
                        <ul class="mt-2 mb-0">
                            <li>📊 Complete sales history (last 50 transactions)</li>
                            <li>💳 Payment methods summary (Cash vs M-Pesa)</li>
                            <li>⚠️ Low stock items (10 units or less)</li>
                            <li>📅 Expired medicines list</li>
                            <li>💊 Complete medicine inventory</li>
                            <li>🛠️ Services offered</li>
                            <li>👥 System users (cashiers & admins)</li>
                        </ul>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                                    <h5>Export as HTML Report</h5>
                                    <p class="text-muted">Download a complete HTML report<br>Can be converted to PDF using browser's "Print to PDF"</p>
                                    <a href="?export_pdf=1" class="btn btn-danger btn-lg">
                                        <i class="fas fa-download"></i> Download Backup Report
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-bar fa-3x text-success mb-3"></i>
                                    <h5>Quick Actions</h5>
                                    <p class="text-muted">Other backup and maintenance options</p>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-outline-primary" onclick="window.location.href='reports.php'">
                                            <i class="fas fa-chart-line"></i> View Detailed Reports
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="window.location.href='medicines.php'">
                                            <i class="fas fa-tablets"></i> Manage Inventory
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i> <strong>How to convert to PDF:</strong>
                        <ol class="mt-2 mb-0">
                            <li>Click the "Download Backup Report" button above</li>
                            <li>Open the downloaded HTML file in any browser</li>
                            <li>Press <kbd>Ctrl + P</kbd> (or <kbd>Cmd + P</kbd> on Mac)</li>
                            <li>Select "Save as PDF" as the destination</li>
                            <li>Click "Save" to create your PDF backup</li>
                        </ol>
                    </div>
                </div>
            </div>
            
            <!-- Recent Backup Activity -->
            <div class="card mt-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Recent Backup Activity</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Date</th><th>Report Type</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?= date('Y-m-d H:i:s') ?></td>
                                <td>System Backup</td>
                                <td><span class="badge bg-success">Ready to Export</span></td>
                                <td><a href="?export_pdf=1" class="btn btn-sm btn-primary">Export Now</a></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>