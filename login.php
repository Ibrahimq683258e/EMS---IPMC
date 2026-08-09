<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/Employee.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$csrf_token = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $posted_token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($posted_token)) {
        $error = "CSRF verification failed. Please refresh the page and try again.";
    } elseif (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $employeeModel = new Employee();
        $user = $employeeModel->login($email, $password);

        if ($user) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_code'] = $user['employee_id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_photo'] = $user['photo'];

            $_SESSION['success_msg'] = "Welcome back, " . $user['first_name'] . "!";
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid email or password, or account is inactive.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IPMC Tamale Campus EMS</title>
    <!-- Google Fonts (Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --ipmc-primary: #0a3d62;
            --ipmc-accent: #f6b93b;
            --ipmc-dark: #1e272e;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--ipmc-primary) 0%, #051d30 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-card {
            background-color: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            border: none;
        }
        .login-header {
            background-color: rgba(10, 61, 98, 0.05);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 2.5rem 2rem 1.5rem;
            text-align: center;
        }
        .login-logo {
            width: 70px;
            height: 70px;
            background-color: var(--ipmc-primary);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1rem;
            box-shadow: 0 4px 10px rgba(10, 61, 98, 0.3);
            border: 3px solid var(--ipmc-accent);
        }
        .login-btn {
            background-color: var(--ipmc-primary);
            color: #fff;
            font-weight: 600;
            padding: 0.75rem;
            border-radius: 8px;
            border: none;
            transition: all 0.2s ease;
        }
        .login-btn:hover {
            background-color: #051d30;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(10, 61, 98, 0.2);
        }
        .form-control:focus {
            border-color: var(--ipmc-primary);
            box-shadow: 0 0 0 3px rgba(10, 61, 98, 0.15);
        }
    </style>
</head>
<body>

<div class="login-card shadow-lg animate-fade-in">
    <div class="login-header">
        <div class="login-logo">
            <i class="fa-solid fa-graduation-cap"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">IPMC Tamale Campus</h4>
        <p class="text-muted small mb-0">Employee Management System</p>
    </div>

    <div class="card-body p-4 p-md-5">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center small" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <div class="mb-3">
                <label for="email" class="form-label text-secondary fw-semibold small">Work Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-secondary border-end-0"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" class="form-control border-start-0" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" placeholder="name@ipmc.edu.gh" required>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label text-secondary fw-semibold small">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-secondary border-end-0"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="Enter your password" required>
                </div>
            </div>

            <div class="d-grid mb-3">
                <button type="submit" class="btn login-btn">
                    <i class="fa-solid fa-right-to-bracket me-2"></i> Sign In
                </button>
            </div>
        </form>
    </div>

    <div class="text-center pb-4 text-muted small">
        <i class="fa-solid fa-shield-halved text-secondary me-1"></i> Authorized Personnel Only
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
