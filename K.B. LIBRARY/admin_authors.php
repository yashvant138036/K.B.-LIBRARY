<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_admin();

$action = $_GET['action'] ?? 'list';
$author_id = (int)($_GET['id'] ?? 0);
$edit_author = null;
$error = '';

if ($action === 'delete' && $author_id > 0) {
    $del_stmt = $pdo->prepare("DELETE FROM authors WHERE id = ?");
    $del_stmt->execute([$author_id]);
    set_flash('success', 'Author deleted successfully.');
    header('Location: admin_authors.php');
    exit;
}

if ($action === 'edit' && $author_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM authors WHERE id = ?");
    $stmt->execute([$author_id]);
    $edit_author = $stmt->fetch();
    if (!$edit_author) {
        set_flash('danger', 'Author not found.');
        header('Location: admin_authors.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $biography = trim($_POST['biography'] ?? '');
    $post_action = $_POST['form_action'] ?? 'create';

    if (empty($name)) {
        $error = 'Author name is required.';
    } else {
        if ($post_action === 'update') {
            $update_id = (int)($_POST['author_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE authors SET name = ?, email = ?, biography = ? WHERE id = ?");
            $stmt->execute([$name, $email, $biography, $update_id]);
            set_flash('success', 'Author details updated successfully.');
            header('Location: admin_authors.php');
            exit;
        } else {
            $stmt = $pdo->prepare("INSERT INTO authors (name, email, biography) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $biography]);
            set_flash('success', 'New author added successfully.');
            header('Location: admin_authors.php');
            exit;
        }
    }
}

$authors_stmt = $pdo->query("
    SELECT a.*, COUNT(b.id) AS book_count 
    FROM authors a 
    LEFT JOIN books b ON a.id = b.author_id 
    GROUP BY a.id 
    ORDER BY a.name ASC
");
$authors = $authors_stmt->fetchAll();

$flash_success = get_flash('success');
$flash_danger = get_flash('danger');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Authors - K.B. Library</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/admin_sidebar.php'; ?>

        <main class="main-content">
            <header class="topbar">
                <div class="topbar-title-section">
                    <h1>Author Directory &amp; Management</h1>
                    <p>Manage literary authors, profiles, and associated works</p>
                </div>
                <div class="topbar-actions">
                    <?php if ($action !== 'create' && $action !== 'edit'): ?>
                        <a href="admin_authors.php?action=create" class="btn btn-primary btn-sm">+ Add New Author</a>
                    <?php else: ?>
                        <a href="admin_authors.php" class="btn btn-secondary btn-sm">&larr; Back to Authors</a>
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
                            <h2 class="panel-title"><?php echo $action === 'edit' ? 'Edit Author Details' : 'Add New Author'; ?></h2>
                        </div>
                        <div class="panel-body">
                            <form method="POST" action="admin_authors.php">
                                <input type="hidden" name="form_action" value="<?php echo $action === 'edit' ? 'update' : 'create'; ?>">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="author_id" value="<?php echo $edit_author['id']; ?>">
                                <?php endif; ?>

                                <div class="form-group">
                                    <label class="form-label" for="name">Author Full Name *</label>
                                    <input type="text" name="name" id="name" class="form-input" required 
                                           value="<?php echo htmlspecialchars($edit_author['name'] ?? $_POST['name'] ?? ''); ?>" placeholder="e.g. Robert C. Martin">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="email">Contact Email</label>
                                    <input type="email" name="email" id="email" class="form-input" 
                                           value="<?php echo htmlspecialchars($edit_author['email'] ?? $_POST['email'] ?? ''); ?>" placeholder="e.g. author@publisher.com">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="biography">Biography &amp; Notes</label>
                                    <textarea name="biography" id="biography" class="form-textarea" rows="4" placeholder="Brief author background, credentials, and literary focus"><?php echo htmlspecialchars($edit_author['biography'] ?? $_POST['biography'] ?? ''); ?></textarea>
                                </div>

                                <div style="display: flex; gap: 10px; margin-top: 24px;">
                                    <button type="submit" class="btn btn-primary"><?php echo $action === 'edit' ? 'Update Author' : 'Save Author'; ?></button>
                                    <a href="admin_authors.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title">Catalog Authors (<?php echo count($authors); ?>)</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Author Name</th>
                                    <th>Email</th>
                                    <th>Biography</th>
                                    <th>Books in Catalog</th>
                                    <th>Added On</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($authors)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                            No authors currently registered.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($authors as $a): ?>
                                        <tr>
                                            <td style="color: var(--text-muted); font-size: 12px; font-weight: 600;">#<?php echo $a['id']; ?></td>
                                            <td style="font-weight: 700; color: var(--text-main); font-size: 14px;">
                                                <?php echo htmlspecialchars($a['name']); ?>
                                            </td>
                                            <td>
                                                <?php echo $a['email'] ? htmlspecialchars($a['email']) : '<span style="color: var(--text-light);">-</span>'; ?>
                                            </td>
                                            <td style="max-width: 280px; color: var(--text-muted); font-size: 12px;">
                                                <?php echo htmlspecialchars($a['biography'] ?? ''); ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-active"><?php echo $a['book_count']; ?> title(s)</span>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($a['created_at'])); ?></td>
                                            <td style="text-align: right;">
                                                <a href="admin_authors.php?action=edit&amp;id=<?php echo $a['id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                                                <a href="admin_authors.php?action=delete&amp;id=<?php echo $a['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this author? All associated books may also be deleted.');">Delete</a>
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
