<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_admin();

$action = $_GET['action'] ?? 'list';
$member_id = (int)($_GET['id'] ?? 0);
$edit_member = null;
$error = '';
$search_query = trim($_GET['q'] ?? '');

if ($action === 'delete' && $member_id > 0) {
    $active_rent_check = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND type = 'rent' AND status = 'active'");
    $active_rent_check->execute([$member_id]);
    $has_active_rents = $active_rent_check->fetchColumn();

    if ($has_active_rents > 0) {
        set_flash('danger', 'Cannot delete member: This member currently has unreturned books on loan.');
    } else {
        $del_stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
        $del_stmt->execute([$member_id]);
        set_flash('success', 'Member record successfully deleted.');
    }
    header('Location: admin_members.php');
    exit;
}

if ($action === 'edit' && $member_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'user'");
    $stmt->execute([$member_id]);
    $edit_member = $stmt->fetch();
    if (!$edit_member) {
        set_flash('danger', 'Member not found.');
        header('Location: admin_members.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? 'create';
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $password = $_POST['password'] ?? '';

    if (empty($full_name) || empty($email)) {
        $error = 'Full name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        if ($form_action === 'update') {
            $target_id = (int)($_POST['member_id'] ?? 0);
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $target_id]);
            if ($check->fetch()) {
                $error = 'Another user with this email already exists.';
            } else {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $up = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, status = ?, password = ? WHERE id = ? AND role = 'user'");
                    $up->execute([$full_name, $email, $phone, $status, $hash, $target_id]);
                } else {
                    $up = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, status = ? WHERE id = ? AND role = 'user'");
                    $up->execute([$full_name, $email, $phone, $status, $target_id]);
                }
                set_flash('success', 'Member profile updated successfully.');
                header('Location: admin_members.php');
                exit;
            }
        } else {
            if (empty($password)) {
                $error = 'A password is required for new accounts.';
            } else {
                $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $check->execute([$email]);
                if ($check->fetch()) {
                    $error = 'An account with this email already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'user', ?)");
                    $ins->execute([$full_name, $email, $phone, $hash, $status]);
                    set_flash('success', 'New library member registered successfully.');
                    header('Location: admin_members.php');
                    exit;
                }
            }
        }
    }
}

$query_sql = "
    SELECT u.*, 
        (SELECT COUNT(*) FROM transactions WHERE user_id = u.id AND type = 'rent' AND status = 'active') AS active_rentals,
        (SELECT COUNT(*) FROM transactions WHERE user_id = u.id) AS total_tx,
        (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = u.id) AS total_spent
    FROM users u 
    WHERE u.role = 'user'
";
$params = [];

if (!empty($search_query)) {
    $query_sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $st = "%$search_query%";
    $params = [$st, $st, $st];
}

$query_sql .= " ORDER BY u.created_at DESC";
$members_stmt = $pdo->prepare($query_sql);
$members_stmt->execute($params);
$members = $members_stmt->fetchAll();

$flash_success = get_flash('success');
$flash_danger = get_flash('danger');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members (CRUD) - K.B. Library</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/admin_sidebar.php'; ?>

        <main class="main-content">
            <header class="topbar">
                <div class="topbar-title-section">
                    <h1>Member Administration &amp; Directory</h1>
                    <p>K.B. Library registered patrons management</p>
                </div>
                <div class="topbar-actions">
                    <?php if ($action !== 'create' && $action !== 'edit'): ?>
                        <a href="admin_members.php?action=create" class="btn btn-primary btn-sm">+ Add New Member</a>
                    <?php else: ?>
                        <a href="admin_members.php" class="btn btn-secondary btn-sm">&larr; Back to Member List</a>
                    <?php endif; ?>
                </div>
            </header>

            <div class="content-body">
                <?php if ($flash_success): ?>
                    <div class="alert alert-success"><span><?php echo htmlspecialchars($flash_success); ?></span></div>
                <?php endif; ?>
                <?php if ($flash_danger): ?>
                    <div class="alert alert-danger"><span><?php echo htmlspecialchars($flash_danger); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><span><?php echo htmlspecialchars($error); ?></span></div>
                <?php endif; ?>

                <?php if ($action === 'create' || $action === 'edit'): ?>
                    <div class="panel-card" style="max-width: 680px;">
                        <div class="panel-header">
                            <h2 class="panel-title"><?php echo $action === 'edit' ? 'Edit Member Details' : 'Register New Member'; ?></h2>
                        </div>
                        <div class="panel-body">
                            <form method="POST" action="admin_members.php">
                                <input type="hidden" name="form_action" value="<?php echo $action === 'edit' ? 'update' : 'create'; ?>">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="member_id" value="<?php echo $edit_member['id']; ?>">
                                <?php endif; ?>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label" for="full_name">Full Name *</label>
                                        <input type="text" name="full_name" id="full_name" class="form-input" required 
                                               value="<?php echo htmlspecialchars($edit_member['full_name'] ?? $_POST['full_name'] ?? ''); ?>" placeholder="e.g. Eleanor Vance">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="email">Email Address *</label>
                                        <input type="email" name="email" id="email" class="form-input" required 
                                               value="<?php echo htmlspecialchars($edit_member['email'] ?? $_POST['email'] ?? ''); ?>" placeholder="e.g. eleanor@example.com">
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label" for="phone">Phone Number</label>
                                        <input type="tel" name="phone" id="phone" class="form-input" 
                                               value="<?php echo htmlspecialchars($edit_member['phone'] ?? $_POST['phone'] ?? ''); ?>" placeholder="e.g. +1-555-0182">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="status">Membership Status</label>
                                        <select name="status" id="status" class="form-select">
                                            <?php $cur_status = $edit_member['status'] ?? $_POST['status'] ?? 'active'; ?>
                                            <option value="active" <?php echo $cur_status === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $cur_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="suspended" <?php echo $cur_status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="password">
                                        Password <?php echo $action === 'edit' ? '<span style="font-weight: 400; color: var(--text-muted);">(Leave blank to keep current)</span>' : '*'; ?>
                                    </label>
                                    <input type="password" name="password" id="password" class="form-input" 
                                           placeholder="<?php echo $action === 'edit' ? 'Enter new password if updating' : 'Initial member password'; ?>" <?php echo $action === 'create' ? 'required' : ''; ?>>
                                </div>

                                <div style="display: flex; gap: 10px; margin-top: 24px;">
                                    <button type="submit" class="btn btn-primary"><?php echo $action === 'edit' ? 'Update Member Profile' : 'Create Member Account'; ?></button>
                                    <a href="admin_members.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title">Registered Members (<?php echo count($members); ?>)</h2>
                        <form method="GET" action="admin_members.php" style="display: flex; gap: 8px;">
                            <input type="text" name="q" class="form-input" style="width: 240px; padding: 6px 10px;" placeholder="Search name, email, phone..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">Search</button>
                            <?php if (!empty($search_query)): ?>
                                <a href="admin_members.php" class="btn btn-secondary btn-sm">Reset</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Member Name</th>
                                    <th>Email Address</th>
                                    <th>Phone</th>
                                    <th>Active Loans</th>
                                    <th>Lifetime Spent</th>
                                    <th>Status</th>
                                    <th>Joined Date</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($members)): ?>
                                    <tr>
                                        <td colspan="9" style="text-align: center; padding: 32px; color: var(--text-muted);">
                                            No members found matching your search.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($members as $m): ?>
                                        <tr>
                                            <td style="color: var(--text-muted); font-size: 12px; font-weight: 600;">#<?php echo $m['id']; ?></td>
                                            <td>
                                                <div class="table-avatar-cell">
                                                    <div class="user-avatar" style="width: 28px; height: 28px; font-size: 11px;">
                                                        <?php echo strtoupper(substr($m['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($m['full_name']); ?></div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($m['email']); ?></td>
                                            <td><?php echo $m['phone'] ? htmlspecialchars($m['phone']) : '<span style="color: var(--text-light);">-</span>'; ?></td>
                                            <td>
                                                <?php if ($m['active_rentals'] > 0): ?>
                                                    <span class="badge badge-rent"><?php echo $m['active_rentals']; ?> borrowed</span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-light); font-size: 12px;">0 borrowed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-weight: 600;">$<?php echo number_format($m['total_spent'], 2); ?></td>
                                            <td>
                                                <?php if ($m['status'] === 'active'): ?>
                                                    <span class="badge badge-active">Active</span>
                                                <?php elseif ($m['status'] === 'suspended'): ?>
                                                    <span class="badge badge-sold">Suspended</span>
                                                <?php else: ?>
                                                    <span class="badge badge-returned">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($m['created_at'])); ?></td>
                                            <td style="text-align: right;">
                                                <a href="admin_members.php?action=edit&amp;id=<?php echo $m['id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                                                <a href="admin_members.php?action=delete&amp;id=<?php echo $m['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Permanently delete member <?php echo htmlspecialchars($m['full_name']); ?>?');">Delete</a>
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
