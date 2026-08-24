<?php require_once 'config/db.php'; 
if(!isAdmin()) redirect('dashboard.php');

if(isset($_GET['delete'])){ 
    $conn->query("DELETE FROM services WHERE id=".$_GET['delete']); 
    redirect('services.php'); 
}

if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['save'])){
    $id = $_POST['id'] ?? 0; 
    $name = $_POST['name']; 
    $price = $_POST['price']; 
    $desc = $_POST['description']; 
    $status = $_POST['status'] ?? 1;
    if($id) {
        $stmt = $conn->prepare("UPDATE services SET name=?, price=?, description=?, status=? WHERE id=?");
        $stmt->bind_param("sdsii", $name, $price, $desc, $status, $id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO services (name, price, description, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sdsi", $name, $price, $desc, $status);
        $stmt->execute();
    }
    redirect('services.php');
}

$services = $conn->query("SELECT * FROM services ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Services Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f4f6f9; }
        .sidebar { min-height: 100vh; background: #2c3e50; }
        .sidebar a { color: white; display: block; padding: 12px 20px; text-decoration: none; }
        .sidebar a:hover { background: #1a252f; }
        .content { padding: 20px; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Include the reusable admin sidebar -->
        <?php include 'admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-10 content">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>💰 Services Management (KES)</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#serviceModal">
                    <i class="fas fa-plus"></i> Add Service
                </button>
            </div>
            
            <div class="card shadow">
                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Service Name</th>
                                <th>Price (KES)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($s = $services->fetch_assoc()): ?>
                            <tr>
                                <td><?= $s['id'] ?></td>
                                <td><?= htmlspecialchars($s['name']) ?></td>
                                <td><?= getCurrency() . number_format($s['price'], 2) ?></td>
                                <td>
                                    <?php if($s['status']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick='edit(<?= json_encode($s) ?>)'>
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="?delete=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this service?')">
                                        <i class="fas fa-trash"></i> Del
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if($services->num_rows == 0): ?>
                            <tr>
                                <td colspan="5" class="text-center">No services found. Click "Add Service" to create one.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="serviceModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-concierge-bell"></i> Service Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="id">
                <div class="mb-3">
                    <label class="form-label">Service Name</label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Price (KES)</label>
                    <input type="number" step="0.01" name="price" id="price" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="desc" class="form-control" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="save" class="btn btn-primary">Save Service</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function edit(data) {
    document.getElementById('id').value = data.id;
    document.getElementById('name').value = data.name;
    document.getElementById('price').value = data.price;
    document.getElementById('desc').value = data.description;
    document.getElementById('status').value = data.status;
    new bootstrap.Modal(document.getElementById('serviceModal')).show();
}
</script>
</body>
</html>