<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    if (is_admin()) {
        header('Location: admin_dashboard.php');
        exit;
    }
    header('Location: user_dashboard.php');
    exit;
}

$error = '';
$selected_role = isset($_GET['role']) && $_GET['role'] === 'admin' ? 'admin' : 'user';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['quick_login'])) {
        $q_role = $_POST['quick_login'] === 'admin' ? 'admin' : 'user';
        $stmt = $pdo->prepare("SELECT * FROM users WHERE role = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$q_role]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];

            if ($user['role'] === 'admin') {
                header('Location: admin_dashboard.php');
            } else {
                header('Location: user_dashboard.php');
            }
            exit;
        }
    }

    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? 'user');
    $selected_role = $role === 'admin' ? 'admin' : 'user';

    if (empty($email)) {
        $email = ($selected_role === 'admin' ? 'admin' : 'user_' . rand(100, 999)) . '@library.local';
    }
    if (empty($password)) {
        $password = '123456';
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        if (!empty($full_name) && $full_name !== $user['full_name']) {
            $up = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
            $up->execute([$full_name, $user['id']]);
            $user['full_name'] = $full_name;
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];

        if ($user['role'] === 'admin') {
            header('Location: admin_dashboard.php');
        } else {
            header('Location: user_dashboard.php');
        }
        exit;
    } else {
        if (empty($full_name)) {
            $raw_name = explode('@', $email)[0];
            $full_name = ucwords(str_replace(['.', '_', '-'], ' ', $raw_name));
        }
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $ins = $pdo->prepare("INSERT INTO users (full_name, email, password, role, phone, status) VALUES (?, ?, ?, ?, '+1-555-0199', 'active')");
        $ins->execute([$full_name, $email, $hashed, $selected_role]);
        $new_id = $pdo->lastInsertId();

        $_SESSION['user_id'] = $new_id;
        $_SESSION['user_name'] = $full_name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = $selected_role;

        if ($selected_role === 'admin') {
            header('Location: admin_dashboard.php');
        } else {
            header('Location: user_dashboard.php');
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - K.B. Library Management System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-brand" style="flex-direction: column; gap: 8px;">
                <img src="assets/images/logo.png" alt="K.B. Library" style="max-width: 220px; height: auto;">
            </div>
            <p class="auth-subtitle">User Defined Access &amp; Circulation Portal</p>
        </div>

        <div class="auth-role-tabs">
            <button type="button" class="role-tab <?php echo $selected_role === 'user' ? 'active' : ''; ?>" onclick="setRole('user')">Member Login</button>
            <button type="button" class="role-tab <?php echo $selected_role === 'admin' ? 'active' : ''; ?>" onclick="setRole('admin')">Staff &amp; Admin</button>
        </div>

        <div class="auth-body">
            <div class="alert alert-success" style="font-size: 12px; margin-bottom: 16px; padding: 10px 14px;">
                <span><strong>Open Access:</strong> Define your credentials below to log in or auto-register instantly.</span>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'registered'): ?>
                <div class="alert alert-success">
                    <span>Registration successful! You can now log in.</span>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out'): ?>
                <div class="alert alert-success">
                    <span>You have been safely signed out.</span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <input type="hidden" name="role" id="login_role" value="<?php echo htmlspecialchars($selected_role); ?>">

                <div class="form-group">
                    <label class="form-label" for="full_name">Your Name</label>
                    <input type="text" name="full_name" id="full_name" class="form-input" placeholder="e.g. John Doe (Optional)">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" name="email" id="email" class="form-input" placeholder="e.g. john@example.com" autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password / Code</label>
                    <input type="password" name="password" id="password" class="form-input" placeholder="Enter code / password">
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary btn-block">Sign In / Continue</button>
                </div>
            </form>

            <div style="margin: 18px 0; display: flex; align-items: center; text-align: center; color: var(--text-light); font-size: 11px;">
                <div style="flex: 1; height: 1px; background: var(--border-color);"></div>
                <span style="padding: 0 10px; font-weight: 600;">OR INSTANT 1-CLICK ACCESS</span>
                <div style="flex: 1; height: 1px; background: var(--border-color);"></div>
            </div>

            <form method="POST" action="login.php" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <button type="submit" name="quick_login" value="user" class="btn btn-secondary btn-sm" style="font-weight: 700;">
                    Member Portal
                </button>
                <button type="submit" name="quick_login" value="admin" class="btn btn-secondary btn-sm" style="font-weight: 700; color: var(--primary);">
                    Admin Panel
                </button>
            </form>
        </div>
    </div>

    <script>
    function setRole(role) {
        document.getElementById('login_role').value = role;
        const tabs = document.querySelectorAll('.role-tab');
        tabs.forEach(tab => tab.classList.remove('active'));
        if (role === 'admin') {
            tabs[1].classList.add('active');
        } else {
            tabs[0].classList.add('active');
        }
    }
    setRole('<?php echo $selected_role; ?>');
    </script>
</body>
</html>
