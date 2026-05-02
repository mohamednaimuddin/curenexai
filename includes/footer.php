    <?php if (isLoggedIn()): ?>
        </main> <!-- Close main-content -->
        
        <!-- Announcement Modal -->
        <div class="announcement-modal-overlay" id="announcementOverlay" style="display: none;">
            <div class="announcement-modal">
                <div class="announcement-modal-header" id="announcementHeader">
                    <span class="announcement-badge" id="announcementBadge">Info</span>
                    <button class="announcement-close" onclick="dismissAnnouncement()">&times;</button>
                </div>
                <div class="announcement-modal-body">
                    <h3 id="announcementTitle"></h3>
                    <div id="announcementContent"></div>
                    <p class="announcement-date" id="announcementDate"></p>
                </div>
                <div class="announcement-modal-footer">
                    <button class="btn btn-outline" onclick="dismissAnnouncement()">Dismiss</button>
                    <button class="btn btn-primary" onclick="nextAnnouncement()">
                        <span id="nextBtnText">OK</span>
                    </button>
                </div>
            </div>
        </div>
        
        <style>
            .announcement-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.6);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
                backdrop-filter: blur(4px);
            }
            
            .announcement-modal {
                background: white;
                border-radius: 16px;
                max-width: 500px;
                width: 90%;
                max-height: 80vh;
                overflow: hidden;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: slideIn 0.3s ease;
            }
            
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: scale(0.9) translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: scale(1) translateY(0);
                }
            }
            
            .announcement-modal-header {
                padding: 20px 24px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .announcement-modal-header.info { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
            .announcement-modal-header.success { background: linear-gradient(135deg, #10b981, #059669); }
            .announcement-modal-header.warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
            .announcement-modal-header.danger { background: linear-gradient(135deg, #ef4444, #dc2626); }
            
            .announcement-badge {
                color: white;
                font-weight: 600;
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .announcement-close {
                background: rgba(255, 255, 255, 0.2);
                border: none;
                color: white;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                font-size: 20px;
                cursor: pointer;
                transition: background 0.2s;
            }
            
            .announcement-close:hover {
                background: rgba(255, 255, 255, 0.3);
            }
            
            .announcement-modal-body {
                padding: 24px;
            }
            
            .announcement-modal-body h3 {
                margin: 0 0 16px 0;
                font-size: 22px;
                color: #1f2937;
            }
            
            .announcement-modal-body #announcementContent {
                color: #4b5563;
                line-height: 1.7;
                margin-bottom: 16px;
            }
            
            .announcement-date {
                font-size: 13px;
                color: #9ca3af;
                margin: 0;
            }
            
            .announcement-modal-footer {
                padding: 16px 24px;
                background: #f9fafb;
                display: flex;
                justify-content: flex-end;
                gap: 12px;
            }
            
            .announcement-modal-footer .btn {
                padding: 10px 24px;
                border-radius: 8px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .announcement-modal-footer .btn-outline {
                background: white;
                border: 1px solid #d1d5db;
                color: #4b5563;
            }
            
            .announcement-modal-footer .btn-outline:hover {
                background: #f3f4f6;
            }
            
            .announcement-modal-footer .btn-primary {
                background: #6366f1;
                border: none;
                color: white;
            }
            
            .announcement-modal-footer .btn-primary:hover {
                background: #4f46e5;
            }
        </style>
    <?php else: ?>
        </div> <!-- Close auth-layout -->
    <?php endif; ?>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container-fluid">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved. | For certified doctors only.</p>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/main.js?v=<?php echo time(); ?>"></script>
    
    <?php if (isset($additionalJS)): ?>
        <?php foreach ($additionalJS as $js): ?>
            <script src="<?php echo APP_URL . $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <script>
        // Global configuration
        const APP_URL = '<?php echo APP_URL; ?>';
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        
        <?php if (isLoggedIn()): ?>
        // Announcement functionality
        let announcements = [];
        let currentAnnouncementIndex = 0;
        
        function loadAnnouncements() {
            // Always check for announcements on every page load
            // The API returns only non-dismissed announcements within date range
            fetch(APP_URL + '/api/get_announcements.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Announcements response:', data); // Debug log
                    if (data.success && data.announcements && data.announcements.length > 0) {
                        // Show all announcements (show_popup defaults to 1 if not set)
                        announcements = data.announcements.filter(a => 
                            a.show_popup == 1 || a.show_popup === true || a.show_popup === '1' || a.show_popup === undefined
                        );
                        console.log('Filtered announcements:', announcements); // Debug log
                        if (announcements.length > 0) {
                            showAnnouncement(0);
                        }
                    }
                })
                .catch(err => console.log('Announcements error:', err));
        }
        
        function showAnnouncement(index) {
            if (index >= announcements.length) {
                document.getElementById('announcementOverlay').style.display = 'none';
                return;
            }
            
            const ann = announcements[index];
            currentAnnouncementIndex = index;
            
            const header = document.getElementById('announcementHeader');
            const badge = document.getElementById('announcementBadge');
            
            // Remove old type classes and add new one
            header.className = 'announcement-modal-header ' + (ann.type || 'info');
            badge.textContent = (ann.type || 'info').toUpperCase();
            
            document.getElementById('announcementTitle').textContent = ann.title;
            document.getElementById('announcementContent').innerHTML = ann.content.replace(/\n/g, '<br>');
            document.getElementById('announcementDate').textContent = 'Posted: ' + ann.formatted_date;
            
            // Update button text
            const nextBtn = document.getElementById('nextBtnText');
            if (index < announcements.length - 1) {
                nextBtn.textContent = 'Next (' + (announcements.length - index - 1) + ' more)';
            } else {
                nextBtn.textContent = 'OK';
            }
            
            document.getElementById('announcementOverlay').style.display = 'flex';
        }
        
        function dismissAnnouncement() {
            const ann = announcements[currentAnnouncementIndex];
            if (ann && ann.id) {
                // Send dismiss request
                fetch(APP_URL + '/api/dismiss_announcement.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'announcement_id=' + ann.id
                }).catch(err => console.log('Dismiss failed'));
            }
            
            // Move to next or close
            if (currentAnnouncementIndex < announcements.length - 1) {
                showAnnouncement(currentAnnouncementIndex + 1);
            } else {
                document.getElementById('announcementOverlay').style.display = 'none';
            }
        }
        
        function nextAnnouncement() {
            if (currentAnnouncementIndex < announcements.length - 1) {
                showAnnouncement(currentAnnouncementIndex + 1);
            } else {
                document.getElementById('announcementOverlay').style.display = 'none';
            }
        }
        
        // Load announcements on page load (only if just logged in)
        <?php if (isset($_SESSION['show_announcements']) && $_SESSION['show_announcements']): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Small delay to let page render first
            setTimeout(loadAnnouncements, 500);
        });
        <?php unset($_SESSION['show_announcements']); ?>
        <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>
