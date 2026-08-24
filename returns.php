<?php
require_once 'config/db.php';
if(!isAdmin()) redirect('dashboard.php');

// Process return if form submitted
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['return_sale'])){
    $sale_id = $_POST['sale_id'];
    $reason = $_POST['reason'];
    $sale = $conn->query("SELECT * FROM sales_master WHERE id=$sale_id AND is_returned=0")->fetch_assoc();
    if($sale){
        $conn->begin_transaction();
        try{
            $conn->query("UPDATE sales_master SET is_returned=1 WHERE id=$sale_id");
            $items = $conn->query("SELECT * FROM sale_items WHERE sale_id=$sale_id");
            while($item = $items->fetch_assoc()){
                if($item['item_type'] == 'medicine'){
                    $conn->query("UPDATE medicines SET stock_quantity = stock_quantity + {$item['quantity']} WHERE id={$item['item_id']}");
                    $conn->query("INSERT INTO stock_history (medicine_id, change_type, quantity, new_quantity) VALUES ({$item['item_id']},'return',{$item['quantity']}, (SELECT stock_quantity FROM medicines WHERE id={$item['item_id']}))");
                }
            }
            $conn->query("INSERT INTO returns (sale_id, return_date, refund_amount, reason, cashier_id) VALUES ($sale_id, CURDATE(), {$sale['net_amount']}, '$reason', {$_SESSION['user_id']})");
            $conn->commit();
            echo "<script>alert('Return processed, stock updated'); window.location='returns.php';</script>";
        } catch(Exception $e){
            $conn->rollback();
            echo "<script>alert('Error: {$e->getMessage()}');</script>";
        }
    } else {
        echo "<script>alert('Invalid sale or already returned');</script>";
    }
}

// Process delete returns
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['delete_returns'])){
    if(isset($_POST['return_ids']) && is_array($_POST['return_ids']) && count($_POST['return_ids']) > 0){
        $ids = array_map('intval', $_POST['return_ids']);
        $ids_string = implode(',', $ids);
        
        $conn->begin_transaction();
        try{
            // Get the sale_ids before deleting returns
            $returns = $conn->query("SELECT sale_id FROM returns WHERE id IN ($ids_string)");
            $sale_ids = [];
            while($ret = $returns->fetch_assoc()){
                $sale_ids[] = $ret['sale_id'];
            }
            
            // Delete the returns
            $conn->query("DELETE FROM returns WHERE id IN ($ids_string)");
            
            // Update sales_master to set is_returned=0
            if(!empty($sale_ids)){
                $sale_ids_string = implode(',', $sale_ids);
                $conn->query("UPDATE sales_master SET is_returned=0 WHERE id IN ($sale_ids_string)");
            }
            
            $conn->commit();
            echo "<script>alert('Selected returns deleted successfully.'); window.location='returns.php';</script>";
        } catch(Exception $e){
            $conn->rollback();
            echo "<script>alert('Error deleting returns: {$e->getMessage()}');</script>";
        }
    } else {
        echo "<script>alert('Please select at least one return to delete.');</script>";
    }
}

// Get list of all returns with sale details
$returns = $conn->query("
    SELECT r.*, sm.invoice_no, sm.sale_date, sm.net_amount, u.name as cashier_name 
    FROM returns r 
    JOIN sales_master sm ON r.sale_id = sm.id 
    JOIN users u ON sm.cashier_id = u.id 
    ORDER BY r.return_date DESC
");

// Get list of non-returned sales
$sales = $conn->query("SELECT sm.*, u.name as cashier FROM sales_master sm JOIN users u ON sm.cashier_id=u.id WHERE sm.is_returned=0 ORDER BY sm.id DESC");

// Get chemist name and admin name for sidebar
$chemist_name = getSetting('chemist_name', $conn);
if(empty($chemist_name)) $chemist_name = 'City Chemist & Medical Store';
$admin_name = $_SESSION['name'] ?? 'Super Admin';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Returns - Chemist POS</title>
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
        .return-deleted {
            background-color: #d4edda;
            opacity: 0.7;
        }
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
            <a href="returns.php" class="active"><i class="fas fa-undo-alt"></i> Returns</a>
            <a href="backup.php"><i class="fas fa-download"></i> Backup PDF</a>
            <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        
        <!-- MAIN CONTENT AREA -->
        <div class="col-md-10 content">
            <!-- Returned Sales List -->
            <div class="card shadow mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-list"></i> Returned Sales</h4>
                    <form method="POST" onsubmit="return confirmDeleteAll()" class="mb-0">
                        <button type="submit" name="delete_returns" class="btn btn-danger btn-sm">
                            <i class="fas fa-trash"></i> Delete Selected
                        </button>
                </div>
                <div class="card-body">
                    <?php if($returns->num_rows == 0): ?>
                        <div class="alert alert-info">No returns recorded yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="returnsTable">
                                <thead class="table-success">
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" id="selectAll" onclick="toggleAllCheckboxes(this)">
                                        </th>
                                        <th>Invoice No</th>
                                        <th>Sale Date</th>
                                        <th>Cashier</th>
                                        <th>Amount (KES)</th>
                                        <th>Return Date</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($r = $returns->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="return_ids[]" value="<?= $r['id'] ?>" class="return-checkbox">
                                        </td>
                                        <td><?= htmlspecialchars($r['invoice_no']) ?></td>
                                        <td><?= $r['sale_date'] ?></td>
                                        <td><?= htmlspecialchars($r['cashier_name']) ?></td>
                                        <td class="text-end"><?= getCurrency() . number_format($r['refund_amount'], 2) ?></td>
                                        <td><?= $r['return_date'] ?></td>
                                        <td><?= htmlspecialchars($r['reason']) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Returns Section -->
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4><i class="fas fa-undo-alt"></i> Pending Returns</h4>
                </div>
                <div class="card-body">
                    <?php if($sales->num_rows == 0): ?>
                        <div class="alert alert-success">No pending returns. All sales are finalized.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Invoice No</th>
                                        <th>Date</th>
                                        <th>Cashier</th>
                                        <th>Net Amount (KES)</th>
                                        <th width="30%">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($s = $sales->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($s['invoice_no']) ?></td>
                                        <td><?= $s['sale_date'] ?></td>
                                        <td><?= htmlspecialchars($s['cashier']) ?></td>
                                        <td class="text-end"><?= getCurrency() . number_format($s['net_amount'], 2) ?></td>
                                        <td>
                                            <form method="POST" class="d-flex gap-2">
                                                <input type="hidden" name="sale_id" value="<?= $s['id'] ?>">
                                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason for return" required size="30">
                                                <button type="submit" name="return_sale" class="btn btn-sm btn-warning" onclick="return confirm('Return this sale? Stock will be restored.')">
                                                    <i class="fas fa-undo-alt"></i> Process Return
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleAllCheckboxes(source) {
    const checkboxes = document.querySelectorAll('.return-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = source.checked;
    });
}

function confirmDeleteAll() {
    const checkboxes = document.querySelectorAll('.return-checkbox:checked');
    if(checkboxes.length === 0) {
        alert('Please select at least one return to delete.');
        return false;
    }
    return confirm('Are you sure you want to delete the selected returns? This action will revert the sale status and CANNOT be undone!');
}
</script>
</body>
</html>