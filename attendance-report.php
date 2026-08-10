<?php
$page_title = "Attendance History Reports";
require_once __DIR__ . '/includes/auth.php';
requireLogin();

require_once __DIR__ . '/classes/Attendance.php';
require_once __DIR__ . '/classes/Employee.php';
require_once __DIR__ . '/classes/Department.php';

$attendanceModel = new Attendance();
$employeeModel = new Employee();
$departmentModel = new Department();

$role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// Filter parameters (defaults to last 30 days)
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';

if ($role === 'Admin' || $role === 'HR') {
    $employee_filter = $_GET['employee_id'] ?? '';
    $department_filter = $_GET['department_id'] ?? '';
} else {
    // Standard staff can only see their own logs!
    $employee_filter = $user_id;
    $department_filter = '';
}

$filters = [
    'start_date' => $start_date,
    'end_date' => $end_date,
    'status' => $status_filter,
    'employee_id' => $employee_filter,
    'department_id' => $department_filter
];

// Fetch data
$logs = $attendanceModel->getReportData($filters);
$employeesList = ($role === 'Admin' || $role === 'HR') ? $employeeModel->getAll() : [];
$departmentsList = ($role === 'Admin' || $role === 'HR') ? $departmentModel->getAll() : [];

include_once __DIR__ . '/includes/header.php';
?>

<div class="row">
    <!-- Filters Toolbar -->
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
            <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-filter text-primary me-2"></i> Filter Logs & Generate Reports</h5>

            <form method="GET" action="attendance-report.php" class="row g-3">
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label fw-semibold small text-secondary">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label fw-semibold small text-secondary">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>

                <div class="col-12 col-sm-6 col-md-2">
                    <label class="form-label fw-semibold small text-secondary">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Statuses</option>
                        <option value="Present" <?php echo ($status_filter === 'Present') ? 'selected' : ''; ?>>Present</option>
                        <option value="Absent" <?php echo ($status_filter === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                        <option value="Late" <?php echo ($status_filter === 'Late') ? 'selected' : ''; ?>>Late</option>
                        <option value="Permission" <?php echo ($status_filter === 'Permission') ? 'selected' : ''; ?>>Permission</option>
                    </select>
                </div>

                <?php if ($role === 'Admin' || $role === 'HR'): ?>
                    <div class="col-12 col-sm-6 col-md-2">
                        <label class="form-label fw-semibold small text-secondary">Department</label>
                        <select class="form-select" name="department_id">
                            <option value="">All Departments</option>
                            <?php foreach ($departmentsList as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo ($department_filter == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-sm-6 col-md-2">
                        <label class="form-label fw-semibold small text-secondary">Employee</label>
                        <select class="form-select" name="employee_id">
                            <option value="">All Employees</option>
                            <?php foreach ($employeesList as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" <?php echo ($employee_filter == $emp['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?> (<?php echo htmlspecialchars($emp['employee_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="col-12 d-flex justify-content-end flex-wrap gap-2 mt-4">
                    <a href="attendance-report.php" class="btn btn-light border btn-submit-mobile"><i class="fa-solid fa-arrow-rotate-left"></i> Reset</a>
                    <button type="submit" class="btn btn-ipmc btn-submit-mobile"><i class="fa-solid fa-magnifying-glass me-1"></i> Apply Filters</button>
                    <button type="button" class="btn btn-outline-secondary btn-submit-mobile" onclick="window.print()"><i class="fa-solid fa-print me-1"></i> Print Report</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results list -->
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-white">
            <div class="card-body p-0">
                <?php if (empty($logs)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-folder-open mb-2" style="font-size: 3rem;"></i>
                        <p class="mb-0">No attendance records found matching your current filter settings.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle text-dark">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Staff ID</th>
                                    <th>Full Name</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Check In / Out</th>
                                    <th>Notes / Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <?php
                                    $badge = 'bg-success';
                                    if ($log['status'] === 'Absent') $badge = 'bg-danger';
                                    elseif ($log['status'] === 'Late') $badge = 'bg-warning text-dark';
                                    elseif ($log['status'] === 'Permission') $badge = 'bg-info';
                                    ?>
                                    <tr>
                                        <td><span class="fw-bold"><?php echo date('M j, Y', strtotime($log['date'])); ?></span></td>
                                        <td><span class="text-primary fw-semibold"><?php echo htmlspecialchars($log['employee_code']); ?></span></td>
                                        <td><span class="fw-semibold"><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></span></td>
                                        <td><small class="text-secondary"><?php echo htmlspecialchars($log['department_name'] ?? 'Unassigned'); ?></small></td>
                                        <td><span class="badge <?php echo $badge; ?> px-2.5 py-1.5"><?php echo $log['status']; ?></span></td>
                                        <td>
                                            <?php if ($log['time_in']): ?>
                                                <small class="fw-medium text-dark"><i class="fa-solid fa-right-to-bracket text-success me-1"></i><?php echo date('h:i A', strtotime($log['time_in'])); ?></small>
                                                <?php if ($log['time_out']): ?>
                                                    <span class="text-muted mx-1">|</span>
                                                    <small class="fw-medium text-dark"><i class="fa-solid fa-right-from-bracket text-danger me-1"></i><?php echo date('h:i A', strtotime($log['time_out'])); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">-- : --</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small class="text-secondary"><?php echo htmlspecialchars($log['notes'] ?? '-'); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
