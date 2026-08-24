<?php 
require_once 'config/db.php'; 
if(!isCashier()) redirect('index.php');

$cashier_id = $_SESSION['user_id'];
if(!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Add to cart (AJAX) - Updated to handle bottles
if(isset($_POST['action']) && $_POST['action']=='add'){
    $id = (int)$_POST['id']; 
    $type = $_POST['type']; 
    $qty = (int)$_POST['qty'];
    
    if($type=='medicine'){
        $stmt = $conn->prepare("SELECT id, name, unit_type, selling_price, stock_quantity, cost_price FROM medicines WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $med = $stmt->get_result()->fetch_assoc();
        
        if($med){
            if($med['stock_quantity'] >= $qty){
                $actualName = $med['name'];
                $unitType = !empty($med['unit_type']) ? $med['unit_type'] : '';
                
                $found = false;
                foreach($_SESSION['cart'] as &$item) {
                    if($item['item_id'] == $id && $item['type'] == 'medicine') {
                        $newQty = $item['quantity'] + $qty;
                        if($med['stock_quantity'] >= $newQty) {
                            $item['quantity'] = $newQty;
                            $found = true;
                            echo json_encode(['success'=>true, 'msg'=>'Quantity updated in cart']);
                        } else {
                            echo json_encode(['success'=>false, 'msg'=>'Insufficient stock. Available: ' . $med['stock_quantity']]);
                        }
                        exit;
                    }
                }
                
                if(!$found) {
                    $_SESSION['cart'][] = [
                        'item_id' => $id,
                        'type' => 'medicine',
                        'name' => $actualName,
                        'display_name' => $unitType ? $actualName . ' (' . $unitType . ')' : $actualName,
                        'unit_type' => $unitType,
                        'price' => (float)$med['selling_price'],
                        'quantity' => $qty,
                        'cost_price' => (float)$med['cost_price'],
                        'stock_quantity' => $med['stock_quantity']
                    ];
                    echo json_encode(['success'=>true, 'msg'=>'Added to cart']);
                }
            } else {
                echo json_encode(['success'=>false, 'msg'=>'Insufficient stock. Available: ' . $med['stock_quantity']]);
            }
        } else {
            echo json_encode(['success'=>false, 'msg'=>'Medicine not found']);
        }
    } elseif($type=='bottle'){
        // Handle bottle addition
        $stmt = $conn->prepare("SELECT id, name, price, stock_quantity FROM bottles WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $bottle = $stmt->get_result()->fetch_assoc();
        
        if($bottle){
            if($bottle['stock_quantity'] >= $qty){
                $actualName = $bottle['name'];
                
                $found = false;
                foreach($_SESSION['cart'] as &$item) {
                    if($item['item_id'] == $id && $item['type'] == 'bottle') {
                        $newQty = $item['quantity'] + $qty;
                        if($bottle['stock_quantity'] >= $newQty) {
                            $item['quantity'] = $newQty;
                            $found = true;
                            echo json_encode(['success'=>true, 'msg'=>'Quantity updated in cart']);
                        } else {
                            echo json_encode(['success'=>false, 'msg'=>'Insufficient stock. Available: ' . $bottle['stock_quantity']]);
                        }
                        exit;
                    }
                }
                
                if(!$found) {
                    $_SESSION['cart'][] = [
                        'item_id' => $id,
                        'type' => 'bottle',
                        'name' => $actualName,
                        'display_name' => $actualName . ' (Bottle)',
                        'unit_type' => 'Bottle',
                        'price' => (float)$bottle['price'],
                        'quantity' => $qty,
                        'cost_price' => 0,
                        'stock_quantity' => $bottle['stock_quantity']
                    ];
                    echo json_encode(['success'=>true, 'msg'=>'Added to cart']);
                }
            } else {
                echo json_encode(['success'=>false, 'msg'=>'Insufficient stock. Available: ' . $bottle['stock_quantity']]);
            }
        } else {
            echo json_encode(['success'=>false, 'msg'=>'Bottle not found']);
        }
    } else {
        // Service
        $stmt = $conn->prepare("SELECT id, name, price FROM services WHERE id = ? AND status = 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $serv = $stmt->get_result()->fetch_assoc();
        
        if($serv){
            $found = false;
            foreach($_SESSION['cart'] as &$item) {
                if($item['item_id'] == $id && $item['type'] == 'service') {
                    $item['quantity'] += $qty;
                    $found = true;
                    echo json_encode(['success'=>true, 'msg'=>'Quantity updated in cart']);
                    exit;
                }
            }
            
            if(!$found) {
                $_SESSION['cart'][] = [
                    'item_id' => $id,
                    'type' => 'service',
                    'name' => $serv['name'],
                    'display_name' => $serv['name'],
                    'unit_type' => '',
                    'price' => (float)$serv['price'],
                    'quantity' => $qty,
                    'cost_price' => 0,
                    'stock_quantity' => 999
                ];
                echo json_encode(['success'=>true, 'msg'=>'Added to cart']);
            }
        } else {
            echo json_encode(['success'=>false, 'msg'=>'Service not found']);
        }
    }
    exit;
}

// Update cart quantity (AJAX) - Updated for bottles
if(isset($_POST['action']) && $_POST['action']=='update_qty'){
    $index = (int)$_POST['index'];
    $qty = (int)$_POST['qty'];
    
    if(isset($_SESSION['cart'][$index])) {
        if($qty <= 0) {
            unset($_SESSION['cart'][$index]);
            $_SESSION['cart'] = array_values($_SESSION['cart']);
            echo json_encode(['success'=>true, 'msg'=>'Item removed']);
        } else {
            // Check stock for medicines and bottles
            if($_SESSION['cart'][$index]['type'] == 'medicine') {
                $itemId = $_SESSION['cart'][$index]['item_id'];
                $stmt = $conn->prepare("SELECT stock_quantity FROM medicines WHERE id = ?");
                $stmt->bind_param("i", $itemId);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                if($result && $qty <= $result['stock_quantity']) {
                    $_SESSION['cart'][$index]['quantity'] = $qty;
                    echo json_encode(['success'=>true, 'msg'=>'Quantity updated']);
                } else {
                    echo json_encode(['success'=>false, 'msg'=>'Insufficient stock. Available: ' . $result['stock_quantity']]);
                }
            } elseif($_SESSION['cart'][$index]['type'] == 'bottle') {
                $itemId = $_SESSION['cart'][$index]['item_id'];
                $stmt = $conn->prepare("SELECT stock_quantity FROM bottles WHERE id = ?");
                $stmt->bind_param("i", $itemId);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                if($result && $qty <= $result['stock_quantity']) {
                    $_SESSION['cart'][$index]['quantity'] = $qty;
                    echo json_encode(['success'=>true, 'msg'=>'Quantity updated']);
                } else {
                    echo json_encode(['success'=>false, 'msg'=>'Insufficient stock. Available: ' . $result['stock_quantity']]);
                }
            } else {
                $_SESSION['cart'][$index]['quantity'] = $qty;
                echo json_encode(['success'=>true, 'msg'=>'Quantity updated']);
            }
        }
    } else {
        echo json_encode(['success'=>false, 'msg'=>'Item not found']);
    }
    exit;
}

// Remove item from cart
if(isset($_GET['remove'])){ 
    $index = (int)$_GET['remove'];
    if(isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    }
    redirect('pos.php'); 
}

// Clear cart
if(isset($_GET['clear'])){ 
    $_SESSION['cart'] = []; 
    redirect('pos.php'); 
}

// Save Sale - Updated for bottles
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['save_sale'])){
    $payment = $_POST['payment_method'];
    $amount_paid = (float)($_POST['amount_paid'] ?? 0);
    $total = 0; 
    
    foreach($_SESSION['cart'] as $it) {
        $total += $it['price'] * $it['quantity'];
    }
    
    $change = $amount_paid - $total;
    $net = $total;
    $invoice = 'INV-' . date('Ymd') . '-' . time();
    
    if(empty($_SESSION['cart'])) {
        echo "<script>alert('Cart is empty!'); window.location='pos.php';</script>";
        exit;
    }
    
    if(empty($payment)) {
        echo "<script>alert('Please select a payment method!'); window.location='pos.php';</script>";
        exit;
    }
    
    if($amount_paid < $total) {
        echo "<script>alert('Amount paid is less than total amount!'); window.location='pos.php';</script>";
        exit;
    }
    
    $conn->begin_transaction();
    try{
        $columns = $conn->query("SHOW COLUMNS FROM sales_master");
        $columnNames = [];
        while($col = $columns->fetch_assoc()) {
            $columnNames[] = $col['Field'];
        }
        
        if(in_array('amount_paid', $columnNames) && in_array('change_amount', $columnNames)) {
            $stmt = $conn->prepare("INSERT INTO sales_master (invoice_no, cashier_id, total_amount, net_amount, payment_method, amount_paid, change_amount, sale_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $date = date('Y-m-d H:i:s');
            $stmt->bind_param("siddsddss", $invoice, $cashier_id, $total, $net, $payment, $amount_paid, $change, $date);
        } else {
            $stmt = $conn->prepare("INSERT INTO sales_master (invoice_no, cashier_id, total_amount, net_amount, payment_method, sale_date) VALUES (?, ?, ?, ?, ?, ?)");
            $date = date('Y-m-d H:i:s');
            $stmt->bind_param("siddss", $invoice, $cashier_id, $total, $net, $payment, $date);
        }
        
        if(!$stmt->execute()) {
            throw new Exception("Failed to save sale: " . $stmt->error);
        }
        $sale_id = $conn->insert_id;
        
        foreach($_SESSION['cart'] as $item){
            $cost = $item['cost_price'] ?? 0;
            $total_price = $item['price'] * $item['quantity'];
            
            // Update stock for medicines and bottles
            if($item['type'] == 'medicine'){
                $update_stmt = $conn->prepare("UPDATE medicines SET stock_quantity = stock_quantity - ? WHERE id = ?");
                $update_stmt->bind_param("ii", $item['quantity'], $item['item_id']);
                if(!$update_stmt->execute()) {
                    throw new Exception("Failed to update stock for medicine ID: " . $item['item_id']);
                }
            } elseif($item['type'] == 'bottle'){
                $update_stmt = $conn->prepare("UPDATE bottles SET stock_quantity = stock_quantity - ? WHERE id = ?");
                $update_stmt->bind_param("ii", $item['quantity'], $item['item_id']);
                if(!$update_stmt->execute()) {
                    throw new Exception("Failed to update stock for bottle ID: " . $item['item_id']);
                }
            }
            
            $itemName = $item['name'];
            
            $ins = $conn->prepare("INSERT INTO sale_items (sale_id, item_type, item_id, item_name, quantity, unit_price, total_price, cost_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("isisiddd", $sale_id, $item['type'], $item['item_id'], $itemName, $item['quantity'], $item['price'], $total_price, $cost);
            
            if(!$ins->execute()) {
                throw new Exception("Failed to save sale item: " . $ins->error);
            }
        }
        
        $conn->commit();
        $_SESSION['cart'] = [];
        
        $changeMsg = "";
        if(in_array('amount_paid', $columnNames) && in_array('change_amount', $columnNames)) {
            $changeMsg = "\nChange: " . number_format($change, 2);
        }
        
        echo "<script>
            alert('Sale saved successfully! Invoice: $invoice$changeMsg');
            window.location='receipt.php?id=$sale_id';
        </script>";
        exit;
        
    } catch(Exception $e){
        $conn->rollback();
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location='pos.php';</script>";
        exit;
    }
}

// Fetch ALL medicines grouped by category
$medicines = $conn->query("
    SELECT m.id, m.name, m.unit_type, m.selling_price, m.stock_quantity,
           COALESCE(c.name, 'General') AS category_name
    FROM medicines m
    LEFT JOIN categories c ON c.id = m.category_id
    ORDER BY category_name ASC, m.name ASC
");

// Fetch ALL bottles
$bottles = $conn->query("SELECT id, name, price, stock_quantity, size, type FROM bottles ORDER BY name ASC");

// Fetch services
$services = $conn->query("SELECT id, name, price FROM services WHERE status = 1 ORDER BY name ASC");

// Group medicines by category
$medicineGroups = [];
while($m = $medicines->fetch_assoc()) {
    $categoryName = trim((string)($m['category_name'] ?? 'General'));
    if($categoryName === '') {
        $categoryName = 'General';
    }
    $medicineGroups[$categoryName][] = $m;
}

// Calculate grand total
$grand_total = 0;
foreach($_SESSION['cart'] as $it) {
    $grand_total += $it['price'] * $it['quantity'];
}

// Unit type icon mapping
function getUnitIcon($unitType) {
    $unitType = strtolower(trim((string)$unitType));
    $icons = [
        'sachet' => 'fa-bag-shopping',
        'syringe' => 'fa-syringe',
        'capsule' => 'fa-capsules',
        'tablet' => 'fa-tablets',
        'bottle' => 'fa-flask',
        'inhaler' => 'fa-lungs',
        'tube' => 'fa-tube',
        'vial' => 'fa-vial',
        'ampoule' => 'fa-flask',
        'pack' => 'fa-box',
        'box' => 'fa-box-open',
        'strip' => 'fa-receipt',
        'piece' => 'fa-cube',
        'drop' => 'fa-droplet',
        'spray' => 'fa-spray-can',
        'cream' => 'fa-pump-soap',
        'ointment' => 'fa-jar',
        'gel' => 'fa-flask',
        'patch' => 'fa-bandage',
        'lozenge' => 'fa-candy-cane',
        'powder' => 'fa-mortar-pestle',
        'suppository' => 'fa-circle',
        'injection' => 'fa-syringe',
        'infusion' => 'fa-droplet'
    ];
    return $icons[$unitType] ?? 'fa-pills';
}

function getUnitLabel($unitType) {
    $unitType = strtolower(trim((string)$unitType));
    $labels = [
        'sachet' => 'Sachet',
        'syringe' => 'Syringe',
        'capsule' => 'Capsule',
        'tablet' => 'Tablet',
        'bottle' => 'Bottle',
        'inhaler' => 'Inhaler',
        'tube' => 'Tube',
        'vial' => 'Vial',
        'ampoule' => 'Ampoule',
        'pack' => 'Pack',
        'box' => 'Box',
        'strip' => 'Strip',
        'piece' => 'Piece',
        'drop' => 'Drop',
        'spray' => 'Spray',
        'cream' => 'Cream',
        'ointment' => 'Ointment',
        'gel' => 'Gel',
        'patch' => 'Patch',
        'lozenge' => 'Lozenge',
        'powder' => 'Powder',
        'suppository' => 'Suppository',
        'injection' => 'Injection',
        'infusion' => 'Infusion'
    ];
    return $labels[$unitType] ?? ucfirst($unitType);
}

function getUnitColor($unitType) {
    $unitType = strtolower(trim((string)$unitType));
    $colors = [
        'sachet' => '#8B5CF6',
        'syringe' => '#EF4444',
        'capsule' => '#22D3EE',
        'tablet' => '#34D399',
        'bottle' => '#3B82F6',
        'inhaler' => '#8B5CF6',
        'tube' => '#F59E0B',
        'vial' => '#EC4899',
        'ampoule' => '#F472B6',
        'pack' => '#8B5CF6',
        'box' => '#F59E0B',
        'strip' => '#10B981',
        'piece' => '#6B7280',
        'drop' => '#3B82F6',
        'spray' => '#8B5CF6',
        'cream' => '#FCD34D',
        'ointment' => '#FCD34D',
        'gel' => '#34D399',
        'patch' => '#F59E0B',
        'lozenge' => '#EC4899',
        'powder' => '#9CA3AF',
        'suppository' => '#6B7280',
        'injection' => '#EF4444',
        'infusion' => '#3B82F6'
    ];
    return $colors[$unitType] ?? '#6B7280';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Premium Pharmacy | Pharmacy POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { background: #f4f6f9; margin: 0; padding: 0; font-family: Inter, system-ui, sans-serif; }
        .content { background: #f4f6f9; padding: 20px; }
        
        .product-card { 
            transition: transform 0.2s, box-shadow 0.2s; 
            cursor: pointer;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            height: 100%;
            background: #fff;
            position: relative;
            overflow: hidden;
        }
        .product-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 12px 25px rgba(23,37,84,0.12);
            border-color: #2563eb;
        }
        .product-card .card-body {
            padding: 16px 12px;
        }
        .product-card .card-title {
            font-size: 13px;
            font-weight: 600;
            min-height: 38px;
            margin-bottom: 6px;
        }
        .product-card .price {
            font-size: 16px;
            font-weight: bold;
            color: #172554;
        }
        .product-card .stock {
            font-size: 11px;
            color: #6c757d;
        }
        .product-card .stock.out-of-stock {
            color: #dc3545;
            font-weight: bold;
        }
        .product-card .btn-add {
            width: 100%;
            margin-top: 5px;
            font-size: 12px;
            padding: 5px 8px;
        }
        .product-card.out-of-stock {
            opacity: 0.6;
            border-color: #dc3545;
        }
        .product-card.out-of-stock .btn-add {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .unit-icon-wrapper {
            width: 52px;
            height: 52px;
            margin: 0 auto 8px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            transition: all 0.3s ease;
        }
        .product-card:hover .unit-icon-wrapper {
            transform: scale(1.05);
        }
        
        .unit-label-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 3px 8px;
            border-radius: 12px;
            color: white;
        }
        
        /* Bottle specific styles */
        .bottle-item .product-card {
            border-color: #0d6efd;
        }
        .bottle-item .product-card .price {
            color: #0d6efd;
        }
        .bottle-icon-wrapper {
            width: 52px;
            height: 52px;
            margin: 0 auto 8px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            background: #0d6efd;
            transition: all 0.3s ease;
        }
        .bottle-item .product-card:hover .bottle-icon-wrapper {
            transform: scale(1.05);
        }
        .bottle-badge {
            background: #0d6efd;
            color: white;
            font-size: 9px;
            padding: 2px 10px;
            border-radius: 12px;
            display: inline-block;
            margin-bottom: 5px;
        }
        
        .cart-table {
            font-size: 13px;
        }
        .cart-table th {
            background: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .cart-item-name {
            max-width: 180px;
            word-wrap: break-word;
        }
        .cart-total {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
        }
        .search-box {
            position: sticky;
            top: 0;
            z-index: 20;
            background: white;
            padding: 10px 0;
        }
        .search-box .input-group {
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            overflow: hidden;
        }
        .empty-cart {
            text-align: center;
            padding: 30px 0;
            color: #999;
        }
        .empty-cart i {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .category-filter .btn {
            margin: 3px;
            font-size: 12px;
            border-radius: 6px;
        }
        .service-item .product-card {
            border-color: #17a2b8;
        }
        .service-item .product-card .price {
            color: #17a2b8;
        }
        .product-grid {
            max-height: 600px;
            overflow-y: auto;
        }
        .category-heading {
            margin-top: 8px;
        }
        .category-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 16px;
            margin: 8px 0 14px;
            border-radius: 12px;
            background: linear-gradient(135deg, #e9f7f3, #f8fbfa);
            border-left: 5px solid #0d6efd;
            color: #243b53;
            font-weight: 700;
        }
        .category-count {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            background: #fff;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .service-badge {
            background: #172554;
            color: white;
            font-size: 10px;
            padding: 2px 10px;
            border-radius: 12px;
            display: inline-block;
            margin-bottom: 5px;
        }
        .medicine-category {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #0d6efd;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .unit-label {
            font-size: 10px;
            color: #6c757d;
            margin-left: 3px;
            font-weight: normal;
        }
        .service-icon {
            font-size: 28px;
            color: #17a2b8;
            margin-bottom: 8px;
            display: block;
        }
        .card-header {
            background: linear-gradient(90deg, #172554, #2563eb) !important;
            border: 0 !important;
        }
        .btn-success {
            background: #2563eb;
            border-color: #2563eb;
        }
        .btn-success:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }
        
        /* Cart quantity input styles */
        .qty-input {
            width: 50px;
            text-align: center;
            padding: 2px 4px;
            font-size: 13px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        .qty-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37,99,235,0.2);
        }
        .qty-btn {
            padding: 2px 6px;
            font-size: 12px;
            line-height: 1;
            border-radius: 4px;
            background: #f8f9fa;
            border: 1px solid #ced4da;
            cursor: pointer;
            transition: all 0.2s;
        }
        .qty-btn:hover {
            background: #e9ecef;
        }
        
        .change-amount {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
        }
        .change-amount.negative {
            color: #dc3545;
        }
        
        .payment-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
        }
        
        .item-type-badge {
            font-size: 8px;
            padding: 2px 6px;
            border-radius: 4px;
            margin-right: 4px;
        }
        
        @media print {
            .no-print { display: none !important; }
        }
        
        .product-grid::-webkit-scrollbar {
            width: 5px;
        }
        .product-grid::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .product-grid::-webkit-scrollbar-thumb {
            background: #2563eb;
            border-radius: 10px;
        }
        .product-grid::-webkit-scrollbar-thumb:hover {
            background: #1d4ed8;
        }
        
        /* Stock indicator badge */
        .stock-indicator {
            position: absolute;
            top: 8px;
            left: 8px;
            font-size: 8px;
            padding: 2px 8px;
            border-radius: 10px;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'cashiers_sidebar.php'; ?>
        
        <div class="col-md-10 content">
            <div class="row">
                <!-- Left Column: Products -->
                <div class="col-md-7">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-capsules"></i> Products & Services</h5>
                            <span class="badge bg-light text-dark">
                                <?= array_sum(array_map('count', $medicineGroups)) ?> Medicines | 
                                <?= $bottles->num_rows ?> Bottles | 
                                <?= $services->num_rows ?> Services
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="search-box">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" id="search" class="form-control" placeholder="Search medicine, bottle or service...">
                                    <button class="btn btn-outline-secondary" onclick="clearSearch()" type="button">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Category Filter -->
                            <div class="category-filter">
                                <button class="btn btn-sm btn-outline-primary active" onclick="filterCategory('all')">All</button>
                                <button class="btn btn-sm btn-outline-success" onclick="filterCategory('medicine')">Medicines</button>
                                <button class="btn btn-sm btn-outline-primary" onclick="filterCategory('bottle')">Bottles</button>
                                <button class="btn btn-sm btn-outline-info" onclick="filterCategory('service')">Services</button>
                                <button class="btn btn-sm btn-outline-danger" onclick="filterCategory('outofstock')">Out of Stock</button>
                            </div>
                            
                            <div class="row product-grid" id="productList">
                                <?php if(!empty($medicineGroups) || $bottles->num_rows > 0 || $services->num_rows > 0): ?>
                                
                                <!-- ===== MEDICINES SECTION ===== -->
                                <?php foreach($medicineGroups as $categoryName => $categoryMedicines): ?>
                                <div class="col-12 category-heading" data-category="medicine">
                                    <div class="category-title">
                                        <span>
                                            <i class="fas fa-layer-group me-2"></i>
                                            <?= htmlspecialchars($categoryName) ?>
                                        </span>
                                        <span class="category-count">
                                            <?= count($categoryMedicines) ?> item<?= count($categoryMedicines) == 1 ? '' : 's' ?>
                                        </span>
                                    </div>
                                </div>

                                <?php foreach($categoryMedicines as $m):
                                    $isOutOfStock = ((int)$m['stock_quantity'] <= 0);
                                    $unitType = trim((string)($m['unit_type'] ?? ''));
                                    $unitIcon = getUnitIcon($unitType);
                                    $unitLabel = getUnitLabel($unitType);
                                    $unitColor = getUnitColor($unitType);
                                    $hasUnit = !empty($unitType);
                                ?>
                                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3 product-item medicine-item <?= $isOutOfStock ? 'out-of-stock-item' : '' ?>" data-type="medicine">
                                    <div class="card product-card <?= $isOutOfStock ? 'out-of-stock' : '' ?>">
                                        <?php if($isOutOfStock): ?>
                                        <span class="stock-indicator badge bg-danger">Out of Stock</span>
                                        <?php elseif((int)$m['stock_quantity'] <= 10): ?>
                                        <span class="stock-indicator badge bg-warning text-dark">Low Stock</span>
                                        <?php endif; ?>
                                        <?php if($hasUnit): ?>
                                        <span class="unit-label-badge" style="background: <?= $unitColor ?>;">
                                            <?= htmlspecialchars($unitLabel) ?>
                                        </span>
                                        <?php endif; ?>
                                        <div class="card-body text-center">
                                            <div class="unit-icon-wrapper" style="background: <?= $unitColor ?>;">
                                                <i class="fas <?= $unitIcon ?>"></i>
                                            </div>
                                            
                                            <div class="medicine-category">
                                                <?= htmlspecialchars($categoryName) ?>
                                            </div>
                                            
                                            <h6 class="card-title">
                                                <?= htmlspecialchars($m['name']) ?>
                                                <?php if($hasUnit): ?>
                                                    <span class="unit-label">(<?= htmlspecialchars($unitLabel) ?>)</span>
                                                <?php endif; ?>
                                            </h6>
                                            
                                            <p class="price">
                                                <?= getCurrency() . number_format((float)$m['selling_price'], 2) ?>
                                            </p>
                                            
                                            <p class="stock">
                                                <?php if($isOutOfStock): ?>
                                                    <span class="badge bg-danger">Out of Stock</span>
                                                <?php elseif((int)$m['stock_quantity'] <= 10): ?>
                                                    <span class="badge bg-warning text-dark">Stock: <?= (int)$m['stock_quantity'] ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Stock: <?= (int)$m['stock_quantity'] ?></span>
                                                <?php endif; ?>
                                            </p>
                                            
                                            <button
                                                onclick="addToCart(<?= (int)$m['id'] ?>,'medicine', '<?= htmlspecialchars($m['name']) ?>', <?= (float)$m['selling_price'] ?>, <?= (int)$m['stock_quantity'] ?>, '<?= htmlspecialchars($unitType) ?>')"
                                                class="btn btn-sm btn-success btn-add"
                                                <?= $isOutOfStock ? 'disabled' : '' ?>
                                            >
                                                <i class="fas fa-cart-plus"></i>
                                                <?= $isOutOfStock ? 'Unavailable' : 'Add' ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endforeach; ?>
                                
                                <!-- ===== BOTTLES SECTION ===== -->
                                <?php if($bottles->num_rows > 0): ?>
                                <div class="col-12 category-heading" data-category="bottle">
                                    <div class="category-title" style="border-left-color: #0d6efd; background: linear-gradient(135deg, #e8f0fe, #f0f7ff);">
                                        <span>
                                            <i class="fas fa-flask me-2 text-primary"></i>
                                            Bottles & Containers
                                        </span>
                                        <span class="category-count">
                                            <?= $bottles->num_rows ?> item<?= $bottles->num_rows == 1 ? '' : 's' ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php while($b = $bottles->fetch_assoc()):
                                    $isOutOfStock = ((int)$b['stock_quantity'] <= 0);
                                    $bottleSize = trim((string)($b['size'] ?? ''));
                                    $bottleType = trim((string)($b['type'] ?? ''));
                                    $sizeLabel = !empty($bottleSize) ? $bottleSize : '';
                                    $typeLabel = !empty($bottleType) ? $bottleType : '';
                                ?>
                                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3 product-item bottle-item <?= $isOutOfStock ? 'out-of-stock-item' : '' ?>" data-type="bottle">
                                    <div class="card product-card <?= $isOutOfStock ? 'out-of-stock' : '' ?>">
                                        <?php if($isOutOfStock): ?>
                                        <span class="stock-indicator badge bg-danger">Out of Stock</span>
                                        <?php elseif((int)$b['stock_quantity'] <= 10): ?>
                                        <span class="stock-indicator badge bg-warning text-dark">Low Stock</span>
                                        <?php endif; ?>
                                        <?php if(!empty($sizeLabel) || !empty($typeLabel)): ?>
                                        <span class="unit-label-badge" style="background: #0d6efd;">
                                            <?= htmlspecialchars($sizeLabel) ?>
                                            <?php if(!empty($sizeLabel) && !empty($typeLabel)): ?> • <?php endif; ?>
                                            <?= htmlspecialchars($typeLabel) ?>
                                        </span>
                                        <?php endif; ?>
                                        <div class="card-body text-center">
                                            <div class="bottle-icon-wrapper">
                                                <i class="fas fa-flask"></i>
                                            </div>
                                            
                                            <div class="medicine-category" style="color: #0d6efd;">
                                                Bottle
                                            </div>
                                            
                                            <h6 class="card-title">
                                                <?= htmlspecialchars($b['name']) ?>
                                                <?php if(!empty($sizeLabel)): ?>
                                                    <span class="unit-label">(<?= htmlspecialchars($sizeLabel) ?>)</span>
                                                <?php endif; ?>
                                            </h6>
                                            
                                            <p class="price" style="color: #0d6efd;">
                                                <?= getCurrency() . number_format((float)$b['price'], 2) ?>
                                            </p>
                                            
                                            <p class="stock">
                                                <?php if($isOutOfStock): ?>
                                                    <span class="badge bg-danger">Out of Stock</span>
                                                <?php elseif((int)$b['stock_quantity'] <= 10): ?>
                                                    <span class="badge bg-warning text-dark">Stock: <?= (int)$b['stock_quantity'] ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">Stock: <?= (int)$b['stock_quantity'] ?></span>
                                                <?php endif; ?>
                                            </p>
                                            
                                            <button
                                                onclick="addToCart(<?= (int)$b['id'] ?>,'bottle', '<?= htmlspecialchars($b['name']) ?>', <?= (float)$b['price'] ?>, <?= (int)$b['stock_quantity'] ?>, 'Bottle')"
                                                class="btn btn-sm btn-primary btn-add"
                                                <?= $isOutOfStock ? 'disabled' : '' ?>
                                            >
                                                <i class="fas fa-cart-plus"></i>
                                                <?= $isOutOfStock ? 'Unavailable' : 'Add' ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                                <?php endif; ?>
                                
                                <!-- ===== SERVICES SECTION ===== -->
                                <?php if($services->num_rows > 0): ?>
                                <div class="col-12 category-heading" data-category="service">
                                    <div class="category-title" style="border-left-color: #17a2b8; background: linear-gradient(135deg, #e6f7fb, #f0fafa);">
                                        <span>
                                            <i class="fas fa-concierge-bell me-2 text-info"></i>
                                            Services
                                        </span>
                                        <span class="category-count">
                                            <?= $services->num_rows ?> service<?= $services->num_rows == 1 ? '' : 's' ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php while($s = $services->fetch_assoc()): ?>
                                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3 product-item service-item" data-type="service">
                                    <div class="card product-card">
                                        <div class="card-body text-center">
                                            <i class="fas fa-hand-holding-heart service-icon"></i>
                                            <span class="service-badge">Service</span>
                                            <h6 class="card-title"><?= htmlspecialchars($s['name']) ?></h6>
                                            <p class="price"><?= getCurrency() . number_format($s['price'], 2) ?></p>
                                            <p class="stock"><span class="badge bg-info">Always Available</span></p>
                                            <button onclick="addToCart(<?= $s['id'] ?>,'service', '<?= htmlspecialchars($s['name']) ?>', <?= $s['price'] ?>, 999, '')" class="btn btn-sm btn-info text-white btn-add">
                                                <i class="fas fa-cart-plus"></i> Add
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                                <?php endif; ?>
                                
                                <?php else: ?>
                                <div class="col-12 text-center py-5">
                                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No products or services available</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Cart -->
                <div class="col-md-5">
                    <div class="card shadow-sm">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> Current Sale Cart</h5>
                            <span class="badge bg-info" id="cartCount"><?= count($_SESSION['cart']) ?> items</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                                <?php if(!empty($_SESSION['cart'])): ?>
                                <table class="table table-sm table-hover cart-table" id="cartTable">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-end">Total</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="cartBody">
                                        <?php foreach($_SESSION['cart'] as $idx => $it): ?>
                                        <tr data-index="<?= $idx ?>">
                                            <td class="cart-item-name">
                                                <?php if($it['type'] == 'service'): ?>
                                                    <span class="badge bg-info item-type-badge">S</span>
                                                <?php elseif($it['type'] == 'bottle'): ?>
                                                    <span class="badge bg-primary item-type-badge">B</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success item-type-badge">M</span>
                                                <?php endif; ?>
                                                <strong><?= htmlspecialchars($it['name']) ?></strong>
                                                <?php if(!empty($it['unit_type'])): ?>
                                                    <br><small class="text-muted"><i class="fas fa-cube me-1"></i><?= htmlspecialchars($it['unit_type']) ?></small>
                                                <?php endif; ?>
                                                <br>
                                                <small class="text-muted"><?= getCurrency() . number_format($it['price'], 2) ?> each</small>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex align-items-center justify-content-center gap-1">
                                                    <button class="qty-btn" onclick="updateQty(<?= $idx ?>, -1)">-</button>
                                                    <input type="number" class="qty-input" id="qty_<?= $idx ?>" value="<?= $it['quantity'] ?>" min="1" max="<?= $it['type'] == 'medicine' || $it['type'] == 'bottle' ? $it['stock_quantity'] : 999 ?>" onchange="updateQtyInput(<?= $idx ?>)" onkeypress="if(event.key === 'Enter') updateQtyInput(<?= $idx ?>)">
                                                    <button class="qty-btn" onclick="updateQty(<?= $idx ?>, 1)">+</button>
                                                </div>
                                            </td>
                                            <td class="text-end item-total"><?= getCurrency() . number_format($it['price'] * $it['quantity'], 2) ?></td>
                                            <td class="text-center">
                                                <button onclick="removeItem(<?= $idx ?>)" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php else: ?>
                                <div class="empty-cart">
                                    <i class="fas fa-shopping-cart"></i>
                                    <p>Cart is empty</p>
                                    <small class="text-muted">Add items from the left panel</small>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="border-top pt-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">Total:</span>
                                    <span class="cart-total" id="grandTotal"><?= getCurrency() . number_format($grand_total, 2) ?></span>
                                </div>
                                
                                <form method="POST" id="saleForm" class="mt-3">
                                    <div class="payment-section">
                                        <div class="mb-2">
                                            <label class="fw-bold">Payment Method</label>
                                            <select name="payment_method" id="paymentMethod" class="form-select" required>
                                                <option value="">-- Select --</option>
                                                <option value="Cash">💵 Cash</option>
                                                <option value="M-Pesa">📱 M-Pesa</option>
                                                <option value="Bank Transfer">🏦 Bank Transfer</option>
                                                <option value="Credit Card">💳 Credit Card</option>
                                            </select>
                                        </div>
                                        <div class="mb-2">
                                            <label class="fw-bold">Amount Paid</label>
                                            <input type="number" step="0.01" name="amount_paid" id="amountPaid" class="form-control" placeholder="Enter amount paid" min="0" oninput="calculateChange()" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="fw-bold">Change</label>
                                            <div class="change-amount" id="changeDisplay"><?= getCurrency() . '0.00' ?></div>
                                        </div>
                                    </div>
                                    <button type="submit" name="save_sale" class="btn btn-success w-100" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>
                                        <i class="fas fa-save"></i> Complete Sale
                                    </button>
                                    <a href="?clear=1" class="btn btn-danger w-100 mt-2" onclick="return confirm('Clear entire cart?')">
                                        <i class="fas fa-trash-alt"></i> Clear Cart
                                    </a>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quantity Modal -->
<div class="modal fade" id="qtyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title"><i class="fas fa-plus-circle"></i> Enter Quantity</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="qtyItemInfo"></div>
                <div class="mb-3">
                    <label class="fw-bold">Quantity</label>
                    <input type="number" id="qtyInput" class="form-control" value="1" min="1" max="999">
                    <small id="qtyStockInfo" class="text-muted"></small>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="confirmAddToCart()"><i class="fas fa-check"></i> Add to Cart</button>
            </div>
        </div>
    </div>
</div>

<script>
let pendingItem = null;
let pendingType = null;
let pendingPrice = 0;
let pendingStock = 0;
let pendingName = '';
let pendingUnit = '';

function addToCart(id, type, name, price, stock, unit) {
    pendingItem = id;
    pendingType = type;
    pendingPrice = price;
    pendingStock = stock;
    pendingName = name;
    pendingUnit = unit || '';
    
    let typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
    let unitDisplay = '';
    if(unit) {
        unitDisplay = `<p><strong>Unit:</strong> ${unit}</p>`;
    }
    $('#qtyItemInfo').html(`
        <p><strong>Item:</strong> ${name}</p>
        <p><strong>Type:</strong> <span class="badge bg-${type === 'medicine' ? 'success' : type === 'bottle' ? 'primary' : 'info'}">${typeLabel}</span></p>
        ${unitDisplay}
        <p><strong>Price:</strong> <?= getCurrency() ?> ${price.toFixed(2)}</p>
        <p><strong>Available:</strong> ${stock === 999 ? 'Unlimited' : stock}</p>
    `);
    $('#qtyInput').val(1).attr('max', stock === 999 ? 999 : stock);
    $('#qtyStockInfo').text(stock === 999 ? 'Service - always available' : `Max: ${stock}`);
    $('#qtyModal').modal('show');
}

function confirmAddToCart() {
    let qty = parseInt($('#qtyInput').val()) || 1;
    if(qty < 1) qty = 1;
    if(pendingStock !== 999 && qty > pendingStock) {
        alert('Insufficient stock! Available: ' + pendingStock);
        return;
    }
    
    $.post('pos.php', {
        action: 'add', 
        id: pendingItem, 
        type: pendingType, 
        qty: qty
    }, function(res) {
        try {
            let data = JSON.parse(res);
            if(data.success) {
                location.reload();
            } else {
                alert(data.msg || 'Error adding item');
            }
        } catch(e) { 
            alert('Invalid response from server');
        }
    });
    $('#qtyModal').modal('hide');
}

function updateQty(index, change) {
    let input = document.getElementById('qty_' + index);
    let currentVal = parseInt(input.value) || 1;
    let newVal = currentVal + change;
    let maxVal = parseInt(input.getAttribute('max')) || 999;
    
    if(newVal < 1) newVal = 1;
    if(newVal > maxVal) {
        alert('Maximum quantity is ' + maxVal);
        return;
    }
    
    updateQtyServer(index, newVal);
}

function updateQtyInput(index) {
    let input = document.getElementById('qty_' + index);
    let val = parseInt(input.value) || 1;
    let maxVal = parseInt(input.getAttribute('max')) || 999;
    
    if(val < 1) val = 1;
    if(val > maxVal) {
        alert('Maximum quantity is ' + maxVal);
        val = maxVal;
    }
    
    updateQtyServer(index, val);
}

function updateQtyServer(index, qty) {
    $.post('pos.php', {
        action: 'update_qty',
        index: index,
        qty: qty
    }, function(res) {
        try {
            let data = JSON.parse(res);
            if(data.success) {
                location.reload();
            } else {
                alert(data.msg || 'Error updating quantity');
                location.reload();
            }
        } catch(e) {
            alert('Error updating quantity');
        }
    });
}

function removeItem(index) {
    if(confirm('Remove this item from cart?')) {
        updateQtyServer(index, 0);
    }
}

function calculateChange() {
    let total = <?= $grand_total ?>;
    let paid = parseFloat(document.getElementById('amountPaid').value) || 0;
    let change = paid - total;
    let display = document.getElementById('changeDisplay');
    
    if(change >= 0) {
        display.className = 'change-amount';
        display.textContent = '<?= getCurrency() ?> ' + change.toFixed(2);
    } else {
        display.className = 'change-amount negative';
        display.textContent = '<?= getCurrency() ?> ' + change.toFixed(2) + ' (Short)';
    }
}

// Search filter
$('#search').on('keyup', function() {
    let val = $(this).val().toLowerCase();
    $('#productList .product-item').each(function() {
        let text = $(this).text().toLowerCase();
        if(text.indexOf(val) === -1) $(this).hide();
        else $(this).show();
    });
});

function clearSearch() {
    $('#search').val('').trigger('keyup');
}

// Category filter
function filterCategory(type) {
    $('.category-filter .btn').removeClass('active');
    $('.category-filter .btn').each(function() {
        let btnText = $(this).text().toLowerCase();
        if(btnText === type || (type === 'all' && btnText === 'all') ||
           (type === 'medicine' && btnText === 'medicines') ||
           (type === 'bottle' && btnText === 'bottles') ||
           (type === 'service' && btnText === 'services') ||
           (type === 'outofstock' && btnText === 'out of stock')) {
            $(this).addClass('active');
        }
    });
    
    if(type === 'all') {
        $('#productList .product-item').show();
        $('#productList .category-heading').show();
    } else if(type === 'medicine') {
        $('#productList .product-item').hide();
        $('#productList .medicine-item').show();
        $('#productList .category-heading').show();
        $('#productList .category-heading[data-category="medicine"]').show();
        $('#productList .category-heading[data-category="bottle"]').hide();
        $('#productList .category-heading[data-category="service"]').hide();
    } else if(type === 'bottle') {
        $('#productList .product-item').hide();
        $('#productList .bottle-item').show();
        $('#productList .category-heading').hide();
        $('#productList .category-heading[data-category="bottle"]').show();
    } else if(type === 'service') {
        $('#productList .product-item').hide();
        $('#productList .service-item').show();
        $('#productList .category-heading').hide();
        $('#productList .category-heading[data-category="service"]').show();
    } else if(type === 'outofstock') {
        $('#productList .product-item').hide();
        $('#productList .out-of-stock-item').show();
        $('#productList .category-heading').show();
        $('#productList .category-heading').each(function() {
            let nextItems = $(this).nextUntil('.category-heading', '.product-item');
            let hasOutOfStock = nextItems.filter('.out-of-stock-item:visible').length > 0;
            if(!hasOutOfStock) $(this).hide();
        });
    }
}

// Update cart count
function updateCartCount() {
    let count = <?= count($_SESSION['cart']) ?>;
    $('#cartCount').text(count + ' items');
}

// Auto-calculate change on amount paid input
document.addEventListener('DOMContentLoaded', function() {
    updateCartCount();
    calculateChange();
    
    document.getElementById('amountPaid').addEventListener('input', calculateChange);
    
    document.getElementById('saleForm').addEventListener('submit', function(e) {
        let paid = parseFloat(document.getElementById('amountPaid').value) || 0;
        let total = <?= $grand_total ?>;
        if(paid < total) {
            e.preventDefault();
            alert('Amount paid is less than the total amount!');
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>