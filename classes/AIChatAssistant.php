<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../includes/config.php';

class AIChatAssistant {
    private $db;
    private $user_id;
    private $user_role;
    private $user_name;

    public function __construct($user_id, $user_role, $user_name) {
        $this->db = Database::connect();
        $this->user_id = (int)$user_id;
        $this->user_role = $user_role; // 'Admin', 'HR', or 'Employee'
        $this->user_name = $user_name;
    }

    /**
     * Get active conversation ID for this user, or create one if none exists.
     */
    public function getActiveConversationId() {
        $stmt = $this->db->prepare("
            SELECT id FROM chat_conversations
            WHERE user_id = :user_id
            ORDER BY updated_at DESC LIMIT 1
        ");
        $stmt->execute(['user_id' => $this->user_id]);
        $conv_id = $stmt->fetchColumn();

        if ($conv_id) {
            return (int)$conv_id;
        }

        // Create a default conversation
        $stmt_insert = $this->db->prepare("
            INSERT INTO chat_conversations (user_id, title)
            VALUES (:user_id, 'New Chat')
        ");
        $stmt_insert->execute(['user_id' => $this->user_id]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Verify ownership of a conversation.
     */
    public function verifyConversationOwnership($conversation_id) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM chat_conversations
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute(['id' => (int)$conversation_id, 'user_id' => $this->user_id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Retrieve chat history strictly belonging to the current authenticated user and given conversation.
     */
    public function getHistory($conversation_id = null) {
        if ($conversation_id === null) {
            $conversation_id = $this->getActiveConversationId();
        }

        if (!$this->verifyConversationOwnership($conversation_id)) {
            error_log("UNAUTHORIZED ACCESS ATTEMPT: User ID {$this->user_id} tried to load Conversation ID {$conversation_id}");
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT role, message, created_at
            FROM chat_messages
            WHERE conversation_id = :conv_id AND user_id = :user_id
            ORDER BY created_at ASC
        ");
        $stmt->execute(['conv_id' => (int)$conversation_id, 'user_id' => $this->user_id]);
        return $stmt->fetchAll();
    }

    /**
     * Save a chat message, validating user_id ownership.
     */
    public function saveMessage($conversation_id, $role, $message) {
        if (!$this->verifyConversationOwnership($conversation_id)) {
            error_log("UNAUTHORIZED WRITE ATTEMPT: User ID {$this->user_id} tried to post to Conversation ID {$conversation_id}");
            return false;
        }

        $stmt = $this->db->prepare("
            INSERT INTO chat_messages (conversation_id, user_id, role, message)
            VALUES (:conv_id, :user_id, :role, :message)
        ");
        return $stmt->execute([
            'conv_id' => (int)$conversation_id,
            'user_id' => $this->user_id,
            'role' => $role, // 'user' or 'assistant'
            'message' => $message
        ]);
    }

    /**
     * Clear messages of a conversation.
     */
    public function clearHistory($conversation_id = null) {
        if ($conversation_id === null) {
            $conversation_id = $this->getActiveConversationId();
        }

        if (!$this->verifyConversationOwnership($conversation_id)) {
            error_log("UNAUTHORIZED CLEAR ATTEMPT: User ID {$this->user_id} tried to clear Conversation ID {$conversation_id}");
            return false;
        }

        $stmt = $this->db->prepare("
            DELETE FROM chat_messages
            WHERE conversation_id = :conv_id AND user_id = :user_id
        ");
        return $stmt->execute(['conv_id' => (int)$conversation_id, 'user_id' => $this->user_id]);
    }

    /**
     * Define authorized tools and their role permissions.
     */
    private function getAvailableTools() {
        return [
            // Employee specific tools
            'get_my_profile' => ['roles' => ['Employee', 'HR', 'Admin'], 'desc' => 'Get the current user\'s employee profile detail summary.'],
            'get_my_leave_balance' => ['roles' => ['Employee', 'HR', 'Admin'], 'desc' => 'Get the current user\'s remaining leave balances.'],
            'get_my_leave_history' => ['roles' => ['Employee', 'HR', 'Admin'], 'desc' => 'Get the current user\'s leave applications and status logs.'],
            'get_my_attendance' => ['roles' => ['Employee', 'HR', 'Admin'], 'desc' => 'Get the current user\'s recent attendance records.'],
            'get_my_appraisal' => ['roles' => ['Employee', 'HR', 'Admin'], 'desc' => 'Get the current user\'s latest performance appraisal rating and feedback comments.'],
            'get_announcements' => ['roles' => ['Employee', 'HR', 'Admin'], 'desc' => 'Get the latest institutional announcements and notices.'],

            // HR / Admin specific tools
            'get_employee_count' => ['roles' => ['HR', 'Admin'], 'desc' => 'Get total employee metrics on active, academic, and non-academic staff.'],
            'get_employees_by_department' => ['roles' => ['HR', 'Admin'], 'desc' => 'Get the list of active employees assigned to a specific department (takes name or code parameter).'],
            'get_today_attendance' => ['roles' => ['HR', 'Admin'], 'desc' => 'Get today\'s list of absent, present, or late employees.'],
            'get_pending_leave_requests' => ['roles' => ['HR', 'Admin'], 'desc' => 'Get counts and details of all pending leave applications awaiting approval.'],
            'get_low_leave_balances' => ['roles' => ['HR', 'Admin'], 'desc' => 'Get the list of active employees whose remaining leave balances are critically below 5 days.'],
            'get_recent_appraisals' => ['roles' => ['HR', 'Admin'], 'desc' => 'Get the list of recently logged performance appraisals.']
        ];
    }

    /**
     * Ask a question, orchestrating storage, analysis, safe tool invocation, and natural responding.
     */
    public function ask($question, $conversation_id = null) {
        if ($conversation_id === null) {
            $conversation_id = $this->getActiveConversationId();
        }

        if (!$this->verifyConversationOwnership($conversation_id)) {
            return "Error: Unauthorized conversation context.";
        }

        // Save User Message
        $this->saveMessage($conversation_id, 'user', $question);

        $provider = defined('AI_PROVIDER') ? AI_PROVIDER : 'fallback';
        $api_key = defined('AI_API_KEY') ? AI_API_KEY : '';

        $assistant_response = "";

        if (($provider === 'nvidia' || $provider === 'gemini' || $provider === 'openai') && !empty($api_key)) {
            $assistant_response = $this->askLLM($question, $provider, $api_key, $conversation_id);
        } else {
            $assistant_response = $this->askFallback($question);
        }

        // Save Assistant Response
        $this->saveMessage($conversation_id, 'assistant', $assistant_response);

        // Update conversation timestamp for updated_at sorting
        $stmt_update = $this->db->prepare("UPDATE chat_conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt_update->execute(['id' => (int)$conversation_id]);

        return $assistant_response;
    }

    /**
     * Handle LLM-based tool/intent selection and structured raw data formatting.
     */
    private function askLLM($question, $provider, $api_key, $conversation_id) {
        $tools = $this->getAvailableTools();
        $tool_list_str = "";
        foreach ($tools as $name => $meta) {
            $tool_list_str .= "- {$name}: {$meta['desc']} (Roles allowed: " . implode(', ', $meta['roles']) . ")\n";
        }

        // Fetch recent messages for context/conversational memory (last 6 messages)
        $stmt = $this->db->prepare("
            SELECT role, message
            FROM chat_messages
            WHERE conversation_id = :conv_id AND user_id = :user_id
            ORDER BY created_at DESC LIMIT 6
        ");
        $stmt->execute(['conv_id' => (int)$conversation_id, 'user_id' => $this->user_id]);
        $history_rows = array_reverse($stmt->fetchAll());

        $history_context = "";
        if (!empty($history_rows)) {
            foreach ($history_rows as $row) {
                $role_label = ($row['role'] === 'user') ? 'User' : 'Assistant';
                $history_context .= "{$role_label}: {$row['message']}\n";
            }
        }

        // System Prompt to extract user intent / map to tools
        $intent_prompt = "
        You are the IPMC Tamale Campus Employee Management System (EMS) AI Assistant.
        Analyze the current user's prompt: \"{$question}\"
        And map it to one of our approved tool functions if a database lookup is required.

        Available tools:
        {$tool_list_str}

        User Session Details:
        User Name: {$this->user_name}
        User Role: {$this->user_role}

        Conversational History Context:
        {$history_context}

        System Rules:
        - If the user query or follow-up maps to one of the available tools, return EXACTLY this JSON format:
        {
           \"tool\": \"tool_name\",
           \"args\": { \"department\": \"value\" }
        }
        (Only include 'args' if required. E.g. get_employees_by_department requires a department name/code argument)

        - If the query does NOT map to any database tools (e.g. standard greetings, smalltalk, general guidance, or malicious prompts), return EXACTLY:
        {
           \"text\": \"Your friendly, professional, and helpful natural-language response.\"
        }
        When returning a text response for greetings or general assistant introductions, you MUST address the user directly by their User Name: {$this->user_name}.

        Do NOT generate raw SQL queries or execute writes. You must only select from the list of approved tools.
        Respond ONLY with valid JSON. No markdown code blocks, no backticks.
        ";

        try {
            $response_raw = "";
            if ($provider === 'nvidia') {
                $response_raw = $this->callNvidiaAPI($intent_prompt, $api_key, true);
            } elseif ($provider === 'gemini') {
                $response_raw = $this->callGeminiAPI($intent_prompt, $api_key, true);
            } else {
                $response_raw = $this->callOpenAIAPI($intent_prompt, $api_key, true);
            }

            // Clean response blocks cleanly with regex
            $response_raw = trim($response_raw);
            if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/i', $response_raw, $matches)) {
                $response_raw = trim($matches[1]);
            }

            $intent = json_decode($response_raw, true);

            if ($intent) {
                if (isset($intent['tool'])) {
                    $tool_name = $intent['tool'];
                    $args = $intent['args'] ?? [];

                    // Invoke backend tool safely
                    $tool_output = $this->executeTool($tool_name, $args);

                    // If the tool execution returned an access-denied/unauthorized error, return it directly to the user
                    if (isset($tool_output['error'])) {
                        return "Access Denied: You are not authorized to view this information.";
                    }

                    // Ask LLM to translate structured database outputs into pleasant conversational formats
                    $formatting_prompt = "
                    You are the IPMC Tamale Campus Employee Management System AI Assistant.
                    The user asked: \"{$question}\"

                    The secure PHP backend executed the tool \"{$tool_name}\" and returned this verified real-time dataset:
                    " . json_encode($tool_output) . "

                    System Rules:
                    1. Use ONLY the verified database data provided above.
                    2. If the data is empty or indicates no records/error, state it politely. Do not invent or hallucinate details.
                    3. Format lists, counts, dates, and appraisal ratings nicely and clearly.
                    4. Keep your answer brief, professional, concise, and helpful.
                    5. Never reveal SQL syntax, raw database tables/columns, or API credentials.
                    ";

                    if ($provider === 'nvidia') {
                        return $this->callNvidiaAPI($formatting_prompt, $api_key, false);
                    } elseif ($provider === 'gemini') {
                        return $this->callGeminiAPI($formatting_prompt, $api_key, false);
                    } else {
                        return $this->callOpenAIAPI($formatting_prompt, $api_key, false);
                    }

                } elseif (isset($intent['text'])) {
                    return $intent['text'];
                }
            }
        } catch (Exception $e) {
            error_log("LLM Intent Router Exception: " . $e->getMessage());
        }

        // Soft fallback if API limits or failures occur
        return $this->askFallback($question);
    }

    /**
     * Executes the requested tool after checking strict backend permissions.
     */
    private function executeTool($tool_name, $args = []) {
        $tools = $this->getAvailableTools();

        // 1. Verify tool exists
        if (!isset($tools[$tool_name])) {
            return ['error' => 'Unsupported function request.'];
        }

        // 2. Strict PHP Role Permission Check (The ultimate security boundary)
        $allowed_roles = $tools[$tool_name]['roles'];
        if (!in_array($this->user_role, $allowed_roles)) {
            error_log("SECURITY VIOLATION: User ID {$this->user_id} with role {$this->user_role} tried to execute restricted tool: {$tool_name}");
            return ['error' => 'Access denied: You do not have permissions to query this data.'];
        }

        // 3. Execute approved backend method
        switch ($tool_name) {
            case 'get_my_profile':
                return $this->get_my_profile();
            case 'get_my_leave_balance':
                return $this->get_my_leave_balance();
            case 'get_my_leave_history':
                return $this->get_my_leave_history();
            case 'get_my_attendance':
                return $this->get_my_attendance();
            case 'get_my_appraisal':
                return $this->get_my_appraisal();
            case 'get_announcements':
                return $this->get_announcements();
            case 'get_employee_count':
                return $this->get_employee_count();
            case 'get_employees_by_department':
                $dept = $args['department'] ?? '';
                return $this->get_employees_by_department($dept);
            case 'get_today_attendance':
                return $this->get_today_attendance();
            case 'get_pending_leave_requests':
                return $this->get_pending_leave_requests();
            case 'get_low_leave_balances':
                return $this->get_low_leave_balances();
            case 'get_recent_appraisals':
                return $this->get_recent_appraisals();
            default:
                return ['error' => 'Function unimplemented.'];
        }
    }

    /**
     * Fallback local semantic router.
     */
    private function askFallback($question) {
        $q = strtolower(trim($question));

        // 1. Basic greetings
        if (preg_match('/\b(hi|hello|hey|greetings|good morning|good afternoon)\b/', $q)) {
            return "Hello, {$this->user_name}! I am your IPMC Tamale Campus EMS Assistant. How can I assist you with your records today?";
        }

        // 2. Announcements
        if (preg_match('/\b(announcement|notice|announcements|news|bulletin)\b/', $q)) {
            $data = $this->get_announcements();
            if (empty($data)) {
                return "There are no announcements posted on the bulletin board currently.";
            }
            $res = "Here are the recent institutional announcements:\n";
            foreach ($data as $r) {
                $date = date('M d, Y', strtotime($r['created_at']));
                $res .= "📢 **{$r['title']}** ({$date})\n   {$r['content']}\n\n";
            }
            return trim($res);
        }

        // 3. Employee Profile
        if (preg_match('/\b(profile|my info|my details|who am i|my description)\b/', $q)) {
            $data = $this->get_my_profile();
            if (isset($data['error'])) return $data['error'];
            return "Here is your profile information:\n" .
                   "• **Name:** {$data['first_name']} {$data['last_name']}\n" .
                   "• **ID Code:** {$data['employee_id']}\n" .
                   "• **Email:** {$data['email']}\n" .
                   "• **Role / Designation:** {$data['role']} - {$data['designation']}\n" .
                   "• **Staff Type:** {$data['staff_type']}\n" .
                   "• **Phone:** {$data['phone']}\n" .
                   "• **Joining Date:** {$data['joining_date']}";
        }

        // 4. Employee Leave Balances
        if (preg_match('/\b(leave balance|leave balances|days left|annual leave|sick leave|casual leave|maternity leave|paternity leave|study leave|leave days|days remaining|how many leave|remaining leave)\b/', $q)) {
            $data = $this->get_my_leave_balance();
            if (isset($data['error'])) return $data['error'];
            if (empty($data)) return "You do not have any leave balances allocated yet.";

            $res = "Your current leave balances are:\n";
            foreach ($data as $r) {
                $rem = $r['allocated'] - $r['used'];
                $res .= "• **{$r['leave_type']} Leave:** {$rem} days remaining (Allocated: {$r['allocated']}, Used: {$r['used']})\n";
            }
            return trim($res);
        }

        // 5. Employee Leave History
        if (preg_match('/\b(my leave history|my leave requests|my applied leaves|applied for leave|leave history)\b/', $q)) {
            $data = $this->get_my_leave_history();
            if (isset($data['error'])) return $data['error'];
            if (empty($data)) return "No leave application history found for you.";

            $res = "Your recent leave applications:\n";
            foreach ($data as $r) {
                $comment_info = !empty($r['comments']) ? " (Comments: \"{$r['comments']}\")" : "";
                $res .= "• **{$r['start_date']}** to **{$r['end_date']}** ({$r['days_requested']} days, Type: {$r['leave_type']}) - **Status: {$r['status']}**{$comment_info}\n";
            }
            return trim($res);
        }

        // 6. Employee Attendance
        if (preg_match('/\b(attendance|present|absent|late|worked|time in|time out)\b/', $q)) {
            $data = $this->get_my_attendance();
            if (isset($data['error'])) return $data['error'];
            if (empty($data)) return "No attendance records found for you in the system.";

            $res = "Here is your latest attendance activity:\n";
            foreach ($data as $r) {
                $time_info = $r['time_in'] ? " (In: {$r['time_in']} | Out: {$r['time_out']})" : "";
                $res .= "• **{$r['date']}**: {$r['status']}{$time_info}\n";
            }
            return trim($res);
        }

        // 7. Employee Appraisal
        if (preg_match('/\b(appraisal|rating|performance|score|appraised)\b/', $q)) {
            $data = $this->get_my_appraisal();
            if (isset($data['error'])) return $data['error'];
            if (empty($data)) return "You have not been appraised yet for any period.";

            return "Your latest appraisal rating is **{$data['rating']}/5** for **{$data['appraisal_period']}**.\n" .
                   "**HR/Admin Comments:** \"{$data['comments']}\" (Date: {$data['appraisal_date']})";
        }

        // 8. HR/Admin tools
        if ($this->user_role === 'Admin' || $this->user_role === 'HR') {
            // Pending Leaves
            if (preg_match('/\b(pending leave|pending requests|leaves pending|leave requests)\b/', $q)) {
                $data = $this->get_pending_leave_requests();
                if (isset($data['error'])) return $data['error'];
                $res = "There are currently **{$data['total_pending']} pending** leave requests awaiting decision.\n";
                if (!empty($data['list'])) {
                    $res .= "Details:\n";
                    foreach ($data['list'] as $r) {
                        $res .= "• **{$r['first_name']} {$r['last_name']}**: {$r['days_requested']} days of {$r['leave_type']} ({$r['start_date']} to {$r['end_date']})\n";
                    }
                }
                return trim($res);
            }

            // List employees in department
            if (preg_match('/\b(department|dept|list employees in|employees in)\b/', $q)) {
                // Try to extract department name/code
                $stmt = $this->db->query("SELECT name, code FROM departments");
                $depts = $stmt->fetchAll();
                $matched_dept_str = "";

                foreach ($depts as $d) {
                    $dname = strtolower($d['name']);
                    $dcode = strtolower($d['code']);
                    if (strpos($q, $dname) !== false || strpos($q, $dcode) !== false || (strpos($q, 'it') !== false && $dcode === 'cs-it')) {
                        $matched_dept_str = $d['name'];
                        break;
                    }
                }

                if (!empty($matched_dept_str)) {
                    $data = $this->get_employees_by_department($matched_dept_str);
                    if (isset($data['error'])) return $data['error'];
                    if (empty($data)) return "No active employees found in the **{$matched_dept_str}** department.";

                    $res = "Active employees in the **{$matched_dept_str}** department:\n";
                    foreach ($data as $index => $r) {
                        $num = $index + 1;
                        $res .= "{$num}. **{$r['first_name']} {$r['last_name']}** - {$r['designation']}\n";
                    }
                    return trim($res);
                }
            }

            // Absent today
            if (preg_match('/\b(absent today|who was absent|who is absent)\b/', $q)) {
                $data = $this->get_today_attendance();
                if (isset($data['error'])) return $data['error'];
                if (empty($data['absent'])) return "Great news! No employees are marked as **Absent** today.";

                $res = "Employees marked as **Absent** today:\n";
                foreach ($data['absent'] as $r) {
                    $res .= "• **{$r['first_name']} {$r['last_name']}** ({$r['employee_id']})\n";
                }
                return trim($res);
            }

            // Leave balance below 5 days
            if (preg_match('/\b(leave balance below|below 5|low leave balance)\b/', $q)) {
                $data = $this->get_low_leave_balances();
                if (isset($data['error'])) return $data['error'];
                if (empty($data)) return "All active employees have 5 or more leave days remaining.";

                $res = "Active staff with less than 5 remaining leave days:\n";
                foreach ($data as $r) {
                    $res .= "• **{$r['first_name']} {$r['last_name']}**: {$r['remaining']} days left ({$r['leave_type']} Leave)\n";
                }
                return trim($res);
            }

            // Total Active Employees
            if (preg_match('/\b(how many active|total employees|active employees|number of active|how many employees)\b/', $q)) {
                $data = $this->get_employee_count();
                if (isset($data['error'])) return $data['error'];

                return "IPMC Tamale Campus currently has **{$data['total']} active employees**.\n" .
                       "- Academic Staff: **{$data['academic']}**\n" .
                       "- Non-Academic Staff: **{$data['non_academic']}**";
            }

            // Recent Appraisals
            if (preg_match('/\b(recent appraisals|recent performance|list appraisals|appraisals list)\b/', $q)) {
                $data = $this->get_recent_appraisals();
                if (isset($data['error'])) return $data['error'];
                if (empty($data)) return "No appraisal records found in the system.";

                $res = "Latest appraisals logged:\n";
                foreach ($data as $r) {
                    $res .= "• **{$r['first_name']} {$r['last_name']}**: Rated **{$r['rating']}/5** for period {$r['appraisal_period']}. Comments: \"{$r['comments']}\"\n";
                }
                return trim($res);
            }
        }

        // Generic reply
        return "I'm here to assist you with the IPMC Tamale Campus EMS database. You can ask me about leaves, attendance, appraisals, or announcements. For security and role restrictions, I can only search and read institutional data.";
    }

    /**
     * API Call to NVIDIA NIM (OpenAI compatible).
     */
    private function callNvidiaAPI($prompt, $api_key, $json_mode = false) {
        $model = defined('AI_MODEL_OVERRIDE') && !empty(AI_MODEL_OVERRIDE) ? AI_MODEL_OVERRIDE : 'nvidia/llama-3.1-nemotron-51b-instruct';
        $base_url = defined('AI_BASE_URL') && !empty(AI_BASE_URL) ? AI_BASE_URL : 'https://integrate.api.nvidia.com/v1';
        $url = rtrim($base_url, '/') . '/chat/completions';

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.1
        ];

        if ($json_mode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception("CURL Error: " . curl_error($ch));
        }
        curl_close($ch);

        $res_data = json_decode($response, true);
        if (isset($res_data['choices'][0]['message']['content'])) {
            return $res_data['choices'][0]['message']['content'];
        }

        // Clean error logging that avoids outputting the raw API key
        throw new Exception("Invalid response from NVIDIA API. Response payload returned error status.");
    }

    /**
     * API Call to Gemini.
     */
    private function callGeminiAPI($prompt, $api_key, $json_mode = false) {
        $model = 'gemini-1.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $api_key;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];

        if ($json_mode) {
            $payload['generationConfig'] = [
                'responseMimeType' => 'application/json'
            ];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }
        curl_close($ch);

        $res_data = json_decode($response, true);
        if (isset($res_data['candidates'][0]['content']['parts'][0]['text'])) {
            return $res_data['candidates'][0]['content']['parts'][0]['text'];
        }

        throw new Exception("Invalid response from Gemini API.");
    }

    /**
     * API Call to OpenAI.
     */
    private function callOpenAIAPI($prompt, $api_key, $json_mode = false) {
        $model = 'gpt-4o-mini';
        $url = "https://api.openai.com/v1/chat/completions";

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.1
        ];

        if ($json_mode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }
        curl_close($ch);

        $res_data = json_decode($response, true);
        if (isset($res_data['choices'][0]['message']['content'])) {
            return $res_data['choices'][0]['message']['content'];
        }

        throw new Exception("Invalid response from OpenAI API.");
    }

    // ==========================================
    // APPROVED DATABASE SECURE PHP TOOLS (PREPARED STATEMENTS)
    // ==========================================

    public function get_my_profile() {
        $stmt = $this->db->prepare("
            SELECT e.first_name, e.last_name, e.employee_id, e.email, e.role, e.staff_type, e.phone, e.designation, e.joining_date
            FROM employees e
            WHERE e.id = :user_id
        ");
        $stmt->execute(['user_id' => $this->user_id]);
        return $stmt->fetch() ?: ['error' => 'Profile not found.'];
    }

    public function get_my_leave_balance() {
        $stmt = $this->db->prepare("
            SELECT leave_type, allocated, used
            FROM leave_balances
            WHERE employee_id = :user_id
        ");
        $stmt->execute(['user_id' => $this->user_id]);
        return $stmt->fetchAll();
    }

    public function get_my_leave_history() {
        $stmt = $this->db->prepare("
            SELECT leave_type, start_date, end_date, days_requested, reason, status, comments
            FROM leaves
            WHERE employee_id = :user_id
            ORDER BY start_date DESC LIMIT 5
        ");
        $stmt->execute(['user_id' => $this->user_id]);
        return $stmt->fetchAll();
    }

    public function get_my_attendance() {
        $stmt = $this->db->prepare("
            SELECT date, status, time_in, time_out
            FROM attendance
            WHERE employee_id = :user_id
            ORDER BY date DESC LIMIT 5
        ");
        $stmt->execute(['user_id' => $this->user_id]);
        return $stmt->fetchAll();
    }

    public function get_my_appraisal() {
        $stmt = $this->db->prepare("
            SELECT rating, comments, appraisal_period, appraisal_date
            FROM appraisals
            WHERE employee_id = :user_id
            ORDER BY appraisal_date DESC LIMIT 1
        ");
        $stmt->execute(['user_id' => $this->user_id]);
        return $stmt->fetch() ?: [];
    }

    public function get_announcements() {
        $stmt = $this->db->prepare("
            SELECT title, content, created_at
            FROM announcements
            ORDER BY created_at DESC LIMIT 3
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // --- HR / ADMIN TOOLS ---

    public function get_employee_count() {
        $total = $this->db->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'")->fetchColumn();
        $academic = $this->db->query("SELECT COUNT(*) FROM employees WHERE status = 'Active' AND staff_type = 'Academic'")->fetchColumn();
        $non_academic = $this->db->query("SELECT COUNT(*) FROM employees WHERE status = 'Active' AND staff_type = 'Non-Academic'")->fetchColumn();
        return [
            'total' => (int)$total,
            'academic' => (int)$academic,
            'non_academic' => (int)$non_academic
        ];
    }

    public function get_employees_by_department($department) {
        $stmt = $this->db->prepare("
            SELECT e.first_name, e.last_name, e.designation
            FROM employees e
            JOIN departments d ON e.department_id = d.id
            WHERE (d.name LIKE :dept1 OR d.code LIKE :dept2) AND e.status = 'Active'
        ");
        $stmt->execute([
            'dept1' => "%" . $department . "%",
            'dept2' => "%" . $department . "%"
        ]);
        return $stmt->fetchAll();
    }

    public function get_today_attendance() {
        // Fetch absent today
        $stmt_absent = $this->db->prepare("
            SELECT e.first_name, e.last_name, e.employee_id
            FROM attendance a
            JOIN employees e ON a.employee_id = e.id
            WHERE a.date = CURRENT_DATE() AND a.status = 'Absent'
        ");
        $stmt_absent->execute();
        $absent = $stmt_absent->fetchAll();

        // Fetch present today
        $stmt_present = $this->db->prepare("
            SELECT e.first_name, e.last_name, e.employee_id
            FROM attendance a
            JOIN employees e ON a.employee_id = e.id
            WHERE a.date = CURRENT_DATE() AND a.status = 'Present'
        ");
        $stmt_present->execute();
        $present = $stmt_present->fetchAll();

        return [
            'date' => date('Y-m-d'),
            'absent' => $absent,
            'present' => $present
        ];
    }

    public function get_pending_leave_requests() {
        $stmt_cnt = $this->db->query("SELECT COUNT(*) FROM leaves WHERE status = 'Pending'");
        $total_pending = (int)$stmt_cnt->fetchColumn();

        $stmt_list = $this->db->query("
            SELECT e.first_name, e.last_name, l.leave_type, l.start_date, l.end_date, l.days_requested
            FROM leaves l
            JOIN employees e ON l.employee_id = e.id
            WHERE l.status = 'Pending'
        ");
        $list = $stmt_list->fetchAll();

        return [
            'total_pending' => $total_pending,
            'list' => $list
        ];
    }

    public function get_low_leave_balances() {
        $stmt = $this->db->query("
            SELECT e.first_name, e.last_name, lb.leave_type, (lb.allocated - lb.used) as remaining
            FROM leave_balances lb
            JOIN employees e ON lb.employee_id = e.id
            WHERE (lb.allocated - lb.used) < 5 AND e.status = 'Active'
            ORDER BY remaining ASC
        ");
        return $stmt->fetchAll();
    }

    public function get_recent_appraisals() {
        $stmt = $this->db->query("
            SELECT e.first_name, e.last_name, a.rating, a.comments, a.appraisal_period
            FROM appraisals a
            JOIN employees e ON a.employee_id = e.id
            ORDER BY a.appraisal_date DESC LIMIT 5
        ");
        return $stmt->fetchAll();
    }
}
?>