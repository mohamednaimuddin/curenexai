<?php
require_once __DIR__ . '/includes/init.php';
requireLogin();
$pageTitle = 'Settings';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>


<div class="dashboard-card settings-card">
    <div class="card-header">
        <h1><i class="fas fa-cog"></i> Settings</h1>
    </div>
    <div class="card-body">
        <p>Manage your account and application settings here.</p>
        <div class="settings-list-grid">
            <a href="change_password.php" class="settings-item">
                <i class="fas fa-key"></i>
                <span>Change Password</span>
            </a>
            <a href="update_profile.php" class="settings-item">
                <i class="fas fa-user-edit"></i>
                <span>Update Profile</span>
            </a>
            <a href="notification_preferences.php" class="settings-item">
                <i class="fas fa-bell"></i>
                <span>Notification Preferences</span>
            </a>
            <a href="theme_appearance.php" class="settings-item">
                <i class="fas fa-paint-brush"></i>
                <span>Theme & Appearance</span>
            </a>
            <a href="privacy_settings.php" class="settings-item">
                <i class="fas fa-shield-alt"></i>
                <span>Privacy & AI Settings</span>
            </a>
        </div>
        <hr>
        <p class="text-muted">More settings options coming soon.</p>
    </div>
</div>

<style>
.settings-card {
    max-width: 600px;
    margin: 40px auto;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07);
    border-radius: 12px;
    background: #fff;
    padding: 0;
}
.settings-card .card-header {
    padding: 24px 24px 8px 24px;
    border-bottom: 1px solid #eee;
    background: var(--primary-bg, #f7f9fa);
    border-radius: 12px 12px 0 0;
}
.settings-card .card-header h1 {
    font-size: 2rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}
.settings-card .card-body {
    padding: 24px;
}
.settings-list-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
    margin: 24px 0;
}
.settings-item {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #f7f9fa;
    padding: 16px;
    border-radius: 8px;
    text-decoration: none;
    color: #333;
    font-weight: 500;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    transition: background 0.2s;
}
.settings-item:hover {
    background: #e3e8ee;
}
.settings-item i {
    font-size: 1.3em;
    color: var(--primary-color, #667eea);
}
@media (max-width: 600px) {
    .settings-card {
        margin: 16px;
        max-width: 100%;
    }
    .settings-list-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .settings-card .card-header, .settings-card .card-body {
        padding: 16px;
    }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
