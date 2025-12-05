<?php
require_once '../config/database.php';
require_once '../config/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $db = getDatabaseConnection();
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_all':
            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك بمشاهدة الإعدادات';
                break;
            }
            
            $group = $_GET['group'] ?? '';
            
            $query = "SELECT * FROM settings";
            $params = [];
            
            if (!empty($group)) {
                $query .= " WHERE setting_group = ?";
                $params[] = $group;
            }
            
            $query .= " ORDER BY setting_group, setting_key";
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $settings = $stmt->fetchAll();
            
            // تنظيم الإعدادات حسب المجموعات
            $grouped_settings = [];
            foreach ($settings as $setting) {
                $group = $setting['setting_group'];
                if (!isset($grouped_settings[$group])) {
                    $grouped_settings[$group] = [];
                }
                $grouped_settings[$group][] = $setting;
            }
            
            $response['success'] = true;
            $response['data'] = $grouped_settings;
            break;
            
        case 'get':
            $key = $_GET['key'] ?? '';
            
            if (empty($key)) {
                $response['message'] = 'مفتاح الإعداد مطلوب';
                break;
            }
            
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            
            $response['success'] = true;
            $response['data'] = $result ? $result['setting_value'] : null;
            break;
            
        case 'update':
            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك بتعديل الإعدادات';
                break;
            }
            
            if (!checkRequestMethod('POST')) {
                $response['message'] = 'طريقة الطلب غير صحيحة';
                break;
            }
            
            $settings_data = $_POST['settings'] ?? [];
            
            if (empty($settings_data)) {
                $response['message'] = 'لا توجد إعدادات للتحديث';
                break;
            }
            
            // التحقق من البيانات وترميزها
            $validated_settings = [];
            foreach ($settings_data as $key => $value) {
                $validated_settings[sanitize($key)] = sanitize($value);
            }
            
            $db->beginTransaction();
            
            try {
                foreach ($validated_settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO settings (setting_key, setting_value) 
                        VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE setting_value = ?
                    ");
                    $stmt->execute([$key, $value, $value]);
                }
                
                $db->commit();
                
                // تسجيل النشاط
                $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $log_stmt->execute([
                    $_SESSION['user_id'],
                    'settings_updated',
                    'تم تحديث الإعدادات',
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                $response['success'] = true;
                $response['message'] = 'تم تحديث الإعدادات بنجاح';
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'update_site':
            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك بتعديل إعدادات الموقع';
                break;
            }
            
            if (!checkRequestMethod('POST')) {
                $response['message'] = 'طريقة الطلب غير صحيحة';
                break;
            }
            
            $site_name = sanitize($_POST['site_name'] ?? '');
            $site_description = sanitize($_POST['site_description'] ?? '');
            $admin_email = sanitize($_POST['admin_email'] ?? '');
            $contact_email = sanitize($_POST['contact_email'] ?? '');
            
            if (empty($site_name) || empty($admin_email)) {
                $response['message'] = 'يرجى ملء الحقول المطلوبة';
                break;
            }
            
            if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = 'بريد المشرف الإلكتروني غير صحيح';
                break;
            }
            
            if (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = 'بريد الاتصال الإلكتروني غير صحيح';
                break;
            }
            
            $db->beginTransaction();
            
            try {
                $settings = [
                    'site_name' => $site_name,
                    'site_description' => $site_description,
                    'admin_email' => $admin_email,
                    'contact_email' => $contact_email
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO settings (setting_key, setting_value, setting_group) 
                        VALUES (?, ?, 'general') 
                        ON DUPLICATE KEY UPDATE setting_value = ?
                    ");
                    $stmt->execute([$key, $value, $value]);
                }
                
                $db->commit();
                
                // تسجيل النشاط
                $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $log_stmt->execute([
                    $_SESSION['user_id'],
                    'site_settings_updated',
                    'تم تحديث إعدادات الموقع',
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                $response['success'] = true;
                $response['message'] = 'تم تحديث إعدادات الموقع بنجاح';
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'update_email':
            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك بتعديل إعدادات البريد';
                break;
            }
            
            if (!checkRequestMethod('POST')) {
                $response['message'] = 'طريقة الطلب غير صحيحة';
                break;
            }
            
            $smtp_host = sanitize($_POST['smtp_host'] ?? '');
            $smtp_port = intval($_POST['smtp_port'] ?? 587);
            $smtp_username = sanitize($_POST['smtp_username'] ?? '');
            $smtp_password = $_POST['smtp_password'] ?? '';
            $smtp_encryption = sanitize($_POST['smtp_encryption'] ?? 'tls');
            
            if (empty($smtp_host) || empty($smtp_username)) {
                $response['message'] = 'يرجى ملء الحقول المطلوبة';
                break;
            }
            
            if (!filter_var($smtp_username, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = 'بريد SMTP الإلكتروني غير صحيح';
                break;
            }
            
            $db->beginTransaction();
            
            try {
                $settings = [
                    'smtp_host' => $smtp_host,
                    'smtp_port' => $smtp_port,
                    'smtp_username' => $smtp_username,
                    'smtp_encryption' => $smtp_encryption
                ];
                
                // إذا تم إدخال كلمة مرور جديدة
                if (!empty($smtp_password)) {
                    $settings['smtp_password'] = $smtp_password;
                }
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO settings (setting_key, setting_value, setting_group) 
                        VALUES (?, ?, 'email') 
                        ON DUPLICATE KEY UPDATE setting_value = ?
                    ");
                    $stmt->execute([$key, $value, $value]);
                }
                
                $db->commit();
                
                // تسجيل النشاط
                $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $log_stmt->execute([
                    $_SESSION['user_id'],
                    'email_settings_updated',
                    'تم تحديث إعدادات البريد',
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                $response['success'] = true;
                $response['message'] = 'تم تحديث إعدادات البريد بنجاح';
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'update_upload':
            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك بتعديل إعدادات التحميل';
                break;
            }
            
            if (!checkRequestMethod('POST')) {
                $response['message'] = 'طريقة الطلب غير صحيحة';
                break;
            }
            
            $max_upload_size_app = intval($_POST['max_upload_size_app'] ?? 500);
            $max_upload_size_image = intval($_POST['max_upload_size_image'] ?? 5);
            $allowed_app_extensions = sanitize($_POST['allowed_app_extensions'] ?? 'exe,msi,zip,rar,7z');
            $allowed_image_extensions = sanitize($_POST['allowed_image_extensions'] ?? 'jpg,jpeg,png,gif,webp');
            
            if ($max_upload_size_app <= 0 || $max_upload_size_image <= 0) {
                $response['message'] = 'قيود الحجم يجب أن تكون أكبر من صفر';
                break;
            }
            
            $db->beginTransaction();
            
            try {
                $settings = [
                    'max_upload_size_app' => $max_upload_size_app,
                    'max_upload_size_image' => $max_upload_size_image,
                    'allowed_app_extensions' => $allowed_app_extensions,
                    'allowed_image_extensions' => $allowed_image_extensions
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO settings (setting_key, setting_value, setting_group) 
                        VALUES (?, ?, 'uploads') 
                        ON DUPLICATE KEY UPDATE setting_value = ?
                    ");
                    $stmt->execute([$key, $value, $value]);
                }
                
                $db->commit();
                
                // تسجيل النشاط
                $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $log_stmt->execute([
                    $_SESSION['user_id'],
                    'upload_settings_updated',
                    'تم تحديث إعدادات التحميل',
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                $response['success'] = true;
                $response['message'] = 'تم تحديث إعدادات التحميل بنجاح';
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'get_site_info':
            // الحصول على معلومات الموقع للعرض العام
            $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'general'");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $response['success'] = true;
            $response['data'] = [
                'site_name' => $settings['site_name'] ?? 'متجر تطبيقاتي',
                'site_description' => $settings['site_description'] ?? '',
                'contact_email' => $settings['contact_email'] ?? '',
                'admin_email' => $settings['admin_email'] ?? ''
            ];
            break;
            
        default:
            $response['message'] = 'عملية غير معروفة';
    }
    
} catch (Exception $e) {
    error_log("API Error (settings.php): " . $e->getMessage());
    $response['message'] = 'حدث خطأ في النظام';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>