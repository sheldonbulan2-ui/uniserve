<?php
declare(strict_types=1);

// Ensure output buffering is active
if (!ob_get_level()) {
    ob_start();
}

/**
 * UniServe Universal Enterprise Database Connector
 * Uses 100% native PDO with ATTR_EMULATE_PREPARES => false.
 * Supports MySQL 8.0+ (Local / Production) & PostgreSQL (Render / Supabase).
 */

class Database {
    private static ?PDO $pdoInstance = null;

    public static function getConnection(): PDO {
        if (self::$pdoInstance !== null) {
            return self::$pdoInstance;
        }

        $dbUrl = getenv('DATABASE_URL') ?: getenv('SUPABASE_DB_URL') ?: null;
        $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
        $dbUser = getenv('DB_USER') ?: 'root';
        $dbPass = getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : (getenv('DB_PASSWORD') !== false ? (string)getenv('DB_PASSWORD') : '');
        $dbName = getenv('DB_NAME') ?: 'uniserve_db';
        $dbPort = getenv('DB_PORT') ? (int)getenv('DB_PORT') : null;
        $dbType = getenv('DB_TYPE') ?: null;

        if ($dbUrl) {
            $parsed = parse_url($dbUrl);
            if ($parsed) {
                $scheme = $parsed['scheme'] ?? '';
                if (str_contains($scheme, 'postgres') || str_contains($scheme, 'pgsql')) {
                    $dbType = 'pgsql';
                } elseif (str_contains($scheme, 'mysql')) {
                    $dbType = 'mysql';
                }
                $dbHost = $parsed['host'] ?? $dbHost;
                $dbPort = isset($parsed['port']) ? (int)$parsed['port'] : ($dbType === 'pgsql' ? 5432 : 3306);
                $dbUser = isset($parsed['user']) ? urldecode($parsed['user']) : $dbUser;
                $dbPass = isset($parsed['pass']) ? urldecode($parsed['pass']) : $dbPass;
                $dbName = isset($parsed['path']) ? ltrim($parsed['path'], '/') : $dbName;
            }
        }

        if (!$dbType) {
            if ($dbPort === 5432 || (is_string($dbHost) && str_contains($dbHost, 'supabase'))) {
                $dbType = 'pgsql';
            } else {
                $dbType = 'mysql';
            }
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            if ($dbType === 'pgsql') {
                $portPart = $dbPort ? ";port={$dbPort}" : '';
                $dsn = "pgsql:host={$dbHost}{$portPart};dbname={$dbName};sslmode=require";
                self::$pdoInstance = new PDO($dsn, $dbUser, $dbPass, $options);
            } else {
                $port = $dbPort ?: 3306;
                $dsn = "mysql:host={$dbHost};port={$port};dbname={$dbName};charset=utf8mb4";
                self::$pdoInstance = new PDO($dsn, $dbUser, $dbPass, $options);
            }

            self::initializeTables(self::$pdoInstance, $dbType);

        } catch (PDOException $e) {
            error_log("Database connection failure: " . $e->getMessage());
            die(json_encode([
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ]));
        }

        return self::$pdoInstance;
    }

    /**
     * Auto-initialize 3NF tables and default accounts if missing
     */
    private static function initializeTables(PDO $pdo, string $dbType): void {
        try {
            // Check if users table exists
            $check = ($dbType === 'pgsql')
                ? "SELECT 1 FROM information_schema.tables WHERE table_name = 'users'"
                : "SHOW TABLES LIKE 'users'";
            $res = $pdo->query($check);
            if (!$res || $res->rowCount() === 0) {
                // Table doesn't exist yet, run initial setup
                self::runSchemaMigration($pdo, $dbType);
            }

            // Guarantee Super Admin seed exists with known standard password: 'password123'
            $adminEmail = 'admin@uniserve.com';
            $knownAdminHash = '$2y$10$qCN028Kmx9ve1Z4lZelB5ejA.XCnyDxaRaW.ibMkzlKneazgErJYG'; // password123

            $stmt = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$adminEmail]);
            $adm = $stmt->fetch();

            if ($adm) {
                if (!password_verify('password123', (string)$adm['password_hash']) || $adm['role'] !== 'admin') {
                    $upd = $pdo->prepare("UPDATE users SET password_hash = ?, role = 'admin', status = 'active' WHERE id = ?");
                    $upd->execute([$knownAdminHash, $adm['id']]);
                }
            } else {
                $ins = $pdo->prepare("INSERT INTO users (uuid, role, full_name, email, password_hash, status) VALUES (?, 'admin', 'UniServe Administrator', ?, ?, 'active')");
                $ins->execute(['a0000000-0000-0000-0000-000000000001', $adminEmail, $knownAdminHash]);
            }

            // Ensure Merchant Seed exists
            $merchantEmail = 'maryjoy@gmail.com';
            $mStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $mStmt->execute([$merchantEmail]);
            $merchant = $mStmt->fetch();
            if (!$merchant) {
                $mIns = $pdo->prepare("INSERT INTO users (uuid, role, full_name, email, password_hash, status) VALUES (?, 'merchant', 'Mary Joy Mallillin', ?, ?, 'active')");
                $mIns->execute(['a0000000-0000-0000-0000-000000000002', $merchantEmail, $knownAdminHash]);
                $mUserId = (int)$pdo->lastInsertId();

                $pIns = $pdo->prepare("INSERT INTO merchant_profiles (user_id, business_name, business_type, address, contact_phone, is_verified) VALUES (?, 'Bean Yard Cafe & Resto', 'Food & Dining / Cafe', '123 Tan Street, Manila', '09264362738', 1)");
                $pIns->execute([$mUserId]);
            }

            // Ensure Customer Seed exists
            $customerEmail = 'joycerine@gmail.com';
            $cStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $cStmt->execute([$customerEmail]);
            if (!$cStmt->fetch()) {
                $cIns = $pdo->prepare("INSERT INTO users (uuid, role, full_name, email, password_hash, status) VALUES (?, 'customer', 'Joycerine Galapon', ?, ?, 'active')");
                $cIns->execute(['a0000000-0000-0000-0000-000000000003', $customerEmail, $knownAdminHash]);
            }

        } catch (Throwable $e) {
            error_log("Schema auto-init note: " . $e->getMessage());
        }
    }

    private static function runSchemaMigration(PDO $pdo, string $dbType): void {
        $sqlPath = __DIR__ . '/../database/uniserve_db.sql';
        if (file_exists($sqlPath)) {
            $sql = file_get_contents($sqlPath);
            if ($sql) {
                if ($dbType === 'pgsql') {
                    // Convert basic MySQL specific syntax to PostgreSQL if running on Supabase
                    $sql = str_ireplace('INT AUTO_INCREMENT PRIMARY KEY', 'SERIAL PRIMARY KEY', $sql);
                    $sql = str_ireplace('DATETIME', 'TIMESTAMPTZ', $sql);
                    $sql = str_ireplace('SET FOREIGN_KEY_CHECKS = 0;', '', $sql);
                    $sql = str_ireplace('SET FOREIGN_KEY_CHECKS = 1;', '', $sql);
                    $sql = preg_replace('/ENGINE=InnoDB[^\;]*/i', '', $sql);
                    $sql = preg_replace('/ON UPDATE CURRENT_TIMESTAMP/i', '', $sql);
                }
                $pdo->exec($sql);
            }
        }
    }
}

// ------------------------------------------------------------------------------
// Global Instances for backward compatibility with existing procedural code
// ------------------------------------------------------------------------------
$pdo = Database::getConnection();

// Minimal PDO-to-MySQLi compatibility wrapper for older admin pages
if (!isset($conn)) {
    class MysqlCompatResult {
        private array $rows;
        private int $index = 0;
        public int $num_rows = 0;

        public function __construct(array $rows) {
            $this->rows = $rows;
            $this->num_rows = count($rows);
        }

        public function fetch_assoc(): ?array {
            if ($this->index < $this->num_rows) {
                return $this->rows[$this->index++];
            }
            return null;
        }

        public function fetch_all($mode = MYSQLI_ASSOC): array {
            return $this->rows;
        }

        public function free(): void {
            $this->rows = [];
            $this->num_rows = 0;
        }

        public function close(): void {
            $this->free();
        }
    }

    class MysqlCompatStmt {
        private PDO $pdo;
        private PDOStatement $pdoStmt;
        private ?object $conn;
        private array $boundParams = [];
        private string $types = '';
        private ?array $executedRows = null;
        private string $rawQuery = '';
        public int $num_rows = 0;
        public int $insert_id = 0;
        public int $affected_rows = 0;
        public string $error = '';
        public int $errno = 0;

        public function __construct(PDO $pdo, PDOStatement $pdoStmt, ?object $conn = null, string $rawQuery = '') {
            $this->pdo = $pdo;
            $this->pdoStmt = $pdoStmt;
            $this->conn = $conn;
            $this->rawQuery = $rawQuery;
        }

        public function bind_param(string $types, mixed &...$vars): bool {
            $this->types = $types;
            $this->boundParams = [];
            foreach ($vars as $i => &$v) {
                $this->boundParams[$i] = &$v;
            }
            return true;
        }

        public function execute(): bool {
            try {
                $this->error = '';
                $this->errno = 0;

                $params = [];
                $len = strlen($this->types);
                for ($i = 0; $i < count($this->boundParams); $i++) {
                    $t = $i < $len ? $this->types[$i] : 's';
                    $val = $this->boundParams[$i];
                    if ($val === null) {
                        $params[] = null;
                    } elseif ($t === 'i') {
                        $params[] = (int)$val;
                    } elseif ($t === 'd') {
                        $params[] = (float)$val;
                    } else {
                        $params[] = (string)$val;
                    }
                }

                $res = $this->pdoStmt->execute($params);
                $this->affected_rows = $this->pdoStmt->rowCount();

                $trimmed = ltrim($this->rawQuery);
                if (stripos($trimmed, 'INSERT') === 0) {
                    try {
                        $lastId = (int)$this->pdo->lastInsertId();
                        if ($lastId) {
                            $this->insert_id = $lastId;
                            if ($this->conn) {
                                $this->conn->insert_id = $lastId;
                            }
                        }
                    } catch (Throwable) {}
                }

                return $res;
            } catch (Exception $e) {
                $this->error = $e->getMessage();
                $this->errno = (int)($e->getCode() ?: 1);
                error_log("MysqlCompatStmt error: " . $e->getMessage());
                return false;
            }
        }

        public function store_result(): bool {
            try {
                $this->executedRows = $this->pdoStmt->fetchAll(PDO::FETCH_ASSOC);
                $this->num_rows = count($this->executedRows);
                return true;
            } catch (Exception) {
                return false;
            }
        }

        public function get_result(): MysqlCompatResult {
            try {
                if ($this->executedRows !== null) {
                    $rows = $this->executedRows;
                    $this->executedRows = null;
                } else {
                    $rows = $this->pdoStmt->fetchAll(PDO::FETCH_ASSOC);
                }
                return new MysqlCompatResult($rows);
            } catch (Exception) {
                return new MysqlCompatResult([]);
            }
        }

        public function close(): bool {
            $this->pdoStmt->closeCursor();
            return true;
        }
    }

    class MysqlCompatConnection {
        public PDO $pdo;
        public ?string $connect_error = null;
        public string $error = '';
        public int $errno = 0;
        public int $insert_id = 0;

        public function __construct(PDO $pdo) {
            $this->pdo = $pdo;
        }

        public function prepare(string $query): MysqlCompatStmt|false {
            try {
                $this->error = '';
                $this->errno = 0;
                $cleanQuery = str_replace('`', '', $query);
                $stmt = $this->pdo->prepare($cleanQuery);
                return new MysqlCompatStmt($this->pdo, $stmt, $this, $cleanQuery);
            } catch (Exception $e) {
                $this->error = $e->getMessage();
                $this->errno = (int)($e->getCode() ?: 1);
                return false;
            }
        }

        public function query(string $query): MysqlCompatResult|bool {
            try {
                $this->error = '';
                $this->errno = 0;
                $cleanQuery = str_replace('`', '', $query);
                $pdoStmt = $this->pdo->query($cleanQuery);
                if (!$pdoStmt) {
                    return false;
                }

                $trimmed = ltrim($cleanQuery);
                if (stripos($trimmed, 'INSERT') === 0) {
                    try {
                        $lastId = (int)$this->pdo->lastInsertId();
                        if ($lastId) {
                            $this->insert_id = $lastId;
                        }
                    } catch (Throwable) {}
                }

                $rows = $pdoStmt->fetchAll(PDO::FETCH_ASSOC);
                return new MysqlCompatResult($rows);
            } catch (Exception $e) {
                $this->error = $e->getMessage();
                $this->errno = (int)($e->getCode() ?: 1);
                return false;
            }
        }

        public function real_escape_string(string $string): string {
            $quoted = $this->pdo->quote($string);
            return substr($quoted, 1, -1);
        }

        public function escape_string(string $string): string {
            return $this->real_escape_string($string);
        }

        public function set_charset(string $charset): bool {
            return true;
        }

        public function begin_transaction(): bool {
            return $this->pdo->beginTransaction();
        }

        public function commit(): bool {
            return $this->pdo->commit();
        }

        public function rollback(): bool {
            return $this->pdo->rollBack();
        }

        public function close(): bool {
            return true;
        }
    }

    $conn = new MysqlCompatConnection($pdo);
}
