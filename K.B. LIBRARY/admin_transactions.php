<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_admin();

$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search_query = trim($_GET['q'] ?? '');

if (isset($_POST['action']) && $_POST['action'] === 'admin_return') {
    $tx_id = (int)($_POST['transaction_id'] ?? 0);
    if ($tx_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND type = 'rent' AND status = 'active'");
        $stmt->execute([$tx_id]);
        $tx = $stmt->fetch();

        if ($tx) {
            $pdo->beginTransaction();
            try {
                $up = $pdo->prepare("UPDATE transactions SET status = 'returned', return_date = ? WHERE id = ?");
                $up->execute([date('Y-m-d'), $tx_id]);

                $inc = $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id = ?");
                $inc->execute([$tx['book_id']]);

                $pdo->commit();
                set_flash('success', 'Book marked as returned. Available inventory copy restored.');
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('danger', 'Error updating return: ' . $e->getMessage());
            }
        }
    }
    header('Location: admin_transactions.php');
    exit;
}

$sql = "
    SELECT t.*, u.full_name AS member_name, u.email AS member_email, 
           b.title AS book_title, b.size_mb, b.cover_image, a.name AS author_name
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    JOIN books b ON t.book_id = b.id
    JOIN authors a ON b.author_id = a.id
    WHERE 1=1
";
$params = [];

if (!empty($type_filter)) {
    $sql .= " AND t.type = ?";
    $params[] = $type_filter;
}

if (!empty($status_filter)) {
    $sql .= " AND t.status = ?";
    $params[] = $status_filter;
}

if (!empty($search_query)) {
    $sql .= " AND (t.transaction_code LIKE ? OR u.full_name LIKE ? OR b.title LIKE ?)";
    $st = "%$search_query%";
    $params[] = $st;
    $params[] = $st;
    $params[] = $st;
}

$sql .= " ORDER BY t.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$flash_success = get_flash('success');
$flash_danger = get_flash('danger');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions &amp; Circulation Ledger - K.B. Library</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/admin_sidebar.php'; ?>

        <main class="main-content">
            <header class="topbar">
                <div class="topbar-title-section">
                    <h1>Circulation &amp; Transactions Ledger</h1>
                    <p>K.B. Library audit trail of book loans, returns, and digital purchases</p>
                </div>
            </header>

            <div class="content-body">
                <?php if ($flash_success): ?>
                    <div class="alert alert-success"><span><?php echo htmlspecialchars($flash_success); ?></span></div>
                <?php endif; ?>
                <?php if ($flash_danger): ?>
                    <div class="alert alert-danger"><span><?php echo htmlspecialchars($flash_danger); ?></span></div>
                <?php endif; ?>

                <div class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title">Transaction Ledger (<?php echo count($transactions); ?> records)</h2>
                    </div>

                    <div style="padding: 16px 24px 0 24px;">
                        <form method="GET" action="admin_transactions.php" class="filter-bar">
                            <div class="search-input-wrap">
                                <input type="text" name="q" class="form-input" placeholder="Search by transaction code, member, or book title..." value="<?php echo htmlspecialchars($search_query); ?>">
                            </div>
                            <div style="min-width: 140px;">
                                <select name="type" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Types</option>
                                    <option value="rent" <?php echo $type_filter === 'rent' ? 'selected' : ''; ?>>Rent</option>
                                    <option value="buy" <?php echo $type_filter === 'buy' ? 'selected' : ''; ?>>Sold / Buy</option>
                                </select>
                            </div>
                            <div style="min-width: 150px;">
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="returned" <?php echo $status_filter === 'returned' ? 'selected' : ''; ?>>Returned</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                            <?php if (!empty($search_query) || !empty($type_filter) || !empty($status_filter)): ?>
                                <a href="admin_transactions.php" class="btn btn-secondary btn-sm">Reset</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Transaction Code</th>
                                    <th>Member Name</th>
                                    <th>Book Details</th>
                                    <th>Author</th>
                                    <th>Size</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Issue Date</th>
                                    <th>Due / Return Date</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="11" style="text-align: center; padding: 36px; color: var(--text-muted);">
                                            No transaction records found matching the filter criteria.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $tx): ?>
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
                                            <td>
                                                <div style="font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($tx['member_name']); ?></div>
                                                <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($tx['member_email']); ?></div>
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
                                            <td><?php echo htmlspecialchars($tx['author_name']); ?></td>
                                            <td><strong><?php echo number_format($tx['size_mb'], 2); ?> MB</strong></td>
                                            <td>
                                                <?php if ($tx['type'] === 'rent'): ?>
                                                    <span class="badge badge-rent">Rental</span>
                                                <?php else: ?>
                                                    <span class="badge badge-sold">Purchased</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-weight: 700;">$<?php echo number_format($tx['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($tx['issue_date']); ?></td>
                                            <td>
                                                <?php if ($tx['type'] === 'rent'): ?>
                                                    <?php if ($tx['status'] === 'returned'): ?>
                                                        Returned: <?php echo htmlspecialchars($tx['return_date']); ?>
                                                    <?php else: ?>
                                                        Due: <strong><?php echo htmlspecialchars($tx['due_date']); ?></strong>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    Perpetual
                                                <?php endif; ?>
                                            </td>
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
                                            <td style="text-align: right;">
                                                <?php if ($tx['type'] === 'rent' && $tx['status'] === 'active'): ?>
                                                    <form method="POST" action="admin_transactions.php" onsubmit="return confirm('Confirm receipt of returned book?');" style="display: inline;">
                                                        <input type="hidden" name="action" value="admin_return">
                                                        <input type="hidden" name="transaction_id" value="<?php echo $tx['id']; ?>">
                                                        <button type="submit" class="btn btn-secondary btn-sm">Mark Returned</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="font-size: 11px; color: var(--text-light);">-</span>
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
