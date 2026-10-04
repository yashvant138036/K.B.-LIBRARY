<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_login();
$user = get_logged_in_user();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'rent') {
        $book_id = (int)($_POST['book_id'] ?? 0);
        if ($book_id <= 0) {
            set_flash('danger', 'Invalid book selection.');
            header('Location: user_dashboard.php');
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
        $stmt->execute([$book_id]);
        $book = $stmt->fetch();

        if (!$book) {
            set_flash('danger', 'Book not found.');
            header('Location: user_dashboard.php');
            exit;
        }

        if ($book['available_copies'] <= 0) {
            set_flash('danger', 'Sorry, this book is currently out of stock for rental.');
            header('Location: user_dashboard.php');
            exit;
        }

        $active_check = $pdo->prepare("SELECT id FROM transactions WHERE user_id = ? AND book_id = ? AND type = 'rent' AND status = 'active'");
        $active_check->execute([$user['id'], $book_id]);
        if ($active_check->fetch()) {
            set_flash('warning', 'You already have an active rental for this book.');
            header('Location: user_dashboard.php?tab=mybooks');
            exit;
        }

        $tx_code = 'TX-R' . strtoupper(substr(uniqid(), 7)) . rand(10, 99);
        $issue_date = date('Y-m-d');
        $due_date = date('Y-m-d', strtotime('+14 days'));

        $pdo->beginTransaction();
        try {
            $dec_stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id = ? AND available_copies > 0");
            $dec_stmt->execute([$book_id]);

            $tx_stmt = $pdo->prepare("INSERT INTO transactions (transaction_code, user_id, book_id, type, amount, issue_date, due_date, status) VALUES (?, ?, ?, 'rent', ?, ?, ?, 'active')");
            $tx_stmt->execute([$tx_code, $user['id'], $book_id, $book['rent_price'], $issue_date, $due_date]);

            $pdo->commit();
            set_flash('success', 'Book rented successfully! Due date: ' . $due_date . ' (Ref: ' . $tx_code . ')');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Transaction error: ' . $e->getMessage());
        }

        header('Location: user_dashboard.php?tab=mybooks');
        exit;
    }

    if ($action === 'buy') {
        $book_id = (int)($_POST['book_id'] ?? 0);
        if ($book_id <= 0) {
            set_flash('danger', 'Invalid book selection.');
            header('Location: user_dashboard.php');
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
        $stmt->execute([$book_id]);
        $book = $stmt->fetch();

        if (!$book) {
            set_flash('danger', 'Book not found.');
            header('Location: user_dashboard.php');
            exit;
        }

        if ($book['available_copies'] <= 0) {
            set_flash('danger', 'Sorry, this title is sold out.');
            header('Location: user_dashboard.php');
            exit;
        }

        $tx_code = 'TX-B' . strtoupper(substr(uniqid(), 7)) . rand(10, 99);
        $issue_date = date('Y-m-d');

        $pdo->beginTransaction();
        try {
            $dec_stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id = ? AND available_copies > 0");
            $dec_stmt->execute([$book_id]);

            $tx_stmt = $pdo->prepare("INSERT INTO transactions (transaction_code, user_id, book_id, type, amount, issue_date, status) VALUES (?, ?, ?, 'buy', ?, ?, 'completed')");
            $tx_stmt->execute([$tx_code, $user['id'], $book_id, $book['buy_price'], $issue_date]);

            $pdo->commit();
            set_flash('success', 'Purchase completed! You own this digital edition. (Ref: ' . $tx_code . ')');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Purchase error: ' . $e->getMessage());
        }

        header('Location: user_dashboard.php?tab=mybooks');
        exit;
    }

    if ($action === 'return') {
        $tx_id = (int)($_POST['transaction_id'] ?? 0);
        if ($tx_id <= 0) {
            set_flash('danger', 'Invalid transaction reference.');
            header('Location: user_dashboard.php?tab=mybooks');
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ? AND type = 'rent' AND status = 'active'");
        $stmt->execute([$tx_id, $user['id']]);
        $tx = $stmt->fetch();

        if (!$tx) {
            set_flash('danger', 'No active rental found matching this record.');
            header('Location: user_dashboard.php?tab=mybooks');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $up_tx = $pdo->prepare("UPDATE transactions SET status = 'returned', return_date = ? WHERE id = ?");
            $up_tx->execute([date('Y-m-d'), $tx_id]);

            $inc_book = $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id = ?");
            $inc_book->execute([$tx['book_id']]);

            $pdo->commit();
            set_flash('success', 'Book returned successfully! Thank you.');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Return failed: ' . $e->getMessage());
        }

        header('Location: user_dashboard.php?tab=mybooks');
        exit;
    }
}

header('Location: user_dashboard.php');
exit;
