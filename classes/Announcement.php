<?php
require_once __DIR__ . '/Database.php';

class Announcement {
    private $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    /**
     * Post a new announcement
     */
    public function create($title, $content, $created_by) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO announcements (title, content, created_by)
                VALUES (:title, :content, :created_by)
            ");
            return $stmt->execute([
                'title' => trim($title),
                'content' => trim($content),
                'created_by' => $created_by
            ]);
        } catch (PDOException $e) {
            error_log("Error creating announcement: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recent announcements
     * @param int $limit
     * @return array
     */
    public function getLatest($limit = 10) {
        $stmt = $this->db->prepare("
            SELECT a.*, e.first_name, e.last_name, e.role as creator_role
            FROM announcements a
            JOIN employees e ON a.created_by = e.id
            ORDER BY a.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Delete an announcement
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM announcements WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
?>