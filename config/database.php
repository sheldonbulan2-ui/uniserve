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
            // Check if apk_releases table exists
            $check = ($dbType === 'pgsql')
                ? "SELECT 1 FROM information_schema.tables WHERE table_name = 'apk_releases'"
                : "SHOW TABLES LIKE 'apk_releases'";
            $res = $pdo->query($check);
            if (!$res || $res->rowCount() === 0) {
                self::createMissingTables($pdo, $dbType);
            }

            // Detect whether users table has 'user_id' or 'id' and migrate/align columns
            $idCol = 'id';
            $pwdCol = 'password_hash';
            try {
                $colCheck = $pdo->query("SELECT user_id, password FROM users LIMIT 1");
                if ($colCheck) {
                    $idCol = 'user_id';
                    $pwdCol = 'password';

                    // Non-destructive compatibility: Add alias columns if missing
                    if ($dbType === 'pgsql') {
                        $pdo->exec("
                            ALTER TABLE users ADD COLUMN IF NOT EXISTS id INT;
                            UPDATE users SET id = user_id WHERE id IS NULL;
                            ALTER TABLE users ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255);
                            UPDATE users SET password_hash = password WHERE password_hash IS NULL;
                            ALTER TABLE users ADD COLUMN IF NOT EXISTS uuid VARCHAR(36);
                            UPDATE users SET uuid = md5(random()::text || clock_timestamp()::text)::uuid WHERE uuid IS NULL;
                            ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(30);
                            ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active';
                            UPDATE users SET status = 'active' WHERE status IS NULL;
                            ALTER TABLE users ADD COLUMN IF NOT EXISTS failed_login_attempts INT DEFAULT 0;
                            ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_until TIMESTAMPTZ NULL;
                        ");
                    } else {
                        // MySQL fallback
                        @$pdo->exec("ALTER TABLE users ADD COLUMN id INT NULL");
                        @$pdo->exec("UPDATE users SET id = user_id WHERE id IS NULL");
                        @$pdo->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL");
                        @$pdo->exec("UPDATE users SET password_hash = password WHERE password_hash IS NULL");
                        @$pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
                        @$pdo->exec("ALTER TABLE users ADD COLUMN failed_login_attempts INT DEFAULT 0");
                        @$pdo->exec("ALTER TABLE users ADD COLUMN locked_until DATETIME NULL");
                    }
                }
            } catch (Throwable $e) {
                error_log("Column check/migration note: " . $e->getMessage());
            }

            // Guarantee Super Admin seed exists with known standard password: 'password123'
            $adminEmail = 'admin@uniserve.com';
            $knownAdminHash = '$2y$10$qCN028Kmx9ve1Z4lZelB5ejA.XCnyDxaRaW.ibMkzlKneazgErJYG'; // password123

            $stmt = $pdo->prepare("SELECT {$idCol} AS id, {$pwdCol} AS pwd, role FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$adminEmail]);
            $adm = $stmt->fetch();

            if ($adm) {
                if (!password_verify('password123', (string)$adm['pwd']) || $adm['role'] !== 'admin') {
                    $upd = $pdo->prepare("UPDATE users SET {$pwdCol} = ?, role = 'admin' WHERE {$idCol} = ?");
                    $upd->execute([$knownAdminHash, $adm['id']]);
                }
            } else {
                $ins = $pdo->prepare("INSERT INTO users (full_name, email, {$pwdCol}, role) VALUES ('UniServe Administrator', ?, ?, 'admin')");
                $ins->execute([$adminEmail, $knownAdminHash]);
            }

        } catch (Throwable $e) {
            error_log("Schema auto-init note: " . $e->getMessage());
        }
    }

    private static function createMissingTables(PDO $pdo, string $dbType): void {
        try {
            if ($dbType === 'pgsql') {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS apk_releases (
                        id SERIAL PRIMARY KEY,
                        version_name VARCHAR(20) NOT NULL,
                        version_code INT NOT NULL,
                        file_name VARCHAR(255) NOT NULL,
                        file_path VARCHAR(255) NOT NULL,
                        file_size_bytes BIGINT NOT NULL DEFAULT 0,
                        sha256_checksum CHAR(64) NOT NULL,
                        release_notes TEXT NULL,
                        is_latest BOOLEAN NOT NULL DEFAULT TRUE,
                        download_count INT NOT NULL DEFAULT 0,
                        uploaded_by INT NULL,
                        created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS inventory_logs (
                        id SERIAL PRIMARY KEY,
                        inventory_id INT NOT NULL,
                        performed_by INT NOT NULL,
                        change_type VARCHAR(30) NOT NULL,
                        quantity_changed INT NOT NULL,
                        previous_stock INT NOT NULL,
                        new_stock INT NOT NULL,
                        remarks VARCHAR(255) NULL,
                        created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS auth_tokens (
                        id SERIAL PRIMARY KEY,
                        user_id INT NOT NULL,
                        refresh_token_hash CHAR(64) NOT NULL,
                        client_platform VARCHAR(20) NOT NULL DEFAULT 'web',
                        device_info VARCHAR(255) NULL,
                        ip_address VARCHAR(45) NULL,
                        expires_at TIMESTAMPTZ NOT NULL,
                        revoked BOOLEAN NOT NULL DEFAULT FALSE,
                        created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS audit_logs (
                        id SERIAL PRIMARY KEY,
                        user_id INT NULL,
                        action VARCHAR(100) NOT NULL,
                        entity_type VARCHAR(50) NOT NULL,
                        entity_id INT NULL,
                        ip_address VARCHAR(45) NULL,
                        user_agent TEXT NULL,
                        details JSONB NULL,
                        created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
                    );
                ");

                // Seed initial APK release record
                $chkApk = $pdo->query("SELECT 1 FROM apk_releases LIMIT 1");
                if ($chkApk && $chkApk->rowCount() === 0) {
                    $pdo->exec("INSERT INTO apk_releases (version_name, version_code, file_name, file_path, file_size_bytes, sha256_checksum, release_notes, is_latest, download_count)
                        VALUES ('v1.0.0', 1, 'UniServe-v1.0.apk', 'downloads/UniServe-v1.0.apk', 15675520, '89B03C5FF2A97E5926F381FCD619E0D86BA1F664DEEAFE710087DBD07432CE53', 'Official Production Release for Merchants and Customers.', TRUE, 14)");
                }
            } else {
                self::runSchemaMigration($pdo, $dbType);
            }
        } catch (Throwable $e) {
            error_log('createMissingTables error: ' . $e->getMessage());
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
