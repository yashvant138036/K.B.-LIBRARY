CREATE DATABASE IF NOT EXISTS library_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE library_db;

DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS books;
DROP TABLE IF EXISTS authors;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    phone VARCHAR(30) NULL,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE authors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NULL,
    biography TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    isbn VARCHAR(30) NULL,
    category VARCHAR(100) NOT NULL,
    rent_price DECIMAL(10,2) NOT NULL DEFAULT 5.00,
    buy_price DECIMAL(10,2) NOT NULL DEFAULT 25.00,
    size_mb DECIMAL(8,2) NOT NULL DEFAULT 15.50,
    total_copies INT NOT NULL DEFAULT 100,
    available_copies INT NOT NULL DEFAULT 100,
    cover_image VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_books_author FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_code VARCHAR(50) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    type ENUM('rent', 'buy') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NULL,
    return_date DATE NULL,
    status ENUM('active', 'returned', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tx_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
