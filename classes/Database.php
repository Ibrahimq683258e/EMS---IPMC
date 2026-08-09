<?php
/**
 * Database Connection Class (OOP PDO)
 * Provides a single PDO instance for clean database access.
 */
class Database {
    private static $host = 'localhost';
    private static $db_name = 'ipmc_ems';
    private static $username = 'ipmc_user';
    private static $password = 'ipmc_secure_pass123!';
    private static $conn = null;

    /**
     * Get database connection
     * @return PDO
     */
    public static function connect() {
        if (self::$conn === null) {
            try {
                $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$db_name . ";charset=utf8mb4";
                self::$conn = new PDO($dsn, self::$username, self::$password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            } catch (PDOException $e) {
                // In production, log error. For this educational FYP system, we'll display a friendly error message.
                die("Database Connection Error: " . $e->getMessage());
            }
        }
        return self::$conn;
    }
}
