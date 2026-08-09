<?php
require_once __DIR__ . '/Database.php';

class Department {
    private $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    /**
     * Fetch all departments
     * @return array
     */
    public function getAll() {
        $stmt = $this->db->query("
            SELECT d.*, COUNT(e.id) as employee_count
            FROM departments d
            LEFT JOIN employees e ON d.id = e.department_id
            GROUP BY d.id
            ORDER BY d.name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Fetch department by ID
     * @param int $id
     * @return array|bool
     */
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM departments WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Create department
     */
    public function create($name, $code, $description = '') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO departments (name, code, description)
                VALUES (:name, :code, :description)
            ");
            return $stmt->execute([
                'name' => trim($name),
                'code' => strtoupper(trim($code)),
                'description' => trim($description)
            ]);
        } catch (PDOException $e) {
            error_log("Error creating department: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update department
     */
    public function update($id, $name, $code, $description = '') {
        try {
            $stmt = $this->db->prepare("
                UPDATE departments
                SET name = :name, code = :code, description = :description
                WHERE id = :id
            ");
            return $stmt->execute([
                'id' => $id,
                'name' => trim($name),
                'code' => strtoupper(trim($code)),
                'description' => trim($description)
            ]);
        } catch (PDOException $e) {
            error_log("Error updating department: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete department (only if no active employees are assigned or handle gracefully)
     */
    public function delete($id) {
        try {
            // Check if employees exist in this department
            $stmt_check = $this->db->prepare("SELECT COUNT(*) FROM employees WHERE department_id = :id");
            $stmt_check->execute(['id' => $id]);
            if ($stmt_check->fetchColumn() > 0) {
                return false; // Cannot delete, employees are assigned
            }

            $stmt = $this->db->prepare("DELETE FROM departments WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("Error deleting department: " . $e->getMessage());
            return false;
        }
    }
}
?>