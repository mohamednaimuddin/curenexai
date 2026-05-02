/**
 * Curenex AI - Modern JavaScript
 * Mobile-First, Accessible, User-Friendly
 */

(function() {
    'use strict';

    // ============================================
    // PAGE LOADER - Execute Immediately
    // ============================================
    
    // Create and inject page loader HTML
    function createPageLoader() {
        const existingLoader = document.getElementById('globalPageLoader');
        if (existingLoader) return;
        
        const loaderHTML = `
            <div class="page-loader" id="globalPageLoader">
                <div class="loader-spinner"></div>
                <div class="loader-text">Loading...</div>
            </div>
        `;
        document.body.insertAdjacentHTML('afterbegin', loaderHTML);
    }
    
    // Show page loader
    window.showPageLoader = function(message = 'Loading...') {
        let loader = document.getElementById('globalPageLoader');
        if (!loader) {
            createPageLoader();
            loader = document.getElementById('globalPageLoader');
        }
        const loaderText = loader.querySelector('.loader-text');
        if (loaderText) loaderText.textContent = message;
        loader.classList.remove('hidden');
    };
    
    // Hide page loader
    window.hidePageLoader = function() {
        const loader = document.getElementById('globalPageLoader');
        if (loader) {
            loader.classList.add('hidden');
        }
    };
    
    // Show button loader
    window.showButtonLoader = function(button, text = 'Processing...') {
        if (!button) return;
        button.disabled = true;
        button.dataset.originalText = button.innerHTML;
        button.innerHTML = `<span class="btn-loader"><span class="spinner"></span> ${text}</span>`;
    };
    
    // Hide button loader
    window.hideButtonLoader = function(button) {
        if (!button || !button.dataset.originalText) return;
        button.disabled = false;
        button.innerHTML = button.dataset.originalText;
        delete button.dataset.originalText;
    };
    
    // Show content loader in element
    window.showContentLoader = function(element, message = 'Loading content...') {
        if (!element) return;
        element.innerHTML = `
            <div class="content-loader">
                <div class="loader-icon"></div>
                <div class="loader-message">${message}</div>
            </div>
        `;
    };
    
    // Show skeleton loader
    window.showSkeletonLoader = function(element, rows = 3) {
        if (!element) return;
        let html = '<div class="skeleton-container">';
        for (let i = 0; i < rows; i++) {
            const width = i === 0 ? 'full' : (i === rows - 1 ? 'short' : 'medium');
            html += `<div class="skeleton skeleton-text ${width}"></div>`;
        }
        html += '</div>';
        element.innerHTML = html;
    };
    
    // Show AI brain loader
    window.showAILoader = function(element, title = 'Analyzing...', subtitle = 'This may take a moment') {
        if (!element) return;
        element.innerHTML = `
            <div class="ai-brain-loader">
                <i class="fas fa-brain brain-icon"></i>
                <div class="loader-title">${title}</div>
                <div class="loader-subtitle">${subtitle}</div>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
            </div>
        `;
    };
    
    // Show dot loader
    window.showDotLoader = function(element) {
        if (!element) return;
        element.innerHTML = `
            <div class="dot-loader">
                <span class="dot"></span>
                <span class="dot"></span>
                <span class="dot"></span>
            </div>
        `;
    };

    // ============================================
    // DOM READY
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        // Hide loader once page is ready
        hidePageLoader();
        
        initSidebar();
        initUserMenu();
        initAlerts();
        initTables();
        initForms();
        initAccessibility();
        initSwipeGestures();
        initFormLoaders();
        initLinkLoaders();
        initKeyboardShortcuts();
    });

    // ============================================
    // GLOBAL KEYBOARD SHORTCUTS
    // ============================================
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Don't trigger shortcuts when typing in input fields
            const activeElement = document.activeElement;
            const isInputField = activeElement && (
                activeElement.tagName === 'INPUT' ||
                activeElement.tagName === 'TEXTAREA' ||
                activeElement.tagName === 'SELECT' ||
                activeElement.isContentEditable
            );
            
            // Get base URL from APP_URL if available, otherwise use empty string for relative paths
            const baseUrl = (typeof APP_URL !== 'undefined' && APP_URL) ? APP_URL : '';
            
            // Alt + D: Go to Dashboard (works even in input fields)
            if (e.altKey && !e.ctrlKey && !e.shiftKey && (e.key === 'd' || e.key === 'D')) {
                e.preventDefault();
                window.location.href = baseUrl + '/dashboard.php';
                return;
            }
            
            // Alt + P: New Patient (works even in input fields)
            if (e.altKey && !e.ctrlKey && !e.shiftKey && (e.key === 'p' || e.key === 'P')) {
                e.preventDefault();
                window.location.href = baseUrl + '/patients/add.php';
                return;
            }
            
            // Alt + C: New Consultation (works even in input fields)
            if (e.altKey && !e.ctrlKey && !e.shiftKey && (e.key === 'c' || e.key === 'C')) {
                e.preventDefault();
                window.location.href = baseUrl + '/consultations/add.php';
                return;
            }
            
            // Alt + R: Search Repertory (works even in input fields)
            if (e.altKey && !e.ctrlKey && !e.shiftKey && (e.key === 'r' || e.key === 'R')) {
                e.preventDefault();
                window.location.href = baseUrl + '/repertory/search.php';
                return;
            }
            
            // Ctrl + K: Quick Search (focus search input if exists)
            if (e.ctrlKey && !e.altKey && !e.shiftKey && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                // Try to find and focus a search input
                const searchInput = document.querySelector('#globalSearch, #searchInput, input[type="search"], input[name="search"], input[name="q"], .search-input');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                } else {
                    // If no search field, go to repertory search
                    window.location.href = baseUrl + '/repertory/search.php';
                }
                return;
            }
            
            // Ctrl + S: Save Form (prevent default browser save, submit form instead)
            if (e.ctrlKey && !e.altKey && !e.shiftKey && (e.key === 's' || e.key === 'S')) {
                // Find the closest form to the active element or any visible form
                const form = activeElement?.closest('form') || document.querySelector('form:not([data-no-save])');
                if (form) {
                    e.preventDefault();
                    // Find and click submit button, or submit the form
                    const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        submitBtn.click();
                    } else if (!submitBtn) {
                        form.submit();
                    }
                } else {
                    // Still prevent browser save dialog even if no form
                    e.preventDefault();
                }
                return;
            }
        });
        
        // Log that shortcuts are initialized (for debugging)
        console.log('Keyboard shortcuts initialized: Alt+D (Dashboard), Alt+P (New Patient), Alt+C (New Consultation), Alt+R (Repertory), Ctrl+K (Search), Ctrl+S (Save)');
    }
    
    // ============================================
    // FORM LOADERS - Auto add loading states
    // ============================================
    function initFormLoaders() {
        // Add loader to all forms on submit
        const forms = document.querySelectorAll('form:not([data-no-loader])');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    // Don't show loader for AJAX forms
                    if (form.dataset.ajax === 'true') return;
                    
                    showButtonLoader(submitBtn, 'Please wait...');
                }
            });
        });
    }
    
    // ============================================
    // LINK LOADERS - Show loader for slow navigations
    // ============================================
    function initLinkLoaders() {
        // Add loader for navigation links that might take time
        const slowLinks = document.querySelectorAll('a[data-show-loader], .btn-primary[href], .nav-link[href]');
        slowLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Skip if it's opening in new tab or is a hash link
                if (this.target === '_blank' || this.href.startsWith('#') || this.href.includes('#')) return;
                // Skip if it's a JavaScript link
                if (this.href.startsWith('javascript:')) return;
                
                // Show loader after small delay (prevents flash for fast loads)
                setTimeout(() => {
                    if (!e.defaultPrevented) {
                        showPageLoader('Loading page...');
                    }
                }, 200);
            });
        });
    }

    // ============================================
    // SIDEBAR FUNCTIONALITY
    // ============================================
    function initSidebar() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileOverlay = document.getElementById('mobileOverlay');
        const mainContent = document.getElementById('mainContent');

        if (!sidebar) return;

        // Toggle sidebar on button click
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleSidebar();
            });
        }

        // Close sidebar when clicking overlay
        if (mobileOverlay) {
            mobileOverlay.addEventListener('click', closeSidebar);
        }

        // Close sidebar when clicking a link on mobile
        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 768) {
                    closeSidebar();
                }
            });
        });

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth >= 768) {
                    // On desktop, ensure sidebar is visible
                    sidebar.classList.remove('active');
                    if (mobileOverlay) mobileOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }, 100);
        });

        // Handle escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                closeSidebar();
            }
        });

        function toggleSidebar() {
            const isActive = sidebar.classList.toggle('active');
            if (mobileOverlay) mobileOverlay.classList.toggle('active', isActive);
            
            // Prevent body scroll when sidebar is open on mobile
            if (window.innerWidth < 768) {
                document.body.style.overflow = isActive ? 'hidden' : '';
            }

            // Update ARIA
            if (sidebarToggle) {
                sidebarToggle.setAttribute('aria-expanded', isActive);
            }
        }

        function closeSidebar() {
            sidebar.classList.remove('active');
            if (mobileOverlay) mobileOverlay.classList.remove('active');
            document.body.style.overflow = '';
            
            if (sidebarToggle) {
                sidebarToggle.setAttribute('aria-expanded', 'false');
            }
        }

        // Expose for external use
        window.toggleSidebar = toggleSidebar;
        window.closeSidebar = closeSidebar;
    }

    // ============================================
    // USER MENU DROPDOWN
    // ============================================
    function initUserMenu() {
        const userMenuBtn = document.getElementById('userMenuBtn');
        const userDropdown = document.getElementById('userDropdown');

        if (!userMenuBtn || !userDropdown) return;

        // Toggle on click
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleUserMenu();
        });

        // Toggle on Enter/Space key
        userMenuBtn.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleUserMenu();
            }
        });

        // Close when clicking outside
        document.addEventListener('click', function(e) {
            if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                closeUserMenu();
            }
        });

        // Handle escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && userDropdown.classList.contains('show')) {
                closeUserMenu();
                userMenuBtn.focus();
            }
        });

        // Keyboard navigation within dropdown
        userDropdown.addEventListener('keydown', function(e) {
            const items = userDropdown.querySelectorAll('a[role="menuitem"]');
            const currentIndex = Array.from(items).indexOf(document.activeElement);

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
                items[nextIndex].focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prevIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
                items[prevIndex].focus();
            }
        });

        function toggleUserMenu() {
            const isOpen = userDropdown.classList.toggle('show');
            userMenuBtn.classList.toggle('active', isOpen);
            userMenuBtn.setAttribute('aria-expanded', isOpen);

            if (isOpen) {
                // Focus first item when opening
                const firstItem = userDropdown.querySelector('a[role="menuitem"]');
                if (firstItem) setTimeout(() => firstItem.focus(), 100);
            }
        }

        function closeUserMenu() {
            userDropdown.classList.remove('show');
            userMenuBtn.classList.remove('active');
            userMenuBtn.setAttribute('aria-expanded', 'false');
        }

        // Expose for external use
        window.toggleUserMenu = toggleUserMenu;
        window.closeUserMenu = closeUserMenu;
    }

    // ============================================
    // ALERTS & NOTIFICATIONS
    // ============================================
    function initAlerts() {
        // Auto-dismiss alerts after delay
        const alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(alert => {
            // Add close button functionality
            const closeBtn = alert.querySelector('.close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    dismissAlert(alert);
                });
            }

            // Auto-dismiss after 5 seconds
            setTimeout(() => dismissAlert(alert), 5000);
        });

        function dismissAlert(alert) {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }
    }

    // Show alert programmatically
    window.showAlert = function(type, message, autoDismiss = true) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible" style="animation: slideIn 0.3s ease">
                <i class="fas fa-${getAlertIcon(type)}"></i>
                <span>${message}</span>
                <button type="button" class="close" aria-label="Close">×</button>
            </div>
        `;

        const container = document.querySelector('.main-content') || document.body;
        const alertElement = document.createElement('div');
        alertElement.innerHTML = alertHtml;
        const alert = alertElement.firstElementChild;

        container.insertBefore(alert, container.firstChild);

        // Add close functionality
        alert.querySelector('.close').addEventListener('click', function() {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        });

        // Auto-dismiss
        if (autoDismiss) {
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }
            }, 5000);
        }

        return alert;
    };

    function getAlertIcon(type) {
        const icons = {
            'success': 'check-circle',
            'danger': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    // Convenience functions
    window.showSuccess = (msg) => window.showAlert('success', msg);
    window.showError = (msg) => window.showAlert('danger', msg);
    window.showWarning = (msg) => window.showAlert('warning', msg);
    window.showInfo = (msg) => window.showAlert('info', msg);

    // ============================================
    // TABLE RESPONSIVENESS
    // ============================================
    function initTables() {
        // Wrap tables in responsive container
        const tables = document.querySelectorAll('table:not(.table-responsive table)');
        tables.forEach(table => {
            if (!table.parentElement.classList.contains('table-responsive')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'table-responsive';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            }
        });

        // Add horizontal scroll indicator
        const responsiveTables = document.querySelectorAll('.table-responsive');
        responsiveTables.forEach(wrapper => {
            wrapper.addEventListener('scroll', function() {
                if (this.scrollLeft > 0) {
                    this.classList.add('scrolled-left');
                } else {
                    this.classList.remove('scrolled-left');
                }
            });
        });
    }

    // ============================================
    // FORM ENHANCEMENTS
    // ============================================
    function initForms() {
        // Prevent zoom on input focus on iOS
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.tagName.toLowerCase() === 'select' || 
                (input.type && ['text', 'email', 'password', 'tel', 'url', 'search'].includes(input.type))) {
                if (!input.style.fontSize || parseInt(input.style.fontSize) < 16) {
                    input.style.fontSize = '16px';
                }
            }
        });

        // Add floating labels animation
        const formGroups = document.querySelectorAll('.form-group');
        formGroups.forEach(group => {
            const input = group.querySelector('.form-control');
            const label = group.querySelector('label');
            
            if (input && label) {
                input.addEventListener('focus', () => group.classList.add('focused'));
                input.addEventListener('blur', () => {
                    group.classList.remove('focused');
                    if (input.value) group.classList.add('has-value');
                    else group.classList.remove('has-value');
                });
                
                // Check initial state
                if (input.value) group.classList.add('has-value');
            }
        });
    }

    // ============================================
    // ACCESSIBILITY IMPROVEMENTS
    // ============================================
    function initAccessibility() {
        // Skip link for keyboard users
        const skipLink = document.createElement('a');
        skipLink.href = '#mainContent';
        skipLink.className = 'skip-link';
        skipLink.textContent = 'Skip to main content';
        skipLink.style.cssText = `
            position: absolute;
            top: -40px;
            left: 0;
            background: var(--primary-500, #6366f1);
            color: white;
            padding: 8px 16px;
            z-index: 9999;
            transition: top 0.2s;
        `;
        skipLink.addEventListener('focus', () => skipLink.style.top = '0');
        skipLink.addEventListener('blur', () => skipLink.style.top = '-40px');
        document.body.insertBefore(skipLink, document.body.firstChild);

        // Announce page changes to screen readers
        const announcer = document.createElement('div');
        announcer.setAttribute('aria-live', 'polite');
        announcer.setAttribute('aria-atomic', 'true');
        announcer.className = 'sr-only';
        announcer.style.cssText = 'position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0;';
        document.body.appendChild(announcer);
        window.announce = (message) => {
            announcer.textContent = '';
            setTimeout(() => announcer.textContent = message, 100);
        };
    }

    // ============================================
    // TOUCH/SWIPE GESTURES
    // ============================================
    function initSwipeGestures() {
        if (!('ontouchstart' in window)) return;

        let touchStartX = 0;
        let touchEndX = 0;
        const sidebar = document.getElementById('sidebar');
        const swipeThreshold = 100;

        document.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        document.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, { passive: true });

        function handleSwipe() {
            const swipeDistance = touchEndX - touchStartX;
            
            // Swipe right from left edge to open sidebar
            if (touchStartX < 30 && swipeDistance > swipeThreshold && window.innerWidth < 768) {
                if (sidebar && !sidebar.classList.contains('active')) {
                    window.toggleSidebar && window.toggleSidebar();
                }
            }
            
            // Swipe left to close sidebar
            if (swipeDistance < -swipeThreshold && window.innerWidth < 768) {
                if (sidebar && sidebar.classList.contains('active')) {
                    window.closeSidebar && window.closeSidebar();
                }
            }
        }
    }

    // ============================================
    // UTILITY FUNCTIONS
    // ============================================
    
    // Loading overlay
    window.showLoading = function(message = 'Loading...') {
        if (document.getElementById('loadingOverlay')) return;

        const overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'loading-overlay';
        overlay.innerHTML = `
            <div class="loading-content" style="text-align: center;">
                <div class="spinner"></div>
                <p style="margin-top: 1rem; color: var(--gray-600)">${message}</p>
            </div>
        `;
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
    };

    window.hideLoading = function() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.remove();
            document.body.style.overflow = '';
        }
    };

    // Confirm dialog
    window.confirmAction = function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    };

    // Date formatting
    window.formatDate = function(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-IN', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    };

    window.formatDateTime = function(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('en-IN', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    // Debounce function
    window.debounce = function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    // Throttle function
    window.throttle = function(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    };

    // AJAX helper
    window.ajaxRequest = function(url, method = 'GET', data = null) {
        return new Promise((resolve, reject) => {
            const fullUrl = url.startsWith('http') ? url : (window.APP_URL || '') + url;
            
            $.ajax({
                url: fullUrl,
                method: method,
                data: data,
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': window.CSRF_TOKEN || ''
                },
                success: resolve,
                error: (xhr, status, error) => reject(error)
            });
        });
    };

    // Copy to clipboard
    window.copyToClipboard = async function(text) {
        try {
            await navigator.clipboard.writeText(text);
            showSuccess('Copied to clipboard!');
            return true;
        } catch (err) {
            showError('Failed to copy');
            return false;
        }
    };

    // Print helper
    window.printContent = function(elementId) {
        const element = document.getElementById(elementId);
        if (!element) return;

        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Print</title>
                <link rel="stylesheet" href="${APP_URL}/assets/css/style.css">
                <style>
                    body { padding: 20px; }
                    @media print {
                        body { padding: 0; }
                    }
                </style>
            </head>
            <body>
                ${element.innerHTML}
                <script>
                    window.onload = function() {
                        window.print();
                        window.onafterprint = function() { window.close(); };
                    };
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    };

    // Export to CSV
    window.exportToCSV = function(data, filename) {
        if (!data || data.length === 0) return;

        const headers = Object.keys(data[0]);
        const csvContent = [
            headers.join(','),
            ...data.map(row => 
                headers.map(header => {
                    const value = row[header] || '';
                    return `"${String(value).replace(/"/g, '""')}"`;
                }).join(',')
            )
        ].join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${filename}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    };

    // ============================================
    // TOAST NOTIFICATION SYSTEM
    // ============================================
    window.showToast = function(message, type = 'info', duration = 3000) {
        // Remove existing toasts
        document.querySelectorAll('.toast-notification').forEach(t => t.remove());
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle',
            loading: 'fa-spinner fa-spin'
        };
        
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.innerHTML = `
            <i class="fas ${icons[type] || icons.info}"></i>
            <span>${message}</span>
            ${type !== 'loading' ? '<button class="toast-close" onclick="this.parentElement.remove()">&times;</button>' : ''}
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove
        if (type !== 'loading' && duration > 0) {
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
        
        return toast;
    };
    
    // Hide loading toast
    window.hideLoadingToast = function() {
        document.querySelectorAll('.toast-notification.toast-loading').forEach(t => {
            t.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => t.remove(), 300);
        });
    };

    // ============================================
    // ENHANCED AJAX WRAPPER WITH LOADERS
    // ============================================
    window.fetchWithLoader = async function(url, options = {}) {
        const {
            loader = 'button', // 'page', 'button', 'toast', 'element', 'none'
            loaderElement = null,
            loaderMessage = 'Loading...',
            showErrorToast = true
        } = options;
        
        let button = null;
        let toast = null;
        
        // Show appropriate loader
        switch (loader) {
            case 'page':
                showPageLoader(loaderMessage);
                break;
            case 'button':
                button = loaderElement || document.activeElement;
                if (button && button.tagName === 'BUTTON') {
                    showButtonLoader(button, loaderMessage);
                }
                break;
            case 'toast':
                toast = showToast(loaderMessage, 'loading', 0);
                break;
            case 'element':
                if (loaderElement) {
                    showContentLoader(loaderElement, loaderMessage);
                }
                break;
        }
        
        try {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                }
            });
            
            const data = await response.json();
            
            // Hide loader
            switch (loader) {
                case 'page':
                    hidePageLoader();
                    break;
                case 'button':
                    if (button) hideButtonLoader(button);
                    break;
                case 'toast':
                    if (toast) toast.remove();
                    break;
            }
            
            if (!response.ok || data.success === false) {
                if (showErrorToast) {
                    showToast(data.error || 'An error occurred', 'error');
                }
                throw new Error(data.error || 'Request failed');
            }
            
            return data;
        } catch (error) {
            // Hide loader on error
            switch (loader) {
                case 'page':
                    hidePageLoader();
                    break;
                case 'button':
                    if (button) hideButtonLoader(button);
                    break;
                case 'toast':
                    if (toast) toast.remove();
                    break;
            }
            
            if (showErrorToast) {
                showToast(error.message || 'An error occurred', 'error');
            }
            throw error;
        }
    };

    // ============================================
    // TABLE LOADING STATE
    // ============================================
    window.showTableLoader = function(tableElement, colspan = 6) {
        const tbody = tableElement.querySelector('tbody');
        if (tbody) {
            tbody.innerHTML = `
                <tr class="table-loading-row">
                    <td colspan="${colspan}">
                        <div class="loading-spinner"></div>
                        <p style="margin-top: 10px; color: var(--gray-500);">Loading data...</p>
                    </td>
                </tr>
            `;
        }
    };

    // ============================================
    // CARD LOADING STATE
    // ============================================
    window.setCardLoading = function(cardElement, loading = true) {
        if (loading) {
            cardElement.classList.add('card-loading');
        } else {
            cardElement.classList.remove('card-loading');
        }
    };

    // ============================================
    // OVERLAY LOADER FOR SPECIFIC ELEMENTS
    // ============================================
    window.showOverlayLoader = function(element, message = 'Loading...') {
        if (!element) return;
        
        // Make element relative if not already positioned
        const position = getComputedStyle(element).position;
        if (position === 'static') {
            element.style.position = 'relative';
        }
        
        const overlay = document.createElement('div');
        overlay.className = 'overlay-loader';
        overlay.innerHTML = `
            <div class="loader-ring"></div>
            <div class="loader-text">${message}</div>
        `;
        element.appendChild(overlay);
        
        return overlay;
    };
    
    window.hideOverlayLoader = function(element) {
        if (!element) return;
        const overlay = element.querySelector('.overlay-loader');
        if (overlay) overlay.remove();
    };

    // ============================================
    // CSS INJECTION FOR DYNAMIC ELEMENTS
    // ============================================
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #e5e7eb;
            border-top-color: #6366f1;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .skip-link:focus {
            outline: 2px solid white;
            outline-offset: 2px;
        }
        
        /* Toast Notification Styles */
        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            z-index: 10000;
            animation: slideIn 0.3s ease;
            max-width: 400px;
        }
        
        .toast-notification i {
            font-size: 1.2rem;
        }
        
        .toast-notification span {
            flex: 1;
            font-size: 0.95rem;
        }
        
        .toast-close {
            background: none;
            border: none;
            font-size: 1.3rem;
            cursor: pointer;
            opacity: 0.7;
            padding: 0;
            margin-left: 10px;
        }
        
        .toast-close:hover {
            opacity: 1;
        }
        
        .toast-success {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
        }
        
        .toast-error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .toast-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .toast-info {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }
        
        .toast-loading {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
        }
        
        @media (max-width: 576px) {
            .toast-notification {
                left: 15px;
                right: 15px;
                bottom: 15px;
                max-width: none;
            }
        }
    `;
    document.head.appendChild(style);

})();
