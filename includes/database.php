<?php
require_once __DIR__ . '/../config.php';

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            // Database configuration from environment
            $host = Config::get('database.host');
            $dbname = Config::get('database.name');
            $username = Config::get('database.user');
            $password = Config::get('database.pass');
            $charset = Config::get('database.charset');

            try {
                $this->connection = new PDO(
                    "mysql:host=$host;dbname=$dbname;charset=$charset",
                    $username,
                    $password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } catch (PDOException $e) {
                // If database doesn't exist, try to create it
                if (strpos($e->getMessage(), 'Unknown database') !== false) {
                    try {
                        $pdo = new PDO(
                            "mysql:host=$host;charset=$charset",
                            $username,
                            $password,
                            [
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            ]
                        );
                        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        error_log("Database '$dbname' created successfully");

                        // Now connect to the created database
                        $this->connection = new PDO(
                            "mysql:host=$host;dbname=$dbname;charset=$charset",
                            $username,
                            $password,
                            [
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                                PDO::ATTR_EMULATE_PREPARES => false,
                            ]
                        );
                    } catch (PDOException $createError) {
                        error_log("Failed to create database: " . $createError->getMessage());
                        throw new Exception("Database does not exist and could not be created. Please create the database manually.");
                    }
                } else {
                    throw $e;
                }
            }
        } catch (PDOException $e) {
            // Log error and throw exception for API handling
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your configuration.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    // Helper method to execute queries
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Query execution failed: " . $e->getMessage());
        }
    }

    // Helper method for SELECT queries
    public function select($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    // Helper method for INSERT queries
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }

    // Helper method for UPDATE/DELETE queries
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    // Begin transaction
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    // Commit transaction
    public function commit() {
        return $this->connection->commit();
    }

    // Rollback transaction
    public function rollback() {
        return $this->connection->rollback();
    }
}

// Global function to get database instance
function db() {
    return Database::getInstance()->getConnection();
}
?>
