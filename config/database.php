<?php
// Ensure output buffering is active so headers and sessions can always be sent cleanly
if (!ob_get_level()) {
    ob_start();
}

/**
 * UniServe Universal Database Configuration
 * Supports both local MySQL/MariaDB (via mysqli) and Supabase/Render PostgreSQL (via PDO PGSQL).
 */

// 1. Detect Environment Variables (Supabase / Render / Cloud)
$dbUrl = getenv("DATABASE_URL") ?: getenv("SUPABASE_DB_URL") ?: null;

$dbHost = getenv("DB_HOST") ?: "localhost";
$dbUser = getenv("DB_USER") ?: "root";
$dbPass = getenv("DB_PASS") !== false ? getenv("DB_PASS") : (getenv("DB_PASSWORD") !== false ? getenv("DB_PASSWORD") : "");
$dbName = getenv("DB_NAME") ?: "uniserve_db";
$dbPort = getenv("DB_PORT") ?: null;
$dbType = getenv("DB_TYPE") ?: null;

if ($dbUrl) {
    $parsed = parse_url($dbUrl);
    if ($parsed) {
        $scheme = $parsed["scheme"] ?? "";
        if (strpos($scheme, "postgres") !== false || strpos($scheme, "pgsql") !== false) {
            $dbType = "pgsql";
        } elseif (strpos($scheme, "mysql") !== false) {
            $dbType = "mysql";
        }
        $dbHost = $parsed["host"] ?? $dbHost;
        $dbPort = $parsed["port"] ?? ($dbType === "pgsql" ? 5432 : 3306);
        $dbUser = isset($parsed["user"]) ? urldecode($parsed["user"]) : $dbUser;
        $dbPass = isset($parsed["pass"]) ? urldecode($parsed["pass"]) : $dbPass;
        $dbName = isset($parsed["path"]) ? ltrim($parsed["path"], "/") : $dbName;
    }
}

// Automatically treat port 5432 or supabase hostnames as PostgreSQL
if (!$dbType) {
    if ((int)$dbPort === 5432 || (is_string($dbHost) && strpos($dbHost, "supabase") !== false)) {
        $dbType = "pgsql";
    } else {
        $dbType = "mysql";
    }
}

// --------------------------------------------------------------------------
// PDO-to-MySQLi Compatibility Layer (for Supabase PostgreSQL)
// --------------------------------------------------------------------------
if ($dbType === "pgsql") {
    class MysqlCompatResult {
        private $rows;
        private $index = 0;
        public $num_rows = 0;

        public function __construct(array $rows) {
            $this->rows = $rows;
            $this->num_rows = count($rows);
        }

        public function fetch_assoc() {
            if ($this->index < $this->num_rows) {
                return $this->rows[$this->index++];
            }
            return null;
        }

        public function fetch_all($mode = MYSQLI_ASSOC) {
            return $this->rows;
        }

        public function free() {
            $this->rows = [];
            $this->num_rows = 0;
        }

        public function close() {
            $this->free();
        }
    }

    class MysqlCompatStmt {
        private $pdo;
        private $pdoStmt;
        private $conn;
        private $boundParams = [];
        private $types = "";
        private $executedRows = null;
        private $rawQuery = "";
        public $num_rows = 0;
        public $insert_id = 0;
        public $affected_rows = 0;
        public $error = "";
        public $errno = 0;

        public function __construct(PDO $pdo, PDOStatement $pdoStmt, $conn = null, $rawQuery = "") {
            $this->pdo = $pdo;
            $this->pdoStmt = $pdoStmt;
            $this->conn = $conn;
            $this->rawQuery = $rawQuery;
        }

        public function bind_param($types, &...$vars) {
            $this->types = $types;
            $this->boundParams = [];
            foreach ($vars as $i => &$v) {
                $this->boundParams[$i] = &$v;
            }
            return true;
        }

        public function execute() {
            try {
                $this->error = "";
                $this->errno = 0;

                $params = [];
                $len = strlen($this->types);
                for ($i = 0; $i < count($this->boundParams); $i++) {
                    $t = $i < $len ? $this->types[$i] : "s";
                    $val = $this->boundParams[$i];
                    if ($val === null) {
                        $params[] = null;
                    } elseif ($t === "i") {
                        $params[] = (int) $val;
                    } elseif ($t === "d") {
                        $params[] = (float) $val;
                    } else {
                        $params[] = (string) $val;
                    }
                }

                $res = $this->pdoStmt->execute($params);
                $this->affected_rows = $this->pdoStmt->rowCount();

                // Only check for generated ID if this was an INSERT
                $trimmed = ltrim($this->rawQuery);
                if (stripos($trimmed, "INSERT") === 0) {
                    try {
                        $lastId = $this->pdo->lastInsertId();
                        if ($lastId) {
                            $this->insert_id = (int)$lastId;
                            if ($this->conn) {
                                $this->conn->insert_id = (int)$lastId;
                            }
                        }
                    } catch (Exception $e) {
                        try {
                            $lastId = $this->pdo->query("SELECT lastval()")->fetchColumn();
                            if ($lastId) {
                                $this->insert_id = (int)$lastId;
                                if ($this->conn) {
                                    $this->conn->insert_id = (int)$lastId;
                                }
                            }
                        } catch (Exception $e2) {}
                    }
                }

                return $res;
            } catch (Exception $e) {
                $this->error = $e->getMessage();
                $this->errno = (int) ($e->getCode() ?: 1);
                error_log("MysqlCompatStmt execute error: " . $e->getMessage());
                return false;
            }
        }

        public function store_result() {
            try {
                $this->executedRows = $this->pdoStmt->fetchAll(PDO::FETCH_ASSOC);
                $this->num_rows = count($this->executedRows);
                return true;
            } catch (Exception $e) {
                return false;
            }
        }

        public function get_result() {
            try {
                if ($this->executedRows !== null) {
                    $rows = $this->executedRows;
                    $this->executedRows = null;
                } else {
                    $rows = $this->pdoStmt->fetchAll(PDO::FETCH_ASSOC);
                }
                return new MysqlCompatResult($rows);
            } catch (Exception $e) {
                return new MysqlCompatResult([]);
            }
        }

        public function close() {
            $this->pdoStmt->closeCursor();
            return true;
        }
    }

    class MysqlCompatConnection {
        private $pdo;
        public $connect_error = null;
        public $error = "";
        public $errno = 0;
        public $insert_id = 0;

        public function __construct(PDO $pdo) {
            $this->pdo = $pdo;
        }

        public function prepare($query) {
            try {
                $this->error = "";
                $this->errno = 0;
                // Normalize any remaining backticks to PostgreSQL identifiers
                $cleanQuery = str_replace("`", "", $query);
                $stmt = $this->pdo->prepare($cleanQuery);
                return new MysqlCompatStmt($this->pdo, $stmt, $this, $cleanQuery);
            } catch (Exception $e) {
                $this->error = $e->getMessage();
                $this->errno = (int) ($e->getCode() ?: 1);
                error_log("MysqlCompatConnection prepare error: " . $e->getMessage());
                return false;
            }
        }

        public function query($query) {
            try {
                $this->error = "";
                $this->errno = 0;
                $cleanQuery = str_replace("`", "", $query);
                $pdoStmt = $this->pdo->query($cleanQuery);
                if (!$pdoStmt) {
                    return false;
                }
                
                $trimmed = ltrim($cleanQuery);
                if (stripos($trimmed, "INSERT") === 0) {
                    try {
                        $lastId = $this->pdo->lastInsertId();
                        if ($lastId) {
                            $this->insert_id = (int)$lastId;
                        }
                    } catch (Exception $e) {
                        try {
                            $lastId = $this->pdo->query("SELECT lastval()")->fetchColumn();
                            if ($lastId) {
                                $this->insert_id = (int)$lastId;
                            }
                        } catch (Exception $e2) {}
                    }
                }

                $rows = $pdoStmt->fetchAll(PDO::FETCH_ASSOC);
                return new MysqlCompatResult($rows);
            } catch (Exception $e) {
                $this->error = $e->getMessage();
                $this->errno = (int) ($e->getCode() ?: 1);
                error_log("MysqlCompatConnection query error: " . $e->getMessage());
                return false;
            }
        }

        public function real_escape_string($string) {
            $quoted = $this->pdo->quote((string)$string);
            return substr($quoted, 1, -1);
        }

        public function escape_string($string) {
            return $this->real_escape_string($string);
        }

        public function set_charset($charset) {
            return true;
        }

        public function begin_transaction() {
            return $this->pdo->beginTransaction();
        }

        public function commit() {
            return $this->pdo->commit();
        }

        public function rollback() {
            return $this->pdo->rollBack();
        }

        public function close() {
            $this->pdo = null;
            return true;
        }
    }

    try {
        $portPart = $dbPort ? ";port=" . (int)$dbPort : "";
        $dsn = "pgsql:host={$dbHost}{$portPart};dbname={$dbName};sslmode=require";
        
        $pdoOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
        $conn = new MysqlCompatConnection($pdo);
    } catch (PDOException $e) {
        die("Supabase / PostgreSQL connection failed: " . $e->getMessage());
    }

} else {
    // ----------------------------------------------------------------------
    // Standard MySQL / MariaDB connection (Local development / XAMPP)
    // ----------------------------------------------------------------------
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort ? (int)$dbPort : 3306);

    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");
}

// --------------------------------------------------------------------------
// Ensure Default Administrator Account is active with standard credentials
// --------------------------------------------------------------------------
try {
    if (isset($conn) && !$conn->connect_error) {
        $adminEmail = "admin@uniserve.com";
        $adminHash = '$2y$10$qCN028Kmx9ve1Z4lZelB5ejA.XCnyDxaRaW.ibMkzlKneazgErJYG'; // password123

        $admCheck = $conn->query("SELECT user_id, password, role FROM users WHERE email = '{$adminEmail}' LIMIT 1");
        if ($admCheck && $admCheck->num_rows > 0) {
            $adm = $admCheck->fetch_assoc();
            // If password doesn't match standard or role isn't admin, sync it
            if (!password_verify('password123', $adm['password']) || $adm['role'] !== 'admin') {
                $conn->query("UPDATE users SET password = '{$adminHash}', role = 'admin' WHERE user_id = " . (int)$adm['user_id']);
            }
        } else {
            $conn->query("INSERT INTO users (full_name, email, password, role) VALUES ('UniServe Administrator', '{$adminEmail}', '{$adminHash}', 'admin')");
        }
    }
} catch (Throwable $syncErr) {
    error_log("Admin credential sync notice: " . $syncErr->getMessage());
}
?>