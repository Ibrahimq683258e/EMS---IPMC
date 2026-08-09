<?php
$page_title = "Manage Leave Applications";
require_once __DIR__ . '/includes/auth.php';
requireRoles(['Admin', 'HR']);

require_once __DIR__ . '/classes/Leave.php';
$leaveModel = new Leave();

$error = '';
$success = '';
$csrf_token = generateCSRFToken();

// Handle leave status processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $posted_token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($posted_token)) {
        $error = "CSRF verification failed.";
    } else {
        $id = intval($_POST['id'] ?? 0);
        $action_type = $_POST['action_type']; // 'Approved' or 'Rejected'
        $comments = trim($_POST['comments'] ?? '');
        $action_by = $_SESSION['user_id'];

        if ($id <= 0 || !in_array($action_type, ['Approved', 'Rejected'])) {
            $error = "Invalid transaction parameters.";
        } else {
            $result = $leaveModel->process($id, $action_type, $action_by, $comments);
            if ($result === true) {
                $_SESSION['success_msg'] = "Leave application marked as {$action_type} successfully!";
                header("Location: leave-manage.php");
                exit;
            } else {
                $error = is_string($result) ? $result : "Failed to process leave application.";
            }
        }
    }
}

// Get filter inputs
$status_filter = $_GET['status'] ?? 'Pending'; // default to pending for administrative convenience
$leave_type_filter = $_GET['leave_type'] ?? '';

$filters = [
    'status' => $status_filter,
    'leave_type' => $leave_type_filter
];

$leaves = $leaveModel->getAll($filters);
include_once __DIR__ . '/includes/header.php';
?>

<div class="row">
    <!-- Filter Bar -->
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm p-4 bg-white">
            <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-filter text-primary me-2"></i> Filter Requests</h5>
            <form method="GET" action="leave-manage.php" class="row g-3">
                <div class="col-12 col-sm-6 col-md-4">
                    <label class="form-label fw-semibold small text-secondary">Decision Status</label>
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="">All Applications</option>
                        <option value="Pending" <?php echo ($status_filter === 'Pending') ? 'selected' : ''; ?>>Pending Decision</option>
                        <option value="Approved" <?php echo ($status_filter === 'Approved') ? 'selected' : ''; ?>>Approved Requests</option>
                        <option value="Rejected" <?php echo ($status_filter === 'Rejected') ? 'selected' : ''; ?>>Rejected Requests</option>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-md-4">
                    <label class="form-label fw-semibold small text-secondary">Leave Type</label>
                    <select class="form-select" name="leave_type" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="Annual" <?php echo ($leave_type_filter === 'Annual') ? 'selected' : ''; ?>>Annual Leave</option>
                        <option value="Sick" <?php echo ($leave_type_filter === 'Sick') ? 'selected' : ''; ?>>Sick Leave</option>
                        <option value="Casual" <?php echo ($leave_type_filter === 'Casual') ? 'selected' : ''; ?>>Casual Leave</option>
                        <option value="Maternity" <?php echo ($leave_type_filter === 'Maternity') ? 'selected' : ''; ?>>Maternity Leave</option>
                        <option value="Paternity" <?php echo ($leave_type_filter === 'Paternity') ? 'selected' : ''; ?>>Paternity Leave</option>
                        <option value="Study" <?php echo ($leave_type_filter === 'Study') ? 'selected' : ''; ?>>Study Leave</option>
                    </select>
                </div>

                <div class="col-12 col-md-4 d-flex align-items-end">
                    <a href="leave-manage.php" class="btn btn-light border w-100"><i class="fa-solid fa-rotate-left me-1"></i> Reset Filters</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Error notice -->
    <?php if (!empty($error)): ?>
        <div class="col-12 mb-3">
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Leave Requests Listing -->
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-white">
            <div class="card-body p-0">
                <?php if (empty($leaves)): ?>
                    <div class="text-center py-5">
                        <div class="text-secondary mb-3" style="font-size: 3rem;"><i class="fa-solid fa-calendar-xmark"></i></div>
                        <h5 class="fw-bold text-dark">No leave applications found</h5>
                        <p class="text-muted">No records match your selected filter criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle text-dark">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Duration Dates</th>
                                    <th>Days</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leaves as $req): ?>
                                    <?php
                                    $badge = 'bg-warning text-dark';
                                    if ($req['status'] === 'Approved') $badge = 'bg-success';
                                    elseif ($req['status'] === 'Rejected') $badge = 'bg-danger';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle overflow-hidden border me-2.5" style="width: 36px; height: 36px;">
                                                    <img src="<?php echo !empty($req['photo']) && file_exists($req['photo']) ? $req['photo'] : 'uploads/default.png'; ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                                </div>
                                                <div>
                                                    <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></span>
                                                    <small class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($req['employee_code']); ?> | <?php echo htmlspecialchars($req['department_name'] ?? 'Unassigned'); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border fw-medium"><?php echo htmlspecialchars($req['leave_type']); ?></span></td>
                                        <td>
                                            <small class="fw-semibold text-dark d-block"><?php echo date('M j, Y', strtotime($req['start_date'])); ?></small>
                                            <small class="text-muted">to <?php echo date('M j, Y', strtotime($req['end_date'])); ?></small>
                                        </td>
                                        <td><span class="fw-bold text-dark"><?php echo $req['days_requested']; ?></span></td>
                                        <td><span class="badge <?php echo $badge; ?> px-2.5 py-1.5"><?php echo $req['status']; ?></span></td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-ipmc" data-bs-toggle="modal" data-bs-target="#processModal-<?php echo $req['id']; ?>">
                                                <i class="fa-solid fa-gears me-1"></i> Review
                                            </button>

                                            <!-- Review and Decision Modal -->
                                            <div class="modal fade" id="processModal-<?php echo $req['id']; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered text-start">
                                                    <div class="modal-content border-0 shadow-lg text-dark">
                                                        <div class="modal-header bg-light border-0">
                                                            <h5 class="modal-title fw-bold">Review Leave Request</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body p-4">
                                                            <div class="mb-3">
                                                                <span class="text-muted small d-block">Employee</span>
                                                                <strong><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?> (<?php echo htmlspecialchars($req['employee_code']); ?>)</strong>
                                                            </div>
                                                            <div class="row mb-3">
                                                                <div class="col-6">
                                                                    <span class="text-muted small d-block">Type</span>
                                                                    <strong><?php echo htmlspecialchars($req['leave_type']); ?> Leave</strong>
                                                                </div>
                                                                <div class="col-6">
                                                                    <span class="text-muted small d-block">Days Requested</span>
                                                                    <strong><?php echo $req['days_requested']; ?> Days</strong>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <span class="text-muted small d-block">Reason</span>
                                                                <p class="text-dark bg-light p-3 rounded mb-0 small" style="line-height: 1.5;"><?php echo nl2br(htmlspecialchars($req['reason'])); ?></p>
                                                            </div>

                                                            <hr class="my-4">

                                                            <form method="POST" action="leave-manage.php">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                                <input type="hidden" name="id" value="<?php echo $req['id']; ?>">

                                                                <div class="mb-4">
                                                                    <label class="form-label fw-semibold small text-secondary">Comments / Feedback</label>
                                                                    <textarea class="form-control" name="comments" rows="3" placeholder="State reason for approval or rejection..."><?php echo htmlspecialchars($req['comments'] ?? ''); ?></textarea>
                                                                </div>

                                                                <div class="d-flex justify-content-end gap-2">
                                                                    <button type="submit" name="action_type" value="Rejected" class="btn btn-danger px-3"><i class="fa-solid fa-circle-xmark me-1"></i> Reject Request</button>
                                                                    <button type="submit" name="action_type" value="Approved" class="btn btn-success px-3"><i class="fa-solid fa-circle-check me-1"></i> Approve Request</button>
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
