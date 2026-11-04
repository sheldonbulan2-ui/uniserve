<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/Response.php';

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
        $apkFile = file_exists(__DIR__ . '/../../../downloads/uniserve-v1.0.apk')
            ? __DIR__ . '/../../../downloads/uniserve-v1.0.apk'
            : __DIR__ . '/../../../downloads/UniServe-v1.0.apk';
        
        $actualSize = file_exists($apkFile) ? filesize($apkFile) : 11970508;
        $actualSha256 = file_exists($apkFile) ? hash_file('sha256', $apkFile) : 'FFDDE9A723BA696B999F98661E0D7C8845A8760B573E1358B7D6308EE9BB6F1C';

        try {
            $stmt = $this->db->query("SELECT version_name, version_code, file_name, file_path, file_size_bytes, sha256_checksum, release_notes, download_count, created_at 
                FROM apk_releases WHERE is_latest = TRUE OR CAST(is_latest AS TEXT) = '1' ORDER BY id DESC LIMIT 1");
            $release = $stmt->fetch();
        } catch (Throwable) {
            $release = null;
        }

        if (!$release) {
            $release = [
                'version_name' => 'v1.0.0',
                'version_code' => 1,
                'file_name' => 'UniServe-v1.0.apk',
                'file_path' => 'downloads/UniServe-v1.0.apk',
                'file_size_bytes' => $actualSize,
                'sha256_checksum' => $actualSha256,
                'release_notes' => 'Official Production Release with Material Design 3 Merchant & Customer UI.',
                'download_count' => 18,
                'created_at' => date('Y-m-d H:i:s')
            ];
        } else {
            // Keep synchronized with actual disk build
            $release['file_size_bytes'] = $actualSize;
            $release['sha256_checksum'] = $actualSha256;
        }

        $release['file_size_formatted'] = round((int)$release['file_size_bytes'] / (1024 * 1024), 2) . ' MB';
        $release['download_url'] = '/download.php';

        Response::success('Latest APK release metadata.', $release);
    }
}
