<?php
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " - IPMC Tamale Campus EMS" : "IPMC Tamale Campus - Employee Management System"; ?></title>

    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <div id="wrapper">
        <!-- Backdrop for mobile sidebar overlay -->
        <div id="sidebarBackdrop" class="sidebar-backdrop"></div>

        <!-- Sidebar -->
        <?php include_once __DIR__ . '/sidebar.php'; ?>

        <!-- Content Area -->
        <div id="content">
            <!-- Top Navbar -->
            <nav class="navbar top-navbar px-3 px-md-4 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <button class="btn btn-light d-lg-none me-2 me-md-3" id="sidebarToggleBtn" aria-label="Toggle Navigation">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <h5 class="m-0 fw-bold text-secondary text-truncate" style="max-width: 180px; font-size: calc(1rem + 0.15vw);">
                        <?php echo isset($page_title) ? $page_title : "Dashboard"; ?>
                    </h5>
                </div>

                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle text-dark" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php
                        $photo_src = 'uploads/default.png';
                        if (!empty($_SESSION['user_photo']) && file_exists($_SESSION['user_photo'])) {
                            $photo_src = $_SESSION['user_photo'];
                        }
                        ?>
                        <div class="rounded-circle overflow-hidden me-0 me-md-2 border" style="width: 36px; height: 36px; min-width: 36px;">
                            <img src="<?php echo $photo_src; ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <span class="d-none d-md-inline-block fw-semibold text-secondary text-truncate" style="max-width: 120px;">
                            <?php echo $_SESSION['user_name'] ?? 'Guest'; ?>
                            <small class="badge bg-secondary ms-1" style="font-size: 0.65rem;"><?php echo $_SESSION['user_role'] ?? 'Employee'; ?></small>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userMenuDropdown">
                        <li class="d-block d-md-none border-bottom pb-2 mb-2 px-3 py-1">
                            <span class="fw-bold d-block text-truncate"><?php echo $_SESSION['user_name'] ?? 'Guest'; ?></span>
                            <small class="badge bg-secondary mt-1"><?php echo $_SESSION['user_role'] ?? 'Employee'; ?></small>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="employee-view.php?id=<?php echo $_SESSION['user_id'] ?? 0; ?>">
                                <i class="fa-solid fa-user-circle me-2 text-primary"></i> My Profile
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item py-2 text-danger" href="logout.php">
                                <i class="fa-solid fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content Container -->
            <div class="container-fluid p-3 p-md-4">

                <!-- Global Alerts (Success/Error/Warning) -->
                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success alert-dismissible fade show shadow-sm border-start border-4 border-success" role="alert">
                        <i class="fa-solid fa-check-circle me-2"></i>
                        <span><?php echo $_SESSION['success_msg']; ?></span>
                        <?php unset($_SESSION['success_msg']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-start border-4 border-danger" role="alert">
                        <i class="fa-solid fa-exclamation-triangle me-2"></i>
                        <span><?php echo $_SESSION['error_msg']; ?></span>
                        <?php unset($_SESSION['error_msg']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
