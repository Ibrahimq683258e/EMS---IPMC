<?php
require_once __DIR__ . '/Database.php';

/**
 * Salary and Bonuses Management Model
 */
class Salary {
    private $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    /**
     * Get current salary of an employee
     */
    public function getSalary($employee_id) {
        $stmt = $this->db->prepare("SELECT * FROM salaries WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        return $stmt->fetch();
    }

    /**
     * Set or Update basic salary for an employee and log in history
     */
    public function updateSalary($employee_id, $basic_salary, $changed_by = null) {
        $basic_salary = floatval($basic_salary);
        $this->db->beginTransaction();
        try {
            // Get old salary if exists
            $stmt = $this->db->prepare("SELECT basic_salary FROM salaries WHERE employee_id = ?");
            $stmt->execute([$employee_id]);
            $old = $stmt->fetch();
            $old_salary = $old ? floatval($old['basic_salary']) : 0.00;

            if ($old) {
                // Update
                $stmt = $this->db->prepare("UPDATE salaries SET basic_salary = ? WHERE employee_id = ?");
                $stmt->execute([$basic_salary, $employee_id]);
            } else {
                // Insert
                $stmt = $this->db->prepare("INSERT INTO salaries (employee_id, basic_salary) VALUES (?, ?)");
                $stmt->execute([$employee_id, $basic_salary]);
            }

            // Log history
            $stmt = $this->db->prepare("INSERT INTO salary_history (employee_id, old_salary, new_salary, changed_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$employee_id, $old_salary, $basic_salary, $changed_by]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Get salary modification history of an employee
     */
    public function getHistory($employee_id) {
        $stmt = $this->db->prepare("
            SELECT h.*, e.first_name AS changer_first, e.last_name AS changer_last
            FROM salary_history h
            LEFT JOIN employees e ON h.changed_by = e.id
            WHERE h.employee_id = ?
            ORDER BY h.changed_at DESC
        ");
        $stmt->execute([$employee_id]);
        return $stmt->fetchAll();
    }

    /**
     * Get all salaries with employee details
     */
    public function getAllSalaries() {
        $stmt = $this->db->query("
            SELECT s.*, e.employee_id AS emp_code, e.first_name, e.last_name, e.designation, d.name AS department_name
            FROM employees e
            LEFT JOIN salaries s ON e.id = s.employee_id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.status = 'Active'
            ORDER BY e.first_name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Add a bonus for an employee
     */
    public function addBonus($employee_id, $bonus_type, $amount, $date_given, $reason = null) {
        $stmt = $this->db->prepare("
            INSERT INTO bonuses (employee_id, bonus_type, amount, date_given, reason)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$employee_id, $bonus_type, floatval($amount), $date_given, $reason]);
    }

    /**
     * Update an existing bonus
     */
    public function updateBonus($id, $bonus_type, $amount, $date_given, $reason = null) {
        $stmt = $this->db->prepare("
            UPDATE bonuses
            SET bonus_type = ?, amount = ?, date_given = ?, reason = ?
            WHERE id = ?
        ");
        return $stmt->execute([$bonus_type, floatval($amount), $date_given, $reason, $id]);
    }

    /**
     * Delete a bonus
     */
    public function deleteBonus($id) {
        $stmt = $this->db->prepare("DELETE FROM bonuses WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Get individual bonus by ID
     */
    public function getBonus($id) {
        $stmt = $this->db->prepare("SELECT * FROM bonuses WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Get bonuses awarded to an employee
     */
    public function getBonusesByEmployee($employee_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM bonuses
            WHERE employee_id = ?
            ORDER BY date_given DESC
        ");
        $stmt->execute([$employee_id]);
        return $stmt->fetchAll();
    }

    /**
     * Get total bonuses of an employee for a specific month and year
     */
    public function getMonthlyBonusTotal($employee_id, $year, $month) {
        $stmt = $this->db->prepare("
            SELECT SUM(amount) AS total
            FROM bonuses
            WHERE employee_id = ? AND YEAR(date_given) = ? AND MONTH(date_given) = ?
        ");
        $stmt->execute([$employee_id, $year, $month]);
        $row = $stmt->fetch();
        return $row ? floatval($row['total'] ?? 0.00) : 0.00;
    }

    /**
     * Get overall earnings summary of an employee (total salary payments + unpaid)
     */
    public function getEmployeeEarningsSummary($employee_id) {
        $stmt = $this->db->prepare("
            SELECT
                SUM(basic_salary) AS total_basic,
                SUM(bonus_amount) AS total_bonus,
                SUM(total_earnings) AS total_earnings
            FROM salary_payments
            WHERE employee_id = ?
        ");
        $stmt->execute([$employee_id]);
        return $stmt->fetch();
    }

    /**
     * Generate or Fetch a monthly payroll entry for an employee.
     * Automatically calculates Total Earnings = Basic + Bonuses logged for that month.
     */
    public function getMonthlyPayrollEntry($employee_id, $year, $month) {
        // Check if payroll entry already exists
        $stmt = $this->db->prepare("
            SELECT * FROM salary_payments
            WHERE employee_id = ? AND year = ? AND month = ?
        ");
        $stmt->execute([$employee_id, $year, $month]);
        $existing = $stmt->fetch();
        if ($existing) {
            return $existing;
        }

        // Otherwise generate preview data dynamically
        $salaryRow = $this->getSalary($employee_id);
        $basic = $salaryRow ? floatval($salaryRow['basic_salary']) : 0.00;
        $bonus = $this->getMonthlyBonusTotal($employee_id, $year, $month);
        $total = $basic + $bonus;

        return [
            'id' => null,
            'employee_id' => $employee_id,
            'year' => $year,
            'month' => $month,
            'basic_salary' => $basic,
            'bonus_amount' => $bonus,
            'total_earnings' => $total,
            'status' => 'Unpaid',
            'paid_date' => null
        ];
    }

    /**
     * Save/Create or Update a monthly payroll entry and mark as Paid/Unpaid
     */
    public function saveMonthlyPayment($employee_id, $year, $month, $status, $paid_date = null) {
        $entry = $this->getMonthlyPayrollEntry($employee_id, $year, $month);
        $basic = floatval($entry['basic_salary']);
        $bonus = floatval($entry['bonus_amount']);
        $total = $basic + $bonus;

        if ($entry['id'] !== null) {
            // Update existing payment status
            $stmt = $this->db->prepare("
                UPDATE salary_payments
                SET status = ?, paid_date = ?, basic_salary = ?, bonus_amount = ?, total_earnings = ?
                WHERE id = ?
            ");
            return $stmt->execute([$status, $paid_date, $basic, $bonus, $total, $entry['id']]);
        } else {
            // Insert new payment log
            $stmt = $this->db->prepare("
                INSERT INTO salary_payments (employee_id, year, month, basic_salary, bonus_amount, total_earnings, status, paid_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([$employee_id, $year, $month, $basic, $bonus, $total, $status, $paid_date]);
        }
    }

    /**
     * Get payment history of an employee
     */
    public function getPaymentHistory($employee_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM salary_payments
            WHERE employee_id = ?
            ORDER BY year DESC, month DESC
        ");
        $stmt->execute([$employee_id]);
        return $stmt->fetchAll();
    }

    /**
     * Report: Get all salary payments for a given month and year
     */
    public function getMonthlyPayrollReport($year, $month) {
        $stmt = $this->db->prepare("
            SELECT p.*, e.employee_id AS emp_code, e.first_name, e.last_name, d.name AS department_name
            FROM salary_payments p
            LEFT JOIN employees e ON p.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE p.year = ? AND p.month = ?
            ORDER BY e.first_name ASC
        ");
        $stmt->execute([$year, $month]);
        return $stmt->fetchAll();
    }

    /**
     * Report: Get all bonuses given in a specific year or month
     */
    public function getBonusesReport($year = null, $month = null) {
        $sql = "
            SELECT b.*, e.employee_id AS emp_code, e.first_name, e.last_name, d.name AS department_name
            FROM bonuses b
            LEFT JOIN employees e ON b.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE 1=1
        ";
        $params = [];
        if ($year !== null && $year !== '') {
            $sql .= " AND YEAR(b.date_given) = ?";
            $params[] = $year;
        }
        if ($month !== null && $month !== '') {
            $sql .= " AND MONTH(b.date_given) = ?";
            $params[] = $month;
        }
        $sql .= " ORDER BY b.date_given DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Report: Get earnings summary across active employees
     */
    public function getEmployeesEarningsSummaryReport() {
        $stmt = $this->db->query("
            SELECT
                e.id AS employee_id,
                e.employee_id AS emp_code,
                e.first_name,
                e.last_name,
                d.name AS department_name,
                s.basic_salary,
                IFNULL(SUM(b.amount), 0.00) AS total_bonuses,
                (IFNULL(s.basic_salary, 0.00) + IFNULL(SUM(b.amount), 0.00)) AS estimated_monthly_earnings
            FROM employees e
            LEFT JOIN salaries s ON e.id = s.employee_id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN bonuses b ON e.id = b.employee_id
            WHERE e.status = 'Active'
            GROUP BY e.id
            ORDER BY e.first_name ASC
        ");
        return $stmt->fetchAll();
    }
}
