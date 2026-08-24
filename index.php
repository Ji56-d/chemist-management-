<?php 
require_once 'config/db.php';

// Get dynamic settings
$chemist_name = getSetting('chemist_name', $conn);
if(empty($chemist_name)) $chemist_name = 'Chemist POS System - Kenya';

$system_tagline = getSetting('system_tagline', $conn);
if(empty($system_tagline)) $system_tagline = 'KES Currency | M-Pesa & Cash';

$bg_color = getSetting('bg_color', $conn);
if(empty($bg_color)) $bg_color = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';

$bg_image = getSetting('bg_image', $conn);
$bg_video = getSetting('bg_video', $conn);

// Determine background style
$bg_style = '';
if(!empty($bg_video) && file_exists($bg_video)){
    $bg_type = 'video';
} elseif(!empty($bg_image) && file_exists($bg_image)){
    $bg_style = "background: url('$bg_image') no-repeat center center fixed; background-size: cover;";
    $bg_type = 'image';
} else {
    $bg_style = "background: $bg_color;";
    $bg_type = 'color';
}

// Current date details
$current_day = date('l');          // e.g., Monday
$current_date = date('jS F Y');    // e.g., 1st June 2026
$current_month_year = date('F Y'); // e.g., June 2026
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($chemist_name) ?> - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            <?= $bg_style ?>
        }
        <?php if($bg_type == 'video'): ?>
        .video-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -1;
        }
        <?php endif; ?>
        .login-card {
            margin-top: 80px;
            border-radius: 20px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(5px);
        }
        .toggle-password {
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 38px;
            z-index: 10;
        }
        .form-group {
            position: relative;
        }
        .date-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #6c757d;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
    </style>
</head>
<body>
<?php if($bg_type == 'video' && !empty($bg_video) && file_exists($bg_video)): ?>
    <video class="video-background" autoplay muted loop>
        <source src="<?= $bg_video ?>" type="video/mp4">
    </video>
<?php endif; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card login-card shadow">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h3><?= htmlspecialchars($chemist_name) ?></h3>
                    <small><?= htmlspecialchars($system_tagline) ?></small>
                </div>
                <div class="card-body p-4">
                    <?php if(isset($_GET['error'])): ?>
                        <div class="alert alert-danger">Invalid username or password</div>
                    <?php endif; ?>
                    <form method="POST" action="authenticate.php">
                        <div class="mb-3">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3 form-group">
                            <label>Password</label>
                            <div class="position-relative">
                                <input type="password" name="password" id="password" class="form-control" required>
                                <i class="fas fa-eye toggle-password" id="togglePassword" style="top: 12px;"></i>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                    
                    <!-- Date / Month / Year Display -->
                    <div class="date-footer">
                        <i class="fas fa-calendar-alt"></i> <?= $current_day ?>, <?= $current_date ?>
                        <br><small><?= $current_month_year ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Password visibility toggle
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    
    togglePassword.addEventListener('click', function() {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.classList.toggle('fa-eye-slash');
    });
</script>
</body>
</html>