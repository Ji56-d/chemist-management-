<?php 
require_once 'config/db.php';

// Check if sale ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    die("Sale ID is required");
}

$id = (int)$_GET['id'];

// Get sale details
$sale_query = $conn->prepare("SELECT sm.*, u.name as cashier FROM sales_master sm JOIN users u ON sm.cashier_id=u.id WHERE sm.id = ?");
if(!$sale_query) {
    die("Database error: " . $conn->error);
}
$sale_query->bind_param("i", $id);
if(!$sale_query->execute()) {
    die("Query execution failed: " . $sale_query->error);
}
$sale_result = $sale_query->get_result();
$sale = $sale_result->fetch_assoc();

if(!$sale) {
    die("Sale not found");
}

// Get sale items
$items_query = $conn->prepare("
    SELECT si.*
    FROM sale_items si
    WHERE si.sale_id = ?
    ORDER BY si.id ASC
");

if(!$items_query) {
    die("Database error: " . $conn->error);
}

$items_query->bind_param("i", $id);

if(!$items_query->execute()) {
    die("Query execution failed: " . $items_query->error);
}

$items_result = $items_query->get_result();

$subtotal = 0;
$items = [];

while($row = $items_result->fetch_assoc()) {
    // Get the item details
    $item_name = trim((string)($row['item_name'] ?? ''));
    $item_type = trim((string)($row['item_type'] ?? ''));
    $item_id = (int)($row['item_id'] ?? 0);
    
    // If item_name is empty or numeric, or contains 'Item #', try to fetch from source table
    $needs_fetch = false;
    if(empty($item_name) || $item_name === '0' || is_numeric($item_name) || strpos($item_name, 'Item #') === 0) {
        $needs_fetch = true;
    }
    
    if($needs_fetch && $item_id > 0) {
        $type_lower = strtolower($item_type);
        $fetched_name = '';
        
        // ************************************************************
        // BOTTLE FETCHING SECTION - Gets the bottle name from database
        // ************************************************************
        if(in_array($type_lower, ['bottle', 'bottles', 'drink', 'drinks'])) {
            // Fetch from bottles table
            $name_query = $conn->prepare("SELECT name FROM bottles WHERE id = ? LIMIT 1");
            if($name_query) {
                $name_query->bind_param("i", $item_id);
                $name_query->execute();
                $name_result = $name_query->get_result();
                if($name_result && $name_result->num_rows > 0) {
                    $name_data = $name_result->fetch_assoc();
                    $fetched_name = trim($name_data['name'] ?? '');
                }
                $name_query->close();
            }
        // ************************************************************
        // END OF BOTTLE FETCHING SECTION
        // ************************************************************
        
        // ************************************************************
        // MEDICINE FETCHING SECTION - Gets the medicine name from database
        // ************************************************************
        } elseif(in_array($type_lower, ['medicine', 'medicines', 'drug', 'drugs'])) {
            // Fetch from medicines table
            $name_query = $conn->prepare("SELECT name FROM medicines WHERE id = ? LIMIT 1");
            if($name_query) {
                $name_query->bind_param("i", $item_id);
                $name_query->execute();
                $name_result = $name_query->get_result();
                if($name_result && $name_result->num_rows > 0) {
                    $name_data = $name_result->fetch_assoc();
                    $fetched_name = trim($name_data['name'] ?? '');
                }
                $name_query->close();
            }
        // ************************************************************
        // END OF MEDICINE FETCHING SECTION
        // ************************************************************
        
        // ************************************************************
        // SERVICE FETCHING SECTION - Gets the service name from database
        // ************************************************************
        } elseif(in_array($type_lower, ['service', 'services'])) {
            // Fetch from services table
            $name_query = $conn->prepare("SELECT name FROM services WHERE id = ? LIMIT 1");
            if($name_query) {
                $name_query->bind_param("i", $item_id);
                $name_query->execute();
                $name_result = $name_query->get_result();
                if($name_result && $name_result->num_rows > 0) {
                    $name_data = $name_result->fetch_assoc();
                    $fetched_name = trim($name_data['name'] ?? '');
                }
                $name_query->close();
            }
        // ************************************************************
        // END OF SERVICE FETCHING SECTION
        // ************************************************************
        }
        
        // Use fetched name if we got one, otherwise keep the original
        if(!empty($fetched_name)) {
            $item_name = $fetched_name;
        }
    }
    
    // If still empty, use a descriptive fallback
    if(empty($item_name)) {
        $item_name = $row['item_type'] . ' #' . $item_id;
    }
    
    // ************************************************************
    // DISPLAY SECTION - This is where the item name is displayed
    // ************************************************************
    // Display just the item name without any type suffix
    $row['display_name'] = $item_name;
    // ************************************************************
    // END OF DISPLAY SECTION
    // ************************************************************
    
    $subtotal += (float)($row['total_price'] ?? 0);
    $items[] = $row;
}

// Get settings
function getSettingSafe($key, $conn) {
    try {
        $check = $conn->query("SHOW TABLES LIKE 'settings'");
        if($check && $check->num_rows > 0) {
            $result = $conn->query("SELECT value FROM settings WHERE setting_key = '$key'");
            if($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                return $row['value'];
            }
        }
        return null;
    } catch(Exception $e) {
        return null;
    }
}

$chemist = getSettingSafe('chemist_name', $conn);
$footer = getSettingSafe('receipt_footer', $conn);
$phone = getSettingSafe('phone', $conn);
$address = getSettingSafe('address', $conn);

// Get currency
$currency = 'KES ';
try {
    $check = $conn->query("SHOW TABLES LIKE 'settings'");
    if($check && $check->num_rows > 0) {
        $currency_result = $conn->query("SELECT value FROM settings WHERE setting_key = 'currency'");
        if($currency_result && $currency_result->num_rows > 0) {
            $currency_row = $currency_result->fetch_assoc();
            $currency = $currency_row['value'];
        }
    }
} catch(Exception $e) {
    $currency = 'KES ';
}

if(function_exists('getCurrency')) {
    $currency = getCurrency();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sale Receipt #<?= htmlspecialchars($sale['invoice_no'] ?? 'N/A') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { 
            background: #f4f6f9; 
            font-family: 'Courier New', monospace;
            padding: 20px;
        }
        .receipt-container {
            max-width: 420px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .receipt {
            background: white;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #ddd;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .receipt-header h4 {
            margin: 0;
            color: #2c3e50;
            font-weight: bold;
            font-size: 18px;
        }
        .receipt-header .store-info {
            font-size: 12px;
            color: #666;
        }
        .receipt-header .store-info i {
            width: 14px;
        }
        .receipt-info {
            border-bottom: 1px dashed #ddd;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .receipt-info p {
            margin: 3px 0;
            font-size: 13px;
        }
        .receipt-info .label {
            font-weight: bold;
            display: inline-block;
            width: 80px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        .items-table thead th {
            border-bottom: 1px solid #ddd;
            text-align: left;
            font-size: 12px;
            padding: 5px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .items-table tbody td {
            padding: 6px 0;
            font-size: 13px;
            border-bottom: 1px dotted #eee;
        }
        .items-table .item-name {
            word-wrap: break-word;
            font-weight: 500;
            max-width: 160px;
        }
        .items-table .item-qty {
            text-align: center;
        }
        .items-table .item-price {
            text-align: right;
            padding-right: 5px;
        }
        .items-table .item-total {
            text-align: right;
            font-weight: bold;
        }
        .receipt-totals {
            border-top: 2px solid #ddd;
            padding-top: 10px;
            margin-top: 10px;
        }
        .receipt-totals .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            padding: 2px 0;
        }
        .receipt-totals .net-total {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            border-top: 2px solid #333;
            padding-top: 8px;
            margin-top: 5px;
        }
        .receipt-totals .payment-info {
            font-size: 13px;
            color: #555;
            border-top: 1px dashed #ddd;
            padding-top: 8px;
            margin-top: 8px;
        }
        .receipt-footer {
            border-top: 2px dashed #ddd;
            padding-top: 10px;
            margin-top: 10px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .receipt-footer small {
            display: block;
            margin: 2px 0;
        }
        .btn-actions {
            margin-top: 20px;
            text-align: center;
        }
        .btn-actions .btn {
            margin: 0 5px;
            min-width: 100px;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .item-count {
            font-size: 12px;
            color: #666;
            text-align: center;
            padding: 5px 0;
            border-bottom: 1px dotted #ddd;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
        }
        @media print {
            .btn-actions, .no-print {
                display: none !important;
            }
            body {
                background: white;
                padding: 0;
            }
            .receipt-container {
                box-shadow: none;
                border-radius: 0;
                padding: 10px;
                max-width: 100%;
            }
            .receipt {
                border: none;
                padding: 5px;
            }
            .receipt-container {
                padding: 0;
            }
            .error-message {
                display: none !important;
            }
        }
        @media (max-width: 480px) {
            .receipt-container {
                padding: 10px;
                border-radius: 0;
            }
            .btn-actions .btn {
                min-width: 80px;
                font-size: 12px;
                padding: 6px 10px;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt" id="receipt-content">
            <!-- Header -->
            <div class="receipt-header">
                <h4><?= htmlspecialchars($chemist ?: 'Chemist POS') ?></h4>
                <div class="store-info">
                    <?php if($address): ?>
                        <div><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($address) ?></div>
                    <?php endif; ?>
                    <?php if($phone): ?>
                        <div><i class="fas fa-phone"></i> <?= htmlspecialchars($phone) ?></div>
                    <?php endif; ?>
                    <div><i class="fas fa-store"></i> Chemist POS - Kenya</div>
                </div>
            </div>

            <!-- Sale Info -->
            <div class="receipt-info">
                <p><span class="label">Invoice:</span> #<?= htmlspecialchars($sale['invoice_no'] ?? 'N/A') ?></p>
                <p><span class="label">Cashier:</span> <?= htmlspecialchars($sale['cashier'] ?? 'N/A') ?></p>
                <p><span class="label">Date:</span> <?= isset($sale['created_at']) ? date('d/m/Y H:i', strtotime($sale['created_at'])) : date('d/m/Y H:i') ?></p>
                <p><span class="label">Payment:</span> <?= isset($sale['payment_method']) ? ucfirst(str_replace('_', ' ', $sale['payment_method'])) : 'N/A' ?></p>
                <?php if(isset($sale['status']) && $sale['status']): ?>
                <p><span class="label">Status:</span> 
                    <span class="status-badge status-<?= strtolower($sale['status']) ?>">
                        <?= ucfirst($sale['status']) ?>
                    </span>
                </p>
                <?php endif; ?>
            </div>

            <!-- Items -->
            <?php if(count($items) > 0): ?>
            <div class="item-count"><?= count($items) ?> item(s)</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="text-align:left; width:45%;">Item</th>
                        <th style="text-align:center; width:12%;">Qty</th>
                        <th style="text-align:right; width:20%;">Price</th>
                        <th style="text-align:right; width:23%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $item): ?>
                    <tr>
                        <td class="item-name">
                            <!-- ************************************************************ -->
                            <!-- THIS IS WHERE THE ITEM NAME IS DISPLAYED ON THE RECEIPT -->
                            <!-- For bottles: Shows the bottle name from bottles table -->
                            <!-- For services: Shows the service name from services table -->
                            <!-- For medicines: Shows the medicine name from medicines table -->
                            <!-- ************************************************************ -->
                            <?= htmlspecialchars($item['display_name']) ?>
                            <!-- ************************************************************ -->
                            <!-- END OF ITEM NAME DISPLAY -->
                            <!-- ************************************************************ -->
                        </td>
                        <td class="item-qty"><?= $item['quantity'] ?? 0 ?></td>
                        <td class="item-price"><?= $currency . number_format($item['unit_price'] ?? 0, 2) ?></td>
                        <td class="item-total"><?= $currency . number_format($item['total_price'] ?? 0, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> No items found for this sale.
            </div>
            <?php endif; ?>

            <!-- Totals -->
            <div class="receipt-totals">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span><?= $currency . number_format($subtotal, 2) ?></span>
                </div>
                <?php if(isset($sale['discount']) && $sale['discount'] > 0): ?>
                <div class="total-row" style="color:#dc3545;">
                    <span>Discount:</span>
                    <span>-<?= $currency . number_format($sale['discount'], 2) ?></span>
                </div>
                <?php endif; ?>
                <?php if(isset($sale['tax_amount']) && $sale['tax_amount'] > 0): ?>
                <div class="total-row">
                    <span>Tax:</span>
                    <span><?= $currency . number_format($sale['tax_amount'], 2) ?></span>
                </div>
                <?php endif; ?>
                <div class="total-row net-total">
                    <span><strong>Total:</strong></span>
                    <span><strong><?= $currency . number_format($sale['net_amount'] ?? 0, 2) ?></strong></span>
                </div>
                <div class="payment-info">
                    <div>Payment Method: <?= isset($sale['payment_method']) ? ucfirst(str_replace('_', ' ', $sale['payment_method'])) : 'N/A' ?></div>
                    <?php if(isset($sale['amount_paid']) && $sale['amount_paid'] > 0): ?>
                        <div>Amount Paid: <?= $currency . number_format($sale['amount_paid'], 2) ?></div>
                    <?php endif; ?>
                    <?php if(isset($sale['balance']) && $sale['balance'] > 0): ?>
                        <div>Balance: <?= $currency . number_format($sale['balance'], 2) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Footer -->
            <div class="receipt-footer">
                <?php if(!empty($footer)): ?>
                    <small><?= nl2br(htmlspecialchars($footer)) ?></small>
                <?php endif; ?>
                <small>Thank you for your purchase!</small>
                <small>Have a great day! 😊</small>
                <small><i class="fas fa-clock"></i> <?= date('d/m/Y H:i') ?></small>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="btn-actions no-print">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print
            </button>
            <button onclick="window.location.href='pos.php'" class="btn btn-success">
                <i class="fas fa-plus"></i> New Sale
            </button>
            <button onclick="window.location.href='dashboard.php'" class="btn btn-secondary">
                <i class="fas fa-home"></i> Dashboard
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-print when loaded (with slight delay for rendering)
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                window.print();
            }, 800);
        });

        // Keyboard shortcut: Ctrl+P for print
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>