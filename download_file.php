<?php
require_once 'config/database.php';
require_once 'config/functions.php';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    die('معرف التطبيق غير صالح');
}

try {
    $db = getDatabaseConnection();
    
    // الحصول على معلومات التطبيق
    $stmt = $db->prepare("SELECT * FROM apps WHERE id = ? AND is_active = 1");
    $stmt->execute([$id]);
    $app = $stmt->fetch();
    
    if (!$app) {
        die('التطبيق غير موجود');
    }
    
    if (empty($app['file_path']) || !file_exists($app['file_path'])) {
        die('ملف التطبيق غير متوفر');
    }
    
    // زيادة عدد التحميلات
    $update_stmt = $db->prepare("UPDATE apps SET downloads = downloads + 1 WHERE id = ?");
    $update_stmt->execute([$id]);
    
    // تسجيل التحميل
    $download_stmt = $db->prepare("INSERT INTO downloads (app_id, user_ip, user_agent, referrer) VALUES (?, ?, ?, ?)");
    $download_stmt->execute([
        $id,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_SERVER['HTTP_REFERER'] ?? ''
    ]);
    
    // إعدادات التحميل
    $file_path = $app['file_path'];
    $file_name = $app['file_name'] ?: basename($file_path);
    $file_size = filesize($file_path);
    
    // إرسال رؤوس التحميل
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . $file_size);
    
    // تنظيف المخزن المؤقت
    ob_clean();
    flush();
    
    // قراءة وإرسال الملف
    readfile($file_path);
    exit;
    
} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    die('حدث خطأ أثناء التحميل');
}
?>