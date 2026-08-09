<?php
$page_title = "Apply for Leave";
require_once __DIR__ . '/includes/auth.php';
requireLogin();

require_once __DIR__ . '/classes/Leave.php';
$leaveModel = new Leave();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$csrf_token = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($posted_token)) {
        $error = "CSRF verification failed.";
    } else {
        $leave_type = $_POST['leave_type'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $reason = trim($_POST['reason'] ?? '');

        if (empty($leave_type) || empty($start_date) || empty($end_date) || empty($reason)) {
            $error = "Please fill in all required fields.";
        } else {
            $result = $leaveModel->apply($user_id, $leave_type, $start_date, $end_date, $reason);
            if ($result === true) {
                $_SESSION['success_msg'] = "Leave application submitted successfully! Pending HR decision.";
                header("Location: leave-apply.php");
                exit;
            } else {
                $error = $result; // returns error message
            }
        }
    }
}

// Fetch employee's balances and history
$balances = $leaveModel->getBalances($user_id);
$history = $leaveModel->getAll(['employee_id' => $user_id]);

include_once __DIR__ . '/includes/header.php';
?>

<div class="row">
    <!-- Left Column: Application Form & Balances -->
    <div class="col-12 col-lg-5 mb-4">
        <!-- Balances Summary -->
        <div class="card border-0 shadow-sm p-4 mb-4 bg-white">
            <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-hourglass-start text-primary me-2"></i> My Leave Balances</h5>
            <div class="row g-2">
                <?php if (empty($balances)): ?>
                    <p class="text-muted small">No allocations yet.</p>
                <?php else: ?>
                    <?php foreach ($balances as $bal): ?>
                        <div class="col-6 mb-2">
                            <div class="p-2.5 border rounded bg-light text-center">
                                <span class="fw-bold text-secondary d-block" style="font-size: 0.85rem;"><?php echo htmlspecialchars($bal['leave_type']); ?></span>
                                <h4 class="fw-bold text-primary m-0"><?php echo ($bal['allocated'] - $bal['used']); ?></h4>
                                <small class="text-muted" style="font-size: 0.7rem;">of <?php echo $bal['allocated']; ?> days left</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Submit Leave Request -->
        <div class="card border-0 shadow-sm p-4 bg-white">
            <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-calendar-plus text-success me-2"></i> Submit Leave Request</h5>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center small" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="leave-apply.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Leave Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="leave_type" required>
                        <option value="">Select leave type</option>
                        <option value="Annual">Annual Leave</option>
                        <option value="Sick">Sick Leave</option>
                        <option value="Casual">Casual Leave</option>
                        <option value="Maternity">Maternity Leave</option>
                        <option value="Paternity">Paternity Leave</option>
                        <option value="Study">Study Leave</option>
                    </select>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold small">Start Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold small">End Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small">Reason / Justification <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="reason" rows="4" required placeholder="State details of your request..."></textarea>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-ipmc"><i class="fa-solid fa-paper-plane me-1"></i> Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Column: Leave Application History -->
    <div class="col-12 col-lg-7 mb-4">
        <div class="card border-0 shadow-sm p-4 bg-white h-100">
            <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Leave Request History</h5>

            <?php if (empty($history)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-calendar-xmark mb-2" style="font-size: 3rem;"></i>
                    <p class="mb-0">No leave requests logged yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive border-0">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Dates</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th class="text-end">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $req): ?>
                                <?php
                                $badge_class = 'bg-warning text-dark';
                                if ($req['status'] === 'Approved') $badge_class = 'bg-success';
                                elseif ($req['status'] === 'Rejected') $badge_class = 'bg-danger';
                                ?>
                                <tr>
                                    <td><span class="fw-bold text-dark"><?php echo htmlspecialchars($req['leave_type']); ?></span></td>
                                    <td>
                                        <small class="fw-semibold text-dark d-block"><?php echo date('M j, Y', strtotime($req['start_date'])); ?></small>
                                        <small class="text-muted">to <?php echo date('M j, Y', strtotime($req['end_date'])); ?></small>
                                    </td>
                                    <td><span class="fw-bold text-secondary"><?php echo $req['days_requested']; ?></span></td>
                                    <td><span class="badge <?php echo $badge_class; ?> px-2.5 py-1.5"><?php echo $req['status']; ?></span></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#leaveModal-<?php echo $req['id']; ?>">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>

                                        <!-- Details Modal -->
                                        <div class="modal fade" id="leaveModal-<?php echo $req['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered text-start">
                                                <div class="modal-content border-0 shadow">
                                                    <div class="modal-header bg-light border-0">
                                                        <h5 class="modal-title fw-bold text-dark"><?php echo htmlspecialchars($req['leave_type']); ?> Leave Request</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <div class="mb-3">
                                                            <span class="text-muted small d-block">Requested Dates</span>
                                                            <strong class="text-dark"><?php echo date('F j, Y', strtotime($req['start_date'])); ?> to <?php echo date('F j, Y', strtotime($req['end_date'])); ?> (<?php echo $req['days_requested']; ?> days)</strong>
                                                        </div>
                                                        <div class="mb-3">
                                                            <span class="text-muted small d-block">Reason for Application</span>
                                                            <p class="text-dark mb-0 bg-light p-2.5 rounded small"><?php echo nl2br(htmlspecialchars($req['reason'])); ?></p>
                                                        </div>
                                                        <hr class="my-3">
                                                        <div class="mb-0">
                                                            <span class="text-muted small d-block">Status</span>
                                                            <span class="badge <?php echo $badge_class; ?> mb-2"><?php echo $req['status']; ?></span>
                                                            <?php if (!empty($req['comments'])): ?>
                                                                <span class="text-muted small d-block mt-2">Approver Comments</span>
                                                                <p class="text-dark bg-light p-2.5 rounded small mb-0"><?php echo nl2br(htmlspecialchars($req['comments'])); ?></p>
                                                            <?php endif; ?>
                                                        </div>
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

<?php include_once __DIR__ . '/includes/footer.php'; ?>
