<?php
$db_host = 'localhost';
$db_name = 'library_db';
$db_user = 'root';
$db_pass = '';
$db_charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;

try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    try {
        $root_dsn = "mysql:host=$db_host;charset=$db_charset";
        $pdo = new PDO($root_dsn, $db_user, $db_pass, $options);
        $schema_file = __DIR__ . '/schema.sql';
        if (file_exists($schema_file)) {
            $sql = file_get_contents($schema_file);
            $pdo->exec($sql);
            $pdo->exec("USE `$db_name`");
        }
    } catch (PDOException $inner) {
        $sqlite_file = __DIR__ . '/library.sqlite';
        $init_required = !file_exists($sqlite_file);
        $pdo = new PDO("sqlite:" . $sqlite_file, null, null, $options);
        $pdo->exec("PRAGMA foreign_keys = ON;");

        if ($init_required) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    full_name TEXT NOT NULL,
                    email TEXT NOT NULL UNIQUE,
                    password TEXT NOT NULL,
                    role TEXT NOT NULL DEFAULT 'user',
                    phone TEXT,
                    status TEXT NOT NULL DEFAULT 'active',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS authors (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    email TEXT,
                    biography TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS books (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    author_id INTEGER NOT NULL,
                    title TEXT NOT NULL,
                    isbn TEXT,
                    category TEXT NOT NULL,
                    rent_price REAL NOT NULL DEFAULT 5.00,
                    buy_price REAL NOT NULL DEFAULT 25.00,
                    size_mb REAL NOT NULL DEFAULT 15.50,
                    total_copies INTEGER NOT NULL DEFAULT 100,
                    available_copies INTEGER NOT NULL DEFAULT 100,
                    cover_image TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS transactions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    transaction_code TEXT NOT NULL UNIQUE,
                    user_id INTEGER NOT NULL,
                    book_id INTEGER NOT NULL,
                    type TEXT NOT NULL,
                    amount REAL NOT NULL,
                    issue_date TEXT NOT NULL,
                    due_date TEXT,
                    return_date TEXT,
                    status TEXT NOT NULL DEFAULT 'active',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
                );

                INSERT INTO users (full_name, email, password, role, phone, status) VALUES
                ('System Administrator', 'admin@library.local', 'admin123', 'admin', '+1-555-0100', 'active'),
                ('Alice Walker', 'user@library.local', 'user123', 'user', '+1-555-0144', 'active'),
                ('Robert Sterling', 'robert@library.local', 'user123', 'user', '+1-555-0178', 'active');

                INSERT INTO authors (name, email, biography) VALUES
                ('Robert C. Martin', 'unclebob@cleancoder.com', 'Software engineer and author focused on software craftsmanship and architectural principles.'),
                ('Martin Fowler', 'fowler@martinfowler.com', 'Chief scientist at Thoughtworks specializing in enterprise architecture, refactoring, and agile software design.'),
                ('Andrew Hunt', 'andy@pragprog.com', 'Co-author of The Pragmatic Programmer and agile methodologies advocate.'),
                ('Kathy Sierra', 'kathy@headfirstlabs.com', 'Creator of the Head First series, instructional designer, and game developer.'),
                ('Donald Knuth', 'knuth@stanford.edu', 'Professor emeritus at Stanford University and author of the multi-volume The Art of Computer Programming.');

                INSERT INTO books (author_id, title, isbn, category, rent_price, buy_price, size_mb, total_copies, available_copies, cover_image) VALUES
                (1, 'Clean Code: A Handbook of Agile Software Craftsmanship', '978-0132350884', 'Software Engineering', 4.50, 32.00, 18.40, 100, 100, 'clean_code.png'),
                (2, 'Refactoring: Improving the Design of Existing Code', '978-0134757599', 'Software Engineering', 5.00, 35.50, 24.10, 100, 100, 'refactoring.png'),
                (3, 'The Pragmatic Programmer: Your Journey to Mastery', '978-0135957059', 'Software Engineering', 3.99, 29.90, 14.80, 100, 100, 'pragmatic_prog.png'),
                (4, 'Head First Design Patterns: Building Extensible Software', '978-0596007126', 'Architecture', 6.00, 39.00, 42.75, 100, 100, 'head_first_patterns.png'),
                (5, 'The Art of Computer Programming, Vol 1: Fundamental Algorithms', '978-0201896831', 'Computer Science', 7.50, 55.00, 58.20, 100, 100, 'taocp_vol1.png'),
                (1, 'Clean Architecture: A Craftsman Guide to Software Structure', '978-0134494166', 'System Design', 4.75, 34.00, 21.60, 100, 100, 'clean_architecture.png');

                INSERT INTO transactions (transaction_code, user_id, book_id, type, amount, issue_date, due_date, return_date, status) VALUES
                ('TX-2026-08101', 2, 1, 'rent', 4.50, '2026-09-01', '2026-09-15', NULL, 'active'),
                ('TX-2026-08102', 2, 3, 'buy', 29.90, '2026-08-28', NULL, NULL, 'completed'),
                ('TX-2026-08103', 3, 4, 'rent', 6.00, '2026-08-10', '2026-08-24', '2026-08-20', 'returned');
            ");
        }
    }
}
