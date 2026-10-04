<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_admin();

$total_books = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$total_authors = $pdo->query("SELECT COUNT(*) FROM authors")->fetchColumn();
$total_members = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$active_rentals = $pdo->query("SELECT COUNT(*) FROM transactions WHERE type = 'rent' AND status = 'active'")->fetchColumn();
$total_revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status IN ('active', 'returned', 'completed')")->fetchColumn();

$recent_tx_stmt = $pdo->query("
    SELECT t.*, u.full_name AS member_name, b.title AS book_title, b.cover_image, b.size_mb
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    JOIN books b ON t.book_id = b.id
    ORDER BY t.created_at DESC
    LIMIT 6
");
$recent_transactions = $recent_tx_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - K.B. Library Management</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/admin_sidebar.php'; ?>

        <main class="main-content">
            <header class="topbar">
                <div class="topbar-title-section">
                    <h1>Executive Dashboard</h1>
                    <p>K.B. Library operational and circulation metrics</p>
                </div>
                <div class="topbar-actions">
                    <a href="admin_books.php?action=create" class="btn btn-primary btn-sm">+ New Book</a>
                    <a href="admin_members.php?action=create" class="btn btn-secondary btn-sm">+ New Member</a>
                </div>
            </header>

            <div class="content-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Titles</div>
                        <div class="stat-value" style="color: var(--primary);"><?php echo number_format($total_books); ?></div>
                        <div class="stat-meta">Active catalog titles</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Registered Members</div>
                        <div class="stat-value"><?php echo number_format($total_members); ?></div>
                        <div class="stat-meta">Verified active patrons</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Active Borrows</div>
                        <div class="stat-value" style="color: var(--rent-color);"><?php echo number_format($active_rentals); ?></div>
                        <div class="stat-meta">Circulating rental copies</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Circulation Revenue</div>
                        <div class="stat-value" style="color: var(--sold-color);">$<?php echo number_format($total_revenue, 2); ?></div>
                        <div class="stat-meta">Gross sales and rental fees</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Catalog Authors</div>
                        <div class="stat-value"><?php echo number_format($total_authors); ?></div>
                        <div class="stat-meta">Contributing creators</div>
                    </div>
                </div>

                <div class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title">Recent Transactions &amp; Circulation</h2>
                        <a href="admin_transactions.php" class="btn btn-secondary btn-sm">View Complete Ledger</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Transaction Ref</th>
                                    <th>Member Name</th>
                                    <th>Book Title</th>
                                    <th>Size</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_transactions)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 28px; color: var(--text-muted);">
                                            No transactions recorded yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_transactions as $tx): ?>
                                        <?php 
                                        $cover_path = 'assets/covers/' . ($tx['cover_image'] ? $tx['cover_image'] : 'default_cover.png');
                                        if (!file_exists(__DIR__ . '/' . $cover_path)) {
                                            $cover_path = 'assets/covers/default_cover.png';
                                        }
                                        ?>
                                        <tr>
                                            <td style="font-family: monospace; font-weight: 700; color: var(--primary);">
                                                <?php echo htmlspecialchars($tx['transaction_code']); ?>
                                            </td>
                                            <td style="font-weight: 600;">
                                                <?php echo htmlspecialchars($tx['member_name']); ?>
                                            </td>
                                            <td>
                                                <div class="table-avatar-cell">
                                                    <div class="table-book-thumb">
                                                        <img src="<?php echo htmlspecialchars($cover_path); ?>" alt="">
                                                    </div>
                                                    <div>
                                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($tx['book_title']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><strong><?php echo number_format($tx['size_mb'], 2); ?> MB</strong></td>
                                            <td>
                                                <?php if ($tx['type'] === 'rent'): ?>
                                                    <span class="badge badge-rent">Rent</span>
                                                <?php else: ?>
                                                    <span class="badge badge-sold">Sold</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-weight: 700;">$<?php echo number_format($tx['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($tx['issue_date']); ?></td>
                                            <td>
                                                <?php if ($tx['status'] === 'active'): ?>
                                                    <span class="badge badge-active">Active</span>
                                                <?php elseif ($tx['status'] === 'returned'): ?>
                                                    <span class="badge badge-returned">Returned</span>
                                                <?php elseif ($tx['status'] === 'completed'): ?>
                                                    <span class="badge badge-completed">Completed</span>
                                                <?php else: ?>
                                                    <span class="badge"><?php echo htmlspecialchars($tx['status']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
