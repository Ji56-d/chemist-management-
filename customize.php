<?php 
require_once 'config/db.php'; 
if(!isAdmin()) redirect('dashboard.php');

$message = '';

if($_SERVER['REQUEST_METHOD']=='POST'){
    // Save text settings
    updateSetting('chemist_name', $_POST['chemist_name'], $conn);
    updateSetting('system_tagline', $_POST['system_tagline'], $conn);
    updateSetting('receipt_footer', $_POST['receipt_footer'], $conn);
    updateSetting('bg_color', $_POST['bg_color'], $conn);
    
    // Handle background image upload
    if(isset($_FILES['bg_image']) && $_FILES['bg_image']['error'] == 0){
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['bg_image']['name'], PATHINFO_EXTENSION));
        if(in_array($ext, $allowed)){
            $upload_dir = 'uploads/backgrounds/';
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = 'bg_image_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['bg_image']['tmp_name'], $upload_dir . $filename);
            updateSetting('bg_image', $upload_dir . $filename, $conn);
            $message = "Background image uploaded successfully.";
        } else {
            $message = "Invalid image format. Use JPG, PNG, GIF, WEBP.";
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
            move_uploaded_file($_FILES['bg_video']['tmp_name'], $upload_dir . $filename);
            updateSetting('bg_video', $upload_dir . $filename, $conn);
            $message = "Background video uploaded successfully.";
        } else {
            $message = "Invalid video format. Use MP4, WEBM, OGG.";
        }
    }
    
    if(empty($message)) $message = "Settings saved successfully.";
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
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar - exactly as in the image -->
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
                                    <label class="form-label"><strong>Background Color / Gradient</strong></label>
                                    <input type="text" name="bg_color" class="form-control" value="<?= htmlspecialchars($bg_color) ?>" placeholder="e.g., #667eea, linear-gradient(135deg, #667eea 0%, #764ba2 100%)">
                                    <small class="text-muted">Used if no image or video is set.</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><strong>Background Image</strong></label>
                                    <input type="file" name="bg_image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                                    <?php if($bg_image && file_exists($bg_image)): ?>
                                        <div class="preview-box">
                                            <img src="<?= $bg_image ?>" style="max-height: 100px;" class="img-thumbnail current-media">
                                            <a href="clear_background.php?type=bg_image" class="btn btn-sm btn-danger" onclick="return confirm('Remove background image?')">Remove</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><strong>Background Video (MP4, WEBM, OGG)</strong></label>
                                    <input type="file" name="bg_video" class="form-control" accept="video/mp4,video/webm,video/ogg">
                                    <?php if($bg_video && file_exists($bg_video)): ?>
                                        <div class="preview-box">
                                            <video src="<?= $bg_video ?>" style="max-height: 100px;" controls class="current-media"></video>
                                            <a href="clear_background.php?type=bg_video" class="btn btn-sm btn-danger" onclick="return confirm('Remove background video?')">Remove</a>
                                        </div>
                                    <?php endif; ?>
                                    <small class="text-muted">Video takes priority over image and color.</small>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>