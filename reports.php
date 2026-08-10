<?php
$page_title = "Institutional HR Reports";
require_once __DIR__ . '/includes/auth.php';
requireRoles(['Admin', 'HR']);

require_once __DIR__ . '/classes/Department.php';
require_once __DIR__ . '/classes/Employee.php';
require_once __DIR__ . '/classes/Leave.php';
require_once __DIR__ . '/classes/Attendance.php';

$departmentModel = new Department();
$employeeModel = new Employee();
$leaveModel = new Leave();
$attendanceModel = new Attendance();

// Collect reports aggregations
$departments = $departmentModel->getAll();
$employees = $employeeModel->getAll();
$leaveSummary = $leaveModel->getSummary();
$allLeaves = $leaveModel->getAll();

// Get date filters for attendance report (defaulting to current month)
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$attendanceLogs = $attendanceModel->getReportData(['start_date' => $start_date, 'end_date' => $end_date]);

include_once __DIR__ . '/includes/header.php';
?>

<!-- Print Styles override to hide sidebar/headers and print tables beautifully -->
<style>
@media print {
    #sidebar, .top-navbar, .btn, .card-header, form, footer {
        display: none !important;
    }
    #content {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .print-header {
        display: block !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
.print-header {
    display: none;
}
</style>

<!-- Printable Header Block -->
<div class="print-header text-center mb-5">
    <div class="d-flex align-items-center justify-content-center mb-2">
        <i class="fa-solid fa-graduation-cap text-primary me-2" style="font-size: 2.5rem;"></i>
        <h2 class="fw-bold text-dark m-0">IPMC Tamale Campus</h2>
    </div>
    <h5 class="text-secondary fw-semibold">Institutional Employee Management System Reports</h5>
    <small class="text-muted">Generated on <?php echo date('F j, Y, h:i A'); ?></small>
    <hr>
</div>

<div class="row">
    <!-- Reports Selector Toolbar -->
    <div class="col-12 mb-4 d-print-none">
        <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i> Report Generator</h5>
                    <p class="text-muted small mb-0">Select and filter the required parameters before exporting/printing your report.</p>
                </div>
                <div>
                    <button class="btn btn-ipmc btn-submit-mobile px-4" onclick="window.print()"><i class="fa-solid fa-print me-1"></i> Print / Export PDF</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Department Summary Section -->
    <div class="col-12 col-xl-6 mb-4">
        <div class="card border-0 shadow-sm bg-white h-100 p-1">
            <div class="card-header bg-light border-0 py-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-building text-primary me-2"></i> Departments Staff Summary</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Department Name</th>
                                <th class="text-end">Registered Staff</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><span class="badge bg-primary px-2.5 py-1.5"><?php echo htmlspecialchars($dept['code']); ?></span></td>
                                    <td><span class="fw-semibold"><?php echo htmlspecialchars($dept['name']); ?></span></td>
                                    <td class="text-end fw-bold text-secondary"><?php echo $dept['employee_count']; ?> Employees</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Staff Types Summary -->
    <div class="col-12 col-xl-6 mb-4">
        <div class="card border-0 shadow-sm bg-white h-100 p-1">
            <div class="card-header bg-light border-0 py-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-users text-success me-2"></i> Institutional Staff Breakdown</h5>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="row text-center">
                    <?php
                    $academicCount = 0;
                    $nonAcademicCount = 0;
                    $activeCount = 0;
                    $inactiveCount = 0;
                    foreach ($employees as $emp) {
                        if ($emp['status'] === 'Active') $activeCount++;
                        else $inactiveCount++;

                        if ($emp['staff_type'] === 'Academic') $academicCount++;
                        else $nonAcademicCount++;
                    }
                    ?>
                    <div class="col-6 mb-4">
                        <div class="p-3 border rounded bg-light">
                            <h6 class="text-secondary fw-semibold mb-1">Academic Staff</h6>
                            <h2 class="fw-bold text-primary m-0"><?php echo $academicCount; ?></h2>
                            <small class="text-muted">Lecturers / Lab Instructors</small>
                        </div>
                    </div>
                    <div class="col-6 mb-4">
                        <div class="p-3 border rounded bg-light">
                            <h6 class="text-secondary fw-semibold mb-1">Non-Academic</h6>
                            <h2 class="fw-bold text-secondary m-0"><?php echo $nonAcademicCount; ?></h2>
                            <small class="text-muted">Admins / Coordinators</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 border rounded bg-light">
                            <h6 class="text-secondary fw-semibold mb-1">Active Status</h6>
                            <h2 class="fw-bold text-success m-0"><?php echo $activeCount; ?></h2>
                            <small class="text-muted">Active Access Accounts</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 border rounded bg-light">
                            <h6 class="text-secondary fw-semibold mb-1">Deactivated Account</h6>
                            <h2 class="fw-bold text-danger m-0"><?php echo $inactiveCount; ?></h2>
                            <small class="text-muted">Disabled Access</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaves Report Summary -->
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm bg-white p-1">
            <div class="card-header bg-light border-0 py-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-calendar-check text-warning me-2"></i> Campus Leaves Summary</h5>
            </div>
            <div class="card-body p-0">
                <div class="p-3 p-md-4 bg-light border-bottom">
                    <div class="row text-center">
                        <div class="col-4 border-end">
                            <h6 class="text-secondary font-weight-medium">Approved Leaves</h6>
                            <h3 class="fw-bold text-success"><?php echo $leaveSummary['approved']; ?></h3>
                        </div>
                        <div class="col-4 border-end">
                            <h6 class="text-secondary font-weight-medium">Pending Approvals</h6>
                            <h3 class="fw-bold text-warning"><?php echo $leaveSummary['pending']; ?></h3>
                        </div>
                        <div class="col-4">
                            <h6 class="text-secondary font-weight-medium">Rejected Leaves</h6>
                            <h3 class="fw-bold text-danger"><?php echo $leaveSummary['rejected']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Total Days</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allLeaves)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No leave requests logged yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach (array_slice($allLeaves, 0, 10) as $leave): ?>
                                    <?php
                                    $lbl_class = 'bg-warning text-dark';
                                    if ($leave['status'] === 'Approved') $lbl_class = 'bg-success';
                                    elseif ($leave['status'] === 'Rejected') $lbl_class = 'bg-danger';
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-dark"><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></span>
                                            <small class="d-block text-muted"><?php echo htmlspecialchars($leave['employee_id']); ?></small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($leave['leave_type']); ?></span></td>
                                        <td><small><?php echo date('M j, Y', strtotime($leave['start_date'])); ?></small></td>
                                        <td><small><?php echo date('M j, Y', strtotime($leave['end_date'])); ?></small></td>
                                        <td><span class="fw-bold text-secondary"><?php echo $leave['days_requested']; ?></span></td>
                                        <td><span class="badge <?php echo $lbl_class; ?> px-2.5 py-1.5"><?php echo $leave['status']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Date-Range Filter Report -->
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm bg-white p-1">
            <div class="card-header bg-light border-0 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-clipboard-user text-info me-2"></i> Attendance Date-Range Summary</h5>
                <form class="row g-2 d-print-none align-items-center m-0">
                    <div class="col-auto">
                        <input type="date" class="form-control form-control-sm" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="col-auto"><span class="text-muted small">to</span></div>
                    <div class="col-auto">
                        <input type="date" class="form-control form-control-sm" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-ipmc"><i class="fa-solid fa-filter"></i></button>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Staff Code</th>
                                <th>Full Name</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Check In / Out</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendanceLogs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No attendance logs found in this date range.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach (array_slice($attendanceLogs, 0, 15) as $log): ?>
                                    <?php
                                    $lbl = 'bg-success';
                                    if ($log['status'] === 'Absent') $lbl = 'bg-danger';
                                    elseif ($log['status'] === 'Late') $lbl = 'bg-warning text-dark';
                                    elseif ($log['status'] === 'Permission') $lbl = 'bg-info';
                                    ?>
                                    <tr>
                                        <td><span class="fw-bold"><?php echo date('M j, Y', strtotime($log['date'])); ?></span></td>
                                        <td><span class="text-primary fw-semibold"><?php echo htmlspecialchars($log['employee_code']); ?></span></td>
                                        <td><span class="fw-semibold"><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></span></td>
                                        <td><small class="text-secondary"><?php echo htmlspecialchars($log['department_name'] ?? 'Unassigned'); ?></small></td>
                                        <td><span class="badge <?php echo $lbl; ?> px-2.5 py-1.5"><?php echo $log['status']; ?></span></td>
                                        <td>
                                            <?php if ($log['time_in']): ?>
                                                <small class="text-dark fw-medium"><?php echo date('h:i A', strtotime($log['time_in'])); ?></small>
                                                <?php if ($log['time_out']): ?>
                                                    <span class="text-muted">|</span>
                                                    <small class="text-dark fw-medium"><?php echo date('h:i A', strtotime($log['time_out'])); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">-- : --</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
