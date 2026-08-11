<?php
$page_title = "Staff Profile Details";
require_once __DIR__ . '/includes/auth.php';
requireLogin();

require_once __DIR__ . '/classes/Employee.php';
require_once __DIR__ . '/classes/Leave.php';
require_once __DIR__ . '/classes/Attendance.php';
require_once __DIR__ . '/classes/Appraisal.php';
require_once __DIR__ . '/classes/Salary.php';

$employeeModel = new Employee();
$leaveModel = new Leave();
$attendanceModel = new Attendance();
$appraisalModel = new Appraisal();
$salaryModel = new Salary();

$id = intval($_GET['id'] ?? 0);

// Access Control: Non-admin/HR users can ONLY view their own profile!
if (!isAdmin() && !isHR() && $id !== intval($_SESSION['user_id'])) {
    $_SESSION['error_msg'] = "Access denied! You do not have permission to view other staff profiles.";
    header("Location: dashboard.php");
    exit;
}

$employee = $employeeModel->findById($id);
if (!$employee) {
    $_SESSION['error_msg'] = "Employee profile not found.";
    header("Location: dashboard.php");
    exit;
}

// Fetch supplementary profile info
$leaveBalances = $leaveModel->getBalances($id);
$appraisals = $appraisalModel->getByEmployee($id);
$attendanceLogs = $attendanceModel->getByEmployee($id, date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
$empSalary = $salaryModel->getSalary($id);
$empBonuses = $salaryModel->getBonusesByEmployee($id);
$empPayments = $salaryModel->getPaymentHistory($id);

include_once __DIR__ . '/includes/header.php';
?>

<div class="row">
    <!-- Profile Left: Quick Stats Card -->
    <div class="col-12 col-lg-4 mb-4">
        <div class="card border-0 shadow-sm text-center p-3 p-md-4 bg-white h-100">
            <div class="profile-photo-wrapper">
                <img src="<?php echo !empty($employee['photo']) && file_exists($employee['photo']) ? $employee['photo'] : 'uploads/default.png'; ?>" alt="Profile Photo">
            </div>
            <h3 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h3>
            <p class="text-secondary mb-2"><?php echo htmlspecialchars($employee['designation'] ?? 'Staff Member'); ?></p>
            <div class="mb-3">
                <span class="badge bg-light text-primary border border-primary px-3 py-1.5"><?php echo htmlspecialchars($employee['staff_type']); ?> Staff</span>
                <span class="badge bg-light text-secondary border border-secondary px-3 py-1.5"><?php echo htmlspecialchars($employee['role']); ?></span>
            </div>

            <hr class="my-4">

            <div class="text-start">
                <h6 class="fw-bold text-secondary mb-3 text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.5px;">Contact Information</h6>
                <div class="mb-3 text-dark small d-flex align-items-center">
                    <i class="fa-solid fa-id-card text-primary me-3" style="width: 16px;"></i>
                    <div>
                        <span class="text-muted d-block" style="font-size: 0.7rem;">Employee ID</span>
                        <strong class="text-dark"><?php echo htmlspecialchars($employee['employee_id']); ?></strong>
                    </div>
                </div>
                <div class="mb-3 text-dark small d-flex align-items-center">
                    <i class="fa-solid fa-envelope text-primary me-3" style="width: 16px;"></i>
                    <div>
                        <span class="text-muted d-block" style="font-size: 0.7rem;">Email Address</span>
                        <strong><?php echo htmlspecialchars($employee['email']); ?></strong>
                    </div>
                </div>
                <div class="mb-3 text-dark small d-flex align-items-center">
                    <i class="fa-solid fa-phone text-primary me-3" style="width: 16px;"></i>
                    <div>
                        <span class="text-muted d-block" style="font-size: 0.7rem;">Phone Number</span>
                        <strong><?php echo htmlspecialchars($employee['phone'] ?? 'N/A'); ?></strong>
                    </div>
                </div>
                <div class="mb-0 text-dark small d-flex align-items-center">
                    <i class="fa-solid fa-calendar-check text-primary me-3" style="width: 16px;"></i>
                    <div>
                        <span class="text-muted d-block" style="font-size: 0.7rem;">Date of Joining</span>
                        <strong><?php echo date('M j, Y', strtotime($employee['joining_date'])); ?></strong>
                    </div>
                </div>
            </div>

            <?php if (isAdmin() || isHR()): ?>
                <div class="d-grid gap-2 mt-4">
                    <a href="employee-edit.php?id=<?php echo $employee['id']; ?>" class="btn btn-warning btn-submit-mobile text-dark fw-bold btn-sm">
                        <i class="fa-solid fa-user-pen me-1"></i> Edit Profile
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Profile Right: Leaves, Appraisals & Logs -->
    <div class="col-12 col-lg-8">
        <!-- Leave Balances -->
        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4 bg-white">
            <h5 class="fw-bold mb-3 text-dark border-bottom pb-2"><i class="fa-solid fa-hourglass-start text-primary me-2"></i> Leave Allotments & Balances</h5>
            <div class="row">
                <?php if (empty($leaveBalances)): ?>
                    <div class="col-12 text-center py-2 text-muted">No leave balances tracked.</div>
                <?php else: ?>
                    <?php foreach ($leaveBalances as $bal): ?>
                        <?php
                        $remaining = $bal['allocated'] - $bal['used'];
                        ?>
                        <div class="col-12 col-md-4 mb-3">
                            <div class="p-3 border rounded bg-light text-center">
                                <h6 class="fw-bold text-secondary mb-1"><?php echo htmlspecialchars($bal['leave_type']); ?></h6>
                                <h3 class="fw-bold text-primary mb-1"><?php echo $remaining; ?></h3>
                                <small class="text-muted">of <?php echo $bal['allocated']; ?> Days Left</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Salary & Bonuses Summary Card (Profile view) -->
        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4 bg-white">
            <h5 class="fw-bold mb-3 text-dark border-bottom pb-2"><i class="fa-solid fa-file-invoice-dollar text-success me-2"></i> Compensation & Earnings</h5>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <div class="p-3 border rounded bg-light">
                        <small class="text-secondary fw-semibold d-block">Basic Salary</small>
                        <h4 class="fw-bold text-dark m-0">GHS <?php echo number_format($empSalary['basic_salary'] ?? 0.00, 2); ?></h4>
                        <small class="text-muted" style="font-size: 0.75rem;">Monthly Base Rate</small>
                    </div>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <?php
                    $totalBonusesAmount = 0.00;
                    if (!empty($empBonuses)) {
                        foreach ($empBonuses as $b) {
                            $totalBonusesAmount += floatval($b['amount']);
                        }
                    }
                    ?>
                    <div class="p-3 border rounded bg-light">
                        <small class="text-secondary fw-semibold d-block">Total Bonuses Awarded</small>
                        <h4 class="fw-bold text-success m-0">GHS <?php echo number_format($totalBonusesAmount, 2); ?></h4>
                        <small class="text-muted" style="font-size: 0.75rem;">All-Time Bonuses (<?php echo count($empBonuses ?? []); ?> payouts)</small>
                    </div>
                </div>
            </div>

            <!-- List of Bonuses received -->
            <?php if (!empty($empBonuses)): ?>
                <h6 class="fw-bold text-secondary mt-3 mb-2 small text-uppercase" style="letter-spacing: 0.5px;">Awarded Bonuses List</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle border-0">
                        <thead>
                            <tr class="table-light">
                                <th>Bonus Type</th>
                                <th>Amount</th>
                                <th>Award Date</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($empBonuses as $bonus): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($bonus['bonus_type']); ?></span></td>
                                    <td><strong class="text-success">GHS <?php echo number_format($bonus['amount'], 2); ?></strong></td>
                                    <td><small><?php echo date('M j, Y', strtotime($bonus['date_given'])); ?></small></td>
                                    <td><small class="text-secondary text-truncate d-block" style="max-width: 250px;" title="<?php echo htmlspecialchars($bonus['reason'] ?? ''); ?>"><?php echo htmlspecialchars($bonus['reason'] ?? 'N/A'); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Pay Slip / Payments History -->
            <?php if (!empty($empPayments)): ?>
                <h6 class="fw-bold text-secondary mt-3 mb-2 small text-uppercase" style="letter-spacing: 0.5px;">Recent Salary Pay Slips</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle border-0">
                        <thead>
                            <tr class="table-light">
                                <th>Period</th>
                                <th>Basic Salary</th>
                                <th>Bonuses Paid</th>
                                <th>Total Net Pay</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($empPayments, 0, 5) as $paySlip): ?>
                                <tr>
                                    <td><span class="fw-bold text-dark"><?php echo date('F Y', mktime(0, 0, 0, $paySlip['month'], 1, $paySlip['year'])); ?></span></td>
                                    <td><small>GHS <?php echo number_format($paySlip['basic_salary'], 2); ?></small></td>
                                    <td><small>GHS <?php echo number_format($paySlip['bonus_amount'], 2); ?></small></td>
                                    <td><strong class="text-success">GHS <?php echo number_format($paySlip['total_earnings'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge bg-success px-2 py-1 text-uppercase" style="font-size: 0.65rem;"><?php echo $paySlip['status']; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Performance Appraisal History -->
        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4 bg-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 border-bottom pb-2">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-star text-warning me-2"></i> Performance Appraisals</h5>
                <?php if (isAdmin() || isHR()): ?>
                    <a href="appraisal.php?employee_id=<?php echo $employee['id']; ?>" class="btn btn-sm btn-outline-primary btn-submit-mobile fw-semibold"><i class="fa-solid fa-plus me-1"></i> Add Rating</a>
                <?php endif; ?>
            </div>
            <?php if (empty($appraisals)): ?>
                <div class="text-center py-4 text-muted">No appraisals recorded yet.</div>
            <?php else: ?>
                <div class="table-responsive border-0">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Appraiser</th>
                                <th>Rating</th>
                                <th>Comments</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appraisals as $app): ?>
                                <tr>
                                    <td><span class="fw-bold text-dark"><?php echo htmlspecialchars($app['appraisal_period']); ?></span></td>
                                    <td><small class="text-secondary"><?php echo htmlspecialchars($app['appraiser_first'] . ' ' . $app['appraiser_last']); ?></small></td>
                                    <td>
                                        <div class="text-warning">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?php echo ($i <= $app['rating']) ? 'fa-solid' : 'fa-regular'; ?> fa-star"></i>
                                            <?php endfor; ?>
                                            <span class="text-dark fw-bold ms-1" style="font-size: 0.85rem;"><?php echo $app['rating']; ?>/5</span>
                                        </div>
                                    </td>
                                    <td><small class="text-muted d-block" style="max-width: 250px;"><?php echo nl2br(htmlspecialchars($app['comments'])); ?></small></td>
                                    <td><small class="text-secondary"><?php echo date('M j, Y', strtotime($app['appraisal_date'])); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Attendance Logs -->
        <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
            <h5 class="fw-bold mb-3 text-dark border-bottom pb-2"><i class="fa-solid fa-clipboard-user text-success me-2"></i> Recent Attendance (Last 30 Days)</h5>
            <?php if (empty($attendanceLogs)): ?>
                <div class="text-center py-4 text-muted">No attendance logs in the last 30 days.</div>
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
                            <?php foreach ($attendanceLogs as $log): ?>
                                <?php
                                $badge = 'bg-success';
                                if ($log['status'] === 'Absent') $badge = 'bg-danger';
                                elseif ($log['status'] === 'Late') $badge = 'bg-warning text-dark';
                                elseif ($log['status'] === 'Permission') $badge = 'bg-info';
                                ?>
                                <tr>
                                    <td><span class="fw-semibold text-dark"><?php echo date('M j, Y', strtotime($log['date'])); ?></span></td>
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

<?php include_once __DIR__ . '/includes/footer.php'; ?>
