<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user_role'] ?? 'Employee';
?>
<div id="sidebar">
    <div class="sidebar-header d-flex flex-column align-items-center text-center">
        <!-- IPMC Shield/Icon and Name -->
        <div class="bg-white rounded-circle p-2 mb-2 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; box-shadow: 0 4px 6px rgba(0,0,0,0.15);">
            <i class="fa-solid fa-graduation-cap text-primary" style="font-size: 2rem;"></i>
        </div>
        <h4 class="mb-0">IPMC Tamale</h4>
        <small class="text-white-50 font-weight-light" style="font-size: 0.75rem;">Employee Management</small>
    </div>

    <div class="py-3">
        <!-- Shared Dashboard Link -->
        <a href="dashboard.php" class="nav-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-gauge"></i> Dashboard
        </a>

        <!-- HR / Admin exclusive links -->
        <?php if ($role === 'Admin' || $role === 'HR'): ?>
            <div class="px-3 py-2 text-uppercase text-white-50 font-weight-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Administration</div>

            <a href="employees.php" class="nav-link <?php echo (in_array($current_page, ['employees.php', 'employee-add.php', 'employee-edit.php', 'employee-view.php'])) ? 'active' : ''; ?>">
                <i class="fa-solid fa-users"></i> Employees
            </a>

            <a href="departments.php" class="nav-link <?php echo ($current_page === 'departments.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-building-user"></i> Departments
            </a>
        <?php endif; ?>

        <!-- Shared Payroll & Compensation -->
        <div class="px-3 py-2 text-uppercase text-white-50 font-weight-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Payroll & Compensation</div>
        <a href="salary-bonuses.php" class="nav-link <?php echo (in_array($current_page, ['salary-bonuses.php', 'salary-update.php', 'bonus-add.php', 'bonus-edit.php'])) ? 'active' : ''; ?>">
            <i class="fa-solid fa-file-invoice-dollar"></i> Salary & Bonuses
        </a>

        <!-- Leave Management Links -->
        <div class="px-3 py-2 text-uppercase text-white-50 font-weight-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Leave & Attendance</div>

        <?php if ($role === 'Admin' || $role === 'HR'): ?>
            <a href="leave-manage.php" class="nav-link <?php echo ($current_page === 'leave-manage.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-calendar-check"></i> Manage Leaves
            </a>
            <a href="attendance.php" class="nav-link <?php echo ($current_page === 'attendance.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-clipboard-user"></i> Log Attendance
            </a>
        <?php else: ?>
            <a href="leave-apply.php" class="nav-link <?php echo ($current_page === 'leave-apply.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-calendar-plus"></i> Apply for Leave
            </a>
        <?php endif; ?>

        <a href="attendance-report.php" class="nav-link <?php echo ($current_page === 'attendance-report.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-calendar-days"></i> Attendance Logs
        </a>

        <!-- Appraisal & Engagement Links -->
        <div class="px-3 py-2 text-uppercase text-white-50 font-weight-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Performance & News</div>

        <a href="appraisal.php" class="nav-link <?php echo ($current_page === 'appraisal.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-star"></i> Performance Appraisal
        </a>

        <a href="announcements.php" class="nav-link <?php echo ($current_page === 'announcements.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-bullhorn"></i> Announcements
        </a>

        <?php if ($role === 'Admin' || $role === 'HR'): ?>
            <div class="px-3 py-2 text-uppercase text-white-50 font-weight-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Reporting</div>
            <a href="reports.php" class="nav-link <?php echo ($current_page === 'reports.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line"></i> Report Generator
            </a>
        <?php endif; ?>

        <div class="border-top border-white-10 my-3 mx-3"></div>

        <a href="logout.php" class="nav-link text-danger">
            <i class="fa-solid fa-right-from-bracket text-danger"></i> Logout
        </a>
    </div>
</div>
