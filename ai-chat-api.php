<?php
/**
 * Secure Backend API Endpoint for AI Chat Assistant
 * Processes loading history, sending messages, and clearing chat logs.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/AIChatAssistant.php';

// 1. Verify Session Authenticity
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized session. Please log in first.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'Employee';
$user_name = $_SESSION['user_name'] ?? 'User';

$assistant = new AIChatAssistant($user_id, $user_role, $user_name);

$action = $_GET['action'] ?? 'send';

switch ($action) {
    case 'load':
        // Retrieve and load chat history securely for the active conversation
        $history = $assistant->getHistory();
        echo json_encode([
            'history' => $history,
            'user_name' => $user_name
        ]);
        exit;

    case 'clear':
        // Clear history for the active conversation safely
        $success = $assistant->clearHistory();
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Chat history cleared successfully.']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Encountered an error clearing chat logs.']);
        }
        exit;

    case 'send':
    default:
        // Read raw POST stream
        $input_raw = file_get_contents('php://input');
        $data = json_decode($input_raw, true);

        if (!$data || empty($data['message'])) {
            http_response_code(400);
            echo json_encode(['error' => 'A valid message input is required.']);
            exit;
        }

        $message = trim($data['message']);

        // Generate response securely
        $reply = $assistant->ask($message);

        echo json_encode([
            'reply' => $reply,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
}
?>