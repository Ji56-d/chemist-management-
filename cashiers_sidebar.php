<?php
// cashiers_sidebar.php - Reusable sidebar for cashier users
$chemist_name = getSetting('chemist_name', $conn);
if(empty($chemist_name)) $chemist_name = 'City Chemist & Medical Store';

$cashier_name = $_SESSION['name'] ?? 'Cashier';
?>
<div class="col-md-2 p-0 sidebar">
    <div class="text-white text-center py-4 bg-dark">
        <h6><?= htmlspecialchars($chemist_name) ?></h6>
        <small>Cashier: <?= htmlspecialchars($cashier_name) ?></small>
    </div>
    <a href="pos.php"><i class="fas fa-shopping-cart"></i> POS Sale</a>
    <a href="my_sales.php"><i class="fas fa-receipt"></i> My Sales</a>
    <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<style>
    /* Ensure the sidebar stretches full height */
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
    .sidebar a.active {
        background: #1a252f;
    }
</style>