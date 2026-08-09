<?php
$page_title = "Performance Appraisal";
require_once __DIR__ . '/includes/auth.php';
requireLogin();

require_once __DIR__ . '/classes/Appraisal.php';
require_once __DIR__ . '/classes/Employee.php';

$appraisalModel = new Appraisal();
$employeeModel = new Employee();

$role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];
$error = '';
$csrf_token = generateCSRFToken();

// Selected employee filter if specified in GET (prefilled when clicking "Appraise Staff" from directory)
$prefilled_employee_id = intval($_GET['employee_id'] ?? 0);

// Handle new appraisal submission (Admin/HR only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isAdmin() || isHR())) {
    $posted_token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($posted_token)) {
        $error = "CSRF verification failed.";
    } else {
        $employee_id = intval($_POST['employee_id'] ?? 0);
        $rating = intval($_POST['rating'] ?? 0);
        $appraisal_period = trim($_POST['appraisal_period'] ?? '');
        $comments = trim($_POST['comments'] ?? '');

        if ($employee_id <= 0 || $rating < 1 || $rating > 5 || empty($appraisal_period) || empty($comments)) {
            $error = "Please fill in all fields. Rating must be between 1 and 5.";
        } else {
            $result = $appraisalModel->create($employee_id, $user_id, $rating, $comments, $appraisal_period);
            if ($result === true) {
                $_SESSION['success_msg'] = "Performance Appraisal submitted successfully!";
                header("Location: appraisal.php");
                exit;
            } else {
                $error = is_string($result) ? $result : "Failed to record performance appraisal.";
            }
        }
    }
}

// Fetch lists based on role
if (isAdmin() || isHR()) {
    $appraisals = $appraisalModel->getAll();
    $employeesList = $employeeModel->getAll(['status' => 'Active']);
} else {
    $appraisals = $appraisalModel->getByEmployee($user_id);
    $employeesList = [];
}

include_once __DIR__ . '/includes/header.php';
?>

<div class="row">
    <!-- Left Column: Submit Appraisal Rating (Admin / HR only) -->
    <?php if (isAdmin() || isHR()): ?>
        <div class="col-12 col-lg-4 mb-4">
            <div class="card border-0 shadow-sm p-4 bg-white">
                <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-star-half-stroke text-warning me-2"></i> Submit New Appraisal</h5>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center small" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="appraisal.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Employee to Appraise <span class="text-danger">*</span></label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employeesList as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" <?php echo ($prefilled_employee_id === $emp['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?> (<?php echo htmlspecialchars($emp['employee_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Appraisal Period <span class="text-danger">*</span></label>
                        <select class="form-select" name="appraisal_period" required>
                            <option value="">Select Period</option>
                            <option value="2025 - Q1">2025 - Q1 (Jan-Mar)</option>
                            <option value="2025 - Q2">2025 - Q2 (Apr-Jun)</option>
                            <option value="2025 - Q3">2025 - Q3 (Jul-Sep)</option>
                            <option value="2025 - Q4">2025 - Q4 (Oct-Dec)</option>
                            <option value="Full Year 2025">Full Year 2025</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Performance Rating <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3 align-items-center bg-light p-2.5 rounded border">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="form-check form-check-inline m-0">
                                    <input class="form-check-input" type="radio" name="rating" id="rating-<?php echo $i; ?>" value="<?php echo $i; ?>" <?php echo ($i === 5) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold text-dark" for="rating-<?php echo $i; ?>"><?php echo $i; ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <small class="text-muted d-block mt-1">Scale of 1 (Lowest) to 5 (Outstanding)</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold small text-secondary">Evaluation Remarks / Feedback <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="comments" rows="5" required placeholder="State employee's achievements, skills, or fields of improvement..."></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-ipmc"><i class="fa-solid fa-square-check me-1"></i> Submit Appraisal</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Right Column: Appraisal History -->
    <div class="col-12 <?php echo (isAdmin() || isHR()) ? 'col-lg-8' : ''; ?> mb-4">
        <div class="card border-0 shadow-sm p-4 bg-white h-100">
            <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-list-check text-primary me-2"></i> Performance Appraisals History</h5>

            <?php if (empty($appraisals)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-star-half-stroke mb-2" style="font-size: 3rem;"></i>
                    <p class="mb-0">No appraisal records logged yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive border-0">
                    <table class="table table-hover align-middle text-dark">
                        <thead>
                            <tr>
                                <?php if (isAdmin() || isHR()): ?>
                                    <th>Employee</th>
                                <?php endif; ?>
                                <th>Period</th>
                                <th>Rating</th>
                                <th>Remarks & Feedback</th>
                                <th>Appraiser</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appraisals as $app): ?>
                                <tr>
                                    <?php if (isAdmin() || isHR()): ?>
                                        <td>
                                            <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($app['emp_first'] . ' ' . $app['emp_last']); ?></span>
                                            <small class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($app['emp_code']); ?> | <?php echo htmlspecialchars($app['department_name'] ?? 'Unassigned'); ?></small>
                                        </td>
                                    <?php endif; ?>
                                    <td><span class="badge bg-light text-dark border fw-medium"><?php echo htmlspecialchars($app['appraisal_period']); ?></span></td>
                                    <td>
                                        <div class="text-warning text-nowrap">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?php echo ($i <= $app['rating']) ? 'fa-solid' : 'fa-regular'; ?> fa-star"></i>
                                            <?php endfor; ?>
                                            <span class="text-dark fw-bold ms-1" style="font-size: 0.85rem;"><?php echo $app['rating']; ?>/5</span>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted d-block" style="max-width: 250px; white-space: normal; line-height: 1.4;">
                                            <?php echo nl2br(htmlspecialchars($app['comments'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-secondary fw-semibold"><?php echo htmlspecialchars($app['appraiser_first'] . ' ' . $app['appraiser_last']); ?></small>
                                    </td>
                                    <td><small class="text-muted"><?php echo date('M j, Y', strtotime($app['appraisal_date'])); ?></small></td>
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
