<?php
require_once __DIR__ . '/admin_auth.php';
require_admin();
$pageTitle = $pageTitle ?? 'Dashboard';
$active = $active ?? 'dashboard';
$user = admin_user();
$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => admin_url('/dashboard')],
    ['key' => 'submissions', 'label' => 'Submissions', 'icon' => 'list', 'href' => admin_url('/dashboard/submissions')],
    ['key' => 'categories', 'label' => 'Categories', 'icon' => 'tag', 'href' => admin_url('/dashboard/categories')],
    ['key' => 'export', 'label' => 'Export', 'icon' => 'download', 'href' => admin_url('/dashboard/export')],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= admin_e($pageTitle) ?> · Music Awards of Nagaland</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= admin_url('/assets/css/admin.css') ?>">
</head>
<body class="admin-body">
<div class="admin-shell">

    <header class="admin-navbar">
        <div class="navbar-inner">
            <a class="brand-lockup" href="<?= admin_url('/dashboard') ?>">
                <div class="brand-mark"><?= admin_icon('trophy') ?></div>
                <div class="brand-copy">
                    <span class="brand-name">Music Awards</span>
                    <span class="brand-sub">of Nagaland · 2026</span>
                </div>
            </a>

            <nav class="navbar-pills" id="navbarPills">
                <?php foreach ($navItems as $item): ?>
                <a class="nav-pill <?= $active === $item['key'] ? 'active' : '' ?>" href="<?= $item['href'] ?>">
                    <span class="nav-pill-icon"><?= admin_icon($item['icon']) ?></span><?= admin_e($item['label']) ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <div class="navbar-right">
                <div class="user-menu" id="userMenu">
                    <button type="button" class="user-trigger" id="userTrigger" aria-haspopup="true" aria-expanded="false">
                        <div class="avatar"><?= admin_e(strtoupper(substr((string)$user['display_name'], 0, 1))) ?></div>
                        <span class="user-trigger-name"><?= admin_e($user['display_name']) ?></span>
                        <?= admin_icon('chevron-down') ?>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="user-dropdown-head">
                            <div class="avatar"><?= admin_e(strtoupper(substr((string)$user['display_name'], 0, 1))) ?></div>
                            <div><strong><?= admin_e($user['display_name']) ?></strong><span>Judge / Admin</span></div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <form action="<?= admin_url('/logout.php') ?>" method="post">
                            <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf()) ?>">
                            <button type="submit" class="dropdown-item danger"><?= admin_icon('log-out') ?> Sign out</button>
                        </form>
                    </div>
                </div>
                <button type="button" class="navbar-toggle" id="navbarToggle" aria-label="Toggle menu" aria-expanded="false" aria-controls="navbarPills">
                    <?= admin_icon('menu') ?>
                </button>
            </div>
        </div>
    </header>

    <div class="admin-main">
    <main class="content-wrap">
