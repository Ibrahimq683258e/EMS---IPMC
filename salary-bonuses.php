<?php
$page_title = "Salary & Bonuses";
require_once __DIR__ . '/includes/auth.php';
requireLogin();

require_once __DIR__ . '/classes/Salary.php';
require_once __DIR__ . '/classes/Employee.php';

$salaryModel = new Salary();
$employeeModel = new Employee();

$role = $_SESSION['user_role'] ?? 'Employee';
$user_id = $_SESSION['user_id'];
$csrf_token = generateCSRFToken();

$error = '';
$success = '';

// Handle Payroll payment post (Admin/HR only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isAdmin() || isHR())) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($posted_token)) {
        $error = "CSRF verification failed.";
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'pay_salary') {
            $employee_id = intval($_POST['employee_id'] ?? 0);
            $year = intval($_POST['year'] ?? 0);
            $month = intval($_POST['month'] ?? 0);
            $status = $_POST['status'] ?? 'Paid';
            $paid_date = $_POST['paid_date'] ?? date('Y-m-d');

            if ($employee_id > 0 && $year > 0 && $month > 0) {
                if ($salaryModel->saveMonthlyPayment($employee_id, $year, $month, $status, $paid_date)) {
                    $success = "Salary summary updated successfully!";
                } else {
                    $error = "Failed to process salary payment status.";
                }
            } else {
                $error = "Invalid parameters provided.";
            }
        }
    }
}

// Year & Month for Payroll overview
$selected_year = intval($_GET['year'] ?? date('Y'));
$selected_month = intval($_GET['month'] ?? date('n'));

// Fetch lists
if (isAdmin() || isHR()) {
    $salaries = $salaryModel->getAllSalaries();
    $bonuses = $salaryModel->getBonusesReport();

    // Build active payroll records dynamically
    $activeEmployees = $employeeModel->getAll(['status' => 'Active']);
    $payrollList = [];
    foreach ($activeEmployees as $emp) {
        $payrollList[] = [
            'emp' => $emp,
            'pay' => $salaryModel->getMonthlyPayrollEntry($emp['id'], $selected_year, $selected_month)
        ];
    }
} else {
    // Current Employee specific information
    $salaries = [$salaryModel->getSalary($user_id)];
    $my_salary = $salaryModel->getSalary($user_id);
    $my_history = $salaryModel->getHistory($user_id);
    $my_bonuses = $salaryModel->getBonusesByEmployee($user_id);
    $my_payments = $salaryModel->getPaymentHistory($user_id);
}

include_once __DIR__ . '/includes/header.php';
?>

<div class="row">
    <!-- Global Alert notices inside container -->
    <?php if (!empty($error)): ?>
        <div class="col-12 mb-3">
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="col-12 mb-3">
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa-solid fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isAdmin() || isHR()): ?>
        <!-- ADMIN / HR CORE PORTAL -->
        <div class="col-12">
            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs border-bottom mb-4 d-flex-mobile-stack" id="payrollTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold text-secondary" id="directory-tab" data-bs-toggle="tab" data-bs-target="#directory-pane" type="button" role="tab" aria-controls="directory-pane" aria-selected="true">
                        <i class="fa-solid fa-users-gear me-2"></i>Basic Salaries
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-secondary" id="payroll-tab" data-bs-toggle="tab" data-bs-target="#payroll-pane" type="button" role="tab" aria-controls="payroll-pane" aria-selected="false">
                        <i class="fa-solid fa-file-invoice-dollar me-2"></i>Monthly Payroll Summary
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-secondary" id="bonuses-tab" data-bs-toggle="tab" data-bs-target="#bonuses-pane" type="button" role="tab" aria-controls="bonuses-pane" aria-selected="false">
                        <i class="fa-solid fa-gift me-2"></i>Bonuses Logs
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="payrollTabsContent">

                <!-- PANE 1: Basic Salaries Configuration Directory -->
                <div class="tab-pane fade show active" id="directory-pane" role="tabpanel" aria-labelledby="directory-tab" tabindex="0">
                    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
                        <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-gears text-primary me-2"></i> Manage Employee Base Salary</h5>
                        <p class="text-muted small">Update basic monthly salaries for campus staff. This will affect dynamic payroll and pay slips.</p>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Staff ID</th>
                                        <th>Employee Name</th>
                                        <th>Department</th>
                                        <th>Designation</th>
                                        <th>Basic Salary</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($salaries)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">No salary data recorded.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($salaries as $sal): ?>
                                            <tr>
                                                <td><span class="fw-bold text-primary"><?php echo htmlspecialchars($sal['emp_code']); ?></span></td>
                                                <td><span class="fw-semibold text-dark"><?php echo htmlspecialchars($sal['first_name'] . ' ' . $sal['last_name']); ?></span></td>
                                                <td><small class="text-secondary"><?php echo htmlspecialchars($sal['department_name'] ?? 'Unassigned'); ?></small></td>
                                                <td><small class="text-dark"><?php echo htmlspecialchars($sal['designation'] ?? 'Staff'); ?></small></td>
                                                <td>
                                                    <span class="fw-bold text-success">GHS <?php echo number_format($sal['basic_salary'] ?? 0.00, 2); ?></span>
                                                    <small class="text-muted d-block" style="font-size: 0.75rem;">Monthly Base</small>
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-flex justify-content-end gap-1 flex-wrap">
                                                        <a href="salary-update.php?id=<?php echo $sal['employee_id']; ?>" class="btn btn-sm btn-outline-warning text-dark fw-semibold">
                                                            <i class="fa-solid fa-pen-to-square me-1"></i>Set Base
                                                        </a>
                                                        <a href="bonus-add.php?id=<?php echo $sal['employee_id']; ?>" class="btn btn-sm btn-outline-success fw-semibold">
                                                            <i class="fa-solid fa-plus me-1"></i>Add Bonus
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- PANE 2: Monthly Payroll & Payments Status Summary -->
                <div class="tab-pane fade" id="payroll-pane" role="tabpanel" aria-labelledby="payroll-tab" tabindex="0">
                    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white mb-4">
                        <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-filter text-primary me-2"></i> Select Payroll Month</h5>

                        <form method="GET" action="salary-bonuses.php" class="row g-3 align-items-end">
                            <input type="hidden" name="tab" value="payroll">
                            <div class="col-12 col-sm-5 col-md-4">
                                <label class="form-label fw-semibold small text-secondary">Year</label>
                                <select class="form-select" name="year">
                                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo ($selected_year === $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-12 col-sm-5 col-md-4">
                                <label class="form-label fw-semibold small text-secondary">Month</label>
                                <select class="form-select" name="month">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo ($selected_month === $m) ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-12 col-sm-2 col-md-4 d-grid">
                                <button type="submit" class="btn btn-ipmc"><i class="fa-solid fa-magnifying-glass me-1"></i>View Summary</button>
                            </div>
                        </form>
                    </div>

                    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
                        <h5 class="fw-bold mb-3 text-dark">
                            <i class="fa-solid fa-file-invoice-dollar text-success me-2"></i>
                            Payroll Status: <?php echo date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year)); ?>
                        </h5>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Staff ID</th>
                                        <th>Employee</th>
                                        <th>Basic Salary</th>
                                        <th>Bonuses (Month)</th>
                                        <th>Total Earnings</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payrollList as $item): ?>
                                        <?php
                                        $emp = $item['emp'];
                                        $pay = $item['pay'];
                                        $isPaid = $pay['status'] === 'Paid';
                                        ?>
                                        <tr>
                                            <td><span class="fw-bold text-primary"><?php echo htmlspecialchars($emp['employee_id']); ?></span></td>
                                            <td>
                                                <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></span>
                                                <small class="text-secondary"><?php echo htmlspecialchars($emp['designation'] ?? 'Staff'); ?></small>
                                            </td>
                                            <td><span class="fw-semibold">GHS <?php echo number_format($pay['basic_salary'], 2); ?></span></td>
                                            <td><span class="fw-semibold text-secondary">GHS <?php echo number_format($pay['bonus_amount'], 2); ?></span></td>
                                            <td><strong class="text-success">GHS <?php echo number_format($pay['total_earnings'], 2); ?></strong></td>
                                            <td>
                                                <span class="badge <?php echo $isPaid ? 'bg-success' : 'bg-warning text-dark'; ?> px-2.5 py-1.5">
                                                    <?php echo $pay['status']; ?>
                                                </span>
                                                <?php if ($isPaid && $pay['paid_date']): ?>
                                                    <small class="d-block text-muted mt-1" style="font-size: 0.7rem;">Paid on <?php echo date('M j, Y', strtotime($pay['paid_date'])); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm <?php echo $isPaid ? 'btn-outline-secondary' : 'btn-success'; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#payModal-<?php echo $emp['id']; ?>">
                                                    <i class="fa-solid fa-credit-card me-1"></i>Mark Pay
                                                </button>

                                                <!-- Payment Status Modal -->
                                                <div class="modal fade" id="payModal-<?php echo $emp['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered text-start">
                                                        <div class="modal-content border-0 shadow">
                                                            <div class="modal-header bg-light border-0">
                                                                <h5 class="modal-title fw-bold">Update Payment Status</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body p-4 text-dark">
                                                                <div class="mb-3">
                                                                    <span class="text-muted small d-block">Employee Name</span>
                                                                    <strong><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></strong>
                                                                </div>
                                                                <div class="row mb-3">
                                                                    <div class="col-6">
                                                                        <span class="text-muted small d-block">Period</span>
                                                                        <strong><?php echo date('F Y', mktime(0,0,0, $selected_month, 1, $selected_year)); ?></strong>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <span class="text-muted small d-block">Total Net Pay</span>
                                                                        <strong class="text-success">GHS <?php echo number_format($pay['total_earnings'], 2); ?></strong>
                                                                    </div>
                                                                </div>
                                                                <hr>
                                                                <form method="POST" action="salary-bonuses.php?year=<?php echo $selected_year; ?>&month=<?php echo $selected_month; ?>">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                                    <input type="hidden" name="action" value="pay_salary">
                                                                    <input type="hidden" name="employee_id" value="<?php echo $emp['id']; ?>">
                                                                    <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
                                                                    <input type="hidden" name="month" value="<?php echo $selected_month; ?>">

                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-semibold small text-secondary">Payment Status</label>
                                                                        <select class="form-select" name="status" required>
                                                                            <option value="Paid" <?php echo $isPaid ? 'selected' : ''; ?>>Paid</option>
                                                                            <option value="Unpaid" <?php echo !$isPaid ? 'selected' : ''; ?>>Unpaid</option>
                                                                        </select>
                                                                    </div>

                                                                    <div class="mb-4">
                                                                        <label class="form-label fw-semibold small text-secondary">Payment Date</label>
                                                                        <input type="date" class="form-control" name="paid_date" value="<?php echo $pay['paid_date'] ?? date('Y-m-d'); ?>">
                                                                    </div>

                                                                    <div class="d-grid">
                                                                        <button type="submit" class="btn btn-ipmc"><i class="fa-solid fa-save me-1"></i>Save Status</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- PANE 3: Employee Bonuses Feed -->
                <div class="tab-pane fade" id="bonuses-pane" role="tabpanel" aria-labelledby="bonuses-tab" tabindex="0">
                    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-award text-warning me-2"></i> Employee Bonuses Log</h5>
                            <a href="bonus-add.php" class="btn btn-sm btn-ipmc btn-submit-mobile"><i class="fa-solid fa-plus me-1"></i>Add New Bonus</a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Bonus Type</th>
                                        <th>Amount</th>
                                        <th>Date Given</th>
                                        <th>Reason/Description</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($bonuses)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">No bonuses registered.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($bonuses as $bn): ?>
                                            <tr>
                                                <td>
                                                    <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($bn['first_name'] . ' ' . $bn['last_name']); ?></span>
                                                    <small class="text-muted"><?php echo htmlspecialchars($bn['emp_code']); ?></small>
                                                </td>
                                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($bn['bonus_type']); ?></span></td>
                                                <td><strong class="text-success">GHS <?php echo number_format($bn['amount'], 2); ?></strong></td>
                                                <td><small class="text-dark"><?php echo date('M j, Y', strtotime($bn['date_given'])); ?></small></td>
                                                <td><small class="text-secondary text-wrap" style="max-width: 250px; display: block;"><?php echo htmlspecialchars($bn['reason'] ?? 'N/A'); ?></small></td>
                                                <td class="text-end">
                                                    <a href="bonus-edit.php?id=<?php echo $bn['id']; ?>" class="btn btn-sm btn-outline-warning text-dark"><i class="fa-solid fa-edit"></i></a>
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

    <?php else: ?>
        <!-- STANDARD STAFF MEMBER PERSONAL COMP DETAILS -->
        <div class="col-12 col-md-4 mb-4">
            <div class="card border-0 shadow-sm text-center p-3 p-md-4 bg-white mb-4">
                <div class="stat-icon bg-success text-white mx-auto mb-3" style="width: 60px; height: 60px; border-radius: 50%;">
                    <i class="fa-solid fa-money-check-dollar" style="font-size: 1.75rem;"></i>
                </div>
                <h6 class="text-secondary fw-semibold mb-1">My Basic Salary</h6>
                <h3 class="fw-bold text-dark">GHS <?php echo number_format($my_salary['basic_salary'] ?? 0.00, 2); ?></h3>
                <span class="text-muted small">Current Monthly Rate</span>
            </div>

            <!-- Earnings Summary Card -->
            <?php $earnings = $salaryModel->getEmployeeEarningsSummary($user_id); ?>
            <div class="card border-0 shadow-sm p-3 p-md-4 bg-white mb-4">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-chart-pie text-primary me-2"></i>Cumulative Earnings</h5>
                <div class="mb-3">
                    <small class="text-muted">Total Basic Paid</small>
                    <h5 class="fw-bold text-dark">GHS <?php echo number_format($earnings['total_basic'] ?? 0.00, 2); ?></h5>
                </div>
                <div class="mb-3">
                    <small class="text-muted">Total Bonuses Received</small>
                    <h5 class="fw-bold text-dark">GHS <?php echo number_format($earnings['total_bonus'] ?? 0.00, 2); ?></h5>
                </div>
                <hr>
                <div class="mb-0">
                    <small class="text-muted">Aggregate Earnings</small>
                    <h4 class="fw-bold text-success">GHS <?php echo number_format($earnings['total_earnings'] ?? 0.00, 2); ?></h4>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-8 mb-4">
            <!-- Navigation Tabs for Employee -->
            <ul class="nav nav-tabs border-bottom mb-4 d-flex-mobile-stack" id="personalCompTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold text-secondary" id="slips-tab" data-bs-toggle="tab" data-bs-target="#slips-pane" type="button" role="tab" aria-controls="slips-pane" aria-selected="true">
                        <i class="fa-solid fa-file-invoice-dollar me-2"></i>My Pay Slips
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-secondary" id="mybonuses-tab" data-bs-toggle="tab" data-bs-target="#mybonuses-pane" type="button" role="tab" aria-controls="mybonuses-pane" aria-selected="false">
                        <i class="fa-solid fa-gift me-2"></i>My Bonuses
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-secondary" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-pane" type="button" role="tab" aria-controls="history-pane" aria-selected="false">
                        <i class="fa-solid fa-clock-rotate-left me-2"></i>Salary History
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="personalCompTabsContent">

                <!-- MY PAY SLIPS -->
                <div class="tab-pane fade show active" id="slips-pane" role="tabpanel" aria-labelledby="slips-tab" tabindex="0">
                    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
                        <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-folder-closed text-primary me-2"></i> Pay Slips History</h5>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>Basic Salary</th>
                                        <th>Bonus Amount</th>
                                        <th>Total Paid</th>
                                        <th>Status</th>
                                        <th>Payment Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($my_payments)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">No payout slips generated yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($my_payments as $pm): ?>
                                            <tr>
                                                <td><span class="fw-bold"><?php echo date('F Y', mktime(0, 0, 0, $pm['month'], 1, $pm['year'])); ?></span></td>
                                                <td>GHS <?php echo number_format($pm['basic_salary'], 2); ?></td>
                                                <td>GHS <?php echo number_format($pm['bonus_amount'], 2); ?></td>
                                                <td><strong class="text-success">GHS <?php echo number_format($pm['total_earnings'], 2); ?></strong></td>
                                                <td><span class="badge bg-success"><?php echo $pm['status']; ?></span></td>
                                                <td><small><?php echo $pm['paid_date'] ? date('M j, Y', strtotime($pm['paid_date'])) : 'N/A'; ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- MY BONUSES -->
                <div class="tab-pane fade" id="mybonuses-pane" role="tabpanel" aria-labelledby="mybonuses-tab" tabindex="0">
                    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
                        <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-award text-warning me-2"></i> Personal Bonuses Awarded</h5>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Bonus Type</th>
                                        <th>Amount</th>
                                        <th>Date Given</th>
                                        <th>Reason / Feedback</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($my_bonuses)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">No bonuses awarded yet. Keep up the high efforts!</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($my_bonuses as $mbn): ?>
                                            <tr>
                                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($mbn['bonus_type']); ?></span></td>
                                                <td><strong class="text-success">GHS <?php echo number_format($mbn['amount'], 2); ?></strong></td>
                                                <td><small><?php echo date('M j, Y', strtotime($mbn['date_given'])); ?></small></td>
                                                <td><small class="text-secondary text-wrap" style="max-width: 250px; display: block;"><?php echo htmlspecialchars($mbn['reason'] ?? 'N/A'); ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- PERSONAL SALARY SCALE MODIFICATION HISTORY -->
                <div class="tab-pane fade" id="history-pane" role="tabpanel" aria-labelledby="history-tab" tabindex="0">
                    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
                        <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Base Salary History</h5>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Old Scale</th>
                                        <th>New Scale</th>
                                        <th>Change Amount</th>
                                        <th>Effective Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($my_history)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">No scale changes registered.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($my_history as $hist): ?>
                                            <?php
                                            $diff = $hist['new_salary'] - $hist['old_salary'];
                                            $diff_class = ($diff >= 0) ? 'text-success' : 'text-danger';
                                            $diff_symbol = ($diff >= 0) ? '+' : '';
                                            ?>
                                            <tr>
                                                <td>GHS <?php echo number_format($hist['old_salary'], 2); ?></td>
                                                <td>GHS <?php echo number_format($hist['new_salary'], 2); ?></td>
                                                <td><strong class="<?php echo $diff_class; ?>"><?php echo $diff_symbol; ?>GHS <?php echo number_format($diff, 2); ?></strong></td>
                                                <td><small class="text-secondary"><?php echo date('M j, Y, h:i A', strtotime($hist['changed_at'])); ?></small></td>
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
    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
