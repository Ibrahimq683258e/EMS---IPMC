<?php
require_once __DIR__ . '/Database.php';

class Leave {
    private $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    /**
     * Get leave balances for an employee
     * @param int $employee_id
     * @return array
     */
    public function getBalances($employee_id) {
        $stmt = $this->db->prepare("SELECT * FROM leave_balances WHERE employee_id = :employee_id");
        $stmt->execute(['employee_id' => $employee_id]);
        return $stmt->fetchAll();
    }

    /**
     * Fetch a single leave balance record
     */
    public function getBalanceByType($employee_id, $leave_type) {
        $stmt = $this->db->prepare("
            SELECT * FROM leave_balances
            WHERE employee_id = :employee_id AND leave_type = :leave_type
        ");
        $stmt->execute(['employee_id' => $employee_id, 'leave_type' => $leave_type]);
        $balance = $stmt->fetch();

        // If not initialized yet, initialize on the fly with standard defaults
        if (!$balance) {
            $allocated = ($leave_type === 'Annual') ? 20 : (($leave_type === 'Sick') ? 10 : 5);
            $stmt_init = $this->db->prepare("
                INSERT INTO leave_balances (employee_id, leave_type, allocated, used)
                VALUES (:employee_id, :leave_type, :allocated, 0)
            ");
            $stmt_init->execute([
                'employee_id' => $employee_id,
                'leave_type' => $leave_type,
                'allocated' => $allocated
            ]);
            return [
                'employee_id' => $employee_id,
                'leave_type' => $leave_type,
                'allocated' => $allocated,
                'used' => 0
            ];
        }
        return $balance;
    }

    /**
     * Apply for leave
     */
    public function apply($employee_id, $leave_type, $start_date, $end_date, $reason) {
        try {
            // Calculate days requested
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $interval = $start->diff($end);
            $days = $interval->days + 1; // inclusive

            // Validate dates
            if ($start > $end) {
                return "Error: Start date cannot be after end date.";
            }

            // Verify leave balance first
            $balance = $this->getBalanceByType($employee_id, $leave_type);
            $remaining = $balance['allocated'] - $balance['used'];

            if ($days > $remaining) {
                return "Error: Insufficient leave balance. You requested {$days} days, but only have {$remaining} remaining.";
            }

            $stmt = $this->db->prepare("
                INSERT INTO leaves (employee_id, leave_type, start_date, end_date, days_requested, reason, status)
                VALUES (:employee_id, :leave_type, :start_date, :end_date, :days, :reason, 'Pending')
            ");
            $stmt->execute([
                'employee_id' => $employee_id,
                'leave_type' => $leave_type,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'days' => $days,
                'reason' => trim($reason)
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error applying for leave: " . $e->getMessage());
            return "Error: " . $e->getMessage();
        }
    }

    /**
     * Get all leaves (optionally filtered)
     */
    public function getAll($filters = []) {
        $query = "
            SELECT l.*, e.first_name, e.last_name, e.employee_id AS employee_code, d.name as department_name, e.photo
            FROM leaves l
            JOIN employees e ON l.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['employee_id'])) {
            $query .= " AND l.employee_id = :employee_id";
            $params['employee_id'] = $filters['employee_id'];
        }

        if (!empty($filters['status'])) {
            $query .= " AND l.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['leave_type'])) {
            $query .= " AND l.leave_type = :leave_type";
            $params['leave_type'] = $filters['leave_type'];
        }

        $query .= " ORDER BY l.created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Find leave application by ID
     */
    public function findById($id) {
        $stmt = $this->db->prepare("
            SELECT l.*, e.first_name, e.last_name, e.employee_id AS employee_code, e.email, d.name as department_name
            FROM leaves l
            JOIN employees e ON l.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE l.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Process leave application (Approve / Reject)
     */
    public function process($id, $status, $action_by, $comments = '') {
        try {
            $this->db->beginTransaction();

            // Fetch current state
            $leave = $this->findById($id);
            if (!$leave) {
                $this->db->rollBack();
                return "Leave request not found.";
            }

            // If state hasn't changed, return true
            if ($leave['status'] === $status) {
                $this->db->commit();
                return true;
            }

            // Deduct / return balance only if transitioning to/from Approved status
            if ($status === 'Approved' && $leave['status'] !== 'Approved') {
                // Check balance again to be safe
                $balance = $this->getBalanceByType($leave['employee_id'], $leave['leave_type']);
                $remaining = $balance['allocated'] - $balance['used'];

                if ($leave['days_requested'] > $remaining) {
                    $this->db->rollBack();
                    return "Insufficient leave balance to approve this request.";
                }

                // Increment used count
                $stmt_up_bal = $this->db->prepare("
                    UPDATE leave_balances
                    SET used = used + :days
                    WHERE employee_id = :employee_id AND leave_type = :leave_type
                ");
                $stmt_up_bal->execute([
                    'days' => $leave['days_requested'],
                    'employee_id' => $leave['employee_id'],
                    'leave_type' => $leave['leave_type']
                ]);
            } elseif ($leave['status'] === 'Approved' && $status !== 'Approved') {
                // If it was previously approved and is now being marked as Rejected or Pending, refund the days
                $stmt_up_bal = $this->db->prepare("
                    UPDATE leave_balances
                    SET used = used - :days
                    WHERE employee_id = :employee_id AND leave_type = :leave_type
                ");
                $stmt_up_bal->execute([
                    'days' => $leave['days_requested'],
                    'employee_id' => $leave['employee_id'],
                    'leave_type' => $leave['leave_type']
                ]);
            }

            // Update status in leaves table
            $stmt_up_leave = $this->db->prepare("
                UPDATE leaves
                SET status = :status, action_by = :action_by, action_date = :action_date, comments = :comments
                WHERE id = :id
            ");
            $stmt_up_leave->execute([
                'status' => $status,
                'action_by' => $action_by,
                'action_date' => date('Y-m-d'),
                'comments' => trim($comments),
                'id' => $id
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error processing leave: " . $e->getMessage());
            return "Database Error: " . $e->getMessage();
        }
    }

    /**
     * Get summaries (e.g. pending requests count)
     */
    public function getSummary() {
        $stats = [];
        $stats['pending'] = $this->db->query("SELECT COUNT(*) FROM leaves WHERE status = 'Pending'")->fetchColumn();
        $stats['approved'] = $this->db->query("SELECT COUNT(*) FROM leaves WHERE status = 'Approved'")->fetchColumn();
        $stats['rejected'] = $this->db->query("SELECT COUNT(*) FROM leaves WHERE status = 'Rejected'")->fetchColumn();
        return $stats;
    }
}
?>