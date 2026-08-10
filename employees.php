<?php
$page_title = "Employee Directory";
require_once __DIR__ . '/includes/auth.php';
requireRoles(['Admin', 'HR']); // Only Admin/HR can manage employee lists

require_once __DIR__ . '/classes/Employee.php';
require_once __DIR__ . '/classes/Department.php';

$employeeModel = new Employee();
$departmentModel = new Department();

// Get filter inputs
$search = trim($_GET['search'] ?? '');
$department_id = trim($_GET['department_id'] ?? '');
$role_filter = trim($_GET['role'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$filters = [
    'search' => $search,
    'department_id' => $department_id,
    'role' => $role_filter,
    'status' => $status_filter
];

// Fetch matching records
$employees = $employeeModel->getAll($filters);
$departments = $departmentModel->getAll();

include_once __DIR__ . '/includes/header.php';
?>

<div class="row">
    <!-- Filter Toolbar -->
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap mb-3 gap-2">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-filter text-primary me-2"></i> Filter & Search Staff</h5>
                <a href="employee-add.php" class="btn btn-ipmc btn-submit-mobile"><i class="fa-solid fa-user-plus me-1"></i> Add New Employee</a>
            </div>

            <form method="GET" action="employees.php" class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, ID or email...">
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-md-2">
                    <select class="form-select" name="department_id">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo ($department_id == $dept['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-md-2">
                    <select class="form-select" name="role">
                        <option value="">All Roles</option>
                        <option value="Admin" <?php echo ($role_filter === 'Admin') ? 'selected' : ''; ?>>Admin</option>
                        <option value="HR" <?php echo ($role_filter === 'HR') ? 'selected' : ''; ?>>HR</option>
                        <option value="Employee" <?php echo ($role_filter === 'Employee') ? 'selected' : ''; ?>>Employee</option>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-md-2">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="Active" <?php echo ($status_filter === 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo ($status_filter === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-md-2 d-grid gap-2 d-sm-flex">
                    <button type="submit" class="btn btn-secondary w-100"><i class="fa-solid fa-sliders me-1"></i> Apply</button>
                    <a href="employees.php" class="btn btn-light border w-100 text-center"><i class="fa-solid fa-rotate-left"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Directory Table -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if (empty($employees)): ?>
                    <div class="text-center py-5">
                        <div class="text-secondary mb-3" style="font-size: 3rem;"><i class="fa-solid fa-users-slash"></i></div>
                        <h5 class="fw-bold text-dark">No employees found</h5>
                        <p class="text-muted">Try adjusting your filters or search criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Full Name</th>
                                    <th>Contact Info</th>
                                    <th>Department & Role</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $emp): ?>
                                    <?php
                                    $status_class = ($emp['status'] === 'Active') ? 'bg-success' : 'bg-danger';
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-primary"><?php echo htmlspecialchars($emp['employee_id']); ?></span>
                                            <small class="d-block text-muted"><?php echo htmlspecialchars($emp['staff_type']); ?> Staff</small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle overflow-hidden border me-3" style="width: 40px; height: 40px; min-width: 40px;">
                                                    <img src="<?php echo !empty($emp['photo']) && file_exists($emp['photo']) ? $emp['photo'] : 'uploads/default.png'; ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                                </div>
                                                <div>
                                                    <a href="employee-view.php?id=<?php echo $emp['id']; ?>" class="fw-bold text-dark text-decoration-none text-primary-hover">
                                                        <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                                    </a>
                                                    <small class="d-block text-secondary"><?php echo htmlspecialchars($emp['designation'] ?? 'No designation'); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="d-block text-dark fw-medium"><i class="fa-solid fa-envelope me-1 text-muted" style="width: 14px;"></i><?php echo htmlspecialchars($emp['email']); ?></small>
                                            <small class="d-block text-secondary"><i class="fa-solid fa-phone me-1 text-muted" style="width: 14px;"></i><?php echo htmlspecialchars($emp['phone'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-secondary d-block"><?php echo htmlspecialchars($emp['department_name'] ?? 'Unassigned'); ?></span>
                                            <span class="badge bg-light text-secondary border fw-medium"><?php echo htmlspecialchars($emp['role']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $status_class; ?> px-3 py-1.5"><?php echo $emp['status']; ?></span>
                                        </td>
                                        <td class="text-end">
                                            <div class="dropdown">
                                                <button class="btn btn-light btn-sm border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Manage
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                                    <li>
                                                        <a class="dropdown-item py-2" href="employee-view.php?id=<?php echo $emp['id']; ?>">
                                                            <i class="fa-solid fa-id-badge text-primary me-2" style="width: 16px;"></i> View Profile
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item py-2" href="employee-edit.php?id=<?php echo $emp['id']; ?>">
                                                            <i class="fa-solid fa-user-pen text-warning me-2" style="width: 16px;"></i> Edit Details
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item py-2" href="appraisal.php?employee_id=<?php echo $emp['id']; ?>">
                                                            <i class="fa-solid fa-star text-info me-2" style="width: 16px;"></i> Appraise Staff
                                                        </a>
                                                    </li>
                                                </ul>
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
