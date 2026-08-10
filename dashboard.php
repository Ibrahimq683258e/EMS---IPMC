<?php
$page_title = "Dashboard";
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Include necessary models
require_once __DIR__ . '/classes/Employee.php';
require_once __DIR__ . '/classes/Department.php';
require_once __DIR__ . '/classes/Leave.php';
require_once __DIR__ . '/classes/Attendance.php';
require_once __DIR__ . '/classes/Announcement.php';

$employeeModel = new Employee();
$departmentModel = new Department();
$leaveModel = new Leave();
$attendanceModel = new Attendance();
$announcementModel = new Announcement();

$role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// Get recent announcements (shared by all roles)
$announcements = $announcementModel->getLatest(5);

if ($role === 'Admin' || $role === 'HR') {
    // Admin / HR Summary stats
    $empStats = $employeeModel->getSummary();
    $leaveStats = $leaveModel->getSummary();
    $todayAttendance = $attendanceModel->getSummary(date('Y-m-d'));

    // Recent pending leaves
    $pendingLeaves = $leaveModel->getAll(['status' => 'Pending']);

    // Quick departments count
    $departments = $departmentModel->getAll();
    $totalDept = count($departments);
} else {
    // Employee Summary stats
    $employeeInfo = $employeeModel->findById($user_id);
    $leaveBalances = $leaveModel->getBalances($user_id);
    $personalAttendance = $attendanceModel->getByEmployee($user_id, date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
}

include_once __DIR__ . '/includes/header.php';
?>

<!-- Role-Based Dashboard Wrapper -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card border-0 bg-white p-3 p-md-4 shadow-sm rounded-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h2 class="fw-bold mb-1 fs-3">Hello, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
                    <p class="text-secondary mb-0 small">Welcome to your dashboard. Today is <span class="fw-semibold text-primary"><?php echo date('F j, Y'); ?></span></p>
                </div>
                <div class="mt-2 mt-sm-0">
                    <span class="badge bg-primary px-3 py-2 fs-6">
                        <i class="fa-solid fa-user-shield me-2"></i><?php echo $role; ?> Panel
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($role === 'Admin' || $role === 'HR'): ?>
    <!-- ADMIN / HR DASHBOARD VIEW -->
    <div class="row mb-4">
        <!-- Total Employees Card -->
        <div class="col-12 col-sm-6 col-xl-3 mb-4">
            <div class="card card-stat bg-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-primary text-white me-3">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <h6 class="text-secondary mb-1">Total Staff</h6>
                        <h3 class="fw-bold m-0"><?php echo $empStats['total']; ?></h3>
                        <small class="text-success"><i class="fa-solid fa-circle-check"></i> <?php echo $empStats['active']; ?> Active</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Leaves Card -->
        <div class="col-12 col-sm-6 col-xl-3 mb-4">
            <div class="card card-stat bg-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-warning text-dark me-3">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div>
                        <h6 class="text-secondary mb-1">Pending Leaves</h6>
                        <h3 class="fw-bold m-0"><?php echo $leaveStats['pending']; ?></h3>
                        <small class="text-muted">Requires review</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Attendance Card -->
        <div class="col-12 col-sm-6 col-xl-3 mb-4">
            <div class="card card-stat bg-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-success text-white me-3">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <div>
                        <h6 class="text-secondary mb-1">Present Today</h6>
                        <h3 class="fw-bold m-0"><?php echo $todayAttendance['present'] + $todayAttendance['late']; ?></h3>
                        <small class="text-danger"><?php echo $todayAttendance['absent']; ?> Absent</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Departments Card -->
        <div class="col-12 col-sm-6 col-xl-3 mb-4">
            <div class="card card-stat bg-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-info text-white me-3">
                        <i class="fa-solid fa-building"></i>
                    </div>
                    <div>
                        <h6 class="text-secondary mb-1">Departments</h6>
                        <h3 class="fw-bold m-0"><?php echo $totalDept; ?></h3>
                        <small class="text-muted">Total Active</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left: Pending Leave Applications -->
        <div class="col-12 col-xl-7 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-hourglass-start text-warning me-2"></i> Pending Leave Requests</h5>
                    <a href="leave-manage.php" class="btn btn-sm btn-outline-primary fw-medium">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pendingLeaves)): ?>
                        <div class="text-center py-5">
                            <div class="text-muted mb-3" style="font-size: 2.5rem;"><i class="fa-solid fa-check-double text-success"></i></div>
                            <h6 class="text-secondary">All caught up! No pending leave requests.</h6>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive border-0">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Duration</th>
                                        <th>Days</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($pendingLeaves, 0, 5) as $req): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle overflow-hidden border me-2" style="width: 32px; height: 32px;">
                                                        <img src="<?php echo !empty($req['photo']) && file_exists($req['photo']) ? $req['photo'] : 'uploads/default.png'; ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                                    </div>
                                                    <div>
                                                        <span class="fw-semibold text-dark"><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></span>
                                                        <small class="d-block text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($req['employee_code']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-light text-dark border fw-medium"><?php echo htmlspecialchars($req['leave_type']); ?></span></td>
                                            <td>
                                                <small class="text-dark d-block fw-medium"><?php echo date('M j, Y', strtotime($req['start_date'])); ?></small>
                                                <small class="text-muted">to <?php echo date('M j, Y', strtotime($req['end_date'])); ?></small>
                                            </td>
                                            <td><span class="fw-bold text-secondary"><?php echo $req['days_requested']; ?></span></td>
                                            <td class="text-end">
                                                <a href="leave-manage.php?action=view&id=<?php echo $req['id']; ?>" class="btn btn-sm btn-ipmc">
                                                    <i class="fa-solid fa-eye"></i> View & Process
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Announcements Board -->
        <div class="col-12 col-xl-5 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-bullhorn text-primary me-2"></i> Announcements</h5>
                    <a href="announcements.php" class="btn btn-sm btn-outline-primary fw-medium">Manage Board</a>
                </div>
                <div class="card-body">
                    <?php if (empty($announcements)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-folder-open mb-2" style="font-size: 2rem;"></i>
                            <p class="mb-0">No active announcements</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($announcements as $ann): ?>
                                <div class="list-group-item py-3 px-0 border-bottom">
                                    <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                        <h6 class="fw-bold text-primary mb-0"><?php echo htmlspecialchars($ann['title']); ?></h6>
                                        <small class="text-muted" style="font-size: 0.75rem;"><?php echo date('M j, Y', strtotime($ann['created_at'])); ?></small>
                                    </div>
                                    <p class="text-secondary small mb-2 text-truncate-3"><?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <small class="text-muted" style="font-size: 0.75rem;">
                                            <i class="fa-solid fa-user me-1"></i> Posted by <?php echo htmlspecialchars($ann['first_name'] . ' ' . $ann['last_name']); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- EMPLOYEE DASHBOARD VIEW -->
    <div class="row">
        <!-- Left Sidebar Column: Personal Info & Leave Balance -->
        <div class="col-12 col-lg-4 mb-4">
            <!-- Profile Overview Card -->
            <div class="card border-0 shadow-sm text-center p-3 p-md-4 mb-4 bg-white">
                <div class="profile-photo-wrapper">
                    <img src="<?php echo !empty($employeeInfo['photo']) && file_exists($employeeInfo['photo']) ? $employeeInfo['photo'] : 'uploads/default.png'; ?>" alt="Profile Photo">
                </div>
                <h4 class="fw-bold mb-1 text-truncate"><?php echo htmlspecialchars($employeeInfo['first_name'] . ' ' . $employeeInfo['last_name']); ?></h4>
                <p class="text-secondary mb-2 text-truncate"><?php echo htmlspecialchars($employeeInfo['designation'] ?? 'Staff Member'); ?></p>
                <span class="badge bg-light text-primary border border-primary px-3 py-1 mb-3"><?php echo htmlspecialchars($employeeInfo['staff_type']); ?> Staff</span>

                <hr class="my-3">

                <div class="text-start">
                    <div class="mb-2 text-secondary small text-truncate"><i class="fa-solid fa-id-card me-2 text-primary"></i> <span class="fw-semibold text-dark">ID:</span> <?php echo htmlspecialchars($employeeInfo['employee_id']); ?></div>
                    <div class="mb-2 text-secondary small text-truncate"><i class="fa-solid fa-envelope me-2 text-primary"></i> <span class="fw-semibold text-dark">Email:</span> <?php echo htmlspecialchars($employeeInfo['email']); ?></div>
                    <div class="mb-2 text-secondary small text-truncate"><i class="fa-solid fa-phone me-2 text-primary"></i> <span class="fw-semibold text-dark">Phone:</span> <?php echo htmlspecialchars($employeeInfo['phone'] ?? 'N/A'); ?></div>
                    <div class="mb-0 text-secondary small text-truncate"><i class="fa-solid fa-building me-2 text-primary"></i> <span class="fw-semibold text-dark">Dept:</span> <?php echo htmlspecialchars($employeeInfo['department_name'] ?? 'Unassigned'); ?></div>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
                <h5 class="fw-bold mb-3">Quick Actions</h5>
                <div class="d-grid gap-2">
                    <a href="leave-apply.php" class="btn btn-ipmc text-white text-start"><i class="fa-solid fa-calendar-plus me-2"></i> Apply for Leave</a>
                    <a href="attendance-report.php" class="btn btn-outline-secondary text-start"><i class="fa-solid fa-calendar-days me-2"></i> My Attendance Logs</a>
                </div>
            </div>
        </div>

        <!-- Right Main Column: Leave Balances, Attendance & Announcements -->
        <div class="col-12 col-lg-8 mb-4">
            <!-- Leave Balance Tracker -->
            <div class="card border-0 shadow-sm p-3 p-md-4 mb-4 bg-white">
                <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-hourglass-start text-primary me-2"></i> Leave Balances</h5>
                <div class="row">
                    <?php if (empty($leaveBalances)): ?>
                        <div class="col-12 text-center py-3 text-muted">
                            No leave balance allocation records found.
                        </div>
                    <?php else: ?>
                        <?php foreach ($leaveBalances as $bal): ?>
                            <?php
                            $remaining = $bal['allocated'] - $bal['used'];
                            $percent = $bal['allocated'] > 0 ? round(($bal['used'] / $bal['allocated']) * 100) : 0;
                            $bar_color = ($percent > 75) ? 'bg-danger' : (($percent > 40) ? 'bg-warning' : 'bg-success');
                            ?>
                            <div class="col-12 col-md-6 mb-3">
                                <div class="border rounded p-3 bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($bal['leave_type']); ?> Leave</span>
                                        <span class="fw-bold text-primary"><?php echo $remaining; ?> / <?php echo $bal['allocated']; ?> days left</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar <?php echo $bar_color; ?>" role="progressbar" style="width: <?php echo $percent; ?>%" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted d-block mt-1" style="font-size: 0.75rem;"><?php echo $bal['used']; ?> days used of <?php echo $bal['allocated']; ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Personal Attendance History -->
            <div class="card border-0 shadow-sm p-3 p-md-4 mb-4 bg-white">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-clipboard-user text-success me-2"></i> Recent Attendance (Last 30 Days)</h5>
                    <a href="attendance-report.php" class="btn btn-sm btn-outline-success fw-semibold btn-submit-mobile">All Logs</a>
                </div>
                <?php if (empty($personalAttendance)): ?>
                    <div class="text-center py-4 text-muted">
                        No attendance logged in the last 30 days.
                    </div>
                <?php else: ?>
                    <div class="table-responsive border-0">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Check In / Out</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($personalAttendance, 0, 5) as $att): ?>
                                    <?php
                                    $status_badge = 'bg-success';
                                    if ($att['status'] === 'Absent') $status_badge = 'bg-danger';
                                    elseif ($att['status'] === 'Late') $status_badge = 'bg-warning text-dark';
                                    elseif ($att['status'] === 'Permission') $status_badge = 'bg-info';
                                    ?>
                                    <tr>
                                        <td><span class="fw-semibold"><?php echo date('M j, Y', strtotime($att['date'])); ?></span></td>
                                        <td><span class="badge <?php echo $status_badge; ?> px-2 py-1"><?php echo htmlspecialchars($att['status']); ?></span></td>
                                        <td>
                                            <?php if ($att['time_in']): ?>
                                                <small class="fw-medium text-dark"><i class="fa-solid fa-right-to-bracket text-success me-1"></i><?php echo date('h:i A', strtotime($att['time_in'])); ?></small>
                                                <?php if ($att['time_out']): ?>
                                                    <span class="text-muted mx-1">|</span>
                                                    <small class="fw-medium text-dark"><i class="fa-solid fa-right-from-bracket text-danger me-1"></i><?php echo date('h:i A', strtotime($att['time_out'])); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">-- : --</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small class="text-secondary"><?php echo htmlspecialchars($att['notes'] ?? '-'); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Bulletins/Announcements Board -->
            <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
                <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-bullhorn text-info me-2"></i> Institutional Bulletins</h5>
                <?php if (empty($announcements)): ?>
                    <div class="text-center py-4 text-muted">
                        No bulletins or announcements posted at this time.
                    </div>
                <?php else: ?>
                    <div class="accordion" id="announcementsAccordion">
                        <?php foreach ($announcements as $index => $ann): ?>
                            <div class="accordion-item border-0 border-bottom">
                                <h2 class="accordion-header" id="heading-<?php echo $ann['id']; ?>">
                                    <button class="accordion-button collapsed px-0 bg-white fw-bold text-dark text-primary-hover" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $ann['id']; ?>" aria-expanded="false" aria-controls="collapse-<?php echo $ann['id']; ?>">
                                        <div class="d-flex justify-content-between align-items-center w-100 pe-3">
                                            <span><?php echo htmlspecialchars($ann['title']); ?></span>
                                            <span class="text-muted fw-normal" style="font-size: 0.75rem;"><?php echo date('M j, Y', strtotime($ann['created_at'])); ?></span>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse-<?php echo $ann['id']; ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo $ann['id']; ?>" data-bs-parent="#announcementsAccordion">
                                    <div class="accordion-body px-0 py-3 text-secondary" style="font-size: 0.9rem;">
                                        <p class="mb-3" style="line-height: 1.6;"><?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>
                                        <small class="text-muted d-block border-top pt-2">
                                            <i class="fa-solid fa-user me-1"></i> Posted by <strong><?php echo htmlspecialchars($ann['first_name'] . ' ' . $ann['last_name']); ?></strong> (<?php echo $ann['creator_role']; ?>)
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
