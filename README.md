# SoftgeniousDev Inventory & Sales Management System

A full-stack **Inventory & Sales Management System** built with **PHP, MySQL, JavaScript, HTML and CSS**.

The system is designed to manage products, stock, suppliers, customers, purchases and sales from a centralized web dashboard.

It was developed as a practical software engineering project to demonstrate backend development, database design, authentication, security, REST APIs, reporting and responsive web development.

---

## Features

### Authentication & User Management

* User registration and login
* Secure password hashing
* Session-based authentication
* Role-based access control
* Admin and regular user roles
* Admin user management
* Protected application routes
* Secure logout
* CSRF protection

### Inventory Management

* Add, edit and view products
* Product SKU management
* Product categories
* Supplier relationships
* Buying and selling prices
* Stock quantity tracking
* Reorder-level configuration
* Low-stock monitoring
* Product image uploads
* Product QR codes
* Product search

### Supplier Management

* Add suppliers
* Edit supplier information
* Supplier contact details
* Supplier-product relationships
* Supplier statistics

### Customer Management

* Add customers
* Customer contact information
* Customer sales history
* Customer statistics

### Purchase Management

* Create purchases
* Select suppliers
* Add multiple products to a purchase
* Automatic purchase totals
* Automatic stock increases
* Purchase history
* Purchase details

### Sales Management

* Create sales
* Walk-in customer support
* Registered customer support
* Multiple products per sale
* Automatic sales totals
* Automatic stock deduction
* Stock validation before completing a sale
* Sale history
* Sale details

### Reports

* Sales summaries
* Revenue summaries
* Top-selling products
* Current stock reports
* Inventory valuation
* Low-stock reporting
* Date-range filtering
* PDF report generation

### REST API

The application includes a protected REST API.

Available endpoints include:

* API login
* API logout
* Protected product listing
* Individual product lookup
* Bearer token authentication

API tokens are stored as SHA-256 hashes in the database and can expire automatically.

### PDF Documents

* PDF sales receipts
* PDF inventory reports
* TCPDF integration

### Security

The project implements several security practices:

* Password hashing using PHP's `password_hash()`
* Password verification using `password_verify()`
* Prepared SQL statements
* PDO
* CSRF protection
* Session regeneration after login
* Role-based authorization
* Bearer token authentication
* Hashed API tokens
* Input validation
* File upload validation
* Transaction-based database operations
* Protected admin routes
* Error logging without exposing internal errors to users

---

## Technology Stack

| Technology | Purpose                       |
| ---------- | ----------------------------- |
| PHP 8.2    | Backend application           |
| MySQL      | Relational database           |
| PDO        | Secure database access        |
| HTML5      | Application structure         |
| CSS3       | Responsive interface          |
| JavaScript | Client-side functionality     |
| AJAX       | Product search                |
| TCPDF      | PDF generation                |
| Composer   | PHP dependency management     |
| Git        | Version control               |
| GitHub     | Source code hosting           |
| XAMPP      | Local development environment |

---

## Database Structure

The application uses a relational MySQL database.

Main tables include:

```text
users
suppliers
customers
products
purchases
purchase_items
sales
sale_items
api_tokens

The database uses primary keys, foreign keys and relational constraints to maintain data integrity.

For example:


Suppliers
    |
    └── Products

Suppliers
    |
    └── Purchases
            |
            └── Purchase Items
                    |
                    └── Products

Customers
    |
    └── Sales
            |
            └── Sale Items
                    |
                    └── Products

---

## Application Structure


softgeniousdev-inventory/
│
├── admin/
│   ├── add_user.php
│   └── users.php
│
├── api/
│   ├── auth.php
│   ├── login.php
│   ├── logout.php
│   └── products.php
│
├── auth/
│   ├── login.php
│   ├── logout.php
│   └── register.php
│
├── config/
│   ├── database.php
│   ├── error_handler.php
│   └── logger.php
│
├── customers/
│   ├── add.php
│   ├── delete.php
│   └── index.php
│
├── includes/
│   ├── admin.php
│   ├── auth.php
│   ├── csrf.php
│   └── sidebar.php
│
├── products/
│   ├── add.php
│   ├── delete.php
│   ├── edit.php
│   ├── index.php
│   ├── qr.php
│   ├── search.php
│   └── view.php
│
├── purchases/
│   ├── add.php
│   ├── index.php
│   └── view.php
│
├── reports/
│   ├── index.php
│   └── pdf.php
│
├── sales/
│   ├── add.php
│   ├── index.php
│   ├── receipt.php
│   └── view.php
│
├── suppliers/
│   ├── add.php
│   ├── delete.php
│   └── index.php
│
├── composer.json
├── composer.lock
├── index.php
└── README.md


Runtime directories such as `uploads/`, `logs/` and Composer's `vendor/` directory are excluded from version control.

---

## Installation

### Requirements

* PHP 8.2 or later
* MySQL
* Apache
* Composer
* Git
* XAMPP or another PHP development environment

### 1. Clone the repository

```bash
git clone https://github.com/SoftgeniousDev/SoftgeniousDev-Inventory-System.git
```

Move the project into your web server directory.

For XAMPP:


C:\xampp\htdocs\


### 2. Create the database

Create a MySQL database named:


softgeniousdev_inventory


Import the project's database schema into the database.

### 3. Configure the database

Update:


config/database.php

with the appropriate local or production database credentials.

Example:


$host = 'localhost';
$dbname = 'softgeniousdev_inventory';
$username = 'root';
$password = '';


Do not commit production credentials to GitHub.

### 4. Install Composer dependencies

From the project directory:


composer install


### 5. Start the application

Start Apache and MySQL through XAMPP.

Then open:


http://localhost/softgeniousdev-inventory/

---

## API Example

### Login


POST /api/login.php
Content-Type: application/json

Example request:


{
    "email": "user@example.com",
    "password": "password"
}

The API returns a bearer token.

Protected requests use:


Authorization: Bearer YOUR_TOKEN

### Products


GET /api/products.php
Authorization: Bearer YOUR_TOKEN


Individual product:


GET /api/products.php?id=1
Authorization: Bearer YOUR_TOKEN

---

## Stock Management Logic

The system keeps inventory synchronized with purchases and sales.

When a purchase is completed:


Purchase
   ↓
Purchase Items
   ↓
Product Stock INCREASES

When a sale is completed:


Sale
   ↓
Sale Items
   ↓
Stock Validation
   ↓
Product Stock DECREASES


Sales use database transactions and row-level locking to reduce the risk of inconsistent stock when multiple operations occur.

---

## Error Handling

The application includes centralized error handling and application logging.

Internal errors are logged to:


logs/app.log

The logs directory is excluded from Git version control.

Users receive a generic error message instead of sensitive internal PHP or database information.

---

## Development

This project was developed locally using:

Windows
XAMPP
Apache
MySQL
PHP
VS Code
Git
Composer

The project follows a modular PHP structure with separate areas for authentication, administration, products, customers, suppliers, purchases, sales, reports and API functionality.

---

## Project Goals

The project was built to demonstrate practical experience with:

* Backend PHP development
* Relational database design
* CRUD operations
* Authentication
* Authorization
* Secure coding
* REST API development
* Database transactions
* Inventory business logic
* PDF generation
* File uploads
* AJAX
* Responsive interfaces
* Error handling
* Version control

---

## Author

**Andrew Omanga Ombogo**

Electrical & Electronics Engineering Student
Machakos University

### GitHub

**SoftgeniousDev**

---

## Project Status

**Completed — portfolio project**

The application is functional locally and is being prepared for live deployment.

---

## License

This project is intended primarily as a portfolio and learning project.

Please contact the author before using the project commercially.
