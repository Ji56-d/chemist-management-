<?php 
require_once 'config/db.php'; 
if(!isAdmin()) redirect('dashboard.php');

$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD']=='POST'){
    // Save text settings
    updateSetting('chemist_name', trim($_POST['chemist_name']), $conn);
    updateSetting('system_tagline', trim($_POST['system_tagline']), $conn);
    updateSetting('receipt_footer', trim($_POST['receipt_footer']), $conn);
    
    // Handle background color - properly escape for database
    $bg_color = trim($_POST['bg_color']);
    // If empty, set default gradient
    if(empty($bg_color)) {
        $bg_color = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
    }
    updateSetting('bg_color', $bg_color, $conn);
    
    // Handle background image upload
    if(isset($_FILES['bg_image']) && $_FILES['bg_image']['error'] == 0){
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['bg_image']['name'], PATHINFO_EXTENSION));
        if(in_array($ext, $allowed)){
            $upload_dir = 'uploads/backgrounds/';
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = 'bg_image_' . time() . '.' . $ext;
            if(move_uploaded_file($_FILES['bg_image']['tmp_name'], $upload_dir . $filename)){
                // Remove old image if exists
                $old_image = getSetting('bg_image', $conn);
                if($old_image && file_exists($old_image)){
                    unlink($old_image);
                }
                updateSetting('bg_image', $upload_dir . $filename, $conn);
                $message = "Background image uploaded successfully.";
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid image format. Use JPG, PNG, GIF, WEBP.";
        }
    }
    
    // Handle background video upload
    if(isset($_FILES['bg_video']) && $_FILES['bg_video']['error'] == 0){
        $allowed = ['mp4', 'webm', 'ogg'];
        $ext = strtolower(pathinfo($_FILES['bg_video']['name'], PATHINFO_EXTENSION));
        if(in_array($ext, $allowed)){
            $upload_dir = 'uploads/backgrounds/';
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = 'bg_video_' . time() . '.' . $ext;
            if(move_uploaded_file($_FILES['bg_video']['tmp_name'], $upload_dir . $filename)){
                // Remove old video if exists
                $old_video = getSetting('bg_video', $conn);
                if($old_video && file_exists($old_video)){
                    unlink($old_video);
                }
                updateSetting('bg_video', $upload_dir . $filename, $conn);
                $message = "Background video uploaded successfully.";
            } else {
                $error = "Failed to upload video.";
            }
        } else {
            $error = "Invalid video format. Use MP4, WEBM, OGG.";
        }
    }
    
    if(empty($message) && empty($error)){
        $message = "All settings saved successfully.";
    }
}

// Get current settings
$chemist = getSetting('chemist_name', $conn);
if(empty($chemist)) $chemist = 'City Chemist & Medical Store';
$tagline = getSetting('system_tagline', $conn);
if(empty($tagline)) $tagline = 'KES Currency | M-Pesa & Cash';
$footer = getSetting('receipt_footer', $conn);
$bg_color = getSetting('bg_color', $conn);
if(empty($bg_color)) $bg_color = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
$bg_image = getSetting('bg_image', $conn);
$bg_video = getSetting('bg_video', $conn);

$admin_name = $_SESSION['name'] ?? 'Super Admin';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Customize - Chemist POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        .sidebar a:hover {
            background: #1a252f;
        }
        .sidebar .active {
            background: #1a252f;
        }
        .content {
            padding: 20px;
        }
        .preview-box {
            border: 1px solid #ddd;
            padding: 10px;
            margin-top: 10px;
            border-radius: 8px;
            background: white;
        }
        .current-media {
            display: inline-block;
            margin-right: 10px;
        }
        .gradient-preview {
            width: 100%;
            height: 80px;
            border-radius: 8px;
            border: 2px solid #ddd;
            margin-top: 10px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .gradient-preview:hover {
            transform: scale(1.01);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .gradient-preset-btn {
            margin: 3px;
            padding: 10px 20px;
            border: 2px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
            min-width: 80px;
        }
        .gradient-preset-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
            border-color: #fff;
        }
        .gradient-preset-btn:active {
            transform: translateY(0px);
        }
        .preset-label {
            font-weight: 700;
            color: #333;
            margin-right: 15px;
            font-size: 14px;
        }
        .preset-container {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            margin-bottom: 5px;
        }
        .hidden-input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
            pointer-events: none;
        }
        .gradient-preview-label {
            position: absolute;
            bottom: 10px;
            right: 15px;
            background: rgba(0,0,0,0.5);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .selected-gradient {
            border-color: #fff !important;
            box-shadow: 0 0 0 3px #007bff, 0 6px 20px rgba(0,0,0,0.3);
            transform: translateY(-3px);
        }
        /* Custom Modal Styles */
        .custom-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        .custom-modal-overlay.active {
            display: flex;
        }
        .custom-modal-box {
            background: white;
            border-radius: 16px;
            padding: 30px 40px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideDown 0.3s ease;
        }
        .custom-modal-box .modal-icon {
            font-size: 50px;
            color: #dc3545;
            margin-bottom: 15px;
        }
        .custom-modal-box h4 {
            margin-bottom: 10px;
            color: #333;
            font-weight: 700;
        }
        .custom-modal-box p {
            color: #666;
            margin-bottom: 25px;
            font-size: 16px;
        }
        .custom-modal-box .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .custom-modal-box .btn {
            padding: 10px 30px;
            border-radius: 8px;
            font-weight: 600;
            min-width: 100px;
        }
        .custom-modal-box .btn-danger {
            background: #dc3545;
            border: none;
        }
        .custom-modal-box .btn-danger:hover {
            background: #c82333;
        }
        .custom-modal-box .btn-secondary {
            background: #6c757d;
            border: none;
        }
        .custom-modal-box .btn-secondary:hover {
            background: #5a6268;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .remove-btn {
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 p-0 sidebar">
            <div class="text-white text-center py-4 bg-dark">
                <h6><?= htmlspecialchars($chemist) ?></h6>
                <small>Admin: <?= htmlspecialchars($admin_name) ?></small>
            </div>
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="medicines.php"><i class="fas fa-tablets"></i> Medicines</a>
            <a href="services.php"><i class="fas fa-concierge-bell"></i> Services</a>
            <a href="cashiers.php"><i class="fas fa-users"></i> Cashiers</a>
            <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
            <a href="customize.php" class="active"><i class="fas fa-palette"></i> Customize</a>
            <a href="returns.php"><i class="fas fa-undo-alt"></i> Returns</a>
            <a href="backup.php"><i class="fas fa-download"></i> Backup PDF</a>
            <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        
        <!-- Main Content Area -->
        <div class="col-md-10 content p-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3><i class="fas fa-palette"></i> System Customization</h3>
                </div>
                <div class="card-body">
                    <?php if($message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <!-- Text Settings -->
                        <div class="card mb-4">
                            <div class="card-header bg-secondary text-white">Text & Branding</div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label"><strong>Chemist Name (Shop Name)</strong></label>
                                    <input type="text" name="chemist_name" class="form-control" value="<?= htmlspecialchars($chemist) ?>" required>
                                    <small class="text-muted">Appears in sidebar, receipts, and login page title.</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><strong>System Tagline (Login Page Subtitle)</strong></label>
                                    <input type="text" name="system_tagline" class="form-control" value="<?= htmlspecialchars($tagline) ?>">
                                    <small class="text-muted">Example: "KES Currency | M-Pesa & Cash"</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><strong>Receipt Footer Text</strong></label>
                                    <textarea name="receipt_footer" class="form-control" rows="4"><?= htmlspecialchars($footer) ?></textarea>
                                    <small class="text-muted">This message will be printed at the bottom of every receipt.</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Background Customization -->
                        <div class="card mb-4">
                            <div class="card-header bg-secondary text-white">Background Customization (Login Page)</div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label"><strong>Choose Background Gradient</strong></label>
                                    
                                    <!-- Hidden input to store the gradient value -->
                                    <input type="hidden" name="bg_color" id="bg_color_input" value="<?= htmlspecialchars($bg_color) ?>">
                                    
                                    <!-- Gradient Presets -->
                                    <div class="preset-container">
                                        <span class="preset-label">Select a gradient:</span>
                                        <button type="button" class="gradient-preset-btn <?= ($bg_color == 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)') ? 'selected-gradient' : '' ?>" 
                                                onclick="setGradient('linear-gradient(135deg, #667eea 0%, #764ba2 100%)', this)" 
                                                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                            Purple
                                        </button>
                                        <button type="button" class="gradient-preset-btn <?= ($bg_color == 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)') ? 'selected-gradient' : '' ?>" 
                                                onclick="setGradient('linear-gradient(135deg, #f093fb 0%, #f5576c 100%)', this)" 
                                                style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                            Pink
                                        </button>
                                        <button type="button" class="gradient-preset-btn <?= ($bg_color == 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)') ? 'selected-gradient' : '' ?>" 
                                                onclick="setGradient('linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)', this)" 
                                                style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                            Blue
                                        </button>
                                        <button type="button" class="gradient-preset-btn <?= ($bg_color == 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)') ? 'selected-gradient' : '' ?>" 
                                                onclick="setGradient('linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)', this)" 
                                                style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: #333;">
                                            Green
                                        </button>
                                        <button type="button" class="gradient-preset-btn <?= ($bg_color == 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)') ? 'selected-gradient' : '' ?>" 
                                                onclick="setGradient('linear-gradient(135deg, #fa709a 0%, #fee140 100%)', this)" 
                                                style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: #333;">
                                            Sunset
                                        </button>
                                        <button type="button" class="gradient-preset-btn <?= ($bg_color == 'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)') ? 'selected-gradient' : '' ?>" 
                                                onclick="setGradient('linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)', this)" 
                                                style="background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); color: #333;">
                                            Lavender
                                        </button>
                                        <button type="button" class="gradient-preset-btn <?= ($bg_color == 'linear-gradient(135deg, #fbc2eb 0%, #a6c1ee 100%)') ? 'selected-gradient' : '' ?>" 
                                                onclick="setGradient('linear-gradient(135deg, #fbc2eb 0%, #a6c1ee 100%)', this)" 
                                                style="background: linear-gradient(135deg, #fbc2eb 0%, #a6c1ee 100%); color: #333;">
                                            Pastel
                                        </button>
                                        <button type="button" class="gradient-preset-btn <?= ($bg_color == 'linear-gradient(135deg, #fccb90 0%, #d57eeb 100%)') ? 'selected-gradient' : '' ?>" 
                                                onclick="setGradient('linear-gradient(135deg, #fccb90 0%, #d57eeb 100%)', this)" 
                                                style="background: linear-gradient(135deg, #fccb90 0%, #d57eeb 100%); color: #333;">
                                            Peach
                                        </button>
                                        <button type="button" class="gradient-preset-btn <?= ($bg_color == 'linear-gradient(135deg, #89f7fe 0%, #66a6ff 100%)') ? 'selected-gradient' : '' ?>" 
                                                onclick="setGradient('linear-gradient(135deg, #89f7fe 0%, #66a6ff 100%)', this)" 
                                                style="background: linear-gradient(135deg, #89f7fe 0%, #66a6ff 100%);">
                                            Sky
                                        </button>
                                        <button type="button" class="gradient-preset-btn <?= ($bg_color == 'linear-gradient(135deg, #f6d365 0%, #fda085 100%)') ? 'selected-gradient' : '' ?>" 
                                                onclick="setGradient('linear-gradient(135deg, #f6d365 0%, #fda085 100%)', this)" 
                                                style="background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); color: #333;">
                                            Gold
                                        </button>
                                    </div>
                                    
                                    <!-- Live Preview -->
                                    <div class="gradient-preview" id="gradientPreview" style="background: <?= htmlspecialchars($bg_color) ?>;">
                                        <span class="gradient-preview-label">Live Preview</span>
                                    </div>
                                    <small class="text-muted d-block mt-2">Click any gradient above to preview. This will be used if no image or video is set.</small>
                                </div>
                                
                                <hr>
                                
                                <div class="mb-3">
                                    <label class="form-label"><strong>Background Image</strong></label>
                                    <input type="file" name="bg_image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                                    <?php if($bg_image && file_exists($bg_image)): ?>
                                        <div class="preview-box">
                                            <img src="<?= htmlspecialchars($bg_image) ?>" style="max-height: 100px;" class="img-thumbnail current-media">
                                            <a href="clear_background.php?type=bg_image" class="btn btn-sm btn-danger remove-btn" data-type="image">Remove</a>
                                        </div>
                                    <?php endif; ?>
                                    <small class="text-muted">Upload an image to use as background (overrides gradient).</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><strong>Background Video (MP4, WEBM, OGG)</strong></label>
                                    <input type="file" name="bg_video" class="form-control" accept="video/mp4,video/webm,video/ogg">
                                    <?php if($bg_video && file_exists($bg_video)): ?>
                                        <div class="preview-box">
                                            <video src="<?= htmlspecialchars($bg_video) ?>" style="max-height: 100px;" controls class="current-media"></video>
                                            <a href="clear_background.php?type=bg_video" class="btn btn-sm btn-danger remove-btn" data-type="video">Remove</a>
                                        </div>
                                    <?php endif; ?>
                                    <small class="text-muted">Video takes priority over image and gradient.</small>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save All Settings</button>
                        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Confirmation Modal -->
<div class="custom-modal-overlay" id="confirmModal">
    <div class="custom-modal-box">
        <div class="modal-icon">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <h4 id="modalTitle">Confirm Removal</h4>
        <p id="modalMessage">Do you want to remove this item?</p>
        <div class="btn-group">
            <button type="button" class="btn btn-secondary" id="modalCancelBtn">Cancel</button>
            <button type="button" class="btn btn-danger" id="modalConfirmBtn">Remove</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Function to set gradient preset
function setGradient(value, button) {
    // Update hidden input
    document.getElementById('bg_color_input').value = value;
    
    // Update preview
    document.getElementById('gradientPreview').style.background = value;
    
    // Remove selected class from all buttons
    document.querySelectorAll('.gradient-preset-btn').forEach(btn => {
        btn.classList.remove('selected-gradient');
    });
    
    // Add selected class to clicked button
    if(button) {
        button.classList.add('selected-gradient');
    }
}

// Set initial selected state
document.addEventListener('DOMContentLoaded', function() {
    const currentValue = document.getElementById('bg_color_input').value;
    const buttons = document.querySelectorAll('.gradient-preset-btn');
    
    buttons.forEach(btn => {
        // Check if this button's gradient matches the current value
        const btnGradient = btn.getAttribute('onclick');
        if(btnGradient && btnGradient.includes(currentValue)) {
            btn.classList.add('selected-gradient');
        }
    });
    
    // Update preview with current value
    document.getElementById('gradientPreview').style.background = currentValue;
    
    // Setup custom modal for remove buttons
    const removeButtons = document.querySelectorAll('.remove-btn');
    let pendingUrl = '';
    
    removeButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const type = this.getAttribute('data-type');
            const url = this.getAttribute('href');
            pendingUrl = url;
            
            // Update modal content based on type
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            
            if(type === 'image') {
                modalTitle.textContent = 'Remove Background Image';
                modalMessage.textContent = 'Do you want to remove the background image?';
            } else if(type === 'video') {
                modalTitle.textContent = 'Remove Background Video';
                modalMessage.textContent = 'Do you want to remove the background video?';
            }
            
            // Show modal
            document.getElementById('confirmModal').classList.add('active');
        });
    });
    
    // Modal confirm button
    document.getElementById('modalConfirmBtn').addEventListener('click', function() {
        if(pendingUrl) {
            window.location.href = pendingUrl;
        }
        document.getElementById('confirmModal').classList.remove('active');
    });
    
    // Modal cancel button
    document.getElementById('modalCancelBtn').addEventListener('click', function() {
        document.getElementById('confirmModal').classList.remove('active');
        pendingUrl = '';
    });
    
    // Close modal when clicking overlay (outside the box)
    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if(e.target === this) {
            this.classList.remove('active');
            pendingUrl = '';
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if(e.key === 'Escape') {
            document.getElementById('confirmModal').classList.remove('active');
            pendingUrl = '';
        }
    });
});
</script>
</body>
</html>
