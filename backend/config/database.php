<?php
/**
 * Database Connection Singleton (PDO)
 */

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Don't expose details in production
            if (APP_ENV === 'development') {
                die(json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]));
            } else {
                die(json_encode(['success' => false, 'message' => 'Database connection failed']));
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    /** Helper: fetch single row */
    public static function row($sql, $params = []) {
        $stmt = self::getInstance()->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /** Helper: fetch multiple rows */
    public static function all($sql, $params = []) {
        $stmt = self::getInstance()->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Helper: execute statement, return affected rows */
    public static function exec($sql, $params = []) {
        $stmt = self::getInstance()->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Helper: insert and return last ID */
    public static function insert($table, $data) {
        $cols = array_keys($data);
        $placeholders = ':' . implode(', :', $cols);
        $sql = "INSERT INTO {$table} (" . implode(', ', $cols) . ") VALUES ({$placeholders})";
        $stmt = self::getInstance()->getConnection()->prepare($sql);
        $stmt->execute($data);
        return self::getInstance()->getConnection()->lastInsertId();
    }

    /** Helper: update by ID */
    public static function update($table, $id, $data) {
        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = "{$col} = :{$col}";
        }
        $sql = "UPDATE {$table} SET " . implode(', ', $set) . " WHERE id = :id";
        $data['id'] = $id;
        $stmt = self::getInstance()->getConnection()->prepare($sql);
        return $stmt->execute($data);
    }
}

// Convenience global function
function db() {
    return Database::getInstance()->getConnection();
}
