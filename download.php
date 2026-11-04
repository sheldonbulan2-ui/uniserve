<?php
/**
 * UniServe APK Direct Download Controller
 * Serves the compiled Android APK with RFC-compliant headers.
 */

$apkPath = __DIR__ . '/downloads/uniserve-v1.0.apk';

if (file_exists($apkPath)) {
    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.android.package-archive');
    header('Content-Disposition: attachment; filename="UniServe-v1.0.apk"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($apkPath));

    readfile($apkPath);
    exit();
} else {
    header('Location: index.php?error=apk_unavailable');
    exit();
}
