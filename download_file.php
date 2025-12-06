<?php
require_once 'config_database.php';
require_once 'config_functions.php';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    exit('معرف التطبيق غير صالح');
}

try {
    $db = getDatabaseConnection();

    // الحصول على معلومات التطبيق
    $stmt = $db->prepare("SELECT * FROM apps WHERE id = ? AND is_active = 1");
    $stmt->execute([$id]);
    $app = $stmt->fetch();

    if (!$app) {
        header('HTTP/1.1 404 Not Found');
        exit('التطبيق غير موجود');
    }

    $file_path = $app['file_path'];

    // منع Path Traversal
    $real_base = realpath(__DIR__ . "/assets/uploads/apps/");
    $real_file = realpath($file_path);

    if (!$real_file || strpos($real_file, $real_base) !== 0) {
        header('HTTP/1.1 403 Forbidden');
        exit('وصول غير مصرح');
    }

    // التحقق من وجود الملف
    if (!file_exists($real_file) || !is_readable($real_file)) {
        header('HTTP/1.1 404 Not Found');
        exit('ملف التطبيق غير متوفر');
    }

    // زيادة التحميلات
    $update_stmt = $db->prepare("UPDATE apps SET downloads = downloads + 1 WHERE id = ?");
    $update_stmt->execute([$id]);

    // تسجيل التحميل
    $download_stmt = $db->prepare("
        INSERT INTO downloads (app_id, user_ip, user_agent, referrer)
        VALUES (?, ?, ?, ?)
    ");
    $download_stmt->execute([
        $id,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_SERVER['HTTP_REFERER'] ?? ''
    ]);

    // إعدادات الملف
    $file_name = $app['file_name'] ?: basename($real_file);
    $file_size = filesize($real_file);

    // الحد المسموح (10MB لاستضافات مجانية)
    $max_file_size = 10 * 1024 * 1024;
    if ($file_size > $max_file_size) {
        header('HTTP/1.1 413 Payload Too Large');
        exit('حجم الملف أكبر من الحد المسموح به (10MB)');
    }

    // تنظيف المخرجات بدون أخطاء
    if (ob_get_level()) {
        ob_end_clean();
    }

    // إرسال رؤوس التحميل
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$file_name.'"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    // إرسال الملف بشكل آمن
    $fp = fopen($real_file, 'rb');
    while (!feof($fp)) {
        echo fread($fp, 8192);
        flush();
    }
    fclose($fp);

    exit;

} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('حدث خطأ أثناء التحميل');
}
?>
