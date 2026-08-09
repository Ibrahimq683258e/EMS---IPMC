<?php
$page_title = "Add Employee";
require_once __DIR__ . '/includes/auth.php';
requireRoles(['Admin', 'HR']);

require_once __DIR__ . '/classes/Employee.php';
require_once __DIR__ . '/classes/Department.php';

$employeeModel = new Employee();
$departmentModel = new Department();

$departments = $departmentModel->getAll();
$error = '';
$csrf_token = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($posted_token)) {
        $error = "CSRF verification failed. Please try again.";
    } else {
        // Collect & clean fields
        $data = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? 'Ipmc123!', // fallback default
            'role' => $_POST['role'] ?? 'Employee',
            'staff_type' => $_POST['staff_type'] ?? 'Non-Academic',
            'gender' => $_POST['gender'] ?? '',
            'phone' => trim($_POST['phone'] ?? ''),
            'department_id' => $_POST['department_id'] ?? '',
            'designation' => trim($_POST['designation'] ?? ''),
            'joining_date' => $_POST['joining_date'] ?? date('Y-m-d'),
            'status' => 'Active'
        ];

        // Validations
        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email']) || empty($data['gender'])) {
            $error = "Please fill in all required fields (First Name, Last Name, Email, Gender).";
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Handle profile picture upload
            $photo_path = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['photo']['tmp_name'];
                $fileName = $_FILES['photo']['name'];
                $fileSize = $_FILES['photo']['size'];
                $fileType = $_FILES['photo']['type'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));

                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($fileExtension, $allowedExtensions)) {
                    if ($fileSize < 2097152) { // 2MB limit
                        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                        $uploadFileDir = __DIR__ . '/uploads/';
                        if (!is_dir($uploadFileDir)) {
                            mkdir($uploadFileDir, 0777, true);
                        }
                        $dest_path = $uploadFileDir . $newFileName;

                        if (move_uploaded_file($fileTmpPath, $dest_path)) {
                            $photo_path = 'uploads/' . $newFileName;
                        } else {
                            $error = "There was an error moving the uploaded photo.";
                        }
                    } else {
                        $error = "Passport photo size must be less than 2MB.";
                    }
                } else {
                    $error = "Invalid image file type. Allowed formats: JPG, JPEG, PNG, WEBP.";
                }
            }

            if (empty($error)) {
                $data['photo'] = $photo_path;

                // Save employee
                $new_id = $employeeModel->create($data);
                if ($new_id) {
                    $_SESSION['success_msg'] = "Employee added successfully and leave balances allocated!";
                    header("Location: employees.php");
                    exit;
                } else {
                    $error = "Failed to add employee. Email or Employee ID might already exist.";
                    // Clean uploaded picture if database insertion failed
                    if ($photo_path && file_exists(__DIR__ . '/' . $photo_path)) {
                        unlink(__DIR__ . '/' . $photo_path);
                    }
                }
            }
        }
    }
}
include_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="card border-0 shadow-sm p-4 bg-white">
            <div class="d-flex align-items-center mb-4 border-bottom pb-3">
                <a href="employees.php" class="btn btn-sm btn-outline-secondary me-3"><i class="fa-solid fa-arrow-left"></i> Back</a>
                <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-user-plus text-primary me-2"></i> Register New Staff Member</h4>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="employee-add.php" enctype="multipart/form-data" class="row g-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <!-- Personal Information Section -->
                <div class="col-12">
                    <h5 class="fw-bold text-secondary mb-3 border-start border-4 border-primary ps-2">Personal Information</h5>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="first_name" required placeholder="Enter first name">
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="last_name" required placeholder="Enter last name">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small">Gender <span class="text-danger">*</span></label>
                    <select class="form-select" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small">Phone Number</label>
                    <input type="text" class="form-control" name="phone" placeholder="e.g. +233241234567">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small">Passport Photograph</label>
                    <input type="file" class="form-control" name="photo" accept="image/*">
                    <small class="text-muted d-block mt-1">Formats: JPG, PNG, WEBP. Max size: 2MB.</small>
                </div>

                <!-- Professional details Section -->
                <div class="col-12 mt-5">
                    <h5 class="fw-bold text-secondary mb-3 border-start border-4 border-primary ps-2">Employment & Account Information</h5>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small">Work Email Address <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="email" required placeholder="e.g. name@ipmc.edu.gh">
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small">Initial Password</label>
                    <input type="password" class="form-control" name="password" placeholder="Defaults to 'Ipmc123!' if left blank">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small">Department</label>
                    <select class="form-select" name="department_id">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small">Designation / Job Title</label>
                    <input type="text" class="form-control" name="designation" placeholder="e.g. IT Lecturer, Admin Assistant">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small">Joining Date</label>
                    <input type="date" class="form-control" name="joining_date" value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small">Role / Authorization <span class="text-danger">*</span></label>
                    <select class="form-select" name="role" required>
                        <option value="Employee" selected>Employee (Standard Staff)</option>
                        <option value="HR">HR Officer</option>
                        <option value="Admin">System Administrator</option>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small">Staff Category <span class="text-danger">*</span></label>
                    <select class="form-select" name="staff_type" required>
                        <option value="Academic">Academic (Lecturers/Lab Instructors)</option>
                        <option value="Non-Academic" selected>Non-Academic Staff</option>
                    </select>
                </div>

                <div class="col-12 mt-4 text-end">
                    <button type="submit" class="btn btn-ipmc px-4 py-2"><i class="fa-solid fa-save me-1"></i> Register Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
