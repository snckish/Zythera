<?php
require_once 'config.php';

// ── Admin-only access control ─────────────────────────────────
// Only users with role = 'admin' in the database can enter.
$loggedIn = $_SESSION['logged_in_user'] ?? null;

// Not logged in at all → go to login page
if (!$loggedIn) {
    header('Location: logsign.php');
    exit;
}

// Check role from session (set during login) or from DB
$adminRole = $_SESSION['role'] ?? '';
if ($adminRole !== 'admin') {
    // Double-check from DB in case session was tampered
    if (!isAdminEmail($loggedIn)) {
        header('Location: website.php');
        exit;
    }
    $_SESSION['role'] = 'admin'; // fix session
}
// ─────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZYTHERA | ADMIN</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,700&family=Roboto:wght@300;400;500;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
    <style>
    :root{--logo-font:'Playfair Display',serif;--ui-font:'Roboto',sans-serif;--text-font:'Merriweather',serif}
    body{font-family:var(--ui-font);}
    h1,h2,h3,h4,h5,.navbar-brand,.brand-name,.section-title,.page-header h2,footer .footer-brand{font-family:var(--logo-font);}
    p,small,.caption,.text-muted{font-family:var(--text-font);}
    </style>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="dark-mode.css">
    <script src="dark-mode.js"></script>

    <style>
        :root {
            --cream: #f5f2ec;
            --sage-light: #d4e4d4;
            --sage-dark: #7aab7a;
            --deep-green: #2d5a2d;
            --white: #ffffff;
        }

        body { 
            background-color: var(--cream); 
            font-family: 'Roboto', sans-serif;
            color: var(--deep-green);
        }

        .brand-admin { 
            font-family: 'Playfair Display', serif;
            font-weight: 700; 
            font-size: 1.6rem;
            letter-spacing: 1px;
            color: var(--deep-green);
            text-decoration: none;
        }

        .navbar { 
            background: var(--white); 
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 0.8rem 2rem;
        }

        .card {
            border: none;
            border-radius: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            background-color: var(--white);
        }

        .form-control, .form-select {
            background-color: var(--sage-light);
            border: 2px solid transparent;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            color: var(--deep-green);
            font-family: var(--text-font);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background-color: var(--white);
            border-color: var(--deep-green);
            box-shadow: 0 0 0 3px rgba(45,90,45,.15);
            color: var(--deep-green);
        }

        .form-control::placeholder {
            color: rgba(45,90,45,.5);
        }

        .form-select:focus {
            border-color: var(--deep-green);
            box-shadow: 0 0 0 3px rgba(45,90,45,.15);
            color: var(--deep-green);
        }

        .form-label {
            color: var(--deep-green);
            font-family: var(--text-font);
        }

        .btn-zythera {
            background-color: var(--deep-green);
            color: white;
            border-radius: 50px;
            padding: 0.6rem 2rem;
            font-weight: 500;
            border: none;
            transition: 0.3s;
        }

        .btn-zythera:hover {
            background-color: var(--sage-dark);
            color: white;
            transform: translateY(-2px);
        }

        .btn-edit {
            background-color: var(--sage-light);
            color: var(--deep-green);
            border-radius: 10px;
        }

        .table thead { background-color: var(--sage-light); }

        .table th {
            border: none;
            padding: 1rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .user-capsule {
            background: var(--white);
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 50px;
            padding: 5px 5px 5px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-info-text { text-align: right; line-height: 1.2; }

        .user-name {
            font-weight: 600;
            color: var(--deep-green);
            display: block;
            font-size: 0.95rem;
        }

        #datetime { font-size: 0.75rem; color: var(--deep-green); opacity: .65; display: block; }

        .user-avatar {
            background-color: var(--deep-green);
            color: var(--white);
            width: 40px; height: 40px;
            min-width: 40px; min-height: 40px;
            max-width: 40px; max-height: 40px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; font-size: 0.85rem; letter-spacing: 0.5px;
            overflow: hidden;
            flex-shrink: 0;
        }

        /* ── Search Bar ── */
        .search-wrap { position: relative; max-width: 340px; }

        .search-wrap input {
            width: 100%;
            height: 42px;
            padding: 0 2.4rem 0 2.6rem;
            border-radius: 50px;
            border: 2px solid var(--sage-light);
            background: var(--sage-light);
            font-size: .88rem;
            color: var(--deep-green);
            outline: none;
            transition: .2s;
            font-family: 'Roboto', sans-serif;
        }

        .search-wrap input:focus {
            border-color: var(--sage-dark);
            background: #fff;
        }

        .search-icon {
            position: absolute;
            left: 13px; top: 50%;
            transform: translateY(-50%);
            color: var(--sage-dark);
            font-size: .85rem;
            pointer-events: none;
        }

        .clear-btn {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--deep-green); opacity: .5; font-size: .85rem;
            cursor: pointer; display: none; line-height: 1;
        }

        /* Highlight matched text */
        mark {
            background: #c8ecc8;
            color: var(--deep-green);
            border-radius: 3px;
            padding: 0 2px;
        }

        /* No results row */
        #noResults td {
            color: var(--deep-green);
            opacity: .5;
            font-size: .9rem;
            padding: 2rem 0;
        }

        #result-count {
            font-size: .78rem;
            color: var(--deep-green);
            opacity: .7;
            font-weight: 400;
            margin-left: 6px;
        }

        /* ── Force vertical button stack in action columns ── */
        td .d-flex.flex-column {
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
        }

        td .d-flex.flex-column .btn {
            width: 100%;
            text-align: center;
        }

        /* ── Sidebar Layout ── */
        .sidebar {
            position: fixed;
            left: 0; top: 0; bottom: 0;
            width: 240px;
            background: linear-gradient(180deg, #1a2e1a 0%, #2d5a2d 100%);
            z-index: 200;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(0,0,0,.15);
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 24px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }
        .sidebar-brand .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: #fff;
            letter-spacing: 3px;
        }
        .sidebar-brand .brand-sub {
            font-size: .72rem;
            color: rgba(255,255,255,.5);
            letter-spacing: 1px;
        }
        .sidebar-nav {
            padding: 16px 0;
            flex: 1;
        }
        .sidebar-label {
            font-size: .65rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,.35);
            padding: 14px 20px 6px;
            font-weight: 600;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 20px;
            color: rgba(255,255,255,.75);
            text-decoration: none;
            font-size: .88rem;
            font-weight: 500;
            transition: .2s;
            border-left: 3px solid transparent;
            cursor: pointer;
            background: none;
            border-top: none;
            border-right: none;
            border-bottom: none;
            width: 100%;
            text-align: left;
        }
        .sidebar-link:hover, .sidebar-link.active {
            background: rgba(255,255,255,.1);
            color: #fff;
            border-left-color: #d4e4d4;
        }
        .sidebar-link i { width: 18px; text-align: center; font-size: .9rem; }
        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,.1);
        }
        .sidebar-footer a {
            color: rgba(255,255,255,.55);
            font-size: .8rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: .2s;
        }
        .sidebar-footer a:hover { color: #fff; }

        /* Main content offset */
        .main-content {
            margin-left: 240px;
        }
        .top-navbar {
            background: var(--white);
            border-bottom: 1px solid rgba(0,0,0,.05);
            padding: 0.8rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        body.dark .sidebar-link,
        body.dark .sidebar-link.active,
        body.dark .sidebar-label,
        body.dark .sidebar-footer,
        body.dark .sidebar-footer a {
            color: #f5fbf5 !important;
        }
        body.dark .sidebar-link {
            background: rgba(255,255,255,.02) !important;
            border-color: rgba(255,255,255,.08) !important;
        }
        body.dark .sidebar-link.active {
            background: rgba(90,158,90,.22) !important;
            border-left-color: rgba(255,255,255,.14) !important;
        }

        .order-card {
            background: #fff;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 12px;
            border-left: 4px solid var(--sage-dark);
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            overflow: hidden;
        }
        .order-user-tag {
            display: inline-block;
            background: var(--sage-light);
            color: var(--deep-green);
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 600;
            padding: 2px 10px;
            margin-bottom: 6px;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: middle;
        }
        /* Order header row — wraps nicely on narrow screens */
        .order-header-row {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }
        /* Status dropdown — never overflows on mobile */
        #section-orders select[id^="status-sel-"] {
            max-width: 100%;
            box-sizing: border-box;
        }
        /* Detail panel grid — single column on small screens */
        .order-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
        }
        .order-detail-grid .full-width { grid-column: 1 / -1; }
        /* Pay-box buttons row */
        .pay-btn-row {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .pay-btn-row button { flex: 1; min-width: 70px; }
        /* Responsive breakpoints */
        @media (max-width: 768px) {
            .order-detail-grid { grid-template-columns: 1fr; }
            .order-detail-grid .full-width { grid-column: 1; }
            .order-user-tag { max-width: 140px; }
            #section-orders select[id^="status-sel-"] { min-width: unset !important; width: 100%; }
        }
        @media (max-width: 576px) {
            .order-card { padding: 10px 12px; }
            .pay-btn-row { flex-direction: column; }
            .pay-btn-row button { width: 100%; }
        }

        /* ══════════════════════════════════════════
           DARK MODE — full admin panel overrides
           ══════════════════════════════════════════ */
        body.dark {
            --cream: #0f1a0f;
            --sage-light: #1e3a1e;
            --sage-dark: #5a9e5a;
            --deep-green: #a8d5a8;
            --white: #1a2a1a;
            background-color: #0f1a0f !important;
            color: #d4e8d4 !important;
        }

        /* Navbar / top bar */
        body.dark .top-navbar {
            background: #1a2a1a !important;
            border-bottom-color: rgba(255,255,255,.08) !important;
        }
        body.dark #sectionTitle { color: #a8d5a8 !important; }

        /* User capsule */
        body.dark .user-capsule {
            background: #1e3a1e !important;
            border-color: rgba(255,255,255,.1) !important;
        }
        body.dark .user-name { color: #c8e8c8 !important; }
        body.dark #datetime  { color: #a8d5a8 !important; }

        /* Cards */
        body.dark .card {
            background-color: #1a2a1a !important;
            box-shadow: 0 4px 20px rgba(0,0,0,.4) !important;
        }

        /* Form controls */
        body.dark .form-control,
        body.dark .form-select {
            background-color: #1e3a1e !important;
            color: #d4e8d4 !important;
            border-color: #2e4a2e !important;
        }
        body.dark .form-control:focus,
        body.dark .form-select:focus {
            background-color: #243c24 !important;
            border-color: #5a9e5a !important;
            color: #e8f5e8 !important;
        }
        body.dark .form-control::placeholder { color: rgba(168,213,168,.45) !important; }
        body.dark .form-label { color: #a8d5a8 !important; }

        /* Tables — Bootstrap 5 uses CSS vars, must override all of them */
        body.dark .table {
            color: #e8f5e8 !important;
            --bs-table-bg: #1a2a1a;
            --bs-table-striped-bg: #1e3a1e;
            --bs-table-hover-bg: #243c24;
            --bs-table-color: #e8f5e8;
            --bs-table-border-color: #2e4a2e;
        }
        body.dark .table thead,
        body.dark .table-success {
            background-color: #1e3a1e !important;
            color: #c8e8c8 !important;
            --bs-table-bg: #1e3a1e;
            --bs-table-color: #c8e8c8;
        }
        body.dark .table th {
            color: #c8e8c8 !important;
            background-color: #1e3a1e !important;
            border-color: #2e4a2e !important;
        }
        body.dark .table td {
            color: #e8f5e8 !important;
            background-color: #1a2a1a !important;
            border-color: #2e4a2e !important;
        }
        body.dark .table tbody tr { background-color: #1a2a1a !important; }
        body.dark .table-hover tbody tr:hover td { background-color: #243c24 !important; }
        body.dark .table-bordered { border-color: #2e4a2e !important; }
        body.dark .table-bordered td,
        body.dark .table-bordered th { border-color: #2e4a2e !important; }
        /* Messages table */
        body.dark #section-messages .table td,
        body.dark #section-messages .table th { color: #e8f5e8 !important; background-color: #1a2a1a !important; }
        body.dark #section-messages .table thead tr th { background-color: #1e3a1e !important; color: #c8e8c8 !important; }
        body.dark #section-messages .table td.fw-semibold { color: #ffffff !important; }
        body.dark #section-messages .table td[style*="color:#999"] { color: #8ab88a !important; }

        /* Search bar */
        body.dark .search-wrap input {
            background: #1e3a1e !important;
            border-color: #2e4a2e !important;
            color: #d4e8d4 !important;
        }
        body.dark .search-wrap input:focus {
            background: #243c24 !important;
            border-color: #5a9e5a !important;
        }
        body.dark .search-icon { color: #5a9e5a !important; }
        body.dark .clear-btn   { color: #a8d5a8 !important; }

        /* Buttons */
        body.dark .btn-edit {
            background-color: #1e3a1e !important;
            color: #a8d5a8 !important;
            border: 1px solid #2e4a2e !important;
        }
        body.dark .btn-outline-success {
            color: #5a9e5a !important;
            border-color: #5a9e5a !important;
        }
        body.dark .btn-outline-success:hover {
            background-color: #5a9e5a !important;
            color: #fff !important;
        }
        body.dark .btn-outline-secondary {
            color: #a8d5a8 !important;
            border-color: #2e4a2e !important;
        }

        /* Result count */
        body.dark #result-count { color: #7aab7a !important; }

        /* Highlight mark */
        body.dark mark {
            background: #2d5a2d !important;
            color: #c8f0c8 !important;
        }

        /* No-results row */
        body.dark #noResults td { color: #7aab7a !important; }

        /* Order cards */
        body.dark .order-card {
            background: #1a2a1a !important;
            border-left-color: #5a9e5a !important;
            box-shadow: 0 2px 10px rgba(0,0,0,.3) !important;
        }
        body.dark .order-user-tag {
            background: #1e3a1e !important;
            color: #a8d5a8 !important;
        }

        /* Order inline styles — hardcoded bg overrides */
        body.dark [style*="background:#f9f9f6"],
        body.dark [style*="background: #f9f9f6"] {
            background: #1e3a1e !important;
        }
        body.dark [style*="background:#f0f7f0"],
        body.dark [style*="background: #f0f7f0"] {
            background: #1e3a1e !important;
        }
        body.dark [style*="background:#f5f2f0"],
        body.dark [style*="background: #f5f2f0"] {
            background: #242424 !important;
        }

        /* Order detail panel boxes */
        body.dark div[style*="border-radius:10px"][style*="padding:10px"] {
            background: #1e3a1e !important;
        }
        body.dark div[style*="border-top:2px dashed"] {
            border-top-color: #2e4a2e !important;
        }
        body.dark [style*="border-bottom:1px dashed"] {
            border-bottom-color: #2e4a2e !important;
        }

        /* Hardcoded text colors — all bumped to readable whites/light greens */
        body.dark [style*="color:#1a2e1a"],
        body.dark [style*="color: #1a2e1a"] {
            color: #e8f5e8 !important;
        }
        body.dark [style*="color:#2d5a2d"],
        body.dark [style*="color: #2d5a2d"] {
            color: #c8e8c8 !important;
        }
        body.dark [style*="color:#888"],
        body.dark [style*="color: #888"] {
            color: #9ab89a !important;
        }
        body.dark [style*="color:#666"],
        body.dark [style*="color: #666"] {
            color: #a8c8a8 !important;
        }
        body.dark [style*="color:#444"],
        body.dark [style*="color: #444"] {
            color: #c8e8c8 !important;
        }
        body.dark [style*="color:#999"],
        body.dark [style*="color: #999"] {
            color: #8ab88a !important;
        }
        body.dark [style*="color:#7aab7a"] {
            color: #7acc7a !important;
        }

        /* Payment method tag — #f5f2f0 bg with #666 text → both need overrides */
        body.dark span[style*="background:#f5f2f0"],
        body.dark span[style*="background: #f5f2f0"] {
            background: #2a3a2a !important;
            color: #c8e8c8 !important;
        }

        /* Status select dropdowns inside orders */
        body.dark select[id^="status-sel-"] {
            background: #1e3a1e !important;
            color: #d4e8d4 !important;
            border-color: #2e4a2e !important;
        }

        /* Detail toggle button */
        body.dark button[style*="background:#f0f7f0"] {
            background: #1e3a1e !important;
            color: #a8d5a8 !important;
            border-color: #2e4a2e !important;
        }

        /* Order ID badge */
        body.dark span[style*="background:#f0f7f0"] {
            background: #1e3a1e !important;
            color: #a8d5a8 !important;
        }

        /* User summary stat boxes */
        body.dark div[style*="flex:1"][style*="background:#f9f9f6"] {
            background: #1e3a1e !important;
        }
        body.dark div[style*="background:#f9f9f6"] {
            background: #1e3a1e !important;
        }

        /* Analytics icon bg tints */
        body.dark div[style*="background:#2d5a2d18"] {
            background: rgba(90,158,90,.15) !important;
        }

        /* Status badges — pastel backgrounds become invisible in dark mode */
        body.dark span[id^="status-badge-"] {
            filter: brightness(0.6) saturate(1.8) !important;
            color: #fff !important;
        }

        /* Order item product name text */
        body.dark .order-card span[style*="min-width:0"] { color: #e8f5e8 !important; }

        /* Shipping fee / date text */
        body.dark .order-card [style*="color:#888"] { color: #9ab89a !important; }

        /* Order total text */
        body.dark .order-card .text-end.fw-bold { color: #7acc7a !important; }

        /* Text-muted utility */
        body.dark .text-muted { color: #8ab88a !important; }

        /* Small / caption text */
        body.dark small.text-muted { color: #8ab88a !important; }

        /* Product image border */
        body.dark img[style*="border:1px solid #e5e5e5"] {
            border-color: #2e4a2e !important;
            background: #1a2a1a !important;
        }

        /* Section icon boxes (sage-light bg) */
        body.dark div[style*="background:var(--sage-light)"],
        body.dark div[style*="background: var(--sage-light)"] {
            background: #1e3a1e !important;
        }

  /* ── LOGOUT CONFIRMATION MODAL ── */   /* ── LOGOUT CONFIRMATION MODAL ── */
    .logout-modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.6);
      z-index: 10000;
      align-items: center;
      justify-content: center;
      backdrop-filter: blur(3px);
    }
    .logout-modal-overlay.active { display: flex; }

    .logout-modal {
      background: #fff;
      border-radius: 20px;
      padding: 32px 28px;
      width: min(420px, calc(100vw - 32px));
      box-shadow: 0 20px 60px rgba(0,0,0,.3);
      text-align: center;
      animation: slideDown .3s ease-out;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .logout-modal h2 {
      font-family: 'Playfair Display', serif;
      color: var(--deep);
      font-size: 1.3rem;
      margin: 0 0 12px 0;
      font-weight: 700;
    }

    .logout-modal p {
      color: #666;
      font-size: .95rem;
      margin: 0 0 24px 0;
      line-height: 1.5;
    }

    .logout-modal-buttons {
      display: flex;
      gap: 12px;
      justify-content: center;
    }

    .logout-modal-buttons button {
      padding: 12px 28px;
      border-radius: 50px;
      border: none;
      font-weight: 600;
      font-size: .9rem;
      cursor: pointer;
      transition: .2s ease;
      font-family: var(--ui-font);
    }

    .logout-cancel-btn {
      background: #f0ece4;
      color: #555;
    }
    .logout-cancel-btn:hover {
      background: #e2ddd4;
    }

    .logout-confirm-btn {
      background: var(--deep-green);
      color: #fff;
      min-width: 120px;
    }
    .logout-confirm-btn:hover {
      background: var(--sage-dark);
    }
    .logout-confirm-btn:active {
      transform: scale(0.98);
    }
    </style>
<script>
/* ZYTHERA dark mode — apply before paint to prevent flash */
(function(){
  if(localStorage.getItem('zythera_dark')==='1'){
    document.documentElement.style.background='#111e11';
    document.addEventListener('DOMContentLoaded',function(){
      document.body.classList.add('dark');
      document.documentElement.style.background='';
    });
  }
})();
</script>
</head>
<body>

<!-- ── LOGOUT MODAL ── -->

<div id="logoutModalOverlay" class="logout-modal-overlay">
    <div class="logout-modal">
        <h2>Log Out Confirmation</h2>
        <p>Are you sure you want to log out of your account?</p>
        <div class="logout-modal-buttons">
            <button type="button" class="logout-cancel-btn" onclick="closeLogoutModal(event)">
                Stay
            </button>
            <button type="button" class="logout-confirm-btn" onclick="performLogout()">
                Logout
            </button>
        </div>
    </div>
</div>

<!-- ── SIDEBAR ── -->
<div class="sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <div class="brand-name"><span style="font-family: 'Playfair Display', serif; color: var(--deep); font-weight: 700;"> ZYTHERA </span></div>
        <div class="brand-sub">Admin Panel</div>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-label">Main</div>
        <button class="sidebar-link active" onclick="showSection('inventory')" id="nav-inventory">
            <i class="fas fa-boxes"></i> Product Inventory
        </button>
        <button class="sidebar-link" onclick="showSection('addproduct')" id="nav-addproduct">
            <i class="fas fa-plus-circle"></i> Add Product
        </button>
        <button class="sidebar-link" onclick="showSection('analytics')" id="nav-analytics">
            <i class="fas fa-chart-bar"></i> Analytics
        </button>

        <div class="sidebar-label" style="margin-top:8px;">Orders</div>
        <button class="sidebar-link" onclick="showSection('orders')" id="nav-orders" style="display:flex;align-items:center;justify-content:space-between;">
            <span><i class="fas fa-receipt me-2"></i>Order History</span>
            <?php
            $pendingCount = 0;
            try {
                $pStmt = getDBConnection()->query("SELECT COUNT(*) FROM orders WHERE order_status='Pending'");
                $pendingCount = (int)$pStmt->fetchColumn();
            } catch(Exception $e) {}
            if ($pendingCount > 0): ?>
            <span id="pending-badge" style="background:#dc2626;color:#fff;border-radius:50px;font-size:.6rem;font-weight:700;padding:2px 7px;"><?= $pendingCount ?></span>
            <?php endif; ?>
        </button>
        <button class="sidebar-link" onclick="showSection('users')" id="nav-users">
            <i class="fas fa-users"></i> User Summary
        </button>
        <button class="sidebar-link" onclick="showSection('reviews')" id="nav-reviews">
            <i class="fas fa-star"></i> Reviews
        </button>
        <button class="sidebar-link" onclick="showSection('messages')" id="nav-messages">
            <i class="fas fa-envelope"></i> Messages
        </button>

        <div class="sidebar-label" style="margin-top:8px;">Store</div>
        <a href="website.php" class="sidebar-link">
            <i class="fas fa-store"></i> View Store
        </a>
        <a href="profile.php" class="sidebar-link">
            <i class="fas fa-user-circle"></i> My Profile
        </a>
    </nav>

    <div class="sidebar-footer">
        <?php
        $adminEmail = $_SESSION['logged_in_user'] ?? 'Admin';

        $adminData = findAccountByEmail($adminEmail);

        $adminName = $adminData ? $adminData->name : 'Admin';
        $adminPic = getAvatarURL($adminData->profile_pic ?? null, $adminEmail ?? null, $adminName ?? null, 40);
        ?>
        <div style="color:rgba(255,255,255,.7);font-size:.8rem;margin-bottom:10px;">
            <i class="fas fa-user-shield me-2"></i><?= htmlspecialchars($adminName) ?>
        </div>
        <a href="javascript:void(0)" onclick="openLogoutModal()"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<!-- ── MAIN CONTENT ── -->
<div class="main-content">

<nav class="top-navbar d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-3">
        <h6 class="mb-0 fw-bold" style="color:var(--deep-green);font-family:'Playfair Display',serif;font-size:1.1rem;letter-spacing:.5px;">
            <span id="sectionTitle">Product Inventory</span>
        </h6>
    </div>
    <div class="user-capsule shadow-sm">
        <div class="user-info-text">
            <span class="user-name">Welcome back, <?= htmlspecialchars($adminName) ?></span>
            <span id="datetime" style="font-size:.7rem;color:var(--deep-green);opacity:.65;display:block;"></span>
        </div>
        <div class="user-avatar"><img src="<?= htmlspecialchars($adminPic) ?>" alt="Admin" style="width:40px;height:40px;min-width:40px;min-height:40px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.12);display:block;"></div>
    </div>
</nav>

<div class="container py-4">

<!-- ── SECTION: Product Inventory ── -->
<div id="section-inventory">
<div class="card p-3 mt-3">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <span id="result-count"></span>
        <div class="search-wrap">
            <i class="fas fa-search search-icon"></i>
            <input type="text"
                   id="searchInput"
                   placeholder="Search products…"
                   oninput="searchProducts(this.value)"
                   autocomplete="off">
            <button class="clear-btn" id="clearBtn" onclick="clearSearch()" title="Clear">✕</button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle text-center">
            <thead class="table-success">
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Size</th>
                    <th>Color</th>
                    <th>Price</th>
                    <th>Description</th>
                    <th>Stock</th>
                    <th>Category</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="inventoryBody">

<?php
$inventory   = $_SESSION['inventory'] ?? [];
$searchQuery = trim($_GET['search'] ?? '');
uasort($inventory, fn($a,$b) => strcmp((string)$a->inv_id, (string)$b->inv_id));
if ($searchQuery !== '') {
    $needle = strtolower($searchQuery);
    $inventory = array_filter($inventory, function($item) use ($needle) {
        return strpos(strtolower((string)($item->name??'')), $needle) !== false
            || strpos(strtolower((string)($item->color??'')), $needle) !== false
            || strpos(strtolower((string)($item->category??'')), $needle) !== false
            || strpos(strtolower((string)($item->description??'')), $needle) !== false
            || strpos(strtolower((string)($item->size??'')), $needle) !== false;
    });
}
?>

<?php foreach($inventory as $item): ?>
<tr class="product-row"
    data-name="<?=        htmlspecialchars(strtolower((string)($item->name        ?? ''))) ?>"
    data-color="<?=       htmlspecialchars(strtolower((string)($item->color       ?? ''))) ?>"
    data-category="<?=    htmlspecialchars(strtolower((string)($item->category    ?? ''))) ?>"
    data-description="<?= htmlspecialchars(strtolower((string)($item->description ?? ''))) ?>"
    data-size="<?=        htmlspecialchars(strtolower((string)($item->size        ?? ''))) ?>">

    <td><?= $item->inv_id ?></td>
    <td>
        <img src="<?= htmlspecialchars($item->image) ?>" width="50" style="border-radius:8px;"
             onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=50&fit=crop'">
    </td>
    <td class="s-name"><?=        htmlspecialchars($item->name) ?></td>
    <td class="s-size"><?=        htmlspecialchars($item->size) ?></td>
    <td class="s-color"><?=       htmlspecialchars($item->color) ?></td>
    <td>₱<?= number_format((float)$item->price, 2) ?></td>
    <td class="s-desc" style="font-size:.82rem;max-width:180px;"><?= htmlspecialchars($item->description) ?></td>
    <td>
        <?php $s=(int)$item->stock; ?>
        <span class="badge <?= $s===0?'bg-danger':($s<=5?'bg-warning text-dark':'bg-success') ?>">
            <?= $s===0?'Out of Stock':($s<=5?'Low: '.$s:$s) ?>
        </span>
    </td>
    <td class="s-category"><?= htmlspecialchars($item->category) ?></td>
    <td>
        <div class="d-flex flex-column gap-1">
            <button class="btn btn-edit btn-sm"
                onclick="editProduct('<?= $item->inv_id ?>','<?= addslashes($item->name) ?>','<?= addslashes($item->size) ?>','<?= addslashes($item->color) ?>','<?= $item->price ?>','<?= addslashes($item->description) ?>','<?= $item->stock ?>','<?= addslashes($item->category) ?>','<?= addslashes($item->image) ?>')">
                <i class="fas fa-edit"></i> Edit
            </button>
            <button class="btn btn-outline-success btn-sm"
                onclick="let qty=prompt('Units to add?');if(qty)window.location.href='admin_action.php?restock_id=<?= $item->inv_id ?>&amount='+qty;">
                <i class="fas fa-plus-circle"></i> Restock
            </button>
            <a href="admin_action.php?delete=<?= $item->inv_id ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Delete this product?')">
               <i class="fas fa-trash"></i> Delete
            </a>
        </div>
    </td>
</tr>
<?php endforeach; ?>

<tr id="noResults" style="display:none;">
    <td colspan="10" style="color:#bbb;padding:2rem 0;font-size:.9rem;">
        <i class="fas fa-search me-2 opacity-50"></i>
        No products found for "<span id="noResultsQuery" style="color:#888;"></span>"
    </td>
</tr>

            </tbody>
        </table>
    </div>

</div><!-- /card -->
</div><!-- /section-inventory -->

<!-- ── SECTION: Add Product ── -->
<div id="section-addproduct" style="display:none;">
<div class="card p-4 mt-3" style="max-width:680px;margin:0 auto;">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:44px;height:44px;background:var(--sage-light);border-radius:12px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-plus-circle" style="color:var(--deep-green);font-size:1.1rem;"></i>
        </div>
        <div>
            <h5 class="fw-bold mb-0" style="color:var(--deep-green);">Add / Edit Product</h5>
              </div>
    </div>
    <form method="POST" action="admin_action.php" id="formCard">
        <input type="hidden" name="id" id="pid">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Product Name</label>
                <input class="form-control" name="name" id="pname" placeholder="e.g. Nordic Lounge Chair" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Size</label>
                <input class="form-control" name="size" id="psize" placeholder="e.g. L120 x W80 x H90 cm">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Color(s)</label>
                <input class="form-control" name="color" id="pcolor" placeholder="e.g. Beige, Walnut">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Price (₱)</label>
                <input class="form-control" name="price" id="pprice" placeholder="e.g. 12500">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold small">Description</label>
                <textarea class="form-control" name="description" id="pdesc" rows="3" placeholder="Short description of the product…"></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Stock</label>
                <input class="form-control" name="stock" id="pstock" type="number" min="0" placeholder="0">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Category</label>
                <select class="form-select" name="category" id="pcat">
                    <option value="Sofa">Sofa</option>
                    <option value="Chair">Chair</option>
                    <option value="Set">Set</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Image Path</label>
                <input class="form-control" name="image" id="pimage" placeholder="pci/image.png">
            </div>  
          
            <div class="col-12 d-flex gap-2 mt-2">
<br><br>
                <button type="submit" class="btn btn-zythera flex-fill">
                    <i class="fas fa-save me-2"></i>Save Product
                </button>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4"
                    onclick="resetForm(); showSection('inventory');">Cancel</button>
            </div>
        </div>
    </form>
</div>
</div><!-- /section-addproduct -->

<!-- ── SECTION: Analytics ── -->
<div id="section-analytics" style="display:none;">
<div class="row g-4 mt-2">
    <?php
    $inv = $_SESSION['inventory'] ?? [];
    $totalProducts = count($inv);
    $outOfStock    = count(array_filter($inv, fn($i)=>((int)($i->stock??0))===0));
    $lowStock      = count(array_filter($inv, fn($i)=>((int)($i->stock??0))>0 && ((int)($i->stock??0))<=5));

    // Load users and orders fresh from DB
    $dbUsers       = loadUsers();
    $totalUsers    = count($dbUsers);
    $dbAllOrders   = loadOrders();
    $totalOrders   = count($dbAllOrders);
    $revenue       = 0;
    foreach ($dbAllOrders as $o) {
        if (isset($o->total) && (float)$o->total > 0) {
            $revenue += (float)$o->total;
        } else {
            foreach ($o->items as $oi)
                $revenue += (float)($oi->price ?? 0) * (int)($oi->qty ?? 1);
        }
    }
    $cards = [
        ['icon'=>'fa-boxes','label'=>'Total Products','value'=>$totalProducts,'color'=>'#2d5a2d'],
        ['icon'=>'fa-exclamation-triangle','label'=>'Out of Stock','value'=>$outOfStock,'color'=>'#2d5a2d'],
        ['icon'=>'fa-battery-quarter','label'=>'Low Stock','value'=>$lowStock,'color'=>'#2d5a2d'],
        ['icon'=>'fa-users','label'=>'Registered Users','value'=>$totalUsers,'color'=>'#2d5a2d'],
        ['icon'=>'fa-receipt','label'=>'Total Orders','value'=>$totalOrders,'color'=>'#2d5a2d'],
        ['icon'=>'fa-peso-sign','label'=>'Total Revenue','value'=>'₱'.number_format($revenue),'color'=>'#2d5a2d'],
    ];
    foreach ($cards as $c): ?>
    <div class="col-sm-6 col-lg-4">
        <div class="card p-4" style="border-left:5px solid <?= $c['color'] ?>;">
            <div class="d-flex align-items-center gap-3">
                <div style="width:48px;height:48px;border-radius:14px;background:<?= $c['color'] ?>18;display:flex;align-items:center;justify-content:center;">
                    <i class="fas <?= $c['icon'] ?>" style="color:<?= $c['color'] ?>;font-size:1.2rem;"></i>
                </div>
                <div>
                    <div style="font-size:.75rem;color:var(--deep-green);text-transform:uppercase;letter-spacing:1px;"><?= $c['label'] ?></div>
                    <div style="font-size:1.6rem;font-weight:700;color:<?= $c['color'] ?>;line-height:1.2;"><?= $c['value'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
</div><!-- /section-analytics -->

<!-- ── SECTION: Order History ── -->
<div id="section-orders" style="display:none;">
<div class="card p-4 mt-3">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:44px;height:44px;background:var(--sage-light);border-radius:12px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-receipt" style="color:var(--deep-green);font-size:1.1rem;"></i>
        </div>
        <div>
            <h5 class="fw-bold mb-0" style="color:var(--deep-green);">Order History</h5>
        </div>
    </div>

    <?php
    // Always load orders fresh from DB for admin
    $allOrders2    = loadOrders();
    $totalOrdCount = 0;
    $grandTotal2   = 0;
    foreach ($allOrders2 as $order):
        $oEmail = $order->email ?? '';
        $totalOrdCount++;
        $orderStoredTotal = (float)($order->total ?? 0);
        $orderStatus = $order->status ?? 'Pending';
        $orderId     = $order->order_id ?? '';
        $orderItems  = $order->items ?? [];
        $orderShipping = (float)($order->shipping ?? 0);
        $orderDate   = $order->date ?? '';
        $orderPayMethod  = $order->pay_method    ?? '';
        $orderPayStatus  = $order->pay_status    ?? 'pending';
        $orderPayRef     = $order->pay_reference ?? '';
        $orderPayProof   = $order->pay_proof     ?? '';
        $orderPayId      = $order->payment_id    ?? '';
        // Use flat columns directly from schema
        $shippingInfo = [
            'full_name' => $order->full_name ?? '',
            'phone'     => $order->phone     ?? '',
            'address'   => $order->address   ?? '',
            'barangay'  => $order->barangay  ?? '',
            'city'      => $order->city       ?? '',
            'province'  => $order->province   ?? '',
            'zip'       => $order->zip        ?? '',
        ];
        $shippingAddr = implode(', ', array_filter([
            $shippingInfo['full_name'] ?? '',
            $shippingInfo['address']   ?? '',
            $shippingInfo['city']      ?? '',
            $shippingInfo['province']  ?? '',
        ]));
        $statusColors = [
            'Pending'    => ['bg'=>'#fff7ed','color'=>'#c2410c','border'=>'#fed7aa'],
            'Processing' => ['bg'=>'#eff6ff','color'=>'#1d4ed8','border'=>'#bfdbfe'],
            'Shipped'    => ['bg'=>'#f0f9ff','color'=>'#0369a1','border'=>'#bae6fd'],
            'Delivered'  => ['bg'=>'#f0fdf4','color'=>'#15803d','border'=>'#bbf7d0'],
            'Cancelled'  => ['bg'=>'#fef2f2','color'=>'#b91c1c','border'=>'#fecaca'],
        ];
        $sc = $statusColors[$orderStatus] ?? $statusColors['Pending'];
    ?>
    <div class="order-card mb-3" id="order-card-<?= htmlspecialchars($orderId) ?>">
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
            <?php if ($orderId !== ''): ?>
            <span style="background:#f0f7f0;color:#2d5a2d;border-radius:50px;padding:2px 10px;font-size:.72rem;font-weight:700;">
                #<?= htmlspecialchars($orderId) ?>
            </span>
            <?php endif; ?>
            <span class="order-user-tag"><i class="fas fa-user me-1"></i><?= htmlspecialchars($oEmail) ?></span>

            <?php if ($orderPayMethod !== ''): ?>
            <span style="background:#f5f2f0;color:#666;border-radius:50px;padding:2px 10px;font-size:.72rem;">
                <i class="fas fa-credit-card me-1"></i><?= htmlspecialchars($orderPayMethod) ?>
            </span>
            <?php endif; ?>

            <span id="status-badge-<?= htmlspecialchars($orderId) ?>"
                style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;border:1px solid <?= $sc['border'] ?>;
                border-radius:50px;padding:2px 10px;font-size:.72rem;font-weight:700;">
                <?= htmlspecialchars($orderStatus) ?>
            </span>
            <div class="ms-auto d-flex align-items-center gap-2">
                <small class="text-muted"><i class="fas fa-calendar me-1"></i><?= htmlspecialchars($orderDate) ?></small>
                <button onclick="toggleOrderDetail('<?= htmlspecialchars($orderId, ENT_QUOTES) ?>')"
                  style="background:#f0f7f0;color:#2d5a2d;border:1px solid #d4e4d4;border-radius:8px;padding:3px 10px;font-size:.72rem;font-weight:600;cursor:pointer;white-space:nowrap;">
                  <i class="fas fa-expand-alt me-1"></i>Details
                </button>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
            <select style="min-width:220px;padding:8px 12px;font-size:.82rem;border-radius:10px;border:2px solid #d4e4d4;background:#f9f9f6;color:#2d5a2d;font-family:inherit;cursor:pointer;outline:none;"
                id="status-sel-<?= htmlspecialchars($orderId) ?>"
                onchange="updateOrderStatus('<?= htmlspecialchars($oEmail, ENT_QUOTES) ?>','<?= htmlspecialchars($orderId, ENT_QUOTES) ?>',this.value)">
                <option value=""> Update Status </option>
                <option value="Pending"    <?= $orderStatus === 'Pending'    ? 'selected' : '' ?>>Pending</option>
                <option value="Processing" <?= $orderStatus === 'Processing' ? 'selected' : '' ?>>Processing</option>
                <option value="Shipped"    <?= $orderStatus === 'Shipped'    ? 'selected' : '' ?>>Shipped</option>
                <option value="Delivered"  <?= $orderStatus === 'Delivered'  ? 'selected' : '' ?>>Delivered</option>
                <option value="Cancelled"  <?= $orderStatus === 'Cancelled'  ? 'selected' : '' ?>>Cancelled</option>
            </select>

        </div>
        <?php
        $orderSubtotal2 = 0;
        foreach ($orderItems as $oi):
            $oiPrice = (float)($oi->price ?? 0);
            $oiQty   = (int)($oi->qty ?? 1);
            $oiName  = $oi->product_name ?? 'Item';
            $oiImage = trim((string)($oi->image ?? ''));
            $oiLine  = $oiPrice * $oiQty;
            $orderSubtotal2 += $oiLine;
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:.85rem;padding:6px 0;border-bottom:1px dashed #f0f0eb;gap:10px;">
            <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
                <?php if ($oiImage): ?>
                <img src="<?= htmlspecialchars($oiImage) ?>" alt="<?= htmlspecialchars($oiName) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:12px;border:1px solid #e5e5e5;background:#fff;flex-shrink:0;">
                <?php endif; ?>
                <span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?= htmlspecialchars($oiName) ?> <b style="color:#7aab7a;">×<?= $oiQty ?></b>
                </span>
            </div>
            <?php if ($oiPrice > 0): ?>
            <span style="color:#2d5a2d;font-weight:600;flex-shrink:0;">₱<?= number_format($oiLine) ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if ($orderShipping > 0): ?>
        <div style="display:flex;justify-content:space-between;font-size:.82rem;padding:4px 0;color:#888;">
            <span><i class="fas fa-truck me-1"></i>Shipping Fee</span>
            <span>₱<?= number_format($orderShipping) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($orderStoredTotal > 0): ?>
        <div class="text-end fw-bold mt-2" style="color:#2d5a2d;">
            Order Total: ₱<?= number_format($orderStoredTotal) ?>
        </div>
        <?php $grandTotal2 += $orderStoredTotal; ?>
        <?php elseif ($orderSubtotal2 > 0): ?>
        <div class="text-end fw-bold mt-2" style="color:#2d5a2d;">
            Order Total: ₱<?= number_format($orderSubtotal2) ?>
        </div>
        <?php $grandTotal2 += $orderSubtotal2; ?>
        <?php endif; ?>

        <!-- ── Collapsible Order Detail Panel ─────────── -->
        <div id="detail-<?= htmlspecialchars($orderId) ?>" style="display:none;margin-top:14px;border-top:2px dashed #d4e4d4;padding-top:14px;">
            <div class="order-detail-grid">
              <div style="background:#f9f9f6;border-radius:10px;padding:10px;">
                <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:4px;">Recipient</div>
                <div style="font-weight:600;font-size:.84rem;color:#1a2e1a;"><?= htmlspecialchars($shippingInfo['full_name'] ?? '—') ?></div>
                <div style="font-size:.78rem;color:#666;"><?= htmlspecialchars($shippingInfo['phone'] ?? '') ?></div>
              </div>
              <div style="background:#f9f9f6;border-radius:10px;padding:10px;" id="pay-box-<?= htmlspecialchars($orderId) ?>">
                <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:6px;">Payment</div>
                <div style="font-weight:600;font-size:.84rem;color:#1a2e1a;margin-bottom:4px;"><?= htmlspecialchars($orderPayMethod ?: '—') ?></div>
                <?php
                $psColors = [
                    'pending'  => ['bg'=>'#fff7ed','color'=>'#c2410c','border'=>'#fed7aa'],
                    'verified' => ['bg'=>'#f0fdf4','color'=>'#15803d','border'=>'#bbf7d0'],
                    'rejected' => ['bg'=>'#fef2f2','color'=>'#b91c1c','border'=>'#fecaca'],
                ];
                $psc = $psColors[$orderPayStatus] ?? $psColors['pending'];
                ?>
                <span id="pay-status-badge-<?= htmlspecialchars($orderId) ?>"
                  style="display:inline-block;background:<?= $psc['bg'] ?>;color:<?= $psc['color'] ?>;border:1px solid <?= $psc['border'] ?>;border-radius:50px;padding:1px 9px;font-size:.68rem;font-weight:700;text-transform:capitalize;margin-bottom:8px;">
                  <?= htmlspecialchars($orderPayStatus) ?>
                </span>
                <?php if ($orderPayRef): ?>
                <div style="font-size:.72rem;color:#666;" id="pay-ref-display-<?= htmlspecialchars($orderId) ?>">
                  Ref: <span id="pay-ref-text-<?= htmlspecialchars($orderId) ?>"><?= htmlspecialchars($orderPayRef) ?></span>
                </div>
                <?php else: ?>
                <div style="font-size:.72rem;color:#aaa;" id="pay-ref-display-<?= htmlspecialchars($orderId) ?>">
                  Ref: <span id="pay-ref-text-<?= htmlspecialchars($orderId) ?>">—</span>
                </div>
                <?php endif; ?>

                <!-- Proof of Payment -->
                <?php if ($orderPayProof): ?>
                <div style="margin-top:8px;" id="proof-display-<?= htmlspecialchars($orderId) ?>">
                  <div style="font-size:.68rem;color:#888;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px;"><i class="fas fa-image me-1"></i>Proof of Payment</div>
                  <a href="<?= htmlspecialchars($orderPayProof) ?>" target="_blank" onclick="openProofLightbox(this.href,event)"
                     style="display:block;border-radius:8px;overflow:hidden;border:1.5px solid #d4e4d4;max-width:140px;">
                    <img src="<?= htmlspecialchars($orderPayProof) ?>" alt="Proof"
                         style="width:100%;display:block;object-fit:cover;max-height:100px;">
                  </a>
                </div>
                <?php else: ?>
                <div style="margin-top:8px;font-size:.71rem;color:#bbb;" id="proof-display-<?= htmlspecialchars($orderId) ?>">
                  <i class="fas fa-image me-1"></i>No proof uploaded
                </div>
                <?php endif; ?>

                <!-- Payment Verification Controls -->
                <div style="margin-top:10px;display:flex;flex-direction:column;gap:6px;">
                  <input type="text"
                    id="pay-ref-input-<?= htmlspecialchars($orderId) ?>"
                    placeholder="Reference / Transaction No."
                    value="<?= htmlspecialchars($orderPayRef) ?>"
                    style="width:100%;padding:5px 9px;font-size:.75rem;border-radius:8px;border:1.5px solid #d4e4d4;background:#fff;outline:none;font-family:inherit;">
                  <div style="display:flex;gap:5px;">
                    <button onclick="updatePayment('<?= htmlspecialchars($orderId, ENT_QUOTES) ?>','verified')"
                      style="flex:1;padding:5px 0;font-size:.72rem;font-weight:700;border:none;border-radius:8px;background:#dcfce7;color:#15803d;cursor:pointer;">
                      <i class="fas fa-check me-1"></i>Verify
                    </button>
                    <button onclick="updatePayment('<?= htmlspecialchars($orderId, ENT_QUOTES) ?>','rejected')"
                      style="flex:1;padding:5px 0;font-size:.72rem;font-weight:700;border:none;border-radius:8px;background:#fee2e2;color:#b91c1c;cursor:pointer;">
                      <i class="fas fa-times me-1"></i>Reject
                    </button>
                    <button onclick="updatePayment('<?= htmlspecialchars($orderId, ENT_QUOTES) ?>','pending')"
                      style="flex:1;padding:5px 0;font-size:.72rem;font-weight:700;border:none;border-radius:8px;background:#fff7ed;color:#c2410c;cursor:pointer;">
                      <i class="fas fa-clock me-1"></i>Pending
                    </button>
                  </div>
                </div>
              </div>
              <div style="background:#f9f9f6;border-radius:10px;padding:10px;grid-column:1/-1;">
                <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:4px;">Delivery Address</div>
                <div style="font-size:.82rem;color:#444;"><?= htmlspecialchars(implode(', ', array_filter([
                    $shippingInfo['address']  ?? '',
                    $shippingInfo['barangay'] ?? '',
                    $shippingInfo['city']     ?? '',
                    $shippingInfo['province'] ?? '',
                    $shippingInfo['zip']      ?? '',
                ]))) ?: '—' ?></div>
              </div>
            </div>
        </div>

    </div>
    <?php endforeach;
    if ($totalOrdCount === 0): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-receipt fa-3x mb-3 opacity-25"></i>
        <p>No orders placed yet.</p>
    </div>
    <?php endif; ?>

    <?php if ($grandTotal2 > 0): ?>
    <div style="background:linear-gradient(135deg,#1a2e1a,#2d5a2d);color:#fff;border-radius:16px;padding:20px 24px;margin-top:8px;display:flex;justify-content:space-between;align-items:center;">
        <div>
            <div style="font-size:.72rem;opacity:.7;letter-spacing:1.5px;text-transform:uppercase;color:#fff;">Grand Total Revenue</div>
            <div style="font-size:1.6rem;font-weight:800;font-family:'Playfair Display',serif;">₱<?= number_format($grandTotal2) ?></div>
        </div>
        <div style="text-align:right;opacity:.8;">
            <div style="font-size:1.3rem;font-weight:700;"><?= $totalOrdCount ?></div>
            <div style="font-size:.72rem;">total order(s)</div>
        </div>
    </div>
    <?php endif; ?>
</div>
</div><!-- /section-orders -->


<!-- ── SECTION: User Summary ── -->
<div id="section-users" style="display:none;">
<div class="card p-4 mt-3">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:44px;height:44px;background:var(--sage-light);border-radius:12px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-users" style="color:var(--deep-green);font-size:1.1rem;"></i>
        </div>
        <div>
            <h5 class="fw-bold mb-0" style="color:var(--deep-green);">User Summary</h5>
        
        </div>
    </div>

    <?php
    $allUsers2 = loadUsers();
    if (empty($allUsers2)): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-users fa-3x mb-3 opacity-25"></i>
        <p>No users registered yet.</p>
    </div>
    <?php else: ?>
    <div class="row g-3">
    <?php
    // Pre-load orders + carts for user stats
    $dbAllOrders3 = loadOrders();
    $dbAllCarts3  = loadCarts();

    // Index orders by email
    $ordersByUser = [];
    foreach ($dbAllOrders3 as $o) {
        $ordersByUser[$o->email][] = $o;
    }
    // Index carts by email
    $cartByUser = [];
    foreach ($dbAllCarts3 as $c) {
        $cartByUser[$c['email']][] = $c;
    }

    foreach ($allUsers2 as $uObj):
        $uEmail = $uObj->email ?? '';
        $uData  = ['name' => $uObj->name ?? '', 'role' => $uObj->role ?? 'user'];
        $userAvatar = getAvatarURL($uObj->profile_pic ?? null, $uEmail, $uData['name'] ?? null, 46);

        $uOrders2    = count($ordersByUser[$uEmail] ?? []);
        $uCartItems  = $cartByUser[$uEmail] ?? [];
        $uCartCount2 = array_sum(array_column($uCartItems, 'qty'));
        $uSpend      = 0;
        foreach ($ordersByUser[$uEmail] ?? [] as $uo)
            $uSpend += (float)($uo->total ?? 0);
        $isAdmin = ($uData['role'] === 'admin');
    ?>
    <div class="col-md-6">
        <div class="order-card h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div style="width:46px;height:46px;border-radius:50%;overflow:hidden;flex-shrink:0;border:2px solid #e5e5e5;">
                    <img src="<?= htmlspecialchars($userAvatar) ?>" alt="<?= htmlspecialchars($uData['name'] ?? 'User') ?>" style="width:100%;height:100%;object-fit:cover;display:block;">
                </div>
                <div style="flex:1;min-width:0;">
                    <div class="fw-bold text-truncate" style="color:#1a2e1a;"><?= htmlspecialchars($uData['name'] ?? '') ?></div>
                    <div style="font-size:.75rem;color:var(--deep-green);opacity:.65;text-overflow:ellipsis;overflow:hidden;white-space:nowrap;" class="user-email-tag" data-email="<?= htmlspecialchars($uEmail) ?>"><?= htmlspecialchars($uEmail) ?></div>
                </div>
                <span style="background:<?= $isAdmin?'#fee2e2':'#d4e4d4' ?>;color:<?= $isAdmin?'#b91c1c':'#2d5a2d' ?>;
                    border-radius:20px;font-size:.68rem;font-weight:700;padding:3px 10px;letter-spacing:1px;white-space:nowrap;">
                    <?= $isAdmin ? 'ADMIN' : 'USER' ?>
                </span>
            </div>
            <?php if (!$isAdmin): ?>
            <div class="d-flex gap-2 flex-wrap mb-3">
                <div style="flex:1;background:#f9f9f6;border-radius:10px;padding:10px;text-align:center;min-width:70px;">
                    <div style="font-size:1.1rem;font-weight:800;color:#2d5a2d;"><?= $uOrders2 ?></div>
                    <div style="font-size:.68rem;color:var(--deep-green);opacity:.65;">Orders</div>
                </div>
                <div style="flex:1;background:#f9f9f6;border-radius:10px;padding:10px;text-align:center;min-width:70px;">
                    <div style="font-size:1.1rem;font-weight:800;color:#2d5a2d;"><?= $uCartCount2 ?></div>
                    <div style="font-size:.68rem;color:var(--deep-green);opacity:.65;">In Cart</div>
                </div>
                <div style="flex:1;background:#f9f9f6;border-radius:10px;padding:10px;text-align:center;min-width:70px;">
                    <div style="font-size:.9rem;font-weight:800;color:#2d5a2d;">₱<?= number_format($uSpend) ?></div>
                    <div style="font-size:.68rem;color:var(--deep-green);opacity:.65;">Spent</div>
                </div>
            </div>
            <?php
            $currentAdmin = $_SESSION['logged_in_user'] ?? '';
            if ($uEmail !== $currentAdmin): ?>
            <button class="btn btn-danger btn-sm w-100 rounded-pill fw-semibold"
                onclick="deleteUser('<?= htmlspecialchars($uEmail, ENT_QUOTES) ?>', '<?= htmlspecialchars($uData['name'] ?? '', ENT_QUOTES) ?>')">
                <i class="fas fa-user-times me-1"></i> Delete User
            </button>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</div><!-- /section-users -->

<!-- ── SECTION: Reviews ── -->
<div id="section-reviews" style="display:none;">
<div class="card p-4 mt-3">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:44px;height:44px;background:var(--sage-light);border-radius:12px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-star" style="color:var(--deep-green);font-size:1.1rem;"></i>
        </div>
        <div>
            <h5 class="fw-bold mb-0" style="color:var(--deep-green);">User Reviews</h5>
            <p class="mb-0 text-muted" style="font-size:.9rem;">Respond to customer feedback and manage review replies.</p>
        </div>
    </div>
    <?php $adminReviews = loadReviews(0, true); ?>
    <?php if (empty($adminReviews)): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-star fa-3x mb-3 opacity-25"></i>
        <p>No reviews have been submitted yet.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle text-center" style="font-size:.88rem;">
            <thead>
                <tr>
                    <th>Reviewer</th>
                    <th>Email</th>
                    <th>Order</th>
                    <th>Rating</th>
                    <th>Comment</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adminReviews as $review): ?>
                <tr id="review-row-<?= htmlspecialchars($review->review_id) ?>">
                    <td style="white-space:nowrap;">
                        <div class="d-flex align-items-center gap-2 justify-content-center">
                            <img src="<?= htmlspecialchars(getAvatarURL($review->author_pic ?? null, $review->author_email ?? null, $review->author_name ?? null, 36)) ?>" alt="Avatar" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:1px solid rgba(0,0,0,.08);">
                            <span><?= htmlspecialchars($review->author_name ?: 'Anonymous') ?></span>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($review->author_email ?: $review->email) ?></td>
                    <td><?= htmlspecialchars($review->order_id) ?></td>
                    <td><?= htmlspecialchars($review->rating) ?>/5</td>
                    <td style="max-width:220px;white-space:pre-wrap;word-break:break-word;"><?= htmlspecialchars($review->comment) ?></td>
                    <td><?= htmlspecialchars($review->created_at) ?></td>
                    <td>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</div><!-- /section-reviews -->

<!-- ── SECTION: Messages ── -->
<div id="section-messages" style="display:none;">
<?php
$contactMsgs = [];
try {
    $db2 = getDBConnection();
    $contactStmt = $db2->query("SELECT * FROM messages ORDER BY created_at DESC");
    $contactMsgs = $contactStmt->fetchAll();
} catch (Exception $e) {
    // Tables may not exist yet.
}
?>
<div class="card p-4 mt-3">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:44px;height:44px;background:var(--sage-light);border-radius:12px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-envelope" style="color:var(--deep-green);font-size:1.1rem;"></i>
        </div>
        <div>
            <h5 class="fw-bold mb-0" style="color:var(--deep-green);">Customer Messages</h5>
        </div>
    </div>
    <?php if (empty($contactMsgs)): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-envelope-open fa-3x mb-3 opacity-25"></i>
        <p>No messages received yet.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table align-middle" style="font-size:.88rem;">
        <thead>
            <tr>
                <th>Name</th><th>Email</th><th>Subject</th><th>Message</th><th>Date</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($contactMsgs as $m): ?>
        <tr>
            <td class="fw-semibold"><?= htmlspecialchars($m->full_name ?? '') ?></td>
            <td><?= htmlspecialchars($m->email ?? '') ?></td>
            <td><?= htmlspecialchars($m->subject ?? '') ?></td>
            <td style="max-width:260px;white-space:pre-wrap;word-break:break-word;"><?= htmlspecialchars($m->message ?? '') ?></td>
            <td style="white-space:nowrap;color:#999;"><?= htmlspecialchars($m->created_at ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
</div><!-- /section-messages -->

</div><!-- /container -->
</div><!-- /main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Date / Time ──────────────────────────────────────────────
function updateDateTime() {
    const now = new Date();
    const d = now.toLocaleDateString('en-US', { month:'short', day:'2-digit', year:'numeric' });
    const t = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true });
    document.getElementById('datetime').textContent = d + ', ' + t;
}
setInterval(updateDateTime, 1000);
updateDateTime();

// ── Section switching (all sidebar nav items) ─────────────────
const sectionTitles = {
    inventory:  'Product Inventory',
    addproduct: 'Add Product',
    analytics:  'Analytics Dashboard',
    orders:     'Order History',
    users:      'User Summary',
    reviews:    'User Reviews',
    messages:   'Customer Messages',
};

function showSection(name) {
    ['inventory','addproduct','analytics','orders','users','reviews','messages'].forEach(s => {
        document.getElementById('section-' + s).style.display = s === name ? '' : 'none';
    });
    document.querySelectorAll('.sidebar-link').forEach(el => el.classList.remove('active'));
    const nav = document.getElementById('nav-' + name);
    if (nav) nav.classList.add('active');
    const titleEl = document.getElementById('sectionTitle');
    if (titleEl) titleEl.textContent = sectionTitles[name] || '';
    if (name === 'addproduct') resetForm();
}

// ── Edit product: switch to Add Product section then fill form ─
function editProduct(id, name, size, color, price, desc, stock, category, image) {
    showSection('addproduct');
    document.getElementById('pid').value    = id;
    document.getElementById('pname').value  = name;
    document.getElementById('psize').value  = size;
    document.getElementById('pcolor').value = color;
    document.getElementById('pprice').value = price;
    document.getElementById('pdesc').value  = desc;
    document.getElementById('pstock').value = stock;
    document.getElementById('pcat').value   = category;
    document.getElementById('pimage').value = image;
    document.getElementById('sectionTitle').textContent = 'Edit Product';
    window.scrollTo({top:0, behavior:'smooth'});
}

// ── Reset form to blank (new product) ────────────────────────
function resetForm() {
    ['pid','pname','psize','pcolor','pprice','pdesc','pstock','pimage'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const cat = document.getElementById('pcat');
    if (cat) cat.value = 'Sofa';
}

// ── Search ────────────────────────────────────────────────────
function searchProducts(query) {
    const q         = query.trim().toLowerCase();
    const rows      = document.querySelectorAll('.product-row');
    const clearBtn  = document.getElementById('clearBtn');
    const noResults = document.getElementById('noResults');
    const countEl   = document.getElementById('result-count');

    clearBtn.style.display = q ? 'block' : 'none';
    let visible = 0;

    rows.forEach(row => {
        const haystack = [row.dataset.name, row.dataset.color, row.dataset.category,
                          row.dataset.description, row.dataset.size].join(' ');
        const match = q === '' || haystack.indexOf(q) !== -1;
        row.style.display = match ? '' : 'none';
        if (match) { visible++; q ? highlightRow(row, q) : clearHighlight(row); }
        else clearHighlight(row);
    });

    noResults.style.display = (visible === 0 && q) ? '' : 'none';
    document.getElementById('noResultsQuery').textContent = query;
    const total = rows.length;
    countEl.textContent = q ? `(${visible} of ${total} shown)` : `(${total} total)`;
}

function highlightRow(row, q) {
    const targets = row.querySelectorAll('.s-name,.s-size,.s-color,.s-desc,.s-category');
    const regex   = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    targets.forEach(cell => {
        if (!cell.dataset.original) cell.dataset.original = cell.textContent;
        cell.innerHTML = cell.dataset.original.replace(regex, '<mark>$1</mark>');
    });
}

function clearHighlight(row) {
    row.querySelectorAll('[data-original]').forEach(cell => {
        cell.textContent = cell.dataset.original;
    });
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    searchProducts('');
}

// ── Update Order Status ───────────────────────────────────────
function updateOrderStatus(email, orderId, newStatus) {
    if (!newStatus) return;
    fetch('admin_action.php?update_status=1&email=' + encodeURIComponent(email)
        + '&order_id=' + encodeURIComponent(orderId)
        + '&status='   + encodeURIComponent(newStatus),
        { credentials: 'same-origin' })
    .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(data => {
        if (data.success) {
            const badge = document.getElementById('status-badge-' + orderId);
            const statusColors = {
                Pending:    { bg:'#fff7ed', color:'#c2410c', border:'#fed7aa' },
                Processing: { bg:'#eff6ff', color:'#1d4ed8', border:'#bfdbfe' },
                Shipped:    { bg:'#f0f9ff', color:'#0369a1', border:'#bae6fd' },
                Delivered:  { bg:'#f0fdf4', color:'#15803d', border:'#bbf7d0' },
                Cancelled:  { bg:'#fef2f2', color:'#b91c1c', border:'#fecaca' },
            };
            const sc = statusColors[newStatus] || statusColors['Pending'];
            if (badge) {
                badge.textContent = newStatus;
                badge.style.background = sc.bg;
                badge.style.color      = sc.color;
                badge.style.border     = '1px solid ' + sc.border;
            }
            showToast('Order #' + orderId + ' → ' + newStatus);
            updatePendingBadge();
            const sel = document.getElementById('status-sel-' + orderId);
            if (sel) sel.value = '';
        } else {
            alert(data.message || 'Could not update status.');
        }
    })
    .catch(err => alert('Request failed: ' + err.message));
}

// ── Update Payment Status ─────────────────────────────────────
function updatePayment(orderId, payStatus) {
    const refInput = document.getElementById('pay-ref-input-' + orderId);
    const refNo    = refInput ? refInput.value.trim() : '';

    const body = new URLSearchParams({
        update_payment: '1',
        order_id:       orderId,
        pay_status:     payStatus,
        reference_no:   refNo,
    });

    fetch('admin_action.php', {
        method:      'POST',
        credentials: 'same-origin',
        headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:        body.toString(),
    })
    .then(r => {
        if (!r.ok) return r.text().then(t => { throw new Error('HTTP ' + r.status + ': ' + t.substring(0, 200)); });
        return r.text().then(t => {
            try { return JSON.parse(t); }
            catch(e) { throw new Error('Bad JSON from server: ' + t.substring(0, 200)); }
        });
    })
    .then(data => {
        if (!data.success) {
            alert(data.message || 'Could not update payment.');
            return;
        }
        // Update status badge colour
        const badge = document.getElementById('pay-status-badge-' + orderId);
        const refText = document.getElementById('pay-ref-text-' + orderId);
        const statusColors = {
            pending:  { bg:'#fff7ed', color:'#c2410c', border:'#fed7aa' },
            verified: { bg:'#f0fdf4', color:'#15803d', border:'#bbf7d0' },
            rejected: { bg:'#fef2f2', color:'#b91c1c', border:'#fecaca' },
        };
        const sc = statusColors[payStatus] || statusColors['pending'];
        if (badge) {
            badge.textContent     = payStatus;
            badge.style.background = sc.bg;
            badge.style.color      = sc.color;
            badge.style.border     = '1px solid ' + sc.border;
        }
        if (refText) {
            refText.textContent = data.reference_no || '—';
        }
        const labels = { verified: '✓ Payment verified', rejected: '✗ Payment rejected', pending: 'Payment set to pending' };
        showToast(labels[payStatus] || 'Payment updated');
        updatePendingBadge();
    })
    .catch(err => alert('Request failed: ' + err.message));
}

// ── Delete User ───────────────────────────────────────────────
function deleteUser(email, name) {
    if (!confirm('Delete user "' + name + '" (' + email + ')?\nThis will also remove their cart and orders.')) return;
    fetch('admin_action.php?delete_user=' + encodeURIComponent(email))
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('User ' + name + ' deleted.');
                // Remove the card from DOM
                document.querySelectorAll('.user-email-tag').forEach(el => {
                    if (el.dataset.email === email) {
                        el.closest('.col-md-6').remove();
                    }
                });
                // Reload section after short delay
                setTimeout(() => window.location.reload(), 800);
            } else {
                alert(data.message || 'Could not delete user.');
            }
        })
        .catch(() => alert('Request failed.'));
}

function showToast(msg, isError = false) {
    let t = document.getElementById('admin-toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'admin-toast';
        t.style.cssText = 'position:fixed;bottom:24px;right:24px;color:#fff;padding:14px 22px;border-radius:12px;font-size:.86rem;z-index:9999;box-shadow:0 6px 24px rgba(0,0,0,.2);transition:.3s;';
        document.body.appendChild(t);
    }
    t.style.background = isError ? '#b91c1c' : '#166534';
    t.style.color = '#ffffff';
    t.textContent = msg;
    t.style.opacity = '1';
    setTimeout(() => t.style.opacity = '0', 3500);
}

// ── Toggle order detail panel ─────────────────────────────────
function toggleOrderDetail(orderId) {
    const panel = document.getElementById('detail-' + orderId);
    if (!panel) return;
    panel.style.display = panel.style.display === 'none' ? '' : 'none';
}

// ── Quick status button (in detail panel) ─────────────────────
function quickStatus(email, orderId, newStatus) {
    fetch('admin_action.php?update_status=1&email=' + encodeURIComponent(email)
        + '&order_id=' + encodeURIComponent(orderId)
        + '&status='   + encodeURIComponent(newStatus),
        { credentials: 'same-origin' })
    .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(data => {
        if (data.success) {
            const badge = document.getElementById('status-badge-' + orderId);
            const statusColors = {
                Pending:    { bg:'#fff7ed', color:'#c2410c', border:'#fed7aa' },
                Processing: { bg:'#eff6ff', color:'#1d4ed8', border:'#bfdbfe' },
                Shipped:    { bg:'#f0f9ff', color:'#0369a1', border:'#bae6fd' },
                Delivered:  { bg:'#f0fdf4', color:'#15803d', border:'#bbf7d0' },
                Cancelled:  { bg:'#fef2f2', color:'#b91c1c', border:'#fecaca' },
            };
            const sc = statusColors[newStatus] || statusColors['Pending'];
            if (badge) {
                badge.textContent = newStatus;
                badge.style.background = sc.bg;
                badge.style.color      = sc.color;
                badge.style.border     = '1px solid ' + sc.border;
            }
            const sel = document.getElementById('status-sel-' + orderId);
            if (sel) sel.value = '';
            // Update pending badge in sidebar
            updatePendingBadge();
            showToast('✓ Order #' + orderId + ' → ' + newStatus);
        } else {
            showToast(data.message || 'Could not update status.', true);
        }
    }).catch(err => showToast('Request failed: ' + err.message, true));
}

// ── Refresh pending count on sidebar ─────────────────────────
function updatePendingBadge() {
    fetch('get_pending.php', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => {
        // Order-status pending badge (next to Orders nav item)
        let badge = document.getElementById('pending-badge');
        if (d.count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.id = 'pending-badge';
                badge.style.cssText = 'background:#dc2626;color:#fff;border-radius:50px;font-size:.6rem;font-weight:700;padding:2px 7px;';
                const navOrders = document.getElementById('nav-orders');
                if (navOrders) navOrders.appendChild(badge);
            }
            badge.textContent = d.count;
        } else if (badge) {
            badge.remove();
        }
    }).catch(() => {});
}

window.addEventListener('DOMContentLoaded', () => {
    const total = document.querySelectorAll('.product-row').length;
    const countEl = document.getElementById('result-count');
    if (countEl) countEl.textContent = `(${total} total)`;
});

// ── Logout modal functions ──
function openLogoutModal() {
  const overlay = document.getElementById('logoutModalOverlay');
  if (overlay) {
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeLogoutModal(event) {
  const overlay = document.getElementById('logoutModalOverlay');
  if (overlay) {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }
}

function performLogout() {
  const confirmBtn = document.querySelector('.logout-confirm-btn');
  if (confirmBtn) {
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Logging out...';
  }
  window.location.href = 'logout.php';
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeLogoutModal();
  }
});

// Close modal on outside click
document.addEventListener('click', function(e) {
  const overlay = document.getElementById('logoutModalOverlay');
  if (overlay && e.target === overlay) {
    closeLogoutModal(e);
  }
});

// ── Proof of Payment Lightbox ─────────────────────────────────
function openProofLightbox(src, e) {
  if (e) e.preventDefault();
  const lb = document.getElementById('proofLightbox');
  const img = document.getElementById('proofLightboxImg');
  if (lb && img) {
    img.src = src;
    lb.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
}
function closeProofLightbox() {
  const lb = document.getElementById('proofLightbox');
  if (lb) { lb.style.display = 'none'; document.body.style.overflow = ''; }
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeProofLightbox(); });

</script>

<!-- Proof Lightbox -->
<div id="proofLightbox" onclick="closeProofLightbox()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.82);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <img id="proofLightboxImg" src="" alt="Proof of Payment"
       style="max-width:94vw;max-height:90vh;border-radius:12px;box-shadow:0 8px 40px rgba(0,0,0,.6);object-fit:contain;">
  <button onclick="closeProofLightbox()" style="position:fixed;top:18px;right:22px;background:rgba(255,255,255,.15);border:none;color:#fff;font-size:1.5rem;border-radius:50%;width:40px;height:40px;cursor:pointer;display:flex;align-items:center;justify-content:center;">✕</button>
</div>

</body>
</html>