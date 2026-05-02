<?php
require_once __DIR__ . '/../includes/init.php';
$pageTitle = 'Prescription Not Found';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="dashboard-card empty-state" style="max-width:600px;margin:60px auto;">
    <i class="fas fa-exclamation-triangle" style="color:#f5576c;font-size:64px;"></i>
    <h1>Prescription Not Found</h1>
    <p>The prescription you are looking for does not exist or you do not have permission to view it.</p>
    <a href="<?php echo APP_URL; ?>/prescriptions/list.php" class="btn btn-primary">
        <i class="fas fa-arrow-left"></i> Back to Prescriptions
    </a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
