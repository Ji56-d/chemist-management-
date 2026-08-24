<?php 
require_once 'config/db.php'; 
if(!isAdmin()) redirect('dashboard.php');

// Create bottles table if it doesn't exist
$conn->query("
CREATE TABLE IF NOT EXISTS bottles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    size VARCHAR(100) DEFAULT NULL,
    type VARCHAR(100) DEFAULT NULL,
    stock_quantity INT DEFAULT 0,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Handle medicine deletion
if(isset($_GET['delete'])){ 
    $delete_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM medicines WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $_SESSION['success'] = "Medicine deleted successfully!";
    redirect('medicines.php'); 
}

// Handle category deletion
if(isset($_GET['delete_cat'])){ 
    $delete_cat_id = (int)$_GET['delete_cat'];
    $check = $conn->prepare("SELECT COUNT(*) as count FROM medicines WHERE category_id = ?");
    $check->bind_param("i", $delete_cat_id);
    $check->execute();
    $result = $check->get_result();
    $row = $result->fetch_assoc();
    
    if($row['count'] == 0) {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $delete_cat_id);
        $stmt->execute();
        $_SESSION['success'] = "Category deleted successfully!";
    } else {
        $_SESSION['error'] = "Cannot delete category with medicines assigned to it!";
    }
    redirect('medicines.php'); 
}

// Handle bottle deletion
if(isset($_GET['delete_bottle'])){ 
    $delete_id = (int)$_GET['delete_bottle'];
    $stmt = $conn->prepare("DELETE FROM bottles WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $_SESSION['success'] = "Bottle deleted successfully!";
    redirect('medicines.php'); 
}

// Handle medicine save (add/edit)
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['save'])){
    $id = (int)($_POST['id'] ?? 0);
    $name = $_POST['name']; 
    $cat = (int)$_POST['category_id']; 
    $unit = $_POST['unit_type']; 
    $stock = (int)$_POST['stock_quantity'];
    $cost = (float)$_POST['cost_price']; 
    $sell = (float)$_POST['selling_price']; 
    $exp = $_POST['expiry_date']; 
    $batch = $_POST['batch_no']; 
    $supp = $_POST['supplier']; 
    $barcode = $_POST['barcode'];
    
    if($id){
        $stmt = $conn->prepare("UPDATE medicines SET name=?, category_id=?, unit_type=?, stock_quantity=?, cost_price=?, selling_price=?, expiry_date=?, batch_no=?, supplier=?, barcode=? WHERE id=?");
        $stmt->bind_param("sisiddssssi", $name, $cat, $unit, $stock, $cost, $sell, $exp, $batch, $supp, $barcode, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO medicines (name, category_id, unit_type, stock_quantity, cost_price, selling_price, expiry_date, batch_no, supplier, barcode) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("sisiddssss", $name, $cat, $unit, $stock, $cost, $sell, $exp, $batch, $supp, $barcode);
    }
    $stmt->execute();
    $_SESSION['success'] = "Medicine saved successfully!";
    redirect('medicines.php');
}

// Handle bottle save (add/edit)
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['save_bottle'])){
    $id = (int)($_POST['bottle_id'] ?? 0);
    $name = trim($_POST['bottle_name']);
    $price = (float)$_POST['bottle_price'];
    $size = trim($_POST['bottle_size'] ?? '');
    $type = trim($_POST['bottle_type'] ?? '');
    $stock = (int)$_POST['bottle_stock'] ?? 0;
    $description = trim($_POST['bottle_description'] ?? '');
    
    if(empty($name) || $price <= 0) {
        $_SESSION['error'] = "Please provide valid bottle name and price!";
        redirect('medicines.php');
    }
    
    if($id){
        $stmt = $conn->prepare("UPDATE bottles SET name=?, price=?, size=?, type=?, stock_quantity=?, description=? WHERE id=?");
        $stmt->bind_param("sdssisi", $name, $price, $size, $type, $stock, $description, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO bottles (name, price, size, type, stock_quantity, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sdssis", $name, $price, $size, $type, $stock, $description);
    }
    $stmt->execute();
    $_SESSION['success'] = "Bottle saved successfully!";
    redirect('medicines.php');
}

// Handle stock addition
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['add_stock'])){
    $medicine_id = (int)$_POST['medicine_id'];
    $additional_stock = (int)$_POST['additional_stock'];
    $reason = $_POST['reason'] ?? 'Stock Addition';
    
    if($additional_stock > 0 && $medicine_id > 0){
        $get_stock = $conn->prepare("SELECT stock_quantity, name FROM medicines WHERE id = ?");
        $get_stock->bind_param("i", $medicine_id);
        $get_stock->execute();
        $result = $get_stock->get_result();
        $medicine = $result->fetch_assoc();
        
        if($medicine){
            $current_stock = $medicine['stock_quantity'];
            $new_stock = $current_stock + $additional_stock;
            $medicine_name = $medicine['name'];
            
            $update_stmt = $conn->prepare("UPDATE medicines SET stock_quantity = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $new_stock, $medicine_id);
            $update_stmt->execute();
            
            $_SESSION['success'] = "Added $additional_stock items to $medicine_name. New stock: $new_stock";
        }
    }
    redirect('medicines.php');
}

// Handle category save
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['save_category'])){
    $category_name = trim($_POST['category_name']);
    if(!empty($category_name)){
        // Check if category already exists
        $check = $conn->prepare("SELECT id FROM categories WHERE name = ?");
        $check->bind_param("s", $category_name);
        $check->execute();
        $result = $check->get_result();
        if($result->num_rows > 0) {
            $_SESSION['error'] = "Category '$category_name' already exists!";
        } else {
            $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->bind_param("s", $category_name);
            $stmt->execute();
            $_SESSION['success'] = "Category '$category_name' added successfully!";
        }
    }
    redirect('medicines.php');
}

// Handle bottle stock addition
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['add_bottle_stock'])){
    $bottle_id = (int)$_POST['bottle_id'];
    $additional_stock = (int)$_POST['additional_bottle_stock'];
    
    if($additional_stock > 0 && $bottle_id > 0){
        $get_stock = $conn->prepare("SELECT stock_quantity, name FROM bottles WHERE id = ?");
        $get_stock->bind_param("i", $bottle_id);
        $get_stock->execute();
        $result = $get_stock->get_result();
        $bottle = $result->fetch_assoc();
        
        if($bottle){
            $current_stock = $bottle['stock_quantity'];
            $new_stock = $current_stock + $additional_stock;
            $bottle_name = $bottle['name'];
            
            $update_stmt = $conn->prepare("UPDATE bottles SET stock_quantity = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $new_stock, $bottle_id);
            $update_stmt->execute();
            
            $_SESSION['success'] = "Added $additional_stock items to $bottle_name. New stock: $new_stock";
        }
    }
    redirect('medicines.php');
}

// Get current medicine ID for navigation
$current_id = isset($_GET['view_id']) ? (int)$_GET['view_id'] : 0;

// Get all medicine IDs for navigation (ordered by ID)
$id_list_result = $conn->query("SELECT id FROM medicines ORDER BY id ASC");
$all_ids = [];
while($row = $id_list_result->fetch_assoc()) {
    $all_ids[] = $row['id'];
}
$total_medicines_count = count($all_ids);

// Find current index
$current_index = -1;
if($current_id > 0) {
    $current_index = array_search($current_id, $all_ids);
    if($current_index === false) {
        // If current_id not found, redirect to first medicine
        if(!empty($all_ids)) {
            redirect('medicines.php?view_id=' . $all_ids[0]);
        } else {
            $current_id = 0;
        }
    }
} else if(!empty($all_ids)) {
    // No view_id, redirect to first medicine
    redirect('medicines.php?view_id=' . $all_ids[0]);
}

// Get previous and next IDs
$prev_id = ($current_index > 0) ? $all_ids[$current_index - 1] : 0;
$next_id = ($current_index < $total_medicines_count - 1) ? $all_ids[$current_index + 1] : 0;

// Fetch the current medicine details
$current_medicine = null;
if($current_id > 0) {
    $stmt = $conn->prepare("SELECT m.*, c.name as cat_name FROM medicines m LEFT JOIN categories c ON m.category_id=c.id WHERE m.id = ?");
    $stmt->bind_param("i", $current_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_medicine = $result->fetch_assoc();
}

// Fetch all bottles with error handling
$bottles = $conn->query("SELECT * FROM bottles ORDER BY name ASC");
if(!$bottles) {
    $conn->query("
    CREATE TABLE IF NOT EXISTS bottles (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        size VARCHAR(100) DEFAULT NULL,
        type VARCHAR(100) DEFAULT NULL,
        stock_quantity INT DEFAULT 0,
        description TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $bottles = $conn->query("SELECT * FROM bottles ORDER BY name ASC");
}

// Large-dataset safe medicine listing
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(25, (int)($_GET['per_page'] ?? 50)));
$offset = ($page - 1) * $perPage;

if ($search !== '') {
    $like = '%' . $search . '%';
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM medicines WHERE name LIKE ? OR batch_no LIKE ? OR supplier LIKE ?");
    $countStmt->bind_param('sss', $like, $like, $like);
    $countStmt->execute();
    $totalMedicines = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $medStmt = $conn->prepare("SELECT m.*, c.name as cat_name FROM medicines m LEFT JOIN categories c ON m.category_id=c.id WHERE m.name LIKE ? OR m.batch_no LIKE ? OR m.supplier LIKE ? ORDER BY m.id DESC LIMIT ? OFFSET ?");
    $medStmt->bind_param('sssii', $like, $like, $like, $perPage, $offset);
    $medStmt->execute();
    $medicines = $medStmt->get_result();
} else {
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM medicines");
    $totalMedicines = (int)$countResult->fetch_assoc()['total'];

    $medStmt = $conn->prepare("SELECT m.*, c.name as cat_name FROM medicines m LEFT JOIN categories c ON m.category_id=c.id ORDER BY m.id DESC LIMIT ? OFFSET ?");
    $medStmt->bind_param('ii', $perPage, $offset);
    $medStmt->execute();
    $medicines = $medStmt->get_result();
}
$totalPages = max(1, (int)ceil($totalMedicines / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$categories = $conn->query("SELECT * FROM categories ORDER BY name");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Medicines & Bottles - Stock Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f4f6f9; }
        .sidebar { min-height: 100vh; background: #2c3e50; }
        .sidebar a { color: white; display: block; padding: 12px 20px; text-decoration: none; }
        .sidebar a:hover { background: #1a252f; }
        .content { padding: 20px; }
        .category-badge {
            display: inline-block;
            padding: 4px 12px;
            margin: 3px;
            background: #e9ecef;
            border-radius: 20px;
            font-size: 14px;
        }
        .category-badge .btn-close-cat {
            font-size: 12px;
            margin-left: 8px;
            cursor: pointer;
            color: #dc3545;
            text-decoration: none;
        }
        .category-badge .btn-close-cat:hover {
            color: #a71d2a;
        }
        .stock-low { color: #dc3545; font-weight: bold; }
        .stock-medium { color: #ffc107; font-weight: bold; }
        .stock-high { color: #198754; font-weight: bold; }
        .add-stock-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .add-stock-btn:hover {
            background: #218838;
            transform: scale(1.05);
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        .confirm-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .confirm-modal-overlay.show {
            display: flex;
        }
        .confirm-modal-box {
            background: white;
            border-radius: 12px;
            padding: 30px 40px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
            text-align: center;
        }
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px) scale(0.9);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }
        .confirm-modal-box .icon {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 15px;
        }
        .confirm-modal-box h4 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .confirm-modal-box p {
            color: #6c757d;
            margin-bottom: 25px;
            font-size: 15px;
        }
        .confirm-modal-box .btn-group {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .confirm-modal-box .btn {
            padding: 8px 30px;
            font-weight: 500;
            border-radius: 8px;
        }
        .medicine-detail-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 5px solid #2c3e50;
        }
        .medicine-detail-card .detail-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .medicine-detail-card .detail-value {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .medicine-detail-card .detail-value .stock-badge {
            font-size: 18px;
            padding: 4px 16px;
        }
        .medicine-detail-card .detail-value .price-tag {
            font-weight: 700;
            color: #198754;
        }
        .nav-buttons .btn {
            padding: 8px 24px;
            font-weight: 500;
            border-radius: 8px;
        }
        .nav-buttons .btn i {
            margin: 0 5px;
        }
        .medicine-detail-card .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .medicine-detail-card .header-actions .btn-group .btn {
            padding: 6px 16px;
        }
        .medicine-count-badge {
            background: #e9ecef;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 14px;
            color: #495057;
        }
        
        /* Bottles Section Styles */
        .bottles-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 5px solid #0d6efd;
        }
        .bottles-section .card-header-custom {
            background: linear-gradient(90deg, #0d6efd, #0a58ca);
            color: white;
            padding: 12px 20px;
            border-radius: 8px 8px 0 0;
            margin: -20px -20px 20px -20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .bottle-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            transition: all 0.2s;
            background: #fafafa;
        }
        .bottle-card:hover {
            border-color: #0d6efd;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.15);
        }
        .bottle-card .bottle-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 15px;
        }
        .bottle-card .bottle-price {
            font-weight: 700;
            color: #0d6efd;
            font-size: 16px;
        }
        .bottle-card .bottle-size {
            font-size: 12px;
            color: #6c757d;
            background: #e9ecef;
            padding: 2px 10px;
            border-radius: 12px;
            display: inline-block;
        }
        .bottle-card .bottle-actions {
            display: flex;
            gap: 5px;
            justify-content: flex-end;
        }
        .bottle-card .bottle-actions .btn {
            padding: 2px 10px;
            font-size: 12px;
        }
        .btn-bottle-add {
            background: #0d6efd;
            border-color: #0d6efd;
            color: white;
        }
        .btn-bottle-add:hover {
            background: #0a58ca;
            border-color: #0a58ca;
            color: white;
        }
        .btn-bottle-edit {
            background: #198754;
            border-color: #198754;
            color: white;
        }
        .btn-bottle-edit:hover {
            background: #157347;
            border-color: #157347;
            color: white;
        }
        .btn-bottle-delete {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        .btn-bottle-delete:hover {
            background: #c82333;
            border-color: #c82333;
            color: white;
        }
        .bottle-icon {
            font-size: 24px;
            color: #0d6efd;
            margin-right: 10px;
        }
        .empty-bottles {
            text-align: center;
            padding: 30px 0;
            color: #999;
        }
        .empty-bottles i {
            font-size: 48px;
            margin-bottom: 10px;
            color: #0d6efd;
        }
        .bottle-stock {
            font-size: 12px;
            font-weight: 600;
        }
        .bottle-stock .badge {
            font-size: 11px;
            padding: 3px 10px;
        }
        .add-bottle-stock-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .add-bottle-stock-btn:hover {
            background: #218838;
            transform: scale(1.05);
        }
        @media (max-width: 768px) {
            .medicine-detail-card .header-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .medicine-detail-card .header-actions .btn-group {
                justify-content: center;
            }
            .nav-buttons {
                display: flex;
                justify-content: center;
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
<?php if(isset($_SESSION['success'])): ?>
<div class="toast-container">
    <div class="toast show align-items-center text-white bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
<?php unset($_SESSION['success']); endif; ?>

<?php if(isset($_SESSION['error'])): ?>
<div class="toast-container">
    <div class="toast show align-items-center text-white bg-danger border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
<?php unset($_SESSION['error']); endif; ?>

<!-- Custom Confirmation Modal -->
<div class="confirm-modal-overlay" id="confirmModal">
    <div class="confirm-modal-box">
        <div class="icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h4>Are You Sure?</h4>
        <p id="confirmModalMessage">Are you sure you want to delete this category?</p>
        <div class="btn-group">
            <button class="btn btn-secondary" onclick="closeConfirmModal()">
                <i class="fas fa-times me-1"></i> Cancel
            </button>
            <button class="btn btn-danger" id="confirmDeleteBtn">
                <i class="fas fa-trash me-1"></i> Delete
            </button>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <?php include 'admin_sidebar.php'; ?>
        
        <div class="col-md-10 content">
            <h2 class="mt-3"><i class="fas fa-pills me-2"></i>Medicine & Bottles Management (KES)</h2>
            
            <div class="mb-3">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#medModal" onclick="resetForm()">
                    <i class="fas fa-plus me-1"></i> Add Medicine
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#categoryModal">
                    <i class="fas fa-tag me-1"></i> Add Category
                </button>
                <button class="btn btn-bottle-add" data-bs-toggle="modal" data-bs-target="#bottleModal" onclick="resetBottleForm()">
                    <i class="fas fa-flask me-1"></i> Add Bottle
                </button>
            </div>

            <!-- Categories Display -->
            <div class="mb-4 p-3 bg-white rounded shadow-sm">
                <h6 class="fw-bold mb-2"><i class="fas fa-tags me-2"></i>Categories</h6>
                <div>
                    <?php 
                    $cats = $conn->query("SELECT * FROM categories ORDER BY name");
                    while($c = $cats->fetch_assoc()): 
                    ?>
                        <span class="category-badge">
                            <?= htmlspecialchars($c['name']) ?>
                            <a href="#" class="btn-close-cat" onclick="showConfirmModal(<?= $c['id'] ?>); return false;">&times;</a>
                        </span>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Medicine Detail View with Navigation -->
            <?php if($current_medicine): ?>
            <div class="medicine-detail-card">
                <div class="header-actions">
                    <div>
                        <h5 class="mb-1">
                            <i class="fas fa-capsules me-2 text-primary"></i>
                            <?= htmlspecialchars($current_medicine['name']) ?>
                        </h5>
                        <small class="text-muted">
                            <i class="fas fa-hashtag me-1"></i> ID: <?= $current_medicine['id'] ?>
                            <span class="mx-2">|</span>
                            <span class="medicine-count-badge">
                                <i class="fas fa-list me-1"></i> <?= ($current_index + 1) ?> of <?= $total_medicines_count ?>
                            </span>
                        </small>
                    </div>
                    <div class="btn-group" role="group">
                        <button class="btn btn-success add-stock-btn" onclick="openAddStockModal(<?= $current_medicine['id'] ?>, '<?= htmlspecialchars($current_medicine['name']) ?>')">
                            <i class="fas fa-plus-circle"></i> Add Stock
                        </button>
                        <button class="btn btn-info" onclick='edit(<?= json_encode($current_medicine) ?>)'>
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <a href="?delete=<?= $current_medicine['id'] ?>" class="btn btn-danger" onclick="return confirm('Delete this medicine?')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="detail-label"><i class="fas fa-tag me-1"></i>Category</div>
                        <div class="detail-value"><?= htmlspecialchars($current_medicine['cat_name'] ?? 'Uncategorized') ?></div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="detail-label"><i class="fas fa-cube me-1"></i>Unit Type</div>
                        <div class="detail-value"><?= htmlspecialchars($current_medicine['unit_type'] ?: 'N/A') ?></div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="detail-label"><i class="fas fa-boxes me-1"></i>Stock Quantity</div>
                        <div class="detail-value">
                            <span class="badge stock-badge bg-<?= $current_medicine['stock_quantity'] <= 10 ? 'danger' : ($current_medicine['stock_quantity'] <= 50 ? 'warning' : 'success') ?>">
                                <?= $current_medicine['stock_quantity'] ?>
                            </span>
                            <?php if($current_medicine['stock_quantity'] <= 10): ?>
                                <span class="ms-2 text-danger"><i class="fas fa-exclamation-circle"></i> Low Stock</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="detail-label"><i class="fas fa-money-bill-wave me-1"></i>Selling Price</div>
                        <div class="detail-value price-tag">KES <?= number_format($current_medicine['selling_price'], 2) ?></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="detail-label"><i class="fas fa-coins me-1"></i>Cost Price</div>
                        <div class="detail-value">KES <?= number_format($current_medicine['cost_price'], 2) ?></div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="detail-label"><i class="fas fa-calendar-alt me-1"></i>Expiry Date</div>
                        <div class="detail-value"><?= $current_medicine['expiry_date'] ?: 'N/A' ?></div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="detail-label"><i class="fas fa-barcode me-1"></i>Batch No</div>
                        <div class="detail-value"><?= htmlspecialchars($current_medicine['batch_no'] ?: 'N/A') ?></div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="detail-label"><i class="fas fa-truck me-1"></i>Supplier</div>
                        <div class="detail-value"><?= htmlspecialchars($current_medicine['supplier'] ?: 'N/A') ?></div>
                    </div>
                </div>
                <?php if($current_medicine['barcode']): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="detail-label"><i class="fas fa-qrcode me-1"></i>Barcode</div>
                        <div class="detail-value"><?= htmlspecialchars($current_medicine['barcode']) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Navigation Buttons -->
                <hr>
                <div class="nav-buttons d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <?php if($prev_id > 0): ?>
                            <a href="?view_id=<?= $prev_id ?>&page=<?= $page ?>&per_page=<?= $perPage ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-outline-primary">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary" disabled>
                                <i class="fas fa-chevron-left"></i> Previous
                            </button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="medicines.php" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-1"></i> Back to List
                        </a>
                    </div>
                    <div>
                        <?php if($next_id > 0): ?>
                            <a href="?view_id=<?= $next_id ?>&page=<?= $page ?>&per_page=<?= $perPage ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-outline-primary">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary" disabled>
                                Next <i class="fas fa-chevron-right"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php elseif($total_medicines_count == 0): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No medicines found. Click <strong>"Add Medicine"</strong> to get started.
            </div>
            <?php endif; ?>

            <!-- Bottles Section -->
            <div class="bottles-section">
                <div class="card-header-custom">
                    <h5 class="mb-0"><i class="fas fa-flask me-2"></i>Bottles & Containers</h5>
                    <span class="badge bg-light text-dark"><?= $bottles ? $bottles->num_rows : 0 ?> bottles</span>
                </div>
                
                <div class="row">
                    <?php if($bottles && $bottles->num_rows > 0): ?>
                        <?php while($bottle = $bottles->fetch_assoc()): ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="bottle-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="bottle-name">
                                            <i class="fas fa-flask bottle-icon"></i>
                                            <?= htmlspecialchars($bottle['name']) ?>
                                        </div>
                                        <div class="bottle-price">KES <?= number_format($bottle['price'], 2) ?></div>
                                        <?php if($bottle['size']): ?>
                                            <span class="bottle-size"><i class="fas fa-ruler me-1"></i><?= htmlspecialchars($bottle['size']) ?></span>
                                        <?php endif; ?>
                                        <?php if($bottle['type']): ?>
                                            <span class="bottle-size"><i class="fas fa-tag me-1"></i><?= htmlspecialchars($bottle['type']) ?></span>
                                        <?php endif; ?>
                                        <div class="bottle-stock mt-1">
                                            <span class="badge bg-<?= $bottle['stock_quantity'] <= 10 ? 'danger' : ($bottle['stock_quantity'] <= 50 ? 'warning' : 'success') ?>">
                                                <i class="fas fa-boxes me-1"></i> Stock: <?= $bottle['stock_quantity'] ?>
                                            </span>
                                            <button class="add-bottle-stock-btn ms-1" onclick="openAddBottleStockModal(<?= $bottle['id'] ?>, '<?= htmlspecialchars($bottle['name']) ?>')">
                                                <i class="fas fa-plus-circle"></i> Add
                                            </button>
                                        </div>
                                        <?php if($bottle['description']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($bottle['description']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bottle-actions">
                                        <button class="btn btn-bottle-edit btn-sm" onclick="editBottle(<?= htmlspecialchars(json_encode($bottle)) ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete_bottle=<?= $bottle['id'] ?>" class="btn btn-bottle-delete btn-sm" onclick="return confirm('Delete this bottle?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="empty-bottles">
                                <i class="fas fa-flask"></i>
                                <p>No bottles added yet</p>
                                <small class="text-muted">Click the "Add Bottle" button to add containers</small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Search and paging -->
            <form method="GET" class="row g-2 mb-3 mt-3">
                <div class="col-md-7">
                    <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search medicine name, batch or supplier...">
                </div>
                <div class="col-md-2">
                    <select name="per_page" class="form-select" onchange="this.form.submit()">
                        <?php foreach([25,50,100] as $size): ?>
                            <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?> per page</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search me-1"></i>Search</button>
                    <?php if($search !== ''): ?>
                        <a class="btn btn-outline-secondary" href="medicines.php">Clear</a>
                    <?php endif; ?>
                </div>
                <?php if($current_id > 0): ?>
                    <input type="hidden" name="view_id" value="<?= $current_id ?>">
                <?php endif; ?>
            </form>
            <div class="mb-2 text-muted small">
                Showing <?= $totalMedicines ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalMedicines) ?> of <?= number_format($totalMedicines) ?> medicines.
            </div>

            <!-- Medicines Table -->
            <div class="table-responsive bg-white rounded shadow-sm p-2">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Stock</th>
                            <th>Selling Price (KES)</th>
                            <th>Expiry</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $medicines->fetch_assoc()): 
                            $stock = $row['stock_quantity'];
                            $stock_class = '';
                            if($stock <= 10) $stock_class = 'stock-low';
                            elseif($stock <= 50) $stock_class = 'stock-medium';
                            else $stock_class = 'stock-high';
                        ?>
                        <tr>
                            <td>
                                <a href="?view_id=<?= $row['id'] ?>&page=<?= $page ?>&per_page=<?= $perPage ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="text-decoration-none">
                                    <?= $row['id'] ?>
                                </a>
                            </td>
                            <td>
                                <a href="?view_id=<?= $row['id'] ?>&page=<?= $page ?>&per_page=<?= $perPage ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="text-decoration-none text-dark">
                                    <strong><?= htmlspecialchars($row['name']) ?></strong>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($row['cat_name'] ?? 'Uncategorized') ?></td>
                            <td><?= htmlspecialchars($row['unit_type']) ?></td>
                            <td class="<?= $stock_class ?>">
                                <span class="badge bg-<?= $stock <= 10 ? 'danger' : ($stock <= 50 ? 'warning' : 'success') ?>">
                                    <?= $stock ?>
                                </span>
                                <?php if($stock <= 10): ?>
                                    <span class="ms-1 text-danger">(Low Stock)</span>
                                <?php endif; ?>
                            </td>
                            <td><?= getCurrency() . number_format($row['selling_price'], 2) ?></td>
                            <td><?= $row['expiry_date'] ?></td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-success add-stock-btn" onclick="openAddStockModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name']) ?>')">
                                        <i class="fas fa-plus-circle"></i> Add Stock
                                    </button>
                                    <a href="?view_id=<?= $row['id'] ?>&page=<?= $page ?>&per_page=<?= $perPage ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="?delete=<?= $row['id'] ?>" class="btn btn-danger" onclick="return confirm('Delete this medicine?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if($medicines->num_rows == 0): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="fas fa-box-open fa-2x d-block mb-2"></i>
                                    No medicines found. Click "Add Medicine" to get started.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if($totalPages > 1): ?>
                <nav aria-label="Medicine pages" class="mt-3">
                    <ul class="pagination justify-content-center flex-wrap">
                        <?php
                        $windowStart = max(1, $page - 2);
                        $windowEnd = min($totalPages, $page + 2);
                        $queryBase = $search !== '' ? '&q=' . urlencode($search) : '';
                        ?>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= max(1,$page-1) ?>&per_page=<?= $perPage ?><?= $queryBase ?>">Previous</a>
                        </li>
                        <?php for($p=$windowStart; $p <= $windowEnd; $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $p ?>&per_page=<?= $perPage ?><?= $queryBase ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= min($totalPages,$page+1) ?>&per_page=<?= $perPage ?><?= $queryBase ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Stock Modal for Medicines -->
<div class="modal fade" id="addStockModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="medicine_id" id="stock_medicine_id">
                <div class="mb-3">
                    <label class="form-label"><strong>Medicine</strong></label>
                    <p class="form-control-static" id="stock_medicine_name"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Current Stock</strong></label>
                    <p class="form-control-static" id="stock_current_stock"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Additional Stock to Add <span class="text-danger">*</span></label>
                    <input type="number" name="additional_stock" class="form-control" 
                           placeholder="Enter quantity to add" required min="1" 
                           oninput="calculateNewStock(this.value)">
                    <small class="text-muted">Enter the quantity you want to add to the current stock.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>New Stock Will Be:</strong></label>
                    <h5 class="text-success" id="stock_new_total">-</h5>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason for Addition</label>
                    <select name="reason" class="form-select">
                        <option value="Purchase">Purchase / Restock</option>
                        <option value="Return">Return from Customer</option>
                        <option value="Inventory Adjustment">Inventory Adjustment</option>
                        <option value="Transfer">Transfer from Another Location</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_stock" class="btn btn-success">
                    <i class="fas fa-check me-1"></i> Add Stock
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Stock Modal for Bottles -->
<div class="modal fade" id="addBottleStockModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header" style="background: #0d6efd; color: white;">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add Bottle Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="bottle_id" id="bottle_stock_id">
                <div class="mb-3">
                    <label class="form-label"><strong>Bottle</strong></label>
                    <p class="form-control-static" id="bottle_stock_name"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Current Stock</strong></label>
                    <p class="form-control-static" id="bottle_current_stock"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Additional Stock to Add <span class="text-danger">*</span></label>
                    <input type="number" name="additional_bottle_stock" class="form-control" 
                           placeholder="Enter quantity to add" required min="1" 
                           oninput="calculateBottleNewStock(this.value)">
                    <small class="text-muted">Enter the quantity you want to add to the current stock.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>New Stock Will Be:</strong></label>
                    <h5 class="text-success" id="bottle_stock_new_total">-</h5>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_bottle_stock" class="btn btn-primary">
                    <i class="fas fa-check me-1"></i> Add Stock
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add / Edit Medicine Modal -->
<div class="modal fade" id="medModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-pills me-2"></i>Medicine Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="id">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Medicine Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Category</label>
                        <select name="category_id" id="category_id" class="form-select">
                            <option value="">Select Category</option>
                            <?php 
                            $cats = $conn->query("SELECT * FROM categories ORDER BY name");
                            while($c = $cats->fetch_assoc()): 
                            ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Unit Type</label>
                        <input type="text" name="unit_type" id="unit_type" class="form-control" placeholder="Tablet/Bottle/Capsule">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" name="stock_quantity" id="stock" class="form-control" min="0">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Cost Price (KES)</label>
                        <input type="number" step="0.01" name="cost_price" id="cost" class="form-control" min="0">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Selling Price (KES) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="selling_price" id="selling" class="form-control" required min="0">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" id="expiry" class="form-control">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Batch No</label>
                        <input type="text" name="batch_no" id="batch" class="form-control">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Supplier</label>
                        <input type="text" name="supplier" id="supplier" class="form-control">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Barcode</label>
                        <input type="text" name="barcode" id="barcode" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="save" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Save Medicine
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-tag me-2"></i>Add New Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="category_name" class="form-control" placeholder="Enter category name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Suggested Categories</label>
                    <div>
                        <span class="category-badge">Antimalarials</span>
                        <span class="category-badge">Antiparasitic</span>
                        <span class="category-badge">Antifungal</span>
                        <span class="category-badge">Dermatological</span>
                        <span class="category-badge">Antibiotics</span>
                        <span class="category-badge">Painkillers</span>
                    </div>
                    <small class="text-muted">Click the + Add Category button to add any of these or create your own.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="save_category" class="btn btn-success">
                    <i class="fas fa-check me-1"></i> Add Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add / Edit Bottle Modal -->
<div class="modal fade" id="bottleModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header" style="background: linear-gradient(90deg, #0d6efd, #0a58ca); color: white;">
                <h5 class="modal-title"><i class="fas fa-flask me-2"></i>Add / Edit Bottle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="bottle_id" id="bottle_id">
                <div class="mb-3">
                    <label class="form-label">Bottle / Container Name <span class="text-danger">*</span></label>
                    <input type="text" name="bottle_name" id="bottle_name" class="form-control" placeholder="e.g., Glass Bottle, Plastic Container, Jar" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Price (KES) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="bottle_price" id="bottle_price" class="form-control" placeholder="0.00" required min="0">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Size</label>
                        <select name="bottle_size" id="bottle_size" class="form-select">
                            <option value="">Select Size</option>
                            <option value="Small">Small</option>
                            <option value="Medium">Medium</option>
                            <option value="Large">Large</option>
                            <option value="Extra Large">Extra Large</option>
                            <option value="250ml">250ml</option>
                            <option value="500ml">500ml</option>
                            <option value="750ml">750ml</option>
                            <option value="1L">1L</option>
                            <option value="1.5L">1.5L</option>
                            <option value="2L">2L</option>
                            <option value="5L">5L</option>
                            <option value="10L">10L</option>
                            <option value="20L">20L</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Type</label>
                        <select name="bottle_type" id="bottle_type" class="form-select">
                            <option value="">Select Type</option>
                            <option value="Glass">Glass</option>
                            <option value="Plastic">Plastic</option>
                            <option value="Metal">Metal</option>
                            <option value="Ceramic">Ceramic</option>
                            <option value="Stainless Steel">Stainless Steel</option>
                            <option value="Aluminum">Aluminum</option>
                            <option value="PET">PET</option>
                            <option value="HDPE">HDPE</option>
                            <option value="PVC">PVC</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Stock Quantity</label>
                    <input type="number" name="bottle_stock" id="bottle_stock" class="form-control" min="0" value="0">
                    <small class="text-muted">Current stock quantity available</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="bottle_description" id="bottle_description" class="form-control" rows="2" placeholder="Optional description (color, material, etc.)"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Quick Add Suggestions</label>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="category-badge" style="cursor:pointer; background: #0d6efd20;" onclick="fillBottle('Glass Bottle', '150.00', 'Medium', 'Glass')">Glass Bottle</span>
                        <span class="category-badge" style="cursor:pointer; background: #0d6efd20;" onclick="fillBottle('Plastic Container', '80.00', 'Large', 'Plastic')">Plastic Container</span>
                        <span class="category-badge" style="cursor:pointer; background: #0d6efd20;" onclick="fillBottle('Medicine Bottle', '120.00', 'Small', 'Glass')">Medicine Bottle</span>
                        <span class="category-badge" style="cursor:pointer; background: #0d6efd20;" onclick="fillBottle('Syrup Bottle', '180.00', 'Large', 'Plastic')">Syrup Bottle</span>
                        <span class="category-badge" style="cursor:pointer; background: #0d6efd20;" onclick="fillBottle('Tincture Bottle', '200.00', 'Small', 'Glass')">Tincture Bottle</span>
                        <span class="category-badge" style="cursor:pointer; background: #0d6efd20;" onclick="fillBottle('Spray Bottle', '250.00', 'Medium', 'Plastic')">Spray Bottle</span>
                        <span class="category-badge" style="cursor:pointer; background: #0d6efd20;" onclick="fillBottle('Dropper Bottle', '300.00', 'Small', 'Glass')">Dropper Bottle</span>
                    </div>
                    <small class="text-muted">Click on any suggestion to auto-fill the form.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="save_bottle" class="btn btn-bottle-add">
                    <i class="fas fa-save me-1"></i> Save Bottle
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Stock data cache
let stockData = {};
let bottleStockData = {};
let deleteCategoryId = null;

function showConfirmModal(categoryId) {
    deleteCategoryId = categoryId;
    document.getElementById('confirmModal').classList.add('show');
    document.getElementById('confirmModalMessage').textContent = 'Are you sure you want to delete this category? Any medicines with this category will not be affected.';
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('show');
    deleteCategoryId = null;
}

function confirmDelete() {
    if(deleteCategoryId) {
        window.location.href = `?delete_cat=${deleteCategoryId}`;
    }
}

document.getElementById('confirmModal').addEventListener('click', function(e) {
    if(e.target === this) {
        closeConfirmModal();
    }
});

document.getElementById('confirmDeleteBtn').addEventListener('click', confirmDelete);

document.addEventListener('keydown', function(e) {
    if(e.key === 'Escape' && document.getElementById('confirmModal').classList.contains('show')) {
        closeConfirmModal();
    }
});

// Medicine Stock Functions
function openAddStockModal(id, name) {
    document.getElementById('stock_medicine_id').value = id;
    document.getElementById('stock_medicine_name').textContent = name;
    
    fetch(`get_stock.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('stock_current_stock').textContent = data.stock || 0;
            stockData[id] = data.stock || 0;
            document.querySelector('input[name="additional_stock"]').value = '';
            document.getElementById('stock_new_total').textContent = '-';
        })
        .catch(() => {
            const row = document.querySelector(`button[onclick*="openAddStockModal(${id}"]`).closest('tr');
            if(row) {
                const stockCell = row.querySelector('td:nth-child(5)');
                if(stockCell) {
                    const stockText = stockCell.textContent.trim();
                    const stockMatch = stockText.match(/\d+/);
                    if(stockMatch) {
                        const currentStock = parseInt(stockMatch[0]);
                        document.getElementById('stock_current_stock').textContent = currentStock;
                        stockData[id] = currentStock;
                    }
                }
            }
        });
    
    new bootstrap.Modal(document.getElementById('addStockModal')).show();
}

function calculateNewStock(value) {
    const id = document.getElementById('stock_medicine_id').value;
    const currentStock = stockData[id] || 0;
    const additional = parseInt(value) || 0;
    const newTotal = currentStock + additional;
    
    if(additional > 0) {
        document.getElementById('stock_new_total').textContent = newTotal;
        document.getElementById('stock_new_total').className = 'text-success';
    } else {
        document.getElementById('stock_new_total').textContent = '-';
        document.getElementById('stock_new_total').className = 'text-muted';
    }
}

// Bottle Stock Functions
function openAddBottleStockModal(id, name) {
    document.getElementById('bottle_stock_id').value = id;
    document.getElementById('bottle_stock_name').textContent = name;
    
    // Get current stock from the card
    const card = document.querySelector(`button[onclick*="openAddBottleStockModal(${id}"]`).closest('.bottle-card');
    if(card) {
        const stockBadge = card.querySelector('.bottle-stock .badge');
        if(stockBadge) {
            const stockText = stockBadge.textContent.trim();
            const stockMatch = stockText.match(/\d+/);
            if(stockMatch) {
                const currentStock = parseInt(stockMatch[0]);
                document.getElementById('bottle_current_stock').textContent = currentStock;
                bottleStockData[id] = currentStock;
            }
        }
    }
    
    document.querySelector('input[name="additional_bottle_stock"]').value = '';
    document.getElementById('bottle_stock_new_total').textContent = '-';
    
    new bootstrap.Modal(document.getElementById('addBottleStockModal')).show();
}

function calculateBottleNewStock(value) {
    const id = document.getElementById('bottle_stock_id').value;
    const currentStock = bottleStockData[id] || 0;
    const additional = parseInt(value) || 0;
    const newTotal = currentStock + additional;
    
    if(additional > 0) {
        document.getElementById('bottle_stock_new_total').textContent = newTotal;
        document.getElementById('bottle_stock_new_total').className = 'text-success';
    } else {
        document.getElementById('bottle_stock_new_total').textContent = '-';
        document.getElementById('bottle_stock_new_total').className = 'text-muted';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const toast = document.querySelector('.toast');
        if(toast) {
            const bsToast = bootstrap.Toast.getInstance(toast);
            if(bsToast) bsToast.hide();
            setTimeout(() => toast.remove(), 500);
        }
    }, 5000);
});

function edit(data) {
    document.getElementById('id').value = data.id;
    document.getElementById('name').value = data.name;
    document.getElementById('category_id').value = data.category_id;
    document.getElementById('unit_type').value = data.unit_type;
    document.getElementById('stock').value = data.stock_quantity;
    document.getElementById('cost').value = data.cost_price;
    document.getElementById('selling').value = data.selling_price;
    document.getElementById('expiry').value = data.expiry_date;
    document.getElementById('batch').value = data.batch_no;
    document.getElementById('supplier').value = data.supplier;
    document.getElementById('barcode').value = data.barcode;
    new bootstrap.Modal(document.getElementById('medModal')).show();
}

function resetForm() {
    document.getElementById('id').value = '';
    document.getElementById('name').value = '';
    document.getElementById('category_id').value = '';
    document.getElementById('unit_type').value = '';
    document.getElementById('stock').value = '';
    document.getElementById('cost').value = '';
    document.getElementById('selling').value = '';
    document.getElementById('expiry').value = '';
    document.getElementById('batch').value = '';
    document.getElementById('supplier').value = '';
    document.getElementById('barcode').value = '';
}

// Bottle functions
function editBottle(data) {
    document.getElementById('bottle_id').value = data.id;
    document.getElementById('bottle_name').value = data.name;
    document.getElementById('bottle_price').value = data.price;
    document.getElementById('bottle_size').value = data.size || '';
    document.getElementById('bottle_type').value = data.type || '';
    document.getElementById('bottle_stock').value = data.stock_quantity || 0;
    document.getElementById('bottle_description').value = data.description || '';
    new bootstrap.Modal(document.getElementById('bottleModal')).show();
}

function resetBottleForm() {
    document.getElementById('bottle_id').value = '';
    document.getElementById('bottle_name').value = '';
    document.getElementById('bottle_price').value = '';
    document.getElementById('bottle_size').value = '';
    document.getElementById('bottle_type').value = '';
    document.getElementById('bottle_stock').value = '0';
    document.getElementById('bottle_description').value = '';
}

function fillBottle(name, price, size, type) {
    document.getElementById('bottle_name').value = name;
    document.getElementById('bottle_price').value = price;
    document.getElementById('bottle_size').value = size || '';
    document.getElementById('bottle_type').value = type || '';
}
</script>
</body>
</html>