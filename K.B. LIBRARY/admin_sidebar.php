<?php
$current_page = basename($_SERVER['PHP_SELF']);
$admin_user = get_logged_in_user();
?>
<aside class="sidebar">
    <div class="sidebar-header" style="padding: 16px 14px;">
        <img src="assets/images/logo.png" alt="K.B. Library" style="max-width: 100%; height: auto; max-height: 48px; object-fit: contain;">
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-heading">Management</div>
        
        <a href="admin_dashboard.php" class="nav-link <?php echo $current_page === 'admin_dashboard.php' ? 'active' : ''; ?>">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </span>
            <span>Dashboard</span>
        </a>

        <a href="admin_books.php" class="nav-link <?php echo $current_page === 'admin_books.php' ? 'active' : ''; ?>">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </span>
            <span>Books Catalog</span>
        </a>

        <a href="admin_authors.php" class="nav-link <?php echo $current_page === 'admin_authors.php' ? 'active' : ''; ?>">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            </span>
            <span>Authors</span>
        </a>

        <a href="admin_members.php" class="nav-link <?php echo $current_page === 'admin_members.php' ? 'active' : ''; ?>">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>
            <span>Members (CRUD)</span>
        </a>

        <a href="admin_transactions.php" class="nav-link <?php echo $current_page === 'admin_transactions.php' ? 'active' : ''; ?>">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </span>
            <span>Transactions</span>
        </a>

        <div class="sidebar-heading">Portal</div>

        <a href="user_dashboard.php" class="nav-link" target="_blank">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            </span>
            <span>View Member Portal</span>
        </a>

        <a href="logout.php" class="nav-link" style="color: #f87171; margin-top: auto;">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </span>
            <span>Sign Out</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-badge-card">
            <div class="admin-profile-info">
                <span class="admin-profile-name"><?php echo htmlspecialchars($admin_user['name'] ?? 'Admin'); ?></span>
                <span class="admin-profile-role">K.B. Library Administrator</span>
            </div>
            <div class="user-avatar" style="width: 32px; height: 32px; background: #1e293b; color: #38bdf8;">KB</div>
        </div>
    </div>
</aside>
