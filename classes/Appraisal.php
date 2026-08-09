<?php
require_once __DIR__ . '/Database.php';

class Appraisal {
    private $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    /**
     * Submit an appraisal rating for an employee
     */
    public function create($employee_id, $appraiser_id, $rating, $comments, $appraisal_period) {
        try {
            if ($rating < 1 || $rating > 5) {
                return "Error: Rating must be an integer between 1 and 5.";
            }

            $stmt = $this->db->prepare("
                INSERT INTO appraisals (employee_id, appraiser_id, rating, comments, appraisal_period, appraisal_date)
                VALUES (:employee_id, :appraiser_id, :rating, :comments, :appraisal_period, :appraisal_date)
            ");
            return $stmt->execute([
                'employee_id' => $employee_id,
                'appraiser_id' => $appraiser_id,
                'rating' => $rating,
                'comments' => trim($comments),
                'appraisal_period' => trim($appraisal_period),
                'appraisal_date' => date('Y-m-d')
            ]);
        } catch (PDOException $e) {
            error_log("Error creating appraisal: " . $e->getMessage());
            return "Error: " . $e->getMessage();
        }
    }

    /**
     * Fetch appraisals for a specific employee
     */
    public function getByEmployee($employee_id) {
        $stmt = $this->db->prepare("
            SELECT a.*, e.first_name as appraiser_first, e.last_name as appraiser_last, e.employee_id as appraiser_code
            FROM appraisals a
            JOIN employees e ON a.appraiser_id = e.id
            WHERE a.employee_id = :employee_id
            ORDER BY a.appraisal_date DESC
        ");
        $stmt->execute(['employee_id' => $employee_id]);
        return $stmt->fetchAll();
    }

    /**
     * Fetch all appraisals
     */
    public function getAll() {
        $stmt = $this->db->query("
            SELECT a.*,
                   emp.first_name as emp_first, emp.last_name as emp_last, emp.employee_id as emp_code, d.name as department_name,
                   app.first_name as appraiser_first, app.last_name as appraiser_last
            FROM appraisals a
            JOIN employees emp ON a.employee_id = emp.id
            JOIN employees app ON a.appraiser_id = app.id
            LEFT JOIN departments d ON emp.department_id = d.id
            ORDER BY a.appraisal_date DESC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Find appraisal by ID
     */
    public function findById($id) {
        $stmt = $this->db->prepare("
            SELECT a.*,
                   emp.first_name as emp_first, emp.last_name as emp_last, emp.employee_id as emp_code,
                   app.first_name as appraiser_first, app.last_name as appraiser_last
            FROM appraisals a
            JOIN employees emp ON a.employee_id = emp.id
            JOIN employees app ON a.appraiser_id = app.id
            WHERE a.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
}
?>