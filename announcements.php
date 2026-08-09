<?php
$page_title = "Institutional Bulletins & Announcements";
require_once __DIR__ . '/includes/auth.php';
requireLogin();

require_once __DIR__ . '/classes/Announcement.php';
$announcementModel = new Announcement();

$role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];
$error = '';
$csrf_token = generateCSRFToken();

// Handle deletion (Admin/HR only)
if (isset($_GET['delete']) && (isAdmin() || isHR())) {
    $id = intval($_GET['delete']);
    if ($id > 0) {
        if ($announcementModel->delete($id)) {
            $_SESSION['success_msg'] = "Announcement removed successfully!";
        } else {
            $_SESSION['error_msg'] = "Failed to remove the announcement.";
        }
    }
    header("Location: announcements.php");
    exit;
}

// Handle posting a new announcement (Admin/HR only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isAdmin() || isHR())) {
    $posted_token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($posted_token)) {
        $error = "CSRF verification failed.";
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if (empty($title) || empty($content)) {
            $error = "Please fill in all fields.";
        } else {
            if ($announcementModel->create($title, $content, $user_id)) {
                $_SESSION['success_msg'] = "Announcement posted successfully to the bulletin board!";
                header("Location: announcements.php");
                exit;
            } else {
                $error = "Failed to post announcement. Database error.";
            }
        }
    }
}

// Fetch announcements
$announcements = $announcementModel->getLatest(20);
include_once __DIR__ . '/includes/header.php';
?>

<div class="row">
    <!-- Left Column: Add Announcement (Admin/HR only) -->
    <?php if (isAdmin() || isHR()): ?>
        <div class="col-12 col-lg-4 mb-4">
            <div class="card border-0 shadow-sm p-4 bg-white">
                <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-plus-circle text-success me-2"></i> Post New Announcement</h5>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center small" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="announcements.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Bulletin Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" required placeholder="e.g. Easter Holiday Notice">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold small text-secondary">Announcement Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="content" rows="6" required placeholder="Type the detailed message here..."></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-ipmc"><i class="fa-solid fa-bullhorn me-1"></i> Post Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Right Column: Bulletins Feed -->
    <div class="col-12 <?php echo (isAdmin() || isHR()) ? 'col-lg-8' : ''; ?> mb-4">
        <div class="card border-0 shadow-sm p-4 bg-white h-100">
            <h5 class="fw-bold mb-4 text-dark"><i class="fa-solid fa-newspaper text-primary me-2"></i> Active Bulletins & Campus Board</h5>

            <?php if (empty($announcements)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-folder-open mb-2" style="font-size: 3rem;"></i>
                    <p class="mb-0">No active bulletins posted on the campus board.</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($announcements as $ann): ?>
                        <div class="list-group-item py-4 px-0 border-bottom">
                            <div class="d-flex w-100 justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                <h5 class="fw-bold text-primary mb-0"><?php echo htmlspecialchars($ann['title']); ?></h5>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-light text-secondary border fw-medium"><?php echo date('M j, Y - h:i A', strtotime($ann['created_at'])); ?></span>

                                    <?php if (isAdmin() || isHR()): ?>
                                        <a href="announcements.php?delete=<?php echo $ann['id']; ?>"
                                           class="btn btn-sm btn-outline-danger px-2 py-1"
                                           onclick="return confirm('Are you sure you want to delete this announcement?')"
                                           title="Delete Bulletin">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="text-dark bg-light p-3 rounded" style="line-height: 1.6; white-space: pre-line; font-size: 0.95rem;"><?php echo htmlspecialchars($ann['content']); ?></p>

                            <div class="d-flex align-items-center text-secondary small">
                                <i class="fa-solid fa-user-circle me-1.5 text-primary" style="font-size: 1.15rem;"></i>
                                <span>Author: <strong><?php echo htmlspecialchars($ann['first_name'] . ' ' . $ann['last_name']); ?></strong> (<?php echo htmlspecialchars($ann['creator_role']); ?>)</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
