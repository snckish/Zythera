# Zythera — A Premium Furniture Marketplace

COMP 010 - Information Management | Final Project

## Setup

1. Import `zythera_db.sql` into MySQL to create the database and tables.
2. Copy the project folder to your web server (e.g. `htdocs/` in XAMPP).
3. Open `config.php` and update `DB_HOST`, `DB_USER`, and `DB_PASS` to match your environment.
4. Visit `http://localhost/Zythera-main/` in your browser.

## Default Admin Credentials

After importing the SQL, log in with any of the seeded admin accounts.  
Default password for all seed admins: `123456qw` — **change this before any real deployment.**

## File Overview

| File | Purpose |
|---|---|
| `website.php` | Main storefront (browse, cart, reviews, contact) |
| `admin.php` | Admin dashboard (products, orders, users) |
| `checkout.php` | Checkout and payment proof upload |
| `profile.php` | User profile and order history |
| `logsign.php` | Login and signup |
| `config.php` | DB connection, shared functions |
| `zythera_db.sql` | Full database schema with stored procedures |
