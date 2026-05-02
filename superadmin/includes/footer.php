        </div><!-- End Content Area -->
    </div><!-- End Main Content -->
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Improved sidebar toggle with overlay
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            
            // Prevent body scroll when sidebar is open on mobile
            if (sidebar.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }
        
        // Close sidebar on window resize (if going from mobile to desktop)
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
        
        // Close sidebar when clicking a nav link (mobile)
        document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    const sidebar = document.getElementById('sidebar');
                    const overlay = document.getElementById('sidebarOverlay');
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
        
        // Swipe to close sidebar (mobile)
        let touchStartX = 0;
        let touchEndX = 0;
        
        document.getElementById('sidebar').addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        }, false);
        
        document.getElementById('sidebar').addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            if (touchStartX - touchEndX > 50) {
                toggleSidebar();
            }
        }, false);
        
        // Load notifications and messages
        function loadNotifications() {
            fetch('api/get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    const notificationCount = document.getElementById('notificationCount');
                    const notificationList = document.getElementById('notificationList');
                    
                    if (data.notifications && data.notifications.length > 0) {
                        notificationCount.textContent = data.count;
                        notificationCount.style.display = data.count > 0 ? 'flex' : 'none';
                        
                        let html = '';
                        data.notifications.forEach(n => {
                            html += `
                                <li>
                                    <a class="dropdown-item py-2" href="${n.link || '#'}">
                                        <div class="d-flex align-items-start">
                                            <i class="bi ${n.icon || 'bi-info-circle'} me-2 mt-1 text-${n.type || 'primary'}"></i>
                                            <div>
                                                <div class="fw-semibold small">${n.title}</div>
                                                <div class="text-muted small">${n.time}</div>
                                            </div>
                                        </div>
                                    </a>
                                </li>`;
                        });
                        notificationList.innerHTML = html;
                    } else {
                        notificationCount.style.display = 'none';
                        notificationList.innerHTML = '<li class="text-center text-muted py-3"><i class="bi bi-bell-slash"></i> No new notifications</li>';
                    }
                })
                .catch(err => {
                    console.log('Notifications not available');
                });
        }
        
        function loadMessages() {
            fetch('api/get_messages.php')
                .then(response => response.json())
                .then(data => {
                    const messageCount = document.getElementById('messageCount');
                    const messageList = document.getElementById('messageList');
                    
                    if (data.messages && data.messages.length > 0) {
                        messageCount.textContent = data.count;
                        messageCount.style.display = data.count > 0 ? 'flex' : 'none';
                        
                        let html = '';
                        data.messages.forEach(m => {
                            html += `
                                <li>
                                    <a class="dropdown-item py-2" href="${m.link || '#'}">
                                        <div class="d-flex align-items-start">
                                            <div class="admin-avatar me-2" style="width: 32px; height: 32px; font-size: 0.7rem;">
                                                ${m.avatar || 'A'}
                                            </div>
                                            <div>
                                                <div class="fw-semibold small">${m.title}</div>
                                                <div class="text-muted small text-truncate" style="max-width: 200px;">${m.preview}</div>
                                            </div>
                                        </div>
                                    </a>
                                </li>`;
                        });
                        messageList.innerHTML = html;
                    } else {
                        messageCount.style.display = 'none';
                        messageList.innerHTML = '<li class="text-center text-muted py-3"><i class="bi bi-envelope-slash"></i> No new messages</li>';
                    }
                })
                .catch(err => {
                    console.log('Messages not available');
                });
        }
        
        function markAllRead() {
            fetch('api/mark_notifications_read.php', { method: 'POST' })
                .then(() => loadNotifications());
        }
        
        // Load on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadNotifications();
            loadMessages();
            // Refresh every 30 seconds
            setInterval(loadNotifications, 30000);
            setInterval(loadMessages, 30000);
        });
    </script>
</body>
</html>
