<?php
require_once __DIR__ . '/Database.php';

class Employee {
    private $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    /**
     * Authenticate an employee
     * @param string $email
     * @param string $password
     * @return array|bool User details array on success, false on failure
     */
    public function login($email, $password) {
        $stmt = $this->db->prepare("
            SELECT e.*, d.name as department_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.email = :email AND e.status = 'Active'
            LIMIT 1
        ");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Remove sensitive password hash from the returned array
            unset($user['password_hash']);
            return $user;
        }
        return false;
    }

    /**
     * Find employee by ID
     * @param int $id
     * @return array|bool
     */
    public function findById($id) {
        $stmt = $this->db->prepare("
            SELECT e.*, d.name as department_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Find employee by Employee Code (e.g. IPMC/TAM/2025/0001)
     * @param string $employee_id
     * @return array|bool
     */
    public function findByEmployeeCode($employee_id) {
        $stmt = $this->db->prepare("
            SELECT e.*, d.name as department_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.employee_id = :employee_id
        ");
        $stmt->execute(['employee_id' => $employee_id]);
        return $stmt->fetch();
    }

    /**
     * Get all employees with optional filters
     * @param array $filters
     * @return array
     */
    public function getAll($filters = []) {
        $query = "
            SELECT e.*, d.name as department_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['department_id'])) {
            $query .= " AND e.department_id = :department_id";
            $params['department_id'] = $filters['department_id'];
        }

        if (!empty($filters['role'])) {
            $query .= " AND e.role = :role";
            $params['role'] = $filters['role'];
        }

        if (!empty($filters['status'])) {
            $query .= " AND e.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $query .= " AND (e.first_name LIKE :search1 OR e.last_name LIKE :search2 OR e.employee_id LIKE :search3 OR e.email LIKE :search4)";
            $search_val = "%" . $filters['search'] . "%";
            $params['search1'] = $search_val;
            $params['search2'] = $search_val;
            $params['search3'] = $search_val;
            $params['search4'] = $search_val;
        }

        $query .= " ORDER BY e.employee_id ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Generate the next unique Employee ID
     * Format: IPMC/TAM/2025/XXXX
     */
    public function generateEmployeeId() {
        $year = date('Y');
        $stmt = $this->db->query("SELECT employee_id FROM employees ORDER BY id DESC LIMIT 1");
        $last_id = $stmt->fetchColumn();

        if ($last_id) {
            // Extract the last sequential number (last 4 characters)
            $parts = explode('/', $last_id);
            $last_num = (int)end($parts);
            $new_num = $last_num + 1;
        } else {
            $new_num = 1;
        }

        return "IPMC/TAM/{$year}/" . str_pad($new_num, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Add a new employee
     * @param array $data
     * @return int|bool Newly created ID or false
     */
    public function create($data) {
        try {
            $this->db->beginTransaction();

            $employee_id = $this->generateEmployeeId();
            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);

            $stmt = $this->db->prepare("
                INSERT INTO employees (
                    employee_id, first_name, last_name, email, password_hash,
                    role, staff_type, gender, phone, department_id,
                    designation, joining_date, photo, status
                ) VALUES (
                    :employee_id, :first_name, :last_name, :email, :password_hash,
                    :role, :staff_type, :gender, :phone, :department_id,
                    :designation, :joining_date, :photo, :status
                )
            ");

            $stmt->execute([
                'employee_id' => $employee_id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password_hash' => $password_hash,
                'role' => $data['role'] ?? 'Employee',
                'staff_type' => $data['staff_type'] ?? 'Non-Academic',
                'gender' => $data['gender'],
                'phone' => $data['phone'] ?? null,
                'department_id' => !empty($data['department_id']) ? $data['department_id'] : null,
                'designation' => $data['designation'] ?? null,
                'joining_date' => $data['joining_date'] ?? date('Y-m-d'),
                'photo' => $data['photo'] ?? null,
                'status' => $data['status'] ?? 'Active'
            ]);

            $new_employee_id = $this->db->lastInsertId();

            // Allocate standard leave balances (Annual: 20, Sick: 10, Casual: 5)
            $stmt_leaves = $this->db->prepare("
                INSERT INTO leave_balances (employee_id, leave_type, allocated, used)
                VALUES (:employee_id, :leave_type, :allocated, 0)
            ");

            $standard_leaves = [
                'Annual' => 20,
                'Sick' => 10,
                'Casual' => 5
            ];

            foreach ($standard_leaves as $type => $days) {
                $stmt_leaves->execute([
                    'employee_id' => $new_employee_id,
                    'leave_type' => $type,
                    'allocated' => $days
                ]);
            }

            $this->db->commit();
            return $new_employee_id;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error creating employee: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update employee record
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        $fields = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'staff_type' => $data['staff_type'],
            'gender' => $data['gender'],
            'phone' => $data['phone'] ?? null,
            'department_id' => !empty($data['department_id']) ? $data['department_id'] : null,
            'designation' => $data['designation'] ?? null,
            'joining_date' => $data['joining_date'],
            'status' => $data['status']
        ];

        // Conditional updates
        $query = "
            UPDATE employees SET
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                role = :role,
                staff_type = :staff_type,
                gender = :gender,
                phone = :phone,
                department_id = :department_id,
                designation = :designation,
                joining_date = :joining_date,
                status = :status
        ";

        if (!empty($data['photo'])) {
            $query .= ", photo = :photo";
            $fields['photo'] = $data['photo'];
        }

        if (!empty($data['password'])) {
            $query .= ", password_hash = :password_hash";
            $fields['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $query .= " WHERE id = :id";
        $fields['id'] = $id;

        $stmt = $this->db->prepare($query);
        return $stmt->execute($fields);
    }

    /**
     * Toggle active status
     */
    public function setStatus($id, $status) {
        $stmt = $this->db->prepare("UPDATE employees SET status = :status WHERE id = :id");
        return $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /**
     * Get statistics summary
     */
    public function getSummary() {
        $stats = [];
        $stats['total'] = $this->db->query("SELECT COUNT(*) FROM employees")->fetchColumn();
        $stats['active'] = $this->db->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'")->fetchColumn();
        $stats['inactive'] = $this->db->query("SELECT COUNT(*) FROM employees WHERE status = 'Inactive'")->fetchColumn();
        $stats['academic'] = $this->db->query("SELECT COUNT(*) FROM employees WHERE staff_type = 'Academic'")->fetchColumn();
        $stats['non_academic'] = $this->db->query("SELECT COUNT(*) FROM employees WHERE staff_type = 'Non-Academic'")->fetchColumn();
        return $stats;
    }
}
?>