<?php 
require_once 'config/db.php'; 
if(!isAdmin()) redirect('dashboard.php');

// Handle user deletion with cascade delete option
if(isset($_GET['delete'])){ 
    $delete_id = (int)$_GET['delete'];
    
    // Check if this user has any sales records
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM sales_master WHERE cashier_id = ?");
    $check_stmt->bind_param("i", $delete_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    $sales_count = $row['count'];
    
    if($sales_count > 0) {
        // Show confirmation modal for cascade delete
        $_SESSION['cascade_delete'] = $delete_id;
        $_SESSION['cascade_sales_count'] = $sales_count;
        redirect('cashiers.php#confirmDelete');
    } else {
        // Safe to delete (no sales)
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if($stmt->execute()) {
            $_SESSION['success'] = "User deleted successfully.";
        } else {
            $_SESSION['error'] = "Error deleting user: " . $conn->error;
        }
        redirect('cashiers.php');
    }
}

// Handle cascade delete confirmation
if(isset($_POST['confirm_delete']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    $delete_type = $_POST['delete_type'] ?? 'cascade';
    
    $conn->begin_transaction();
    try {
        if($delete_type == 'cascade') {
            // Get all sale IDs for this cashier
            $sales_query = $conn->prepare("SELECT id FROM sales_master WHERE cashier_id = ?");
            $sales_query->bind_param("i", $user_id);
            $sales_query->execute();
            $sales_result = $sales_query->get_result();
            
            $sale_ids = [];
            while($sale = $sales_result->fetch_assoc()) {
                $sale_ids[] = $sale['id'];
            }
            
            // Delete sale items for each sale
            if(!empty($sale_ids)) {
                $placeholders = implode(',', array_fill(0, count($sale_ids), '?'));
                $types = str_repeat('i', count($sale_ids));
                
                // Delete from sale_items
                $stmt = $conn->prepare("DELETE FROM sale_items WHERE sale_id IN ($placeholders)");
                $stmt->bind_param($types, ...$sale_ids);
                $stmt->execute();
                
                // Delete from sales_master
                $stmt = $conn->prepare("DELETE FROM sales_master WHERE cashier_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
            }
            
            // Finally delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['success'] = "User and all associated records deleted successfully. ($sales_result->num_rows sales removed)";
        } else {
            // Reassign sales (soft delete approach)
            // This would reassign sales to another user instead of deleting
            $new_cashier_id = (int)$_POST['new_cashier_id'] ?? 0;
            if($new_cashier_id > 0 && $new_cashier_id != $user_id) {
                $stmt = $conn->prepare("UPDATE sales_master SET cashier_id = ? WHERE cashier_id = ?");
                $stmt->bind_param("ii", $new_cashier_id, $user_id);
                $stmt->execute();
                
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                $conn->commit();
                $_SESSION['success'] = "User deleted. Sales reassigned to selected cashier.";
            } else {
                throw new Exception("Please select a valid cashier to reassign sales.");
            }
        }
    } catch(Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error deleting user: " . $e->getMessage();
    }
    redirect('cashiers.php');
}

// Handle user save (add/edit)
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['save'])){
    $id = (int)($_POST['id'] ?? 0); 
    $name = trim($_POST['name']); 
    $username = trim($_POST['username']); 
    $pass = trim($_POST['password']); 
    $role = $_POST['role'] ?? 'cashier';
    
    // Validate inputs
    if(empty($name) || empty($username) || empty($pass)) {
        $_SESSION['error'] = "All fields are required.";
        redirect('cashiers.php');
        exit;
    }
    
    // Check for duplicate username
    if($id > 0) {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->bind_param("si", $username, $id);
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $username);
    }
    $check_stmt->execute();
    if($check_stmt->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Username already exists. Please choose a different username.";
        redirect('cashiers.php');
        exit;
    }
    
    // Use prepared statements for security
    if($id > 0) {
        // Update existing user
        $stmt = $conn->prepare("UPDATE users SET name = ?, username = ?, password = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $username, $pass, $role, $id);
    } else {
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $username, $pass, $role);
    }
    
    if($stmt->execute()) {
        $_SESSION['success'] = $id > 0 ? "User updated successfully." : "User added successfully.";
    } else {
        $_SESSION['error'] = "Error saving user: " . $conn->error;
    }
    redirect('cashiers.php');
}

// Get all users
$users = $conn->query("SELECT * FROM users ORDER BY id DESC");

// Get all cashiers for reassignment dropdown
$cashiers = $conn->query("SELECT id, name FROM users WHERE role = 'cashier' ORDER BY name");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cashiers - Chemist POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f4f6f9; }
        .sidebar { min-height: 100vh; background: #2c3e50; }
        .sidebar a { color: white; display: block; padding: 12px 20px; text-decoration: none; }
        .sidebar a:hover { background: #1a252f; }
        .content { padding: 20px; }
        .alert-dismissible .btn-close {
            position: absolute;
            right: 10px;
            top: 10px;
        }
        .user-role-badge {
            font-size: 12px;
            padding: 4px 12px;
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        .has-sales {
            background-color: #fff3cd;
        }
        .has-sales td {
            border-bottom: 2px solid #ffc107;
        }
        .delete-danger {
            color: #dc3545;
            font-weight: bold;
        }
        .confirm-delete-modal .modal-header {
            border-bottom: 3px solid #dc3545;
        }
        .btn-cascade-delete {
            background-color: #dc3545;
            color: white;
        }
        .btn-cascade-delete:hover {
            background-color: #c82333;
            color: white;
        }
        .btn-reassign-delete {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-reassign-delete:hover {
            background-color: #e0a800;
            color: #212529;
        }
        .warning-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Include the reusable admin sidebar -->
        <?php include 'admin_sidebar.php'; ?>
        
        <!-- Main content area -->
        <div class="col-md-10 content" id="mainContent">
            <h2><i class="fas fa-users"></i> Cashier Management</h2>
            
            <!-- Alert Messages -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <!-- Stats -->
            <?php 
            $stats_query = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
            $stats = [];
            while($row = $stats_query->fetch_assoc()) {
                $stats[$row['role']] = $row['count'];
            }
            ?>
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="stats-card bg-primary text-white">
                        <h6><i class="fas fa-user-shield"></i> Admins</h6>
                        <h3><?= $stats['admin'] ?? 0 ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card bg-success text-white">
                        <h6><i class="fas fa-user-tie"></i> Cashiers</h6>
                        <h3><?= $stats['cashier'] ?? 0 ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card bg-info text-white">
                        <h6><i class="fas fa-users"></i> Total Users</h6>
                        <h3><?= array_sum($stats) ?></h3>
                    </div>
                </div>
            </div>
            
            <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#userModal">
                <i class="fas fa-user-plus"></i> Add Cashier/Admin
            </button>
            
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Role</th>
                            <th>Sales</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($u = $users->fetch_assoc()): 
                            // Check if user has sales
                            $sales_check = $conn->prepare("SELECT COUNT(*) as count FROM sales_master WHERE cashier_id = ?");
                            $sales_check->bind_param("i", $u['id']);
                            $sales_check->execute();
                            $sales_result = $sales_check->get_result();
                            $sales_count = $sales_result->fetch_assoc()['count'];
                            $has_sales = $sales_count > 0;
                            $row_class = $has_sales ? 'has-sales' : '';
                        ?>
                        <tr class="<?= $row_class ?>">
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['name']) ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td class="text-danger fw-bold"><?= htmlspecialchars($u['password']) ?></td>
                            <td>
                                <span class="badge <?= $u['role'] == 'admin' ? 'bg-danger' : 'bg-primary' ?> user-role-badge">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if($has_sales): ?>
                                    <span class="badge bg-warning text-dark" title="This user has sales records">
                                        <i class="fas fa-receipt"></i> <?= $sales_count ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check"></i> No sales
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick='edit(<?= json_encode($u) ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user? This action cannot be undone.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle"></i> 
                <strong>Note:</strong> Deleting a cashier will permanently remove all their sales records and associated items. 
                This action cannot be undone.
            </div>
        </div>
    </div>
</div>

<!-- Cascade Delete Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content confirm-delete-modal">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> Confirm Permanent Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="warning-box">
                    <h5 class="text-danger"><i class="fas fa-skull"></i> WARNING: This action cannot be undone!</h5>
                </div>
                
                <?php if(isset($_SESSION['cascade_delete']) && isset($_SESSION['cascade_sales_count'])): 
                    $user_id = $_SESSION['cascade_delete'];
                    $sales_count = $_SESSION['cascade_sales_count'];
                    
                    // Get user details
                    $user_query = $conn->prepare("SELECT name, role FROM users WHERE id = ?");
                    $user_query->bind_param("i", $user_id);
                    $user_query->execute();
                    $user_data = $user_query->get_result()->fetch_assoc();
                ?>
                <input type="hidden" name="user_id" value="<?= $user_id ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6><i class="fas fa-user"></i> User Details</h6>
                                <p><strong>Name:</strong> <?= htmlspecialchars($user_data['name']) ?></p>
                                <p><strong>Role:</strong> <?= ucfirst($user_data['role']) ?></p>
                                <p><strong>Sales Records:</strong> <span class="badge bg-danger"><?= $sales_count ?></span></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-danger">
                            <div class="card-body">
                                <h6 class="text-danger"><i class="fas fa-trash-alt"></i> Will be permanently deleted:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-times text-danger"></i> User account</li>
                                    <li><i class="fas fa-times text-danger"></i> <?= $sales_count ?> sale record(s)</li>
                                    <li><i class="fas fa-times text-danger"></i> All sale items</li>
                                    <li><i class="fas fa-times text-danger"></i> All associated data</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmCheck" required>
                        <label class="form-check-label" for="confirmCheck">
                            I understand that this action is <strong>permanent</strong> and cannot be reversed.
                        </label>
                    </div>
                </div>
                
                <div class="mt-3">
                    <label class="form-label">Select delete method:</label>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-danger">
                                <div class="card-body text-center">
                                    <button type="submit" name="confirm_delete" value="1" class="btn btn-cascade-delete btn-lg w-100" id="cascadeBtn" disabled>
                                        <i class="fas fa-bomb"></i> Cascade Delete (All Records)
                                    </button>
                                    <small class="text-muted">Deletes everything permanently</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-warning">
                                <div class="card-body text-center">
                                    <div class="mb-2">
                                        <select name="new_cashier_id" class="form-select" required>
                                            <option value="">-- Select Cashier --</option>
                                            <?php 
                                            $cashiers->data_seek(0);
                                            while($c = $cashiers->fetch_assoc()): 
                                                if($c['id'] != $user_id):
                                            ?>
                                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                            <?php endif; endwhile; ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="confirm_delete" value="1" class="btn btn-reassign-delete btn-lg w-100" id="reassignBtn" disabled>
                                        <i class="fas fa-exchange-alt"></i> Reassign & Delete
                                    </button>
                                    <small class="text-muted">Reassigns sales, then deletes user</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php 
                // Clear session variables after showing
                unset($_SESSION['cascade_delete']);
                unset($_SESSION['cascade_sales_count']);
                endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-cog"></i> User Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="id">
                <div class="mb-3">
                    <label>Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" id="username" class="form-control" required>
                    <small class="text-muted">Must be unique.</small>
                </div>
                <div class="mb-3">
                    <label>Password <span class="text-danger">*</span></label>
                    <input type="text" name="password" id="password" class="form-control" required>
                    <small class="text-muted">Plain text (visible for demonstration).</small>
                </div>
                <div class="mb-3">
                    <label>Role</label>
                    <select name="role" id="role" class="form-select">
                        <option value="cashier">Cashier</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="save" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save User
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function edit(d) {
    document.getElementById('id').value = d.id;
    document.getElementById('name').value = d.name;
    document.getElementById('username').value = d.username;
    document.getElementById('password').value = d.password;
    document.getElementById('role').value = d.role;
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            let bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Check if we need to show the confirm delete modal
    <?php if(isset($_SESSION['cascade_delete'])): ?>
    setTimeout(function() {
        let modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
        modal.show();
    }, 300);
    <?php endif; ?>
    
    // Enable/disable delete buttons based on checkbox
    const confirmCheck = document.getElementById('confirmCheck');
    if(confirmCheck) {
        confirmCheck.addEventListener('change', function() {
            document.getElementById('cascadeBtn').disabled = !this.checked;
            document.getElementById('reassignBtn').disabled = !this.checked;
        });
    }
});
</script>
</body>
</html>