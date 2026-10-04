# K.B. Library Management System

A web-based Library Management System built with PHP, MySQL, HTML5, and CSS3.

![K.B. Library Logo](assets/images/logo.png)

## Features

- **Dual-Role Authentication**: Dedicated portals for Members and Administrative Staff.
- **Member Dashboard**:
  - Book catalog with cover previews (.png), author names, and digital file sizes in MB.
  - One-click Rent and Buy actions.
  - Active rentals and return ledger.
- **Admin Panel & Management Suite**:
  - Operational metrics and live circulation analytics.
  - Book inventory catalog with direct author input and .png cover management.
  - Author directory with biography and catalog book tracking.
  - Full Member CRUD (Create, Read, Update, Delete with loan safeguards).
  - Complete Transactions Ledger with filters (Rent vs Sold).
- **Zero External Dependencies**: Pure native CSS, system fonts, and local .png assets.

## Default Credentials

| Role | Email | Password |
| :--- | :--- | :--- |
| **Administrator** | admin@library.local | admin123 |
| **Member** | user@library.local | user123 |

## Quick Start

Run the server using the included batch file:
```cmd
run_server.bat
```
Or start via PHP CLI:
```cmd
php -S localhost:8000
```
Then navigate to: `http://localhost:8000`
