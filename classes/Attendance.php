<?php
require_once __DIR__ . '/Database.php';

class Attendance {
    private $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    /**
     * Mark or update attendance for an employee on a specific date
     */
    public function mark($employee_id, $date, $status, $time_in = null, $time_out = null, $notes = '') {
        try {
            $stmt_check = $this->db->prepare("SELECT id FROM attendance WHERE employee_id = :employee_id AND date = :date");
            $stmt_check->execute(['employee_id' => $employee_id, 'date' => $date]);
            $exists = $stmt_check->fetch();

            if ($exists) {
                // Update
                $stmt = $this->db->prepare("
                    UPDATE attendance
                    SET status = :status, time_in = :time_in, time_out = :time_out, notes = :notes
                    WHERE employee_id = :employee_id AND date = :date
                ");
            } else {
                // Insert
                $stmt = $this->db->prepare("
                    INSERT INTO attendance (employee_id, date, status, time_in, time_out, notes)
                    VALUES (:employee_id, :date, :status, :time_in, :time_out, :notes)
                ");
            }

            return $stmt->execute([
                'employee_id' => $employee_id,
                'date' => $date,
                'status' => $status,
                'time_in' => !empty($time_in) ? $time_in : null,
                'time_out' => !empty($time_out) ? $time_out : null,
                'notes' => trim($notes)
            ]);
        } catch (PDOException $e) {
            error_log("Error marking attendance: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get attendance for all employees on a specific date
     */
    public function getByDate($date) {
        $stmt = $this->db->prepare("
            SELECT e.id as employee_id, e.employee_id as employee_code, e.first_name, e.last_name, d.name as department_name,
                   a.status, a.time_in, a.time_out, a.notes, a.id as attendance_id
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = :date
            WHERE e.status = 'Active'
            ORDER BY e.employee_id ASC
        ");
        $stmt->execute(['date' => $date]);
        return $stmt->fetchAll();
    }

    /**
     * Get attendance records for a specific employee
     */
    public function getByEmployee($employee_id, $start_date = null, $end_date = null) {
        $query = "
            SELECT * FROM attendance
            WHERE employee_id = :employee_id
        ";
        $params = ['employee_id' => $employee_id];

        if ($start_date) {
            $query .= " AND date >= :start_date";
            $params['start_date'] = $start_date;
        }

        if ($end_date) {
            $query .= " AND date <= :end_date";
            $params['end_date'] = $end_date;
        }

        $query .= " ORDER BY date DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get overall attendance summary for a specific date (for Dashboard)
     */
    public function getSummary($date) {
        $stmt = $this->db->prepare("
            SELECT
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status = 'Permission' THEN 1 ELSE 0 END) as permission
            FROM attendance
            WHERE date = :date
        ");
        $stmt->execute(['date' => $date]);
        $res = $stmt->fetch();

        return [
            'present' => $res['present'] ?? 0,
            'absent' => $res['absent'] ?? 0,
            'late' => $res['late'] ?? 0,
            'permission' => $res['permission'] ?? 0
        ];
    }

    /**
     * Get custom reporting data
     */
    public function getReportData($filters = []) {
        $query = "
            SELECT a.*, e.first_name, e.last_name, e.employee_id as employee_code, d.name as department_name
            FROM attendance a
            JOIN employees e ON a.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['department_id'])) {
            $query .= " AND e.department_id = :department_id";
            $params['department_id'] = $filters['department_id'];
        }

        if (!empty($filters['employee_id'])) {
            $query .= " AND a.employee_id = :employee_id";
            $params['employee_id'] = $filters['employee_id'];
        }

        if (!empty($filters['start_date'])) {
            $query .= " AND a.date >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $query .= " AND a.date <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        if (!empty($filters['status'])) {
            $query .= " AND a.status = :status";
            $params['status'] = $filters['status'];
        }

        $query .= " ORDER BY a.date DESC, e.employee_id ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
?>