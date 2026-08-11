<?php
$page_title = "Add Employee Bonus";
require_once __DIR__ . '/includes/auth.php';
requireRoles(['Admin', 'HR']);

require_once __DIR__ . '/classes/Salary.php';
require_once __DIR__ . '/classes/Employee.php';

$salaryModel = new Salary();
$employeeModel = new Employee();

$prefilled_id = intval($_GET['id'] ?? 0);
$employees = $employeeModel->getAll(['status' => 'Active']);

$error = '';
$csrf_token = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($posted_token)) {
        $error = "CSRF verification failed.";
    } else {
        $employee_id = intval($_POST['employee_id'] ?? 0);
        $bonus_type = trim($_POST['bonus_type'] ?? '');
        $amount = trim($_POST['amount'] ?? '');
        $date_given = $_POST['date_given'] ?? date('Y-m-d');
        $reason = trim($_POST['reason'] ?? '');

        if ($employee_id <= 0 || empty($bonus_type) || empty($amount) || !is_numeric($amount) || floatval($amount) <= 0) {
            $error = "Please fill in all fields with valid data. Amount must be a positive number.";
        } else {
            if ($salaryModel->addBonus($employee_id, $bonus_type, floatval($amount), $date_given, $reason)) {
                $_SESSION['success_msg'] = "Bonus awarded successfully to the staff member!";
                header("Location: salary-bonuses.php");
                exit;
            } else {
                $error = "Failed to record bonus. Database error occurred.";
            }
        }
    }
}

include_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
            <div class="d-flex align-items-center mb-4 border-bottom pb-3 gap-2">
                <a href="salary-bonuses.php" class="btn btn-sm btn-outline-secondary me-2"><i class="fa-solid fa-arrow-left"></i> Back</a>
                <h4 class="fw-bold mb-0 text-dark fs-5"><i class="fa-solid fa-award text-success me-2"></i> Award Employee Bonus</h4>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center small mb-3" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="bonus-add.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary">Select Employee <span class="text-danger">*</span></label>
                    <select class="form-select" name="employee_id" required>
                        <option value="">Choose active employee...</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo ($prefilled_id === $emp['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?> (<?php echo htmlspecialchars($emp['employee_id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary">Bonus Type / category <span class="text-danger">*</span></label>
                    <select class="form-select" name="bonus_type" required>
                        <option value="">Select category...</option>
                        <option value="Performance Bonus">Performance Bonus</option>
                        <option value="Annual Bonus">Annual Bonus</option>
                        <option value="Special Bonus">Special Bonus</option>
                        <option value="Holiday Bonus">Holiday Bonus</option>
                        <option value="Project Completion Bonus">Project Completion Bonus</option>
                    </select>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-semibold small text-secondary">Bonus Amount (GHS) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-dark fw-bold">GHS</span>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="amount" required placeholder="e.g. 500.00">
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-semibold small text-secondary">Date Given <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date_given" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small text-secondary">Reason / Remarks</label>
                    <textarea class="form-control" name="reason" rows="4" placeholder="Brief explanation of achievements or justification..."></textarea>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-ipmc"><i class="fa-solid fa-gift me-1"></i> Award Bonus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
