<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    header('Location: user_dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Please fill out all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'An account with this email address already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, phone, status) VALUES (?, ?, ?, 'user', ?, 'active')");
            $stmt->execute([$full_name, $email, $hashed, $phone]);

            header('Location: login.php?msg=registered');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Registration - K.B. Library</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-brand" style="flex-direction: column; gap: 8px;">
                <img src="assets/images/logo.png" alt="K.B. Library" style="max-width: 220px; height: auto;">
            </div>
            <p class="auth-subtitle">Create a New Member Account</p>
        </div>

        <div class="auth-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name *</label>
                    <input type="text" name="full_name" id="full_name" class="form-input" placeholder="e.g. John Doe" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address *</label>
                    <input type="email" name="email" id="email" class="form-input" placeholder="e.g. john@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number</label>
                    <input type="tel" name="phone" id="phone" class="form-input" placeholder="e.g. +1 555-0199" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password *</label>
                    <input type="password" name="password" id="password" class="form-input" placeholder="Minimum 6 characters" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password *</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="Re-type password" required>
                </div>

                <div class="form-group" style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary btn-block">Register Account</button>
                </div>
            </form>

            <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-color); font-size: 13px; text-align: center; color: var(--text-muted);">
                Already have an account? <a href="login.php">Sign In</a>
            </div>
        </div>
    </div>
</body>
</html>
