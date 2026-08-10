<?php
$page_title = "Department Management";
require_once __DIR__ . '/includes/auth.php';
requireRoles(['Admin', 'HR']);

require_once __DIR__ . '/classes/Department.php';
$departmentModel = new Department();

$error = '';
$success = '';
$csrf_token = generateCSRFToken();

// Handle Actions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($posted_token)) {
        $error = "CSRF verification failed.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($name) || empty($code)) {
                $error = "Department Name and Code are required.";
            } else {
                if ($departmentModel->create($name, $code, $description)) {
                    $_SESSION['success_msg'] = "Department created successfully!";
                    header("Location: departments.php");
                    exit;
                } else {
                    $error = "Failed to create department. Code or Name might already exist.";
                }
            }
        } elseif ($action === 'update') {
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if ($id <= 0 || empty($name) || empty($code)) {
                $error = "Invalid inputs. Name and Code are required.";
            } else {
                if ($departmentModel->update($id, $name, $code, $description)) {
                    $_SESSION['success_msg'] = "Department updated successfully!";
                    header("Location: departments.php");
                    exit;
                } else {
                    $error = "Failed to update department. Name or Code might be in use.";
                }
            }
        }
    }
}

// Handle GET deletion requests
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id > 0) {
        if ($departmentModel->delete($id)) {
            $_SESSION['success_msg'] = "Department deleted successfully!";
        } else {
            $_SESSION['error_msg'] = "Cannot delete department! Please ensure there are no employees assigned to it first.";
        }
    }
    header("Location: departments.php");
    exit;
}

$departments = $departmentModel->getAll();
include_once __DIR__ . '/includes/header.php';
?>

<div class="row">
    <!-- Left Column: Department List -->
    <div class="col-12 col-lg-8 mb-4">
        <div class="card border-0 shadow-sm bg-white p-3 p-md-4 h-100">
            <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-building-user text-primary me-2"></i> Institutional Departments</h5>

            <?php if (empty($departments)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-folder-open mb-2" style="font-size: 3rem;"></i>
                    <p class="mb-0">No departments added yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive border-0">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Department Name</th>
                                <th>Active Employees</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><span class="badge bg-primary text-white font-weight-bold"><?php echo htmlspecialchars($dept['code']); ?></span></td>
                                    <td>
                                        <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($dept['name']); ?></span>
                                        <small class="text-secondary d-block text-truncate" style="max-width: 250px;"><?php echo htmlspecialchars($dept['description'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border fw-medium"><?php echo $dept['employee_count']; ?> Assigned</span>
                                    </td>
                                    <td class="text-end">
                                        <!-- Edit trigger -->
                                        <button class="btn btn-sm btn-outline-warning text-dark me-1"
                                                onclick="editDepartment(<?php echo $dept['id']; ?>, '<?php echo addslashes($dept['name']); ?>', '<?php echo addslashes($dept['code']); ?>', '<?php echo addslashes($dept['description']); ?>')">
                                            <i class="fa-solid fa-pencil"></i>
                                        </button>

                                        <!-- Delete trigger -->
                                        <a href="departments.php?delete=<?php echo $dept['id']; ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this department? This action cannot be undone.')">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Form (Contextual - Add or Edit) -->
    <div class="col-12 col-lg-4 mb-4">
        <div class="card border-0 shadow-sm bg-white p-3 p-md-4 h-100" id="formContainer">
            <h5 class="fw-bold mb-3 text-dark" id="formTitle"><i class="fa-solid fa-plus-circle text-success me-2"></i> Add New Department</h5>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center small" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="departments.php" id="deptForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="deptId" value="">

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Department Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="code" id="deptCode" required placeholder="e.g. CS-IT" style="text-transform: uppercase;">
                    <small class="text-muted">Unique identifier code</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Department Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" id="deptName" required placeholder="e.g. Computer Science Faculty">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Description</label>
                    <textarea class="form-control" name="description" id="deptDescription" rows="4" placeholder="Brief outline of duties or responsibilities..."></textarea>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-ipmc" id="submitBtn"><i class="fa-solid fa-save me-1"></i> Save Department</button>
                    <button type="button" class="btn btn-light border d-none" id="cancelBtn" onclick="resetForm()"><i class="fa-solid fa-cancel me-1"></i> Cancel Edit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    /**
     * Switch form state to edit a department
     */
    function editDepartment(id, name, code, description) {
        document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-pencil text-warning me-2"></i> Edit Department';
        document.getElementById('formAction').value = 'update';
        document.getElementById('deptId').value = id;
        document.getElementById('deptName').value = name;
        document.getElementById('deptCode').value = code;
        document.getElementById('deptDescription').value = description;
        document.getElementById('submitBtn').className = 'btn btn-warning text-dark fw-bold';
        document.getElementById('submitBtn').innerHTML = '<i class="fa-solid fa-check me-1"></i> Update Department';
        document.getElementById('cancelBtn').classList.remove('d-none');

        // Scroll to form on mobile devices
        if(window.innerWidth < 992) {
            document.getElementById('formContainer').scrollIntoView({ behavior: 'smooth' });
        }
    }

    /**
     * Reset form state back to creating
     */
    function resetForm() {
        document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-plus-circle text-success me-2"></i> Add New Department';
        document.getElementById('formAction').value = 'create';
        document.getElementById('deptId').value = '';
        document.getElementById('deptForm').reset();
        document.getElementById('submitBtn').className = 'btn btn-ipmc';
        document.getElementById('submitBtn').innerHTML = '<i class="fa-solid fa-save me-1"></i> Save Department';
        document.getElementById('cancelBtn').classList.add('d-none');
    }
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
