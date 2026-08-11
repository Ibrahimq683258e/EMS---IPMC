<?php
$page_title = "Update Salary Base";
require_once __DIR__ . '/includes/auth.php';
requireRoles(['Admin', 'HR']);

require_once __DIR__ . '/classes/Salary.php';
require_once __DIR__ . '/classes/Employee.php';

$salaryModel = new Salary();
$employeeModel = new Employee();

$id = intval($_GET['id'] ?? 0);
$employee = $employeeModel->findById($id);

if (!$employee) {
    $_SESSION['error_msg'] = "Employee profile not found.";
    header("Location: salary-bonuses.php");
    exit;
}

$current_salary = $salaryModel->getSalary($id);
$history = $salaryModel->getHistory($id);
$error = '';
$csrf_token = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($posted_token)) {
        $error = "CSRF verification failed.";
    } else {
        $basic_salary = trim($_POST['basic_salary'] ?? '');
        if (empty($basic_salary) || !is_numeric($basic_salary) || floatval($basic_salary) < 0) {
            $error = "Please enter a valid, positive basic salary amount.";
        } else {
            if ($salaryModel->updateSalary($id, floatval($basic_salary), $_SESSION['user_id'])) {
                $_SESSION['success_msg'] = "Basic salary scale updated successfully!";
                header("Location: salary-bonuses.php");
                exit;
            } else {
                $error = "Failed to update employee salary scale.";
            }
        }
    }
}

include_once __DIR__ . '/includes/header.php';
?>

<div class="row">
    <!-- Left Column: Update Scale -->
    <div class="col-12 col-lg-5 mb-4">
        <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
            <div class="d-flex align-items-center mb-3 flex-wrap gap-2">
                <a href="salary-bonuses.php" class="btn btn-sm btn-outline-secondary me-2"><i class="fa-solid fa-arrow-left"></i> Back</a>
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-money-bill-transfer text-primary me-2"></i> Update Salary Scale</h5>
            </div>

            <div class="mb-4 bg-light p-3 rounded text-center">
                <div class="rounded-circle overflow-hidden mx-auto mb-2 border" style="width: 70px; height: 70px;">
                    <img src="<?php echo !empty($employee['photo']) && file_exists($employee['photo']) ? $employee['photo'] : 'uploads/default.png'; ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h6>
                <small class="badge bg-secondary"><?php echo htmlspecialchars($employee['employee_id']); ?></small>
                <div class="text-muted mt-1" style="font-size: 0.8rem;"><?php echo htmlspecialchars($employee['designation'] ?? 'Staff'); ?></div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center small mb-3" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="salary-update.php?id=<?php echo $employee['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary">Current Basic Salary (GHS)</label>
                    <input type="text" class="form-control bg-light" value="<?php echo number_format($current_salary['basic_salary'] ?? 0.00, 2); ?>" readonly disabled>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small text-secondary">New Monthly Basic Salary (GHS) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-dark fw-bold">GHS</span>
                        <input type="number" step="0.01" min="0" class="form-control" name="basic_salary" required value="<?php echo htmlspecialchars($current_salary['basic_salary'] ?? ''); ?>" placeholder="e.g. 5500.00">
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-ipmc"><i class="fa-solid fa-floppy-disk me-1"></i> Save Scale Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Column: Modification History -->
    <div class="col-12 col-lg-7 mb-4">
        <div class="card border-0 shadow-sm p-3 p-md-4 bg-white h-100">
            <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Salary Revision Log</h5>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Old Scale</th>
                            <th>New Scale</th>
                            <th>Changer / Admin</th>
                            <th>Revision Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">No salary scale revisions logged.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td>GHS <?php echo number_format($h['old_salary'], 2); ?></td>
                                    <td><strong class="text-success">GHS <?php echo number_format($h['new_salary'], 2); ?></strong></td>
                                    <td>
                                        <small class="text-secondary fw-semibold">
                                            <?php echo $h['changer_first'] ? htmlspecialchars($h['changer_first'] . ' ' . $h['changer_last']) : 'System'; ?>
                                        </small>
                                    </td>
                                    <td><small class="text-muted"><?php echo date('M j, Y, h:i A', strtotime($h['changed_at'])); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
