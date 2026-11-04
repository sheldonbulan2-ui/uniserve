<?php
/**
 * UniServe Enterprise Admin — APK Release & Distribution Manager
 */
$pageTitle = "Android APK Release Manager";
$pageSubtitle = "Upload, verify, and publish official production .apk builds with auto-computed SHA-256 integrity";
$activeNav = "apk_manager";

require_once __DIR__ . '/includes/admin_header.php';

$message = '';
$messageType = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $versionName = trim($_POST['version_name'] ?? '');
    $versionCode = (int)($_POST['version_code'] ?? 1);
    $releaseNotes = trim($_POST['release_notes'] ?? '');

    if ($versionName === '' || !isset($_FILES['apk_file'])) {
        $message = 'Version name and an APK file are required.';
    } elseif ($_FILES['apk_file']['error'] !== UPLOAD_ERR_OK) {
        $message = 'File upload error code: ' . $_FILES['apk_file']['error'];
    } else {
        $ext = strtolower(pathinfo($_FILES['apk_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'apk') {
            $message = 'Invalid file type. Only .apk files are accepted.';
        } else {
            $destDir = __DIR__ . '/downloads';
            if (!is_dir($destDir)) {
                @mkdir($destDir, 0755, true);
            }

            $sanitizedVersion = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $versionName);
            $fileName = "UniServe-{$sanitizedVersion}.apk";
            $destPath = $destDir . '/' . $fileName;

            if (move_uploaded_file($_FILES['apk_file']['tmp_name'], $destPath)) {
                $fileSizeBytes = filesize($destPath);
                $sha256 = hash_file('sha256', $destPath);
                $relPath = 'downloads/' . $fileName;

                // Also overwrite downloads/UniServe-v1.0.apk for standard landing page download
                @copy($destPath, $destDir . '/UniServe-v1.0.apk');

                $pdo->exec('UPDATE apk_releases SET is_latest = 0');
                $stmt = $pdo->prepare('INSERT INTO apk_releases (version_name, version_code, file_name, file_path, file_size_bytes, sha256_checksum, release_notes, is_latest, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)');
                $stmt->execute([$versionName, $versionCode, $fileName, $relPath, $fileSizeBytes, $sha256, $releaseNotes, $_SESSION['user_id']]);

                $message = "APK {$versionName} successfully uploaded and published!";
                $messageType = 'success';
            } else {
                $message = 'Failed to move uploaded APK into destination directory.';
            }
        }
    }
}

// Fetch all releases
$stmt = $pdo->query('SELECT a.*, u.full_name AS uploaded_by_name FROM apk_releases a LEFT JOIN users u ON a.uploaded_by = u.id ORDER BY a.id DESC');
$releases = $stmt ? $stmt->fetchAll() : [];
?>

<?php if ($message): ?>
    <div class="admin-alert alert-<?= $messageType ?>" style="margin-bottom: var(--space-6);">
        <span><?= htmlspecialchars($message) ?></span>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: var(--space-6); margin-bottom: var(--space-8);">
    <!-- Upload Card -->
    <div class="card">
        <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: var(--space-4);">Publish New Build</h3>
        <form method="POST" action="apk_manager.php" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label" for="version_name">Version Name (e.g., v1.0.1)</label>
                <input class="form-control" type="text" id="version_name" name="version_name" required placeholder="v1.0.1">
            </div>

            <div class="form-group">
                <label class="form-label" for="version_code">Version Code</label>
                <input class="form-control" type="number" id="version_code" name="version_code" value="2" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="apk_file">Select .apk File</label>
                <input class="form-control" type="file" id="apk_file" name="apk_file" accept=".apk" required style="padding: 6px;">
            </div>

            <div class="form-group">
                <label class="form-label" for="release_notes">Release Notes</label>
                <textarea class="form-control" id="release_notes" name="release_notes" rows="3" placeholder="Performance improvements and bug fixes."></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: var(--space-2);">
                Publish & Distribute Build &rarr;
            </button>
        </form>
    </div>

    <!-- Active Release Stats -->
    <div class="card">
        <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: var(--space-4);">Published Releases</h3>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>File Name</th>
                        <th>Size</th>
                        <th>SHA-256 Checksum</th>
                        <th>Downloads</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($releases as $rel): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($rel['version_name']) ?></strong> (code <?= (int)$rel['version_code'] ?>)</td>
                            <td><?= htmlspecialchars($rel['file_name']) ?></td>
                            <td><?= round((int)$rel['file_size_bytes'] / (1024 * 1024), 2) ?> MB</td>
                            <td><code style="font-size: 0.6875rem; color: var(--color-cyan);"><?= substr((string)$rel['sha256_checksum'], 0, 16) ?>...</code></td>
                            <td><strong><?= (int)$rel['download_count'] ?></strong></td>
                            <td>
                                <?php if ($rel['is_latest']): ?>
                                    <span class="badge badge-success">Latest Live</span>
                                <?php else: ?>
                                    <span class="badge badge-info">Archived</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
