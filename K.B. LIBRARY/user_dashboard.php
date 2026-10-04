<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_login();
if (is_admin()) {
    header('Location: admin_dashboard.php');
    exit;
}

$user = get_logged_in_user();
$active_tab = $_GET['tab'] ?? 'catalog';
$search_query = trim($_GET['q'] ?? '');
$category_filter = trim($_GET['category'] ?? '');

$prof_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$prof_stmt->execute([$user['id']]);
$profile = $prof_stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $new_code = $_POST['new_code'] ?? '';
    $confirm_code = $_POST['confirm_code'] ?? '';

    if (empty($full_name) || empty($email)) {
        set_flash('danger', 'Full name and email are required fields.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('danger', 'Please enter a valid email address.');
    } else {
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->execute([$email, $user['id']]);
        if ($chk->fetch()) {
            set_flash('danger', 'This email address is already in use by another account.');
        } else {
            if (!empty($new_code)) {
                if ($new_code !== $confirm_code) {
                    set_flash('danger', 'New passcode and confirmation do not match.');
                    header('Location: user_dashboard.php?tab=profile');
                    exit;
                }
                $hashed = password_hash($new_code, PASSWORD_DEFAULT);
                $up = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
                $up->execute([$full_name, $email, $phone, $hashed, $user['id']]);
            } else {
                $up = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
                $up->execute([$full_name, $email, $phone, $user['id']]);
            }

            $_SESSION['user_name'] = $full_name;
            $_SESSION['user_email'] = $email;
            set_flash('success', 'User profile details and passcode successfully updated.');
            header('Location: user_dashboard.php?tab=profile');
            exit;
        }
    }
    header('Location: user_dashboard.php?tab=profile');
    exit;
}

$categories_stmt = $pdo->query("SELECT DISTINCT category FROM books ORDER BY category ASC");
$all_categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

$sql = "SELECT b.*, a.name AS author_name 
        FROM books b 
        JOIN authors a ON b.author_id = a.id 
        WHERE 1=1";
$params = [];

if (!empty($search_query)) {
    $sql .= " AND (b.title LIKE ? OR a.name LIKE ? OR b.isbn LIKE ?)";
    $term = "%$search_query%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($category_filter)) {
    $sql .= " AND b.category = ?";
    $params[] = $category_filter;
}

$sql .= " ORDER BY b.title ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

$user_stats_stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN type = 'rent' AND status = 'active' THEN 1 ELSE 0 END) AS active_rents,
        SUM(CASE WHEN type = 'rent' AND status = 'returned' THEN 1 ELSE 0 END) AS returned_rents,
        SUM(CASE WHEN type = 'buy' THEN 1 ELSE 0 END) AS total_bought,
        COUNT(id) AS total_tx
    FROM transactions 
    WHERE user_id = ?
");
$user_stats_stmt->execute([$user['id']]);
$user_stats = $user_stats_stmt->fetch();

$my_tx_stmt = $pdo->prepare("
    SELECT t.*, b.title AS book_title, b.cover_image, b.size_mb, a.name AS author_name 
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    JOIN authors a ON b.author_id = a.id
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC
");
$my_tx_stmt->execute([$user['id']]);
$my_transactions = $my_tx_stmt->fetchAll();

$flash_success = get_flash('success');
$flash_danger = get_flash('danger');
$flash_warning = get_flash('warning');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - K.B. Library</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="topbar">
        <div class="topbar-title-section" style="display: flex; align-items: center; gap: 14px;">
            <img src="assets/images/logo.png" alt="K.B. Library" style="max-height: 44px; width: auto; object-fit: contain;">
            <div>
                <h1 style="font-size: 17px;">K.B. Library Portal</h1>
                <p>Welcome back, <?php echo htmlspecialchars($user['name']); ?></p>
            </div>
        </div>
        <div class="topbar-actions">
            <div class="user-profile-badge">
                <div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                <div style="font-size: 12px;">
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="color: var(--text-muted); font-size: 11px;">Member</div>
                </div>
            </div>
            <a href="logout.php" class="btn btn-secondary btn-sm">Sign Out</a>
        </div>
    </header>

    <div class="content-body">
        <?php if ($flash_success): ?>
            <div class="alert alert-success"><span><?php echo htmlspecialchars($flash_success); ?></span></div>
        <?php endif; ?>
        <?php if ($flash_danger): ?>
            <div class="alert alert-danger"><span><?php echo htmlspecialchars($flash_danger); ?></span></div>
        <?php endif; ?>
        <?php if ($flash_warning): ?>
            <div class="alert alert-warning"><span><?php echo htmlspecialchars($flash_warning); ?></span></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Active Rentals</div>
                <div class="stat-value" style="color: var(--rent-color);"><?php echo (int)($user_stats['active_rents'] ?? 0); ?></div>
                <div class="stat-meta">Books currently borrowed</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Purchased Books</div>
                <div class="stat-value" style="color: var(--sold-color);"><?php echo (int)($user_stats['total_bought'] ?? 0); ?></div>
                <div class="stat-meta">Lifetime digital copies owned</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Books Returned</div>
                <div class="stat-value"><?php echo (int)($user_stats['returned_rents'] ?? 0); ?></div>
                <div class="stat-meta">Completed rental returns</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Transactions</div>
                <div class="stat-value"><?php echo (int)($user_stats['total_tx'] ?? 0); ?></div>
                <div class="stat-meta">Total library interactions</div>
            </div>
        </div>

        <nav class="user-nav-tabs">
            <a href="user_dashboard.php?tab=catalog" class="user-tab-btn <?php echo $active_tab === 'catalog' ? 'active' : ''; ?>">
                Browse Book Catalog
            </a>
            <a href="user_dashboard.php?tab=mybooks" class="user-tab-btn <?php echo $active_tab === 'mybooks' ? 'active' : ''; ?>">
                My Rented &amp; Purchased Books (<?php echo count($my_transactions); ?>)
            </a>
            <a href="user_dashboard.php?tab=profile" class="user-tab-btn <?php echo $active_tab === 'profile' ? 'active' : ''; ?>">
                Profile &amp; Change Code
            </a>
        </nav>

        <?php if ($active_tab === 'catalog'): ?>
            <form method="GET" action="user_dashboard.php" class="filter-bar">
                <input type="hidden" name="tab" value="catalog">
                <div class="search-input-wrap">
                    <input type="text" name="q" class="form-input" placeholder="Search by title, author name, or ISBN..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div style="min-width: 180px;">
                    <select name="category" class="form-select" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($all_categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <?php if (!empty($search_query) || !empty($category_filter)): ?>
                    <a href="user_dashboard.php?tab=catalog" class="btn btn-secondary btn-sm">Reset</a>
                <?php endif; ?>
            </form>

            <?php if (empty($books)): ?>
                <div class="panel-card">
                    <div class="empty-state">
                        <h3>No books match your criteria</h3>
                        <p style="margin-top: 6px;">Try adjusting your search terms or clearing the filter.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="book-grid">
                    <?php foreach ($books as $book): ?>
                        <?php 
                        $cover_path = 'assets/covers/' . ($book['cover_image'] ? $book['cover_image'] : 'default_cover.png');
                        if (!file_exists(__DIR__ . '/' . $cover_path)) {
                            $cover_path = 'assets/covers/default_cover.png';
                        }
                        $is_sold_out = $book['available_copies'] <= 0;
                        ?>
                        <div class="book-card">
                            <div class="book-cover-wrap">
                                <img src="<?php echo htmlspecialchars($cover_path); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>">
                                <span class="book-tag-size"><?php echo number_format($book['size_mb'], 2); ?> MB</span>
                                <span class="book-tag-category"><?php echo htmlspecialchars($book['category']); ?></span>
                            </div>

                            <div class="book-details">
                                <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <div class="book-author">By <strong><?php echo htmlspecialchars($book['author_name']); ?></strong></div>

                                <div class="book-specs">
                                    <div class="spec-item">
                                        <span class="spec-label">Rental Price</span>
                                        <span class="spec-val" style="color: var(--rent-color);">$<?php echo number_format($book['rent_price'], 2); ?></span>
                                    </div>
                                    <div class="spec-item">
                                        <span class="spec-label">Buy Price</span>
                                        <span class="spec-val" style="color: var(--sold-color);">$<?php echo number_format($book['buy_price'], 2); ?></span>
                                    </div>
                                    <div class="spec-item">
                                        <span class="spec-label">File Size</span>
                                        <span class="spec-val"><?php echo number_format($book['size_mb'], 2); ?> MB</span>
                                    </div>
                                </div>

                                <div class="book-actions">
                                    <?php if ($is_sold_out): ?>
                                        <button type="button" class="btn btn-secondary btn-sm btn-disabled" disabled>Sold Out</button>
                                        <button type="button" class="btn btn-secondary btn-sm btn-disabled" disabled>Unavailable</button>
                                    <?php else: ?>
                                        <form method="POST" action="process_action.php" onsubmit="return confirm('Rent this book for $<?php echo number_format($book['rent_price'], 2); ?> for 14 days?');">
                                            <input type="hidden" name="action" value="rent">
                                            <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                            <button type="submit" class="btn btn-rent btn-sm btn-block">Rent Book</button>
                                        </form>

                                        <form method="POST" action="process_action.php" onsubmit="return confirm('Buy permanent digital copy of this book for $<?php echo number_format($book['buy_price'], 2); ?>?');">
                                            <input type="hidden" name="action" value="buy">
                                            <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                            <button type="submit" class="btn btn-sold btn-sm btn-block">Buy Now</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($active_tab === 'profile'): ?>
            <div class="panel-card" style="max-width: 640px;">
                <div class="panel-header">
                    <h2 class="panel-title">User Defined Profile &amp; Change Code</h2>
                </div>
                <div class="panel-body">
                    <form method="POST" action="user_dashboard.php?tab=profile">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-group">
                            <label class="form-label" for="full_name">Full Name *</label>
                            <input type="text" name="full_name" id="full_name" class="form-input" required 
                                   value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">Email Address *</label>
                            <input type="email" name="email" id="email" class="form-input" required 
                                   value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="phone">Phone Number</label>
                            <input type="text" name="phone" id="phone" class="form-input" 
                                   value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" placeholder="e.g. +1 555-0199">
                        </div>

                        <div style="margin: 20px 0; border-top: 1px dashed var(--border-color); padding-top: 16px;">
                            <h3 style="font-size: 14px; font-weight: 700; margin-bottom: 12px; color: var(--text-main);">Change Passcode / Password</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="new_code">New Code / Password</label>
                                    <input type="password" name="new_code" id="new_code" class="form-input" placeholder="Leave blank to keep current">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="confirm_code">Confirm New Code</label>
                                    <input type="password" name="confirm_code" id="confirm_code" class="form-input" placeholder="Confirm new passcode">
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">Save Changes &amp; Update Code</button>
                        </div>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <div class="panel-card">
                <div class="panel-header">
                    <h2 class="panel-title">My Book Activity &amp; Records</h2>
                    <span style="font-size: 13px; color: var(--text-muted);"><?php echo count($my_transactions); ?> record(s)</span>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Transaction Code</th>
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
                            <?php if (empty($my_transactions)): ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                        You haven't rented or purchased any books yet. <a href="user_dashboard.php?tab=catalog">Browse our catalog</a> to get started.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($my_transactions as $row): ?>
                                    <?php 
                                    $cover_path = 'assets/covers/' . ($row['cover_image'] ? $row['cover_image'] : 'default_cover.png');
                                    if (!file_exists(__DIR__ . '/' . $cover_path)) {
                                        $cover_path = 'assets/covers/default_cover.png';
                                    }
                                    ?>
                                    <tr>
                                        <td style="font-family: monospace; font-weight: 700; color: var(--primary);"><?php echo htmlspecialchars($row['transaction_code']); ?></td>
                                        <td>
                                            <div class="table-avatar-cell">
                                                <div class="table-book-thumb">
                                                    <img src="<?php echo htmlspecialchars($cover_path); ?>" alt="">
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($row['book_title']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['author_name']); ?></td>
                                        <td><strong><?php echo number_format($row['size_mb'], 2); ?> MB</strong></td>
                                        <td>
                                            <?php if ($row['type'] === 'rent'): ?>
                                                <span class="badge badge-rent">Rental</span>
                                            <?php else: ?>
                                                <span class="badge badge-sold">Purchased</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-weight: 700;">$<?php echo number_format($row['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($row['issue_date']); ?></td>
                                        <td>
                                            <?php if ($row['type'] === 'rent'): ?>
                                                <?php if ($row['status'] === 'returned'): ?>
                                                    Returned on <?php echo htmlspecialchars($row['return_date']); ?>
                                                <?php else: ?>
                                                    Due: <strong><?php echo htmlspecialchars($row['due_date']); ?></strong>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                Lifetime
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['status'] === 'active'): ?>
                                                <span class="badge badge-active">Active</span>
                                            <?php elseif ($row['status'] === 'returned'): ?>
                                                <span class="badge badge-returned">Returned</span>
                                            <?php elseif ($row['status'] === 'completed'): ?>
                                                <span class="badge badge-completed">Completed</span>
                                            <?php else: ?>
                                                <span class="badge"><?php echo htmlspecialchars($row['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <?php if ($row['type'] === 'rent' && $row['status'] === 'active'): ?>
                                                <form method="POST" action="process_action.php" onsubmit="return confirm('Return this book now?');" style="display: inline;">
                                                    <input type="hidden" name="action" value="return">
                                                    <input type="hidden" name="transaction_id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm">Return Book</button>
                                                </form>
                                            <?php elseif ($row['type'] === 'buy'): ?>
                                                <span style="font-size: 11px; color: var(--success); font-weight: 600;">Owned</span>
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
        <?php endif; ?>
    </div>
</body>
</html>
