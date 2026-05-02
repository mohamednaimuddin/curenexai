<?php
/**
 * Super Admin Header Component
 */

if (!defined('ADMIN_PAGE')) {
    die('Direct access not allowed');
}

requireSuperAdmin();

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminRole = $_SESSION['admin_role'] ?? 'admin';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#0f0f23">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo $pageTitle ?? 'Dashboard'; ?> - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Bootstrap Grid Fallback */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -0.75rem;
            margin-left: -0.75rem;
        }
        .row > * {
            flex-shrink: 0;
            width: 100%;
            max-width: 100%;
            padding-right: 0.75rem;
            padding-left: 0.75rem;
        }
        .g-4 { --bs-gutter-x: 1.5rem; --bs-gutter-y: 1.5rem; }
        .g-4 > * { padding-right: calc(var(--bs-gutter-x) * .5); padding-left: calc(var(--bs-gutter-x) * .5); margin-top: var(--bs-gutter-y); }
        @media (min-width: 768px) { .col-md-6 { flex: 0 0 auto; width: 50%; } }
        @media (min-width: 992px) { .col-lg-3 { flex: 0 0 auto; width: 25%; } .col-lg-6 { flex: 0 0 auto; width: 50%; } .col-lg-8 { flex: 0 0 auto; width: 66.666667%; } .col-lg-4 { flex: 0 0 auto; width: 33.333333%; } }
        .d-flex { display: flex !important; }
        .justify-content-between { justify-content: space-between !important; }
        .align-items-center { align-items: center !important; }
        .align-items-start { align-items: flex-start !important; }
        .mb-0 { margin-bottom: 0 !important; }
        .mb-1 { margin-bottom: 0.25rem !important; }
        .mb-3 { margin-bottom: 1rem !important; }
        .mb-4 { margin-bottom: 1.5rem !important; }
        .mt-3 { margin-top: 1rem !important; }
        .me-2 { margin-right: 0.5rem !important; }
        .gap-3 { gap: 1rem !important; }
        .gap-5 { gap: 3rem !important; }
        .text-muted { color: #6c757d !important; }
        .text-danger { color: #dc3545 !important; }
        .text-primary { color: #0d6efd !important; }
        .text-center { text-align: center !important; }
        .small { font-size: .875em !important; }
        .table { width: 100%; margin-bottom: 1rem; color: #212529; vertical-align: top; border-color: #dee2e6; }
        .table > :not(caption) > * > * { padding: 0.5rem 0.5rem; border-bottom-width: 1px; }
        .table > thead { vertical-align: bottom; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        
        /* Dropdown styles */
        .dropdown { position: relative; }
        .dropdown-menu {
            display: none;
            position: absolute;
            z-index: 1000;
            min-width: 10rem;
            padding: 0.5rem 0;
            margin: 0;
            list-style: none;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0,0,0,.15);
            border-radius: 0.375rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.175);
        }
        .dropdown-menu.show { display: block; }
        .dropdown-menu-end { right: 0; left: auto; }
        .dropdown-item {
            display: block;
            width: 100%;
            padding: 0.5rem 1rem;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            text-decoration: none;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
        }
        .dropdown-item:hover { color: #1e2125; background-color: #e9ecef; }
        .dropdown-header { display: block; padding: 0.5rem 1rem; margin-bottom: 0; font-size: .875rem; color: #6c757d; }
        .dropdown-divider { height: 0; margin: 0.5rem 0; overflow: hidden; border-top: 1px solid rgba(0,0,0,.15); }
        ul, ol { list-style: none; padding-left: 0; margin: 0; }
        
        :root {
            --admin-primary: #0f0f23;
            --admin-secondary: #1a1a3e;
            --admin-accent: #2d2d5a;
            --admin-highlight: #6366f1;
            --admin-highlight-hover: #818cf8;
            --admin-success: #10b981;
            --admin-warning: #f59e0b;
            --admin-danger: #ef4444;
            --admin-info: #3b82f6;
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
            --header-height: 65px;
            --safe-area-top: env(safe-area-inset-top, 0px);
            --safe-area-bottom: env(safe-area-inset-bottom, 0px);
        }
        
        * {
            font-family: 'Inter', sans-serif;
            box-sizing: border-box;
        }
        
        body {
            background-color: #f0f2f5;
            overflow-x: hidden;
            padding-top: var(--safe-area-top);
            padding-bottom: var(--safe-area-bottom);
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
        }
        
        .sidebar-brand {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-brand h4 {
            color: white;
            margin: 0;
            font-weight: 700;
        }
        
        .sidebar-brand small {
            color: var(--admin-highlight);
            font-size: 0.75rem;
        }
        
        .sidebar-nav {
            padding: 15px 0;
        }
        
        .nav-section {
            padding: 10px 20px 5px;
            color: rgba(255,255,255,0.4);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .sidebar-nav .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .sidebar-nav .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.05);
            border-left-color: var(--admin-highlight);
        }
        
        .sidebar-nav .nav-link.active {
            color: white;
            background: rgba(233, 69, 96, 0.2);
            border-left-color: var(--admin-highlight);
        }
        
        .sidebar-nav .nav-link i {
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        /* Top Header */
        .top-header {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .header-search {
            max-width: 400px;
            flex: 1;
        }
        
        .header-search .form-control {
            border-radius: 25px;
            padding-left: 40px;
            background: #f0f2f5;
            border: none;
        }
        
        .header-search .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-actions .btn-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f2f5;
            border: none;
            color: #495057;
            position: relative;
        }
        
        .header-actions .btn-icon:hover {
            background: #e9ecef;
        }
        
        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 18px;
            height: 18px;
            background: var(--admin-highlight);
            border-radius: 50%;
            font-size: 0.65rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .admin-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 15px 5px 5px;
            border-radius: 25px;
            transition: background 0.3s;
        }
        
        .admin-profile:hover {
            background: #f0f2f5;
        }
        
        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--admin-highlight), #ff6b6b);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .admin-info {
            text-align: left;
        }
        
        .admin-info .name {
            font-weight: 600;
            font-size: 0.9rem;
            color: #212529;
        }
        
        .admin-info .role {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        /* Content Area */
        .content-area {
            padding: 30px;
        }
        
        /* Cards */
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .stat-card .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #212529;
        }
        
        .stat-card .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .stat-card .stat-change {
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .stat-change.positive {
            color: var(--admin-success);
        }
        
        .stat-change.negative {
            color: var(--admin-danger);
        }
        
        /* Tables */
        .data-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .data-table .table-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .data-table .table-header h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .data-table .table {
            margin: 0;
        }
        
        .data-table .table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            color: #6c757d;
            border: none;
            padding: 15px 20px;
        }
        
        .data-table .table td {
            padding: 15px 20px;
            vertical-align: middle;
            border-color: #f0f2f5;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-active {
            background: rgba(0, 208, 156, 0.15);
            color: var(--admin-success);
        }
        
        .status-inactive {
            background: rgba(108, 117, 125, 0.15);
            color: #6c757d;
        }
        
        .status-suspended {
            background: rgba(255, 71, 87, 0.15);
            color: var(--admin-danger);
        }
        
        /* Action Buttons */
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            transition: all 0.3s ease;
        }
        
        .action-btn.view {
            background: rgba(55, 66, 250, 0.1);
            color: var(--admin-info);
        }
        
        .action-btn.edit {
            background: rgba(245, 158, 11, 0.1);
            color: var(--admin-warning);
        }
        
        .action-btn.delete {
            background: rgba(239, 68, 68, 0.1);
            color: var(--admin-danger);
        }
        
        .action-btn:hover {
            transform: scale(1.1);
        }
        
        /* Mobile Toggle */
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #212529;
            padding: 8px;
            border-radius: 8px;
            transition: background 0.3s;
        }
        
        .sidebar-toggle:hover {
            background: #f0f2f5;
        }
        
        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        /* ===============================================
           SUPER RESPONSIVE STYLES - All Devices
           =============================================== */
        
        /* Large Desktop (1400px+) */
        @media (min-width: 1400px) {
            .content-area {
                padding: 35px 40px;
            }
            
            .stat-card .stat-value {
                font-size: 2.2rem;
            }
        }
        
        /* Desktop (992px - 1399px) */
        @media (max-width: 1399px) {
            .content-area {
                padding: 25px;
            }
        }
        
        /* Tablet Landscape & Small Desktop (992px - 1199px) */
        @media (max-width: 1199px) {
            :root {
                --sidebar-width: 240px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-card .stat-value {
                font-size: 1.75rem;
            }
        }
        
        /* Tablet (768px - 991px) */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1001;
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .top-header {
                padding: 12px 20px;
            }
            
            .header-search {
                max-width: 250px;
            }
            
            .content-area {
                padding: 20px;
            }
            
            .data-table .table-header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .data-table .table th,
            .data-table .table td {
                padding: 12px 15px;
            }
        }
        
        /* Mobile Landscape & Small Tablet (576px - 767px) */
        @media (max-width: 767px) {
            .top-header {
                padding: 10px 15px;
            }
            
            .header-search {
                max-width: 200px;
            }
            
            .header-search .form-control {
                padding: 8px 15px 8px 35px;
                font-size: 0.9rem;
            }
            
            .header-actions {
                gap: 8px;
            }
            
            .header-actions .btn-icon {
                width: 36px;
                height: 36px;
            }
            
            .admin-profile {
                padding: 5px 10px 5px 5px;
            }
            
            .admin-info {
                display: none;
            }
            
            .admin-avatar {
                width: 36px;
                height: 36px;
                font-size: 0.85rem;
            }
            
            .content-area {
                padding: 15px;
            }
            
            .stat-card {
                padding: 18px;
            }
            
            .stat-card .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }
            
            .stat-card .stat-value {
                font-size: 1.5rem;
            }
            
            .stat-card .stat-label {
                font-size: 0.85rem;
            }
            
            /* Table Responsive */
            .data-table {
                border-radius: 12px;
            }
            
            .data-table .table-header {
                padding: 12px 15px;
            }
            
            .data-table .table-header h5 {
                font-size: 1rem;
            }
            
            .data-table .table th,
            .data-table .table td {
                padding: 10px 12px;
                font-size: 0.85rem;
            }
            
            .status-badge {
                padding: 4px 10px;
                font-size: 0.7rem;
            }
            
            .action-btn {
                width: 28px;
                height: 28px;
            }
            
            /* Cards in mobile */
            .data-table.mobile-cards .table,
            .data-table.mobile-cards .table thead {
                display: none;
            }
            
            /* Chart container */
            .chart-container {
                padding: 15px;
            }
        }
        
        /* Mobile Portrait (under 576px) */
        @media (max-width: 575px) {
            .top-header {
                padding: 10px 12px;
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .header-search {
                order: 3;
                max-width: 100%;
                flex: 1 1 100%;
                margin-top: 5px;
            }
            
            .header-search .form-control {
                padding: 10px 15px 10px 38px;
            }
            
            .content-area {
                padding: 12px;
            }
            
            .d-flex.justify-content-between.align-items-center.mb-4 {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 12px;
            }
            
            .d-flex.justify-content-between.align-items-center.mb-4 > div:last-child {
                width: 100%;
            }
            
            .d-flex.justify-content-between.align-items-center.mb-4 .btn {
                width: 100%;
            }
            
            h4.mb-1 {
                font-size: 1.25rem;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-card .stat-icon {
                width: 45px;
                height: 45px;
                border-radius: 12px;
            }
            
            .stat-card .stat-value {
                font-size: 1.35rem;
            }
            
            /* Stacked filter form */
            .data-table .p-3 form.row {
                flex-direction: column;
            }
            
            .data-table .p-3 form.row > div {
                width: 100% !important;
                max-width: 100% !important;
                flex: 0 0 100% !important;
            }
            
            /* Better table display on mobile */
            .table-responsive {
                border-radius: 0;
            }
            
            .data-table .table th {
                font-size: 0.75rem;
                padding: 8px 10px;
                white-space: nowrap;
            }
            
            .data-table .table td {
                font-size: 0.8rem;
                padding: 10px;
            }
            
            /* Cards grid */
            .row.g-4 {
                --bs-gutter-x: 0.75rem;
                --bs-gutter-y: 0.75rem;
            }
            
            /* Modals */
            .modal-dialog {
                margin: 10px;
            }
            
            .modal-content {
                border-radius: 16px;
            }
            
            .modal-header, .modal-body, .modal-footer {
                padding: 15px;
            }
            
            /* Alerts */
            .alert {
                padding: 12px 15px;
                font-size: 0.9rem;
                border-radius: 10px;
            }
            
            /* Buttons */
            .btn {
                padding: 10px 16px;
                font-size: 0.9rem;
            }
            
            .btn-lg {
                padding: 12px 20px;
            }
            
            /* Form controls */
            .form-control, .form-select {
                font-size: 16px; /* Prevents iOS zoom */
                padding: 10px 14px;
            }
            
            /* Pagination */
            .pagination {
                flex-wrap: wrap;
                justify-content: center;
                gap: 5px;
            }
            
            .pagination .page-link {
                padding: 8px 12px;
                font-size: 0.85rem;
            }
        }
        
        /* Extra Small Mobile (under 380px) */
        @media (max-width: 379px) {
            .top-header {
                padding: 8px 10px;
            }
            
            .sidebar-toggle {
                font-size: 1.3rem;
            }
            
            .content-area {
                padding: 10px;
            }
            
            .stat-card {
                padding: 12px;
            }
            
            .stat-card .stat-value {
                font-size: 1.2rem;
            }
            
            .stat-card .stat-label {
                font-size: 0.8rem;
            }
            
            .stat-card .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            h4.mb-1 {
                font-size: 1.1rem;
            }
            
            .data-table .table-header h5 {
                font-size: 0.9rem;
            }
        }
        
        /* Height-based adjustments for landscape phones */
        @media (max-height: 500px) and (orientation: landscape) {
            .sidebar {
                overflow-y: auto;
            }
            
            .sidebar-brand {
                padding: 12px 15px;
            }
            
            .sidebar-nav .nav-link {
                padding: 10px 15px;
            }
            
            .content-area {
                padding: 10px 15px;
            }
        }
        
        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .action-btn {
                min-width: 44px;
                min-height: 44px;
            }
            
            .sidebar-nav .nav-link {
                min-height: 48px;
            }
            
            .btn-icon {
                min-width: 44px;
                min-height: 44px;
            }
            
            .dropdown-item {
                padding: 12px 16px;
            }
        }
        
        /* Print Styles */
        @media print {
            .sidebar, .top-header, .sidebar-toggle {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
            }
            
            .content-area {
                padding: 0 !important;
            }
        }
        
        /* Alerts */
        .alert-modern {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
        }
        
        /* Charts Container */
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .chart-container h6 {
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Smooth transitions */
        .sidebar, .main-content, .top-header {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h4><i class="bi bi-shield-check"></i> Admin</h4>
            <small>Control Center</small>
        </div>
        
        <div class="sidebar-nav">
            <div class="nav-section">Main</div>
            <a href="dashboard.php" class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            
            <div class="nav-section">Management</div>
            <a href="doctors.php" class="nav-link <?php echo $currentPage === 'doctors' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Doctors
            </a>
            <a href="patients.php" class="nav-link <?php echo $currentPage === 'patients' ? 'active' : ''; ?>">
                <i class="bi bi-person-badge"></i> Patients
            </a>
            <a href="consultations.php" class="nav-link <?php echo $currentPage === 'consultations' ? 'active' : ''; ?>">
                <i class="bi bi-clipboard2-pulse"></i> Consultations
            </a>
            <a href="prescriptions.php" class="nav-link <?php echo $currentPage === 'prescriptions' ? 'active' : ''; ?>">
                <i class="bi bi-file-medical"></i> Prescriptions
            </a>
            
            <div class="nav-section">Monitoring</div>
            <a href="activity_logs.php" class="nav-link <?php echo $currentPage === 'activity_logs' ? 'active' : ''; ?>">
                <i class="bi bi-journal-text"></i> Activity Logs
            </a>
            <a href="doctor_logs.php" class="nav-link <?php echo $currentPage === 'doctor_logs' ? 'active' : ''; ?>">
                <i class="bi bi-person-lines-fill"></i> Doctor Logs
            </a>
            
            <div class="nav-section">System</div>
            <a href="announcements.php" class="nav-link <?php echo $currentPage === 'announcements' ? 'active' : ''; ?>">
                <i class="bi bi-megaphone"></i> Announcements
            </a>
            <a href="settings.php" class="nav-link <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                <i class="bi bi-gear"></i> Settings
            </a>
            <a href="admins.php" class="nav-link <?php echo $currentPage === 'admins' ? 'active' : ''; ?>">
                <i class="bi bi-person-gear"></i> Admin Users
            </a>
            
            <div class="nav-section">Account</div>
            <a href="logout.php" class="nav-link text-danger">
                <i class="bi bi-box-arrow-left"></i> Logout
            </a>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="d-flex align-items-center gap-3">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i>
                </button>
                <div class="header-search position-relative">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" class="form-control" placeholder="Search...">
                </div>
            </div>
            
            <div class="header-actions">
                <!-- Notifications Dropdown -->
                <div class="dropdown">
                    <button class="btn-icon" title="Notifications" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell"></i>
                        <span class="notification-badge" id="notificationCount">0</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end notification-dropdown" style="width: 320px; max-height: 400px; overflow-y: auto;">
                        <li class="dropdown-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-bell me-2"></i>Notifications</span>
                            <a href="#" class="text-primary small" onclick="markAllRead()">Mark all read</a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <div id="notificationList">
                            <li class="text-center text-muted py-3">
                                <i class="bi bi-bell-slash"></i> No new notifications
                            </li>
                        </div>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center text-primary" href="activity_logs.php">View All Activity</a></li>
                    </ul>
                </div>
                
                <!-- Messages Dropdown -->
                <div class="dropdown">
                    <button class="btn-icon" title="Messages" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-envelope"></i>
                        <span class="notification-badge" id="messageCount">0</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end message-dropdown" style="width: 320px; max-height: 400px; overflow-y: auto;">
                        <li class="dropdown-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-envelope me-2"></i>Messages</span>
                            <a href="announcements.php" class="text-primary small">Announcements</a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <div id="messageList">
                            <li class="text-center text-muted py-3">
                                <i class="bi bi-envelope-slash"></i> No new messages
                            </li>
                        </div>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center text-primary" href="announcements.php">Manage Announcements</a></li>
                    </ul>
                </div>
                
                <div class="dropdown">
                    <div class="admin-profile" data-bs-toggle="dropdown">
                        <div class="admin-avatar">
                            <?php echo strtoupper(substr($adminName, 0, 1)); ?>
                        </div>
                        <div class="admin-info">
                            <div class="name"><?php echo htmlspecialchars($adminName); ?></div>
                            <div class="role"><?php echo ucfirst($adminRole); ?></div>
                        </div>
                        <i class="bi bi-chevron-down ms-2"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Content Area -->
        <div class="content-area">
