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
     * Process a natural language question and return a professional response.
     */
    public function ask($question) {
        $question = trim($question);
        if (empty($question)) {
            return "Please ask a question, and I'll be happy to help!";
        }

        // Determine which provider to use
        $provider = defined('AI_PROVIDER') ? AI_PROVIDER : 'fallback';
        $api_key = defined('AI_API_KEY') ? AI_API_KEY : '';

        if (($provider === 'gemini' || $provider === 'openai') && !empty($api_key)) {
            return $this->askLLM($question, $provider, $api_key);
        }

        // Fallback to our robust local keyword-based hybrid engine
        return $this->askFallback($question);
    }

    /**
     * Ask an LLM (Gemini or OpenAI) to analyze the request and safe-generate read-only responses or SQL.
     */
    private function askLLM($question, $provider, $api_key) {
        $schema_desc = "
        Tables in the IPMC Tamale EMS database:
        1. departments (id, name, code, description)
        2. employees (id, employee_id, first_name, last_name, email, role, staff_type, gender, phone, department_id, designation, joining_date, status)
        3. leave_balances (id, employee_id, leave_type ['Annual', 'Sick', 'Casual', 'Maternity', 'Paternity', 'Study'], allocated, used)
        4. leaves (id, employee_id, leave_type, start_date, end_date, days_requested, reason, status ['Pending', 'Approved', 'Rejected'], action_by, action_date, comments)
        5. attendance (id, employee_id, date, status ['Present', 'Absent', 'Late', 'Permission'], time_in, time_out, notes)
        6. appraisals (id, employee_id, appraiser_id, rating [1-5], comments, appraisal_period, appraisal_date)
        7. announcements (id, title, content, created_by, created_at)
        8. salaries (id, employee_id, basic_salary)
        9. bonuses (id, employee_id, bonus_type, amount, date_given, reason)
        10. salary_payments (id, employee_id, year, month, basic_salary, bonus_amount, total_earnings, status ['Unpaid', 'Paid'], paid_date)
        ";

        $role_rules = "
        Logged-in user details:
        Name: {$this->user_name}
        Role: {$this->user_role}
        ID (int): {$this->user_id}

        Security Rule:
        - If role is 'Employee', the user MUST ONLY be able to see their own data. Any query generated must strictly check that the employee_id/id matches {$this->user_id}. No access to any other employees' rows is allowed.
        - If role is 'HR' or 'Admin', the user can see broad system-wide information.
        ";

        $prompt = "
        You are a highly secure database assistant for the IPMC Tamale Campus Employee Management System (EMS).
        Given the following database schema and user role details, analyze the user's question: \"{$question}\".

        {$schema_desc}
        {$role_rules}

        Output guidelines:
        1. Decide whether the question can be answered by generating a single safe, read-only SELECT SQL statement.
        2. If YES, return the output in exactly this JSON format:
        {
           \"type\": \"sql\",
           \"sql\": \"SELECT ... \",
           \"params\": { \"param1\": \"val1\", ... }
        }
        Ensure you only use standard SELECT queries. Ensure you bind variables properly.
        - For Employee role: ALWAYS enforce `employee_id = :my_id` or `e.id = :my_id` inside the WHERE clause and map `:my_id` to {$this->user_id}.

        3. If NO (e.g. general greeting or irrelevant/malicious prompt), return:
        {
           \"type\": \"text\",
           \"text\": \"Your friendly, professional text response here.\"
        }

        Return ONLY valid raw JSON. No markdown code blocks, no backticks.
        ";

        try {
            $response_raw = "";
            if ($provider === 'gemini') {
                $response_raw = $this->callGeminiAPI($prompt, $api_key);
            } else {
                $response_raw = $this->callOpenAIAPI($prompt, $api_key);
            }

            // Clean response
            $response_raw = trim($response_raw);
            if (strpos($response_raw, '```json') !== false) {
                $response_raw = str_replace(['```json', '```'], '', $response_raw);
                $response_raw = trim($response_raw);
            }

            $data = json_decode($response_raw, true);
            if ($data && isset($data['type'])) {
                if ($data['type'] === 'sql' && !empty($data['sql'])) {
                    return $this->executeSafeSQL($data['sql'], $data['params'] ?? []);
                } elseif ($data['type'] === 'text' && !empty($data['text'])) {
                    return $data['text'];
                }
            }
        } catch (Exception $e) {
            // Log LLM call error and fallback gracefully
            error_log("LLM API Call Error: " . $e->getMessage());
        }

        return $this->askFallback($question);
    }

    /**
     * Call Google Gemini REST API.
     */
    private function callGeminiAPI($prompt, $api_key) {
        $model = defined('AI_MODEL_OVERRIDE') && !empty(AI_MODEL_OVERRIDE) ? AI_MODEL_OVERRIDE : 'gemini-1.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $api_key;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json'
            ]
        ];

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

        throw new Exception("Invalid response from Gemini API: " . $response);
    }

    /**
     * Call OpenAI Chat Completion API.
     */
    private function callOpenAIAPI($prompt, $api_key) {
        $model = defined('AI_MODEL_OVERRIDE') && !empty(AI_MODEL_OVERRIDE) ? AI_MODEL_OVERRIDE : 'gpt-4o-mini';
        $url = "https://api.openai.com/v1/chat/completions";

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.1
        ];

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

        throw new Exception("Invalid response from OpenAI API: " . $response);
    }

    /**
     * Sanitize, inspect and execute safe generated read-only SQL queries.
     */
    private function executeSafeSQL($sql, $params = []) {
        $clean_sql = trim($sql);

        // Security check 1: SQL must start with SELECT
        if (!preg_match('/^select\b/i', $clean_sql)) {
            return "Security violation: Only read-only queries are authorized.";
        }

        // Security check 2: Deny write operations
        $dangerous_patterns = ['insert', 'update', 'delete', 'drop', 'alter', 'truncate', 'replace', 'grant', 'revoke'];
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match('/\b' . $pattern . '\b/i', $clean_sql)) {
                return "Security violation: Unsupported query format detected.";
            }
        }

        // Security check 3: Row level security for employees
        if ($this->user_role === 'Employee') {
            // Employee must strictly see their own records.
            // Check if the query references the user_id variable, or strictly filter the query to lock down output.
            $has_user_binding = false;
            foreach ($params as $key => $val) {
                if ((int)$val === $this->user_id) {
                    $has_user_binding = true;
                }
            }

            if (!$has_user_binding) {
                return "Access denied: Employees can only view their own records.";
            }
        }

        try {
            $stmt = $this->db->prepare($clean_sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll();

            if (empty($results)) {
                return "No matching records found for your request.";
            }

            return $this->formatQueryResults($results);
        } catch (Exception $e) {
            error_log("Secure LLM SQL Execution Failed: " . $e->getMessage());
            return "I understood your intent, but encountered an issue retrieving the data. Could you please rephrase the question?";
        }
    }

    /**
     * Local hybrid rule-based semantic parser for standard questions.
     */
    private function askFallback($question) {
        $q = strtolower(trim($question));

        // 1. Basic greetings
        if (preg_match('/\b(hi|hello|hey|greetings|good morning|good afternoon)\b/', $q)) {
            return "Hello, {$this->user_name}! I am your IPMC Tamale EMS Assistant. How can I assist you with your records today?";
        }

        // 2. Announcements
        if (preg_match('/\b(announcement|notice|announcements|news|bulletin)\b/', $q)) {
            $stmt = $this->db->query("SELECT title, content, created_at FROM announcements ORDER BY created_at DESC LIMIT 3");
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                return "There are no announcements posted on the bulletin board currently.";
            }
            $res = "Here are the recent institutional announcements:\n";
            foreach ($rows as $r) {
                $date = date('M d, Y', strtotime($r['created_at']));
                $res .= "📢 **{$r['title']}** ({$date})\n   {$r['content']}\n\n";
            }
            return trim($res);
        }

        // --- EMPLOYEE-SPECIFIC QUESTIONS ---
        if ($this->user_role === 'Employee') {
            // Leave Balances
            if (preg_match('/\b(leave balance|leave balances|days left|annual leave|sick leave|casual leave|maternity leave|paternity leave|study leave|leave days|days remaining|how many leave|remaining leave)\b/', $q)) {
                $stmt = $this->db->prepare("SELECT leave_type, allocated, used FROM leave_balances WHERE employee_id = :emp_id");
                $stmt->execute(['emp_id' => $this->user_id]);
                $rows = $stmt->fetchAll();

                if (empty($rows)) {
                    return "You do not have any leave balances allocated yet.";
                }

                $res = "Your current leave balances are:\n";
                foreach ($rows as $r) {
                    $rem = $r['allocated'] - $r['used'];
                    $res .= "• **{$r['leave_type']} Leave:** {$rem} days remaining (Allocated: {$r['allocated']}, Used: {$r['used']})\n";
                }
                return trim($res);
            }

            // Attendance
            if (preg_match('/\b(attendance|present|absent|late|worked|time in|time out)\b/', $q)) {
                $stmt = $this->db->prepare("
                    SELECT date, status, time_in, time_out
                    FROM attendance
                    WHERE employee_id = :emp_id
                    ORDER BY date DESC LIMIT 5
                ");
                $stmt->execute(['emp_id' => $this->user_id]);
                $rows = $stmt->fetchAll();

                if (empty($rows)) {
                    return "No attendance records found for you in the system.";
                }

                $res = "Here is your latest attendance activity:\n";
                foreach ($rows as $r) {
                    $time_info = $r['time_in'] ? " (In: {$r['time_in']} | Out: {$r['time_out']})" : "";
                    $res .= "• **{$r['date']}**: {$r['status']}{$time_info}\n";
                }
                return trim($res);
            }

            // Appraisal
            if (preg_match('/\b(appraisal|rating|performance|score|appraised)\b/', $q)) {
                $stmt = $this->db->prepare("
                    SELECT rating, comments, appraisal_period, appraisal_date
                    FROM appraisals
                    WHERE employee_id = :emp_id
                    ORDER BY appraisal_date DESC LIMIT 1
                ");
                $stmt->execute(['emp_id' => $this->user_id]);
                $r = $stmt->fetch();

                if (!$r) {
                    return "You have not been appraised yet for any period.";
                }

                return "Your latest appraisal rating is **{$r['rating']}/5** for **{$r['appraisal_period']}**.\n" .
                       "**HR/Admin Comments:** \"{$r['comments']}\" (Date: {$r['appraisal_date']})";
            }
        }

        // --- HR / ADMIN SPECIFIC QUESTIONS ---
        if ($this->user_role === 'Admin' || $this->user_role === 'HR') {
            // Pending Leaves
            if (preg_match('/\b(pending leave|pending requests|leaves pending|leave requests)\b/', $q)) {
                $stmt = $this->db->query("
                    SELECT COUNT(*) as cnt
                    FROM leaves
                    WHERE status = 'Pending'
                ");
                $cnt = $stmt->fetchColumn();
                return "There are currently **{$cnt} pending** leave requests awaiting decision in the system.";
            }

            // List employees in department
            if (preg_match('/\b(department|dept|list employees in|employees in)\b/', $q)) {
                // Match department name or code from keywords
                $stmt = $this->db->query("SELECT id, name, code FROM departments");
                $depts = $stmt->fetchAll();
                $matched_dept = null;

                foreach ($depts as $d) {
                    $dname = strtolower($d['name']);
                    $dcode = strtolower($d['code']);
                    if (strpos($q, $dname) !== false || strpos($q, $dcode) !== false || (strpos($q, 'it') !== false && $dcode === 'cs-it')) {
                        $matched_dept = $d;
                        break;
                    }
                }

                if ($matched_dept) {
                    $stmt = $this->db->prepare("
                        SELECT first_name, last_name, designation
                        FROM employees
                        WHERE department_id = :dept_id AND status = 'Active'
                    ");
                    $stmt->execute(['dept_id' => $matched_dept['id']]);
                    $rows = $stmt->fetchAll();

                    if (empty($rows)) {
                        return "There are no active employees currently assigned to the **{$matched_dept['name']}** department.";
                    }

                    $res = "Active employees in the **{$matched_dept['name']}** department:\n";
                    foreach ($rows as $index => $r) {
                        $num = $index + 1;
                        $res .= "{$num}. **{$r['first_name']} {$r['last_name']}** - {$r['designation']}\n";
                    }
                    return trim($res);
                }
            }

            // Absent today
            if (preg_match('/\b(absent today|who was absent|who is absent)\b/', $q)) {
                $stmt = $this->db->prepare("
                    SELECT e.first_name, e.last_name, e.employee_id
                    FROM attendance a
                    JOIN employees e ON a.employee_id = e.id
                    WHERE a.date = CURRENT_DATE() AND a.status = 'Absent'
                ");
                $stmt->execute();
                $rows = $stmt->fetchAll();

                if (empty($rows)) {
                    return "Great news! No employees are marked as **Absent** today.";
                }

                $res = "Employees marked as **Absent** today:\n";
                foreach ($rows as $r) {
                    $res .= "• **{$r['first_name']} {$r['last_name']}** ({$r['employee_id']})\n";
                }
                return trim($res);
            }

            // Leave balance below 5 days
            if (preg_match('/\b(leave balance below|below 5|low leave balance)\b/', $q)) {
                $stmt = $this->db->query("
                    SELECT e.first_name, e.last_name, lb.leave_type, (lb.allocated - lb.used) as remaining
                    FROM leave_balances lb
                    JOIN employees e ON lb.employee_id = e.id
                    WHERE (lb.allocated - lb.used) < 5 AND e.status = 'Active'
                    ORDER BY remaining ASC
                ");
                $rows = $stmt->fetchAll();

                if (empty($rows)) {
                    return "All active employees have 5 or more leave days remaining.";
                }

                $res = "Active staff with less than 5 remaining leave days:\n";
                foreach ($rows as $r) {
                    $res .= "• **{$r['first_name']} {$r['last_name']}**: {$r['remaining']} days left ({$r['leave_type']} Leave)\n";
                }
                return trim($res);
            }

            // Total Active Employees
            if (preg_match('/\b(how many active|total employees|active employees|number of active|how many employees)\b/', $q)) {
                $total = $this->db->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'")->fetchColumn();
                $academic = $this->db->query("SELECT COUNT(*) FROM employees WHERE status = 'Active' AND staff_type = 'Academic'")->fetchColumn();
                $non_academic = $this->db->query("SELECT COUNT(*) FROM employees WHERE status = 'Active' AND staff_type = 'Non-Academic'")->fetchColumn();

                return "IPMC Tamale Campus currently has **{$total} active employees**.\n" .
                       "- Academic Staff: **{$academic}**\n" .
                       "- Non-Academic Staff: **{$non_academic}**";
            }

            // Recent Appraisals
            if (preg_match('/\b(recent appraisals|recent performance|list appraisals|appraisals list)\b/', $q)) {
                $stmt = $this->db->query("
                    SELECT e.first_name, e.last_name, a.rating, a.comments, a.appraisal_period
                    FROM appraisals a
                    JOIN employees e ON a.employee_id = e.id
                    ORDER BY a.appraisal_date DESC LIMIT 5
                ");
                $rows = $stmt->fetchAll();

                if (empty($rows)) {
                    return "No appraisal records found in the system.";
                }

                $res = "Latest appraisals logged:\n";
                foreach ($rows as $r) {
                    $res .= "• **{$r['first_name']} {$r['last_name']}**: Rated **{$r['rating']}/5** for period {$r['appraisal_period']}. Comments: \"{$r['comments']}\"\n";
                }
                return trim($res);
            }
        }

        // Generic friendly reply
        return "I'm here to assist you with the IPMC Tamale Campus EMS database. You can ask me about leaves, attendance, appraisals, or announcements. For security and role restrictions, I can only search and read institutional data.";
    }

    /**
     * Format a generic multi-dimensional query result array into clear, readable text.
     */
    private function formatQueryResults($results) {
        $res = "Here is the information I retrieved based on your request:\n\n";
        foreach ($results as $index => $row) {
            $num = $index + 1;
            $res .= "Entry #{$num}:\n";
            foreach ($row as $col => $val) {
                // Do not display columns with ID values or system hashes for security
                if ($col === 'id' || strpos($col, 'password') !== false || strpos($col, 'hash') !== false) {
                    continue;
                }
                $formatted_col = ucwords(str_replace('_', ' ', $col));
                $res .= "• **{$formatted_col}**: {$val}\n";
            }
            $res .= "\n";
        }
        return trim($res);
    }
}
?>