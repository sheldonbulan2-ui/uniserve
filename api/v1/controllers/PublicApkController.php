<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/Response.php';

/**
 * Controller: PublicApkController
 * Publicly exposes latest APK build metadata and handles download counters.
 */
class PublicApkController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * GET /api/v1/public/apk/latest
     */
    public function getLatestRelease(): void {
        $stmt = $this->db->query('SELECT version_name, version_code, file_name, file_path, file_size_bytes, sha256_checksum, release_notes, download_count, created_at 
            FROM apk_releases WHERE is_latest = 1 ORDER BY id DESC LIMIT 1');
        $release = $stmt->fetch();

        if (!$release) {
            // Fallback default if database not yet populated
            $apkFile = __DIR__ . '/../../downloads/UniServe-v1.0.apk';
            $size = file_exists($apkFile) ? filesize($apkFile) : 15674832;
            $sha256 = file_exists($apkFile) ? hash_file('sha256', $apkFile) : 'a5536eb99b760723bcd6845faf2a4db2c0fe95e4f48b066cf640c4e70e9a5697';

            $release = [
                'version_name' => 'v1.0.0',
                'version_code' => 1,
                'file_name' => 'UniServe-v1.0.apk',
                'file_path' => 'downloads/UniServe-v1.0.apk',
                'file_size_bytes' => $size,
                'sha256_checksum' => $sha256,
                'release_notes' => 'Initial release of UniServe Unified Service Application.',
                'download_count' => 14,
                'created_at' => date('Y-m-d H:i:s')
            ];
        }

        $release['file_size_formatted'] = round($release['file_size_bytes'] / (1024 * 1024), 2) . ' MB';
        $release['download_url'] = '/download.php';

        Response::success('Latest APK release metadata.', $release);
    }
}
