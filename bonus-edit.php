<?php
$page_title = "Edit Employee Bonus";
require_once __DIR__ . '/includes/auth.php';
requireRoles(['Admin', 'HR']);

require_once __DIR__ . '/classes/Salary.php';
require_once __DIR__ . '/classes/Employee.php';

$salaryModel = new Salary();
$employeeModel = new Employee();

$id = intval($_GET['id'] ?? 0);
$bonus = $salaryModel->getBonus($id);

if (!$bonus) {
    $_SESSION['error_msg'] = "Bonus record not found.";
    header("Location: salary-bonuses.php");
    exit;
}

$employee = $employeeModel->findById($bonus['employee_id']);
$error = '';
$csrf_token = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($posted_token)) {
        $error = "CSRF verification failed.";
    } else {
        $action = $_POST['action'] ?? 'update';

        if ($action === 'delete') {
            if ($salaryModel->deleteBonus($id)) {
                $_SESSION['success_msg'] = "Bonus record deleted successfully.";
                header("Location: salary-bonuses.php");
                exit;
            } else {
                $error = "Failed to delete bonus record.";
            }
        } else {
            $bonus_type = trim($_POST['bonus_type'] ?? '');
            $amount = trim($_POST['amount'] ?? '');
            $date_given = $_POST['date_given'] ?? date('Y-m-d');
            $reason = trim($_POST['reason'] ?? '');

            if (empty($bonus_type) || empty($amount) || !is_numeric($amount) || floatval($amount) <= 0) {
                $error = "Please enter a valid bonus type and positive amount.";
            } else {
                if ($salaryModel->updateBonus($id, $bonus_type, floatval($amount), $date_given, $reason)) {
                    $_SESSION['success_msg'] = "Bonus record updated successfully!";
                    header("Location: salary-bonuses.php");
                    exit;
                } else {
                    $error = "Failed to update bonus record.";
                }
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
                <h4 class="fw-bold mb-0 text-dark fs-5"><i class="fa-solid fa-pen-to-square text-warning me-2"></i> Edit Employee Bonus</h4>
            </div>

            <div class="mb-4 bg-light p-3 rounded text-center">
                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h6>
                <small class="badge bg-secondary"><?php echo htmlspecialchars($employee['employee_id']); ?></small>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center small mb-3" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="bonus-edit.php?id=<?php echo $id; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" id="bonusAction" value="update">

                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary">Bonus Type / category <span class="text-danger">*</span></label>
                    <select class="form-select" name="bonus_type" required>
                        <option value="Performance Bonus" <?php echo ($bonus['bonus_type'] === 'Performance Bonus') ? 'selected' : ''; ?>>Performance Bonus</option>
                        <option value="Annual Bonus" <?php echo ($bonus['bonus_type'] === 'Annual Bonus') ? 'selected' : ''; ?>>Annual Bonus</option>
                        <option value="Special Bonus" <?php echo ($bonus['bonus_type'] === 'Special Bonus') ? 'selected' : ''; ?>>Special Bonus</option>
                        <option value="Holiday Bonus" <?php echo ($bonus['bonus_type'] === 'Holiday Bonus') ? 'selected' : ''; ?>>Holiday Bonus</option>
                        <option value="Project Completion Bonus" <?php echo ($bonus['bonus_type'] === 'Project Completion Bonus') ? 'selected' : ''; ?>>Project Completion Bonus</option>
                    </select>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-semibold small text-secondary">Bonus Amount (GHS) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-dark fw-bold">GHS</span>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="amount" required value="<?php echo htmlspecialchars($bonus['amount']); ?>">
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-semibold small text-secondary">Date Given <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date_given" required value="<?php echo htmlspecialchars($bonus['date_given']); ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small text-secondary">Reason / Remarks</label>
                    <textarea class="form-control" name="reason" rows="4"><?php echo htmlspecialchars($bonus['reason'] ?? ''); ?></textarea>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-ipmc"><i class="fa-solid fa-save me-1"></i> Update Bonus</button>
                    <button type="button" class="btn btn-outline-danger" onclick="triggerDelete()"><i class="fa-solid fa-trash-can me-1"></i> Delete Bonus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function triggerDelete() {
    if (confirm("Are you sure you want to delete this bonus award? This cannot be undone.")) {
        document.getElementById('bonusAction').value = 'delete';
        document.querySelector('form').submit();
    }
}
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
