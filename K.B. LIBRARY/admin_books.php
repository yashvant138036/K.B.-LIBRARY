<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_admin();

$action = $_GET['action'] ?? 'list';
$book_id = (int)($_GET['id'] ?? 0);
$edit_book = null;
$edit_author_name = '';
$error = '';
$search_query = trim($_GET['q'] ?? '');

$authors_stmt = $pdo->query("SELECT id, name FROM authors ORDER BY name ASC");
$all_authors = $authors_stmt->fetchAll();

if ($action === 'delete' && $book_id > 0) {
    $active_tx = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE book_id = ? AND type = 'rent' AND status = 'active'");
    $active_tx->execute([$book_id]);
    if ($active_tx->fetchColumn() > 0) {
        set_flash('danger', 'Cannot delete book: Copies of this title are currently rented out to members.');
    } else {
        $del = $pdo->prepare("DELETE FROM books WHERE id = ?");
        $del->execute([$book_id]);
        set_flash('success', 'Book title removed from catalog.');
    }
    header('Location: admin_books.php');
    exit;
}

if ($action === 'edit' && $book_id > 0) {
    $stmt = $pdo->prepare("SELECT b.*, a.name AS author_name FROM books b JOIN authors a ON b.author_id = a.id WHERE b.id = ?");
    $stmt->execute([$book_id]);
    $edit_book = $stmt->fetch();
    if (!$edit_book) {
        set_flash('danger', 'Book not found.');
        header('Location: admin_books.php');
        exit;
    }
    $edit_author_name = $edit_book['author_name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? 'create';
    $title = trim($_POST['title'] ?? '');
    $author_name = trim($_POST['author_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $rent_price = (float)($_POST['rent_price'] ?? 0);
    $buy_price = (float)($_POST['buy_price'] ?? 0);
    $size_mb = (float)($_POST['size_mb'] ?? 0);
    $cover_image = trim($_POST['cover_image'] ?? 'default_cover.png');

    if (empty($title) || empty($author_name) || empty($category)) {
        $error = 'Book title, author name, and category are required fields.';
    } elseif ($size_mb <= 0) {
        $error = 'Please enter a valid file size in MB.';
    } else {
        $auth_check = $pdo->prepare("SELECT id FROM authors WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $auth_check->execute([$author_name]);
        $author_id = $auth_check->fetchColumn();

        if (!$author_id) {
            $ins_auth = $pdo->prepare("INSERT INTO authors (name) VALUES (?)");
            $ins_auth->execute([$author_name]);
            $author_id = $pdo->lastInsertId();
        }

        if ($form_action === 'update') {
            $target_id = (int)($_POST['book_id'] ?? 0);
            $up = $pdo->prepare("
                UPDATE books SET 
                    title = ?, author_id = ?, category = ?, isbn = ?, 
                    rent_price = ?, buy_price = ?, size_mb = ?, cover_image = ?
                WHERE id = ?
            ");
            $up->execute([$title, $author_id, $category, $isbn, $rent_price, $buy_price, $size_mb, $cover_image, $target_id]);
            set_flash('success', 'Book details updated successfully.');
            header('Location: admin_books.php');
            exit;
        } else {
            $default_copies = 100;
            $ins = $pdo->prepare("
                INSERT INTO books 
                    (title, author_id, category, isbn, rent_price, buy_price, size_mb, total_copies, available_copies, cover_image) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([$title, $author_id, $category, $isbn, $rent_price, $buy_price, $size_mb, $default_copies, $default_copies, $cover_image]);
            set_flash('success', 'New book title added to library catalog.');
            header('Location: admin_books.php');
            exit;
        }
    }
}

$sql = "
    SELECT b.*, a.name AS author_name 
    FROM books b 
    JOIN authors a ON b.author_id = a.id 
    WHERE 1=1
";
$params = [];

if (!empty($search_query)) {
    $sql .= " AND (b.title LIKE ? OR a.name LIKE ? OR b.isbn LIKE ? OR b.category LIKE ?)";
    $st = "%$search_query%";
    $params = [$st, $st, $st, $st];
}

$sql .= " ORDER BY b.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

$flash_success = get_flash('success');
$flash_danger = get_flash('danger');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books - K.B. Library</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/admin_sidebar.php'; ?>

        <main class="main-content">
            <header class="topbar">
                <div class="topbar-title-section">
                    <h1>Book Catalog &amp; Inventory</h1>
                    <p>K.B. Library digital catalog, rental fees, and file sizes</p>
                </div>
                <div class="topbar-actions">
                    <?php if ($action !== 'create' && $action !== 'edit'): ?>
                        <a href="admin_books.php?action=create" class="btn btn-primary btn-sm">+ Add New Book</a>
                    <?php else: ?>
                        <a href="admin_books.php" class="btn btn-secondary btn-sm">&larr; Back to Catalog</a>
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
                    <div class="panel-card" style="max-width: 800px;">
                        <div class="panel-header">
                            <h2 class="panel-title"><?php echo $action === 'edit' ? 'Edit Book Record' : 'Add New Book to Collection'; ?></h2>
                        </div>
                        <div class="panel-body">
                            <form method="POST" action="admin_books.php">
                                <input type="hidden" name="form_action" value="<?php echo $action === 'edit' ? 'update' : 'create'; ?>">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="book_id" value="<?php echo $edit_book['id']; ?>">
                                <?php endif; ?>

                                <div class="form-group">
                                    <label class="form-label" for="title">Book Title *</label>
                                    <input type="text" name="title" id="title" class="form-input" required 
                                           value="<?php echo htmlspecialchars($edit_book['title'] ?? $_POST['title'] ?? ''); ?>" placeholder="e.g. Design Patterns: Elements of Reusable Object-Oriented Software">
                                </div>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label" for="author_name">Author Name *</label>
                                        <input type="text" name="author_name" id="author_name" class="form-input" list="authors_list" required 
                                               value="<?php echo htmlspecialchars($edit_author_name ?: ($_POST['author_name'] ?? '')); ?>" placeholder="Type or select author name...">
                                        <datalist id="authors_list">
                                            <?php foreach ($all_authors as $auth): ?>
                                                <option value="<?php echo htmlspecialchars($auth['name']); ?>"></option>
                                            <?php endforeach; ?>
                                        </datalist>
                                        <div class="form-hint">Type author name directly or choose from registered authors</div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="category">Category / Domain *</label>
                                        <input type="text" name="category" id="category" class="form-input" required 
                                               value="<?php echo htmlspecialchars($edit_book['category'] ?? $_POST['category'] ?? ''); ?>" placeholder="e.g. Software Engineering">
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label" for="isbn">ISBN Identifier</label>
                                        <input type="text" name="isbn" id="isbn" class="form-input" 
                                               value="<?php echo htmlspecialchars($edit_book['isbn'] ?? $_POST['isbn'] ?? ''); ?>" placeholder="e.g. 978-0201633610">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="size_mb">File Size in MB *</label>
                                        <input type="number" step="0.01" name="size_mb" id="size_mb" class="form-input" required 
                                               value="<?php echo htmlspecialchars($edit_book['size_mb'] ?? $_POST['size_mb'] ?? '16.50'); ?>" placeholder="e.g. 18.40">
                                        <div class="form-hint">Digital volume resource weight in Megabytes</div>
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label" for="rent_price">Rental Price ($) *</label>
                                        <input type="number" step="0.01" name="rent_price" id="rent_price" class="form-input" required 
                                               value="<?php echo htmlspecialchars($edit_book['rent_price'] ?? $_POST['rent_price'] ?? '4.99'); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="buy_price">Buy Price ($) *</label>
                                        <input type="number" step="0.01" name="buy_price" id="buy_price" class="form-input" required 
                                               value="<?php echo htmlspecialchars($edit_book['buy_price'] ?? $_POST['buy_price'] ?? '29.99'); ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="cover_image">Book Cover Image (.png)</label>
                                    <select name="cover_image" id="cover_image" class="form-select">
                                        <?php 
                                        $cur_cover = $edit_book['cover_image'] ?? $_POST['cover_image'] ?? 'default_cover.png';
                                        $covers = [
                                            'clean_code.png' => 'Clean Code (Cyan / Dark)',
                                            'refactoring.png' => 'Refactoring (Emerald / Gold)',
                                            'pragmatic_prog.png' => 'Pragmatic Programmer (Violet)',
                                            'head_first_patterns.png' => 'Head First Design Patterns (Crimson)',
                                            'taocp_vol1.png' => 'The Art of Computer Programming (Indigo)',
                                            'clean_architecture.png' => 'Clean Architecture (Cyan / Slate)',
                                            'default_cover.png' => 'Standard Library Edition (Slate Minimal)'
                                        ];
                                        foreach ($covers as $file => $label): 
                                        ?>
                                            <option value="<?php echo $file; ?>" <?php echo $cur_cover === $file ? 'selected' : ''; ?>>
                                                <?php echo $label; ?> (<?php echo $file; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div style="display: flex; gap: 10px; margin-top: 24px;">
                                    <button type="submit" class="btn btn-primary"><?php echo $action === 'edit' ? 'Update Book Record' : 'Save Book to Catalog'; ?></button>
                                    <a href="admin_books.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title">Catalog Titles (<?php echo count($books); ?>)</h2>
                        <form method="GET" action="admin_books.php" style="display: flex; gap: 8px;">
                            <input type="text" name="q" class="form-input" style="width: 260px; padding: 6px 10px;" placeholder="Search title, author, ISBN..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">Search</button>
                            <?php if (!empty($search_query)): ?>
                                <a href="admin_books.php" class="btn btn-secondary btn-sm">Reset</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Book Cover &amp; Title</th>
                                    <th>Author</th>
                                    <th>Category</th>
                                    <th>Size</th>
                                    <th>Rental Fee</th>
                                    <th>Buy Price</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($books)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 32px; color: var(--text-muted);">
                                            No books found matching your criteria.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($books as $b): ?>
                                        <?php 
                                        $cover_path = 'assets/covers/' . ($b['cover_image'] ? $b['cover_image'] : 'default_cover.png');
                                        if (!file_exists(__DIR__ . '/' . $cover_path)) {
                                            $cover_path = 'assets/covers/default_cover.png';
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="table-avatar-cell">
                                                    <div class="table-book-thumb">
                                                        <img src="<?php echo htmlspecialchars($cover_path); ?>" alt="">
                                                    </div>
                                                    <div>
                                                        <div style="font-weight: 700; color: var(--text-main); font-size: 14px;">
                                                            <?php echo htmlspecialchars($b['title']); ?>
                                                        </div>
                                                        <div style="font-size: 11px; color: var(--text-muted); font-family: monospace;">
                                                            ISBN: <?php echo htmlspecialchars($b['isbn'] ?? 'N/A'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="font-weight: 600;"><?php echo htmlspecialchars($b['author_name']); ?></td>
                                            <td><span class="badge" style="background: #f1f5f9;"><?php echo htmlspecialchars($b['category']); ?></span></td>
                                            <td><strong><?php echo number_format($b['size_mb'], 2); ?> MB</strong></td>
                                            <td style="color: var(--rent-color); font-weight: 700;">$<?php echo number_format($b['rent_price'], 2); ?></td>
                                            <td style="color: var(--sold-color); font-weight: 700;">$<?php echo number_format($b['buy_price'], 2); ?></td>
                                            <td style="text-align: right;">
                                                <a href="admin_books.php?action=edit&amp;id=<?php echo $b['id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                                                <a href="admin_books.php?action=delete&amp;id=<?php echo $b['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete book title <?php echo htmlspecialchars(addslashes($b['title'])); ?>?');">Delete</a>
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
