<?php
require_once 'config/db.php';
if(!isAdmin()) redirect('dashboard.php');

$type = $_GET['type'] ?? '';
if($type == 'bg_image'){
    updateSetting('bg_image', '', $conn);
} elseif($type == 'bg_video'){
    updateSetting('bg_video', '', $conn);
}
redirect('customize.php');
?>