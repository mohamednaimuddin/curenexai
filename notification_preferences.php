<?php
require_once __DIR__ . '/includes/init.php';
requireLogin();
$pageTitle = 'Notification Preferences';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<div class="dashboard-card" style="max-width:500px;margin:40px auto;">
    <h2><i class="fas fa-bell"></i> Notification Preferences</h2>
    <form method="post" action="">
        <div class="form-group">
            <label><input type="checkbox" name="email_notifications" checked> Email Notifications</label>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="sms_notifications"> SMS Notifications</label>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="app_notifications" checked> In-App Notifications</label>
        </div>
        <button type="submit" class="btn btn-primary">Save Preferences</button>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
