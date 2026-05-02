<?php
require_once __DIR__ . '/includes/init.php';
requireLogin();
$pageTitle = 'Theme & Appearance';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<div class="dashboard-card" style="max-width:500px;margin:40px auto;">
    <h2><i class="fas fa-paint-brush"></i> Theme & Appearance</h2>
    <form method="post" action="">
        <div class="form-group">
            <label for="theme">Select Theme</label>
            <select name="theme" id="theme" class="form-control">
                <option value="light">Light</option>
                <option value="dark">Dark</option>
                <option value="system">System Default</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Apply Theme</button>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
