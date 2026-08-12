<?php
/**
 * Backend API Endpoint for AI Chat Assistant
 * Safely processes user messages.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/AIChatAssistant.php';

// Verify session authenticity
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized session access. Please log in first.']);
    exit;
}

// Read raw POST stream
$input_raw = file_get_contents('php://input');
$data = json_decode($input_raw, true);

if (!$data || empty($data['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid message input is required.']);
    exit;
}

// Retrieve context attributes
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'Employee';
$user_name = $_SESSION['user_name'] ?? 'User';
$message = $data['message'];

// Execute chat logic safely
$assistant = new AIChatAssistant($user_id, $user_role, $user_name);
$reply = $assistant->ask($message);

echo json_encode([
    'reply' => $reply,
    'timestamp' => date('Y-m-d H:i:s')
]);
exit;
?>