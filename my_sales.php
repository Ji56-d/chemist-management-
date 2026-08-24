<?php 
require_once 'config/db.php'; 
if(!isCashier()) redirect('index.php');

$cashier_id = $_SESSION['user_id'];
$cashier_name = $_SESSION['name'] ?? 'Cashier';

// Fetch sales for this cashier, most recent first
$sales = $conn->query("SELECT * FROM sales_master WHERE cashier_id = $cashier_id ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Sales - Chemist POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f4f6f9; margin: 0; padding: 0; }
        .content { background: #f4f6f9; padding: 20px; }
        .table-card { background: white; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .table thead th { background-color: #343a40; color: white; position: sticky; top: 0; z-index: 10; }
        .badge-cash { background-color: #28a745; }
        .badge-mpesa { background-color: #17a2b8; }
        .badge-bank { background-color: #6f42c1; }
        .badge-credit { background-color: #fd7e14; }
        .badge-service { background-color: #6c757d; }
        .expand-btn {
            cursor: pointer;
            color: #007bff;
        }
        .expand-btn:hover {
            text-decoration: underline;
        }
        .sale-details-row {
            background-color: #f8f9fa;
        }
        .sale-details-row td {
            padding: 10px 15px !important;
        }
        .detail-table {
            font-size: 13px;
            margin: 0;
        }
        .detail-table th {
            background-color: #e9ecef !important;
            color: #333 !important;
        }
        .table-container {
            max-height: 500px;
            overflow-y: auto;
        }
        .summary-card {
            transition: transform 0.2s;
        }
        .summary-card:hover {
            transform: translateY(-3px);
        }
        .no-sales {
            padding: 40px 0;
            text-align: center;
        }
        .no-sales i {
            font-size: 48px;
            color: #ccc;
        }
        .item-badge {
            display: inline-block;
            background: #e9ecef;
            padding: 2px 10px;
            margin: 2px;
            border-radius: 12px;
            font-size: 12px;
        }
        .item-badge .qty-badge {
            background: #6c757d;
            color: white;
            border-radius: 10px;
            padding: 0 6px;
            font-size: 10px;
            margin-left: 3px;
        }
        .invoice-items-preview {
            max-height: 60px;
            overflow: hidden;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Cashier Sidebar -->
        <?php include 'cashiers_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-10 content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-receipt"></i> My Sales History</h2>
                <span class="badge bg-secondary">Cashier: <?= htmlspecialchars($cashier_name) ?></span>
            </div>
            
            <div class="table-card p-3">
                <div class="mb-3">
                    <div class="row">
                        <div class="col-md-8">
                            <input type="text" id="searchInput" class="form-control" placeholder="🔍 Filter by invoice, item name, date, or payment...">
                        </div>
                        <div class="col-md-4">
                            <select id="filterPayment" class="form-select">
                                <option value="">All Payment Methods</option>
                                <option value="Cash">💵 Cash</option>
                                <option value="M-Pesa">📱 M-Pesa</option>
                                <option value="Bank Transfer">🏦 Bank Transfer</option>
                                <option value="Credit Card">💳 Credit Card</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="table-container">
                    <table class="table table-hover" id="salesTable">
                        <thead>
                            <tr>
                                <th style="width:30px;">#</th>
                                <th>Invoice</th>
                                <th>Date</th>
                                <th>Items Sold</th>
                                <th class="text-end">Total (KES)</th>
                                <th class="text-end">Net (KES)</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($sales->num_rows > 0): 
                                $counter = 1;
                                while($s = $sales->fetch_assoc()): 
                                    // Fetch items
                                    $items = [];
                                    $item_names = [];
                                    $item_details = [];
                                    
                                    $sale_id = intval($s['id']);
                                    
                                    // First, get the structure of sale_items table
                                    $columns_query = "SHOW COLUMNS FROM sale_items";
                                    $columns_result = $conn->query($columns_query);
                                    $columns = [];
                                    if($columns_result) {
                                        while($col = $columns_result->fetch_assoc()) {
                                            $columns[] = $col['Field'];
                                        }
                                    }
                                    
                                    // Determine the sale ID column name
                                    $sale_id_column = 'sale_id';
                                    if(in_array('sales_id', $columns)) {
                                        $sale_id_column = 'sales_id';
                                    } elseif(in_array('sale_id', $columns)) {
                                        $sale_id_column = 'sale_id';
                                    } elseif(in_array('master_id', $columns)) {
                                        $sale_id_column = 'master_id';
                                    }
                                    
                                    // Determine the product/medicine ID column name
                                    $product_column = null;
                                    if(in_array('product_id', $columns)) {
                                        $product_column = 'product_id';
                                    } elseif(in_array('medicine_id', $columns)) {
                                        $product_column = 'medicine_id';
                                    } elseif(in_array('item_id', $columns)) {
                                        $product_column = 'item_id';
                                    }
                                    
                                    // Get items - first try with JOIN for medicines only
                                    $items_query = "
                                        SELECT 
                                            si.*,
                                            m.name as medicine_name
                                        FROM sale_items si
                                        LEFT JOIN medicines m ON si.{$product_column} = m.id AND si.item_type = 'medicine'
                                        WHERE si.{$sale_id_column} = {$sale_id}
                                        ORDER BY si.id ASC
                                    ";
                                    $items_result = $conn->query($items_query);
                                    
                                    if($items_result && $items_result->num_rows > 0) {
                                        while($item = $items_result->fetch_assoc()) {
                                            $display_name = '';
                                            $item_type = $item['item_type'] ?? $item['type'] ?? 'medicine';
                                            
                                            // For service items, use the item_name directly
                                            if($item_type == 'service' || $item_type == 'Service') {
                                                $display_name = $item['item_name'] ?? $item['name'] ?? 'Service';
                                            } 
                                            // For medicine items, try to get from medicines table
                                            else {
                                                // Try to get name from JOIN result
                                                if(isset($item['medicine_name']) && !empty($item['medicine_name'])) {
                                                    $display_name = $item['medicine_name'];
                                                } 
                                                // Try item_name column
                                                elseif(isset($item['item_name']) && !empty($item['item_name']) && !is_numeric($item['item_name']) && $item['item_name'] != '0') {
                                                    $display_name = $item['item_name'];
                                                }
                                                // Try name column
                                                elseif(isset($item['name']) && !empty($item['name'])) {
                                                    $display_name = $item['name'];
                                                }
                                                // Try to get from medicines table directly if we have a product ID
                                                elseif($product_column && isset($item[$product_column]) && $item[$product_column] > 0) {
                                                    $med_direct = $conn->query("SELECT name FROM medicines WHERE id = " . intval($item[$product_column]));
                                                    if($med_direct && $med_direct->num_rows > 0) {
                                                        $med_row = $med_direct->fetch_assoc();
                                                        $display_name = $med_row['name'];
                                                    }
                                                }
                                            }
                                            
                                            if(empty($display_name)) {
                                                $display_name = 'Item #' . $item['id'];
                                            }
                                            
                                            $items[] = $item;
                                            $item_names[] = $display_name;
                                            $item_details[] = [
                                                'id' => $item['id'],
                                                'name' => $display_name,
                                                'qty' => $item['quantity'] ?? $item['qty'] ?? 1,
                                                'type' => $item_type,
                                                'unit_price' => $item['unit_price'] ?? $item['price'] ?? 0,
                                                'total_price' => $item['total_price'] ?? $item['total'] ?? 0
                                            ];
                                        }
                                    } else {
                                        // Fallback: get items without JOIN
                                        $fallback_query = "SELECT * FROM sale_items WHERE {$sale_id_column} = {$sale_id} ORDER BY id ASC";
                                        $fallback_result = $conn->query($fallback_query);
                                        
                                        if($fallback_result && $fallback_result->num_rows > 0) {
                                            while($item = $fallback_result->fetch_assoc()) {
                                                $item_type = $item['item_type'] ?? $item['type'] ?? 'medicine';
                                                $display_name = $item['item_name'] ?? $item['name'] ?? 'Unknown';
                                                
                                                // If it's a medicine and we have a product ID, try to get the name
                                                if($item_type == 'medicine' && $product_column && isset($item[$product_column]) && $item[$product_column] > 0) {
                                                    $med_direct = $conn->query("SELECT name FROM medicines WHERE id = " . intval($item[$product_column]));
                                                    if($med_direct && $med_direct->num_rows > 0) {
                                                        $med_row = $med_direct->fetch_assoc();
                                                        $display_name = $med_row['name'];
                                                    }
                                                }
                                                
                                                $items[] = $item;
                                                $item_names[] = $display_name;
                                                $item_details[] = [
                                                    'id' => $item['id'],
                                                    'name' => $display_name,
                                                    'qty' => $item['quantity'] ?? $item['qty'] ?? 1,
                                                    'type' => $item_type,
                                                    'unit_price' => $item['unit_price'] ?? $item['price'] ?? 0,
                                                    'total_price' => $item['total_price'] ?? $item['total'] ?? 0
                                                ];
                                            }
                                        }
                                    }
                                    
                                    $payment_class = 'badge-cash';
                                    if(isset($s['payment_method'])) {
                                        if($s['payment_method'] == 'M-Pesa') $payment_class = 'badge-mpesa';
                                        elseif($s['payment_method'] == 'Bank Transfer') $payment_class = 'badge-bank';
                                        elseif($s['payment_method'] == 'Credit Card') $payment_class = 'badge-credit';
                                    }
                                    
                                    $search_string = strtolower(($s['invoice_no'] ?? '') . ' ' . implode(' ', $item_names) . ' ' . ($s['payment_method'] ?? ''));
                                    ?>
                                    <tr class="sale-row" data-payment="<?= $s['payment_method'] ?? '' ?>" data-search="<?= htmlspecialchars($search_string) ?>">
                                        <td><?= $counter++ ?></td>
                                        <td><strong><?= htmlspecialchars($s['invoice_no'] ?? 'N/A') ?></strong></td>
                                        <td><?= isset($s['created_at']) ? date('d/m/Y H:i', strtotime($s['created_at'])) : 'N/A' ?></td>
                                        <td>
                                            <div class="invoice-items-preview">
                                                <?php if(count($item_details) > 0): ?>
                                                    <?php 
                                                    $display_items = array_slice($item_details, 0, 3);
                                                    $remaining = count($item_details) - 3;
                                                    foreach($display_items as $item): 
                                                    ?>
                                                        <span class="item-badge">
                                                            <?= htmlspecialchars($item['name']) ?>
                                                            <span class="qty-badge">x<?= $item['qty'] ?></span>
                                                            <?php if(isset($item['type']) && ($item['type'] == 'service' || $item['type'] == 'Service')): ?>
                                                                <span class="badge bg-secondary" style="font-size:8px;">S</span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                    <?php if($remaining > 0): ?>
                                                        <span class="item-badge text-muted" style="background:transparent;">
                                                            +<?= $remaining ?> more
                                                            <i class="fas fa-chevron-down expand-btn" onclick="toggleDetails(<?= $s['id'] ?>)"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if(count($item_details) <= 3): ?>
                                                        <span class="item-badge text-muted" style="background:transparent;font-size:10px;">
                                                            <i class="fas fa-chevron-down expand-btn" onclick="toggleDetails(<?= $s['id'] ?>)"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No items</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-end"><?= number_format($s['total_amount'] ?? 0, 2) ?></td>
                                        <td class="text-end fw-bold"><?= number_format($s['net_amount'] ?? 0, 2) ?></td>
                                        <td><span class="badge <?= $payment_class ?> p-2"><?= $s['payment_method'] ?? 'N/A' ?></span></td>
                                        <td>
                                            <a href="receipt.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-info" target="_blank">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="toggleDetails(<?= $s['id'] ?>)">
                                                <i class="fas fa-list"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <!-- Detail Row -->
                                    <tr id="detail-<?= $s['id'] ?>" class="sale-details-row" style="display:none;">
                                        <td colspan="8">
                                            <div class="p-2">
                                                <h6 class="mb-2"><i class="fas fa-cubes"></i> Items in this sale - Invoice: <?= htmlspecialchars($s['invoice_no'] ?? 'N/A') ?></h6>
                                                <table class="table table-sm detail-table">
                                                    <thead>
                                                        <tr>
                                                            <th>#</th>
                                                            <th>Item Name</th>
                                                            <th class="text-center">Type</th>
                                                            <th class="text-center">Qty</th>
                                                            <th class="text-end">Unit Price</th>
                                                            <th class="text-end">Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if(count($items) > 0): 
                                                            $item_counter = 1;
                                                            foreach($items as $item): 
                                                                $detail_name = $item_details[$item_counter-1]['name'] ?? 'Unknown Item';
                                                                $item_type = $item['item_type'] ?? $item['type'] ?? 'medicine';
                                                                ?>
                                                            <tr>
                                                                <td><?= $item_counter++ ?></td>
                                                                <td><strong><?= htmlspecialchars($detail_name) ?></strong></td>
                                                                <td class="text-center">
                                                                    <?php if($item_type == 'service' || $item_type == 'Service'): ?>
                                                                        <span class="badge bg-secondary">Service</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-success">Medicine</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-center"><?= $item['quantity'] ?? $item['qty'] ?? 0 ?></td>
                                                                <td class="text-end"><?= getCurrency() . number_format($item['unit_price'] ?? $item['price'] ?? 0, 2) ?></td>
                                                                <td class="text-end"><?= getCurrency() . number_format($item['total_price'] ?? $item['total'] ?? 0, 2) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr><td colspan="6" class="text-center text-muted">No items found for this sale</td></tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr class="table-active">
                                                            <td colspan="4"></td>
                                                            <td class="text-end fw-bold">Total:</td>
                                                            <td class="text-end fw-bold text-success"><?= getCurrency() . number_format($s['net_amount'] ?? 0, 2) ?></td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                                <button class="btn btn-sm btn-secondary" onclick="toggleDetails(<?= $s['id'] ?>)">
                                                    <i class="fas fa-chevron-up"></i> Close Details
                                                </button>
                                                <a href="receipt.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-primary" target="_blank">
                                                    <i class="fas fa-print"></i> Print Receipt
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="no-sales">
                                            <i class="fas fa-shopping-bag"></i>
                                            <h5 class="mt-3">No sales found</h5>
                                            <p class="text-muted">You haven't made any sales yet. Start selling!</p>
                                            <a href="pos.php" class="btn btn-primary">
                                                <i class="fas fa-plus"></i> Go to POS
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <?php 
            $summary = $conn->query("SELECT 
                COUNT(*) as total_transactions,
                SUM(net_amount) as total_revenue,
                SUM(total_amount) as total_gross
                FROM sales_master WHERE cashier_id = $cashier_id
            ")->fetch_assoc();
            ?>
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white summary-card">
                        <div class="card-body">
                            <h6><i class="fas fa-receipt"></i> Transactions</h6>
                            <h3><?= number_format($summary['total_transactions'] ?? 0) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white summary-card">
                        <div class="card-body">
                            <h6><i class="fas fa-money-bill-wave"></i> Total Revenue (Net)</h6>
                            <h3><?= getCurrency() . number_format($summary['total_revenue'] ?? 0, 2) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white summary-card">
                        <div class="card-body">
                            <h6><i class="fas fa-calculator"></i> Gross Sales</h6>
                            <h3><?= getCurrency() . number_format($summary['total_gross'] ?? 0, 2) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle details row
function toggleDetails(saleId) {
    let detailRow = document.getElementById('detail-' + saleId);
    if (detailRow) {
        if (detailRow.style.display === 'none') {
            detailRow.style.display = 'table-row';
            detailRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            detailRow.style.display = 'none';
        }
    }
}

// Live search/filter
document.getElementById('searchInput').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#salesTable tbody tr.sale-row');
    rows.forEach(row => {
        let searchData = row.getAttribute('data-search') || '';
        let text = row.innerText.toLowerCase();
        let matches = text.includes(filter) || searchData.includes(filter);
        row.style.display = matches ? '' : 'none';
        let detailRow = row.nextElementSibling;
        if (detailRow && detailRow.classList.contains('sale-details-row')) {
            if (row.style.display === 'none') {
                detailRow.style.display = 'none';
            }
        }
    });
});

// Payment method filter
document.getElementById('filterPayment').addEventListener('change', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#salesTable tbody tr.sale-row');
    rows.forEach(row => {
        let payment = row.getAttribute('data-payment') || '';
        if (filter === '' || payment.toLowerCase() === filter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
            let detailRow = row.nextElementSibling;
            if (detailRow && detailRow.classList.contains('sale-details-row')) {
                detailRow.style.display = 'none';
            }
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>