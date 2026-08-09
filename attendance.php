<?php
$page_title = "Log Daily Attendance";
require_once __DIR__ . '/includes/auth.php';
requireRoles(['Admin', 'HR']);

require_once __DIR__ . '/classes/Attendance.php';
$attendanceModel = new Attendance();

$error = '';
$csrf_token = generateCSRFToken();

// Selected Date (defaults to today)
$selected_date = $_GET['date'] ?? date('Y-m-d');

// Process bulk attendance marking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($posted_token)) {
        $error = "CSRF verification failed.";
    } else {
        $attendance_data = $_POST['attendance'] ?? [];
        $notes_data = $_POST['notes'] ?? [];
        $time_in_data = $_POST['time_in'] ?? [];
        $time_out_data = $_POST['time_out'] ?? [];

        $success_count = 0;
        foreach ($attendance_data as $employee_id => $status) {
            $notes = $notes_data[$employee_id] ?? '';
            $time_in = $time_in_data[$employee_id] ?? null;
            $time_out = $time_out_data[$employee_id] ?? null;

            // Set defaults if Present/Late but times are blank
            if (empty($time_in) && ($status === 'Present' || $status === 'Late')) {
                $time_in = ($status === 'Present') ? '08:00:00' : '08:45:00';
            }
            if (empty($time_out) && ($status === 'Present' || $status === 'Late')) {
                $time_out = '17:00:00';
            }

            // For Absent or Permission, times are ignored/cleared
            if ($status === 'Absent' || $status === 'Permission') {
                $time_in = null;
                $time_out = null;
            }

            if ($attendanceModel->mark($employee_id, $selected_date, $status, $time_in, $time_out, $notes)) {
                $success_count++;
            }
        }

        $_SESSION['success_msg'] = "Attendance registered/updated successfully for {$success_count} staff members!";
        header("Location: attendance.php?date=" . urlencode($selected_date));
        exit;
    }
}

// Fetch active employees with their attendance status for the selected date
$records = $attendanceModel->getByDate($selected_date);
include_once __DIR__ . '/includes/header.php';
?>

<div class="row">
    <!-- Date selector toolbar -->
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm p-4 bg-white">
            <form method="GET" action="attendance.php" class="row g-3 align-items-center">
                <div class="col-12 col-md-4">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-clipboard-user text-primary me-2"></i> Attendance Log</h5>
                </div>
                <div class="col-12 col-sm-8 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light fw-medium">Log Date:</span>
                        <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($selected_date); ?>" max="<?php echo date('Y-m-d'); ?>" onchange="this.form.submit()">
                    </div>
                </div>
                <div class="col-12 col-sm-4 col-md-3 d-grid">
                    <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-circle-arrow-right me-1"></i> Go to Date</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Error container -->
    <?php if (!empty($error)): ?>
        <div class="col-12 mb-3">
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Attendance Grid -->
    <div class="col-12">
        <form method="POST" action="attendance.php?date=<?php echo urlencode($selected_date); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <div class="card border-0 shadow-sm bg-white mb-4">
                <div class="card-body p-0">
                    <?php if (empty($records)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-user-slash mb-2" style="font-size: 3rem;"></i>
                            <p class="mb-0">No active employees are currently registered in the database.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle text-dark">
                                <thead>
                                    <tr>
                                        <th>Staff Code</th>
                                        <th>Full Name</th>
                                        <th>Department</th>
                                        <th>Status Code</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Notes / Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($records as $rec): ?>
                                        <?php
                                        $emp_id = $rec['employee_id'];
                                        $current_status = $rec['status'] ?? 'Present'; // default present
                                        $time_in = $rec['time_in'] ? date('H:i', strtotime($rec['time_in'])) : '';
                                        $time_out = $rec['time_out'] ? date('H:i', strtotime($rec['time_out'])) : '';
                                        ?>
                                        <tr>
                                            <td><span class="fw-bold text-primary"><?php echo htmlspecialchars($rec['employee_code']); ?></span></td>
                                            <td><span class="fw-semibold"><?php echo htmlspecialchars($rec['first_name'] . ' ' . $rec['last_name']); ?></span></td>
                                            <td><small class="text-secondary"><?php echo htmlspecialchars($rec['department_name'] ?? 'Unassigned'); ?></small></td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <!-- Present -->
                                                    <input type="radio" class="btn-check" name="attendance[<?php echo $emp_id; ?>]" id="pres-<?php echo $emp_id; ?>" value="Present" <?php echo ($current_status === 'Present') ? 'checked' : ''; ?>>
                                                    <label class="btn btn-sm btn-outline-success px-2 py-1 text-uppercase fw-semibold" style="font-size: 0.75rem;" for="pres-<?php echo $emp_id; ?>">P</label>

                                                    <!-- Late -->
                                                    <input type="radio" class="btn-check" name="attendance[<?php echo $emp_id; ?>]" id="late-<?php echo $emp_id; ?>" value="Late" <?php echo ($current_status === 'Late') ? 'checked' : ''; ?>>
                                                    <label class="btn btn-sm btn-outline-warning text-dark px-2 py-1 text-uppercase fw-semibold" style="font-size: 0.75rem;" for="late-<?php echo $emp_id; ?>">L</label>

                                                    <!-- Absent -->
                                                    <input type="radio" class="btn-check" name="attendance[<?php echo $emp_id; ?>]" id="abs-<?php echo $emp_id; ?>" value="Absent" <?php echo ($current_status === 'Absent') ? 'checked' : ''; ?>>
                                                    <label class="btn btn-sm btn-outline-danger px-2 py-1 text-uppercase fw-semibold" style="font-size: 0.75rem;" for="abs-<?php echo $emp_id; ?>">A</label>

                                                    <!-- Permission -->
                                                    <input type="radio" class="btn-check" name="attendance[<?php echo $emp_id; ?>]" id="perm-<?php echo $emp_id; ?>" value="Permission" <?php echo ($current_status === 'Permission') ? 'checked' : ''; ?>>
                                                    <label class="btn btn-sm btn-outline-info px-2 py-1 text-uppercase fw-semibold" style="font-size: 0.75rem;" for="perm-<?php echo $emp_id; ?>">Perm</label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="time" class="form-control form-control-sm" name="time_in[<?php echo $emp_id; ?>]" value="<?php echo $time_in; ?>" style="max-width: 110px;">
                                            </td>
                                            <td>
                                                <input type="time" class="form-control form-control-sm" name="time_out[<?php echo $emp_id; ?>]" value="<?php echo $time_out; ?>" style="max-width: 110px;">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" name="notes[<?php echo $emp_id; ?>]" value="<?php echo htmlspecialchars($rec['notes'] ?? ''); ?>" placeholder="Add remarks...">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($records)): ?>
                    <div class="card-footer bg-light p-3 text-end border-0">
                        <button type="submit" class="btn btn-ipmc px-4 py-2"><i class="fa-solid fa-save me-1"></i> Register Attendance</button>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
