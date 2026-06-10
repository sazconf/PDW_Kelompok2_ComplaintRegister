<?php
require_once __DIR__ . '/functions.php';
start_secure_session();

$page_title = $page_title ?? 'Yogyakarta City Complaint Register';
$base_url = '/complaint-system/';
$current_path = $_SERVER['SCRIPT_NAME'] ?? '';
$is_logged_in = is_logged_in();
$is_admin_user = is_admin();
$home_link = $base_url . ($is_admin_user ? 'admin/dashboard.php' : 'index.php');
$avatar_path = $_SESSION['user_avatar'] ?? '';
$avatar_initial = strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1));
$flash_message = get_flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo $base_url; ?>assets/css/style.css?v=20260601-1" rel="stylesheet">
    <?php echo $extra_head ?? ''; ?>
</head>
<body class="bg-light">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="<?php echo $home_link; ?>">
                <span class="brand-mark">
                    <img src="<?php echo $base_url; ?>logo/logo.png" alt="Yogyakarta City Complaint Register logo" class="brand-logo">
                </span>
                <span>Yogyakarta City Complaint Register</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main_navbar" aria-controls="main_navbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="main_navbar">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <?php if (!$is_logged_in): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo str_ends_with($current_path, '/index.php') ? 'active' : ''; ?>" href="<?php echo $base_url; ?>index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo str_contains($current_path, '/login.php') ? 'active' : ''; ?>" href="<?php echo $base_url; ?>login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo str_contains($current_path, '/register.php') ? 'active' : ''; ?>" href="<?php echo $base_url; ?>register.php">Register</a>
                        </li>
                    <?php elseif ($is_admin_user): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo str_contains($current_path, '/admin/dashboard.php') ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo str_contains($current_path, '/admin/manage_reports.php') || str_contains($current_path, '/admin/report_details.php') ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/manage_reports.php">Manage Reports</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo str_ends_with($current_path, '/index.php') ? 'active' : ''; ?>" href="<?php echo $base_url; ?>index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo str_contains($current_path, '/submit_report.php') ? 'active' : ''; ?>" href="<?php echo $base_url; ?>submit_report.php">Submit Report</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo str_contains($current_path, '/my_reports.php') || str_contains($current_path, '/report_details.php') ? 'active' : ''; ?>" href="<?php echo $base_url; ?>my_reports.php">My Reports</a>
                        </li>
                    <?php endif; ?>
                </ul>

                <?php if ($is_logged_in): ?>
                    <div class="dropdown navbar-user ms-lg-3">
                        <button class="btn navbar-user-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="User menu">
                            <span class="navbar-user-icon">
                                <?php if (!empty($avatar_path)): ?>
                                    <img src="<?php echo htmlspecialchars($base_url . $avatar_path); ?>" alt="Profile" class="navbar-avatar navbar-avatar-lg">
                                <?php else: ?>
                                    <span class="navbar-avatar navbar-avatar-fallback navbar-avatar-lg"><?php echo htmlspecialchars($avatar_initial); ?></span>
                                <?php endif; ?>
                                <span class="navbar-user-caret" aria-hidden="true">▾</span>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end navbar-user-menu">
                            <li>
                                <a class="dropdown-item" href="<?php echo $base_url; ?>profile.php">Profile</a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $base_url; ?>logout.php">Logout</a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main id="main-content">
