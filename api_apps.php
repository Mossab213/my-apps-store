<?php
require_once '../config/database.php';
require_once '../config/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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
        case 'get':
            // الحصول على التطبيقات
            $category = $_GET['category'] ?? '';
            $search = $_GET['search'] ?? '';
            $page = intval($_GET['page'] ?? 1);
            $limit = intval($_GET['limit'] ?? 12);
            $offset = ($page - 1) * $limit;
            
            $query = "SELECT * FROM apps WHERE is_active = 1";
            $params = [];
            
            if (!empty($category) && $category !== 'all') {
                $query .= " AND category = ?";
                $params[] = $category;
            }
            
            if (!empty($search)) {
                $query .= " AND (name LIKE ? OR description LIKE ? OR developer LIKE ?)";
                $search_term = "%$search%";
                $params[] = $search_term;
                $params[] = $search_term;
                $params[] = $search_term;
            }
            
            // التطبيقات المميزة أولاً
            $query .= " ORDER BY is_featured DESC, downloads DESC, created_at DESC";
            
            // الحصول على إجمالي عدد التطبيقات
            $count_query = "SELECT COUNT(*) as total FROM (" . str_replace("SELECT *", "SELECT id", $query) . ") as count_table";
            $count_stmt = $db->prepare($count_query);
            $count_stmt->execute($params);
            $total = $count_stmt->fetch()['total'];
            
            // الحصول على التطبيقات مع التقسيم
            $query .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $apps = $stmt->fetchAll();
            
            // إضافة روابط التحميل
            foreach ($apps as &$app) {
                $app['download_url'] = SITE_URL . 'download.php?id=' . $app['id'];
                $app['image_url'] = !empty($app['image_path']) ? 
                    SITE_URL . 'assets/uploads/images/' . basename($app['image_path']) : 
                    'https://images.unsplash.com/photo-1551650975-87deedd944c3?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80';
            }
            
            $response['success'] = true;
            $response['data'] = [
                'apps' => $apps,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ];
            break;
            
        case 'get_by_id':
            $id = intval($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                $response['message'] = 'معرف التطبيق غير صالح';
                break;
            }
            
            $stmt = $db->prepare("SELECT * FROM apps WHERE id = ? AND is_active = 1");
            $stmt->execute([$id]);
            $app = $stmt->fetch();
            
            if ($app) {
                // زيادة عدد المشاهدات
                $update_stmt = $db->prepare("UPDATE apps SET views = views + 1 WHERE id = ?");
                $update_stmt->execute([$id]);
                
                // إضافة رابط التحميل
                $app['download_url'] = SITE_URL . 'download.php?id=' . $app['id'];
                $app['image_url'] = !empty($app['image_path']) ? 
                    SITE_URL . 'assets/uploads/images/' . basename($app['image_path']) : 
                    'https://images.unsplash.com/photo-1551650975-87deedd944c3?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80';
                
                $response['success'] = true;
                $response['data'] = $app;
            } else {
                $response['message'] = 'التطبيق غير موجود';
            }
            break;
            
        case 'add':
            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك بإضافة تطبيقات';
                break;
            }
            
            if (!checkRequestMethod('POST')) {
                $response['message'] = 'طريقة الطلب غير صحيحة';
                break;
            }
            
            $name = sanitize($_POST['name'] ?? '');
            $category = sanitize($_POST['category'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $version = sanitize($_POST['version'] ?? '');
            $size_mb = floatval($_POST['size_mb'] ?? 0);
            $developer = sanitize($_POST['developer'] ?? '');
            $os_requirements = sanitize($_POST['os_requirements'] ?? 'Windows 7 أو أحدث');
            $license_type = sanitize($_POST['license_type'] ?? 'مجاني');
            $website_url = sanitize($_POST['website_url'] ?? '');
            $whats_new = sanitize($_POST['whats_new'] ?? '');
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            
            // التحقق من البيانات
            if (empty($name) || empty($category) || empty($description) || empty($version) || $size_mb <= 0) {
                $response['message'] = 'يرجى ملء جميع الحقول المطلوبة';
                break;
            }
            
            // معالجة ملف التطبيق
            $file_data = null;
            if (isset($_FILES['app_file']) && $_FILES['app_file']['error'] === UPLOAD_ERR_OK) {
                $file_data = uploadFile($_FILES['app_file'], 'app');
                if (!$file_data['success']) {
                    $response['message'] = $file_data['message'];
                    break;
                }
            }
            
            // معالجة صورة التطبيق
            $image_data = null;
            if (isset($_FILES['app_image']) && $_FILES['app_image']['error'] === UPLOAD_ERR_OK) {
                $image_data = uploadFile($_FILES['app_image'], 'image');
                if (!$image_data['success']) {
                    $response['message'] = $image_data['message'];
                    break;
                }
            }
            
            // إدخال التطبيق في قاعدة البيانات
            $stmt = $db->prepare("
                INSERT INTO apps (
                    name, category, description, version, size_mb, developer, 
                    os_requirements, license_type, website_url, whats_new, 
                    file_name, file_path, image_name, image_path, is_featured
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $name, $category, $description, $version, $size_mb, $developer,
                $os_requirements, $license_type, $website_url, $whats_new,
                $file_data['original_name'] ?? null,
                $file_data['file_path'] ?? null,
                $image_data['original_name'] ?? null,
                $image_data['file_path'] ?? null,
                $is_featured
            ]);
            
            $app_id = $db->lastInsertId();
            
            // تسجيل النشاط
            $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $log_stmt->execute([
                $_SESSION['user_id'],
                'app_added',
                'تم إضافة تطبيق جديد: ' . $name,
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $response['success'] = true;
            $response['message'] = 'تم إضافة التطبيق بنجاح';
            $response['data'] = ['app_id' => $app_id];
            break;
            
        case 'update':
            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك بتعديل التطبيقات';
                break;
            }
            
            if (!checkRequestMethod('POST')) {
                $response['message'] = 'طريقة الطلب غير صحيحة';
                break;
            }
            
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                $response['message'] = 'معرف التطبيق غير صالح';
                break;
            }
            
            // التحقق من وجود التطبيق
            $check_stmt = $db->prepare("SELECT id FROM apps WHERE id = ?");
            $check_stmt->execute([$id]);
            if (!$check_stmt->fetch()) {
                $response['message'] = 'التطبيق غير موجود';
                break;
            }
            
            $name = sanitize($_POST['name'] ?? '');
            $category = sanitize($_POST['category'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $version = sanitize($_POST['version'] ?? '');
            $size_mb = floatval($_POST['size_mb'] ?? 0);
            $developer = sanitize($_POST['developer'] ?? '');
            $os_requirements = sanitize($_POST['os_requirements'] ?? '');
            $license_type = sanitize($_POST['license_type'] ?? '');
            $website_url = sanitize($_POST['website_url'] ?? '');
            $whats_new = sanitize($_POST['whats_new'] ?? '');
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // بناء استعلام التحديث
            $update_fields = [];
            $params = [];
            
            $update_fields[] = "name = ?"; $params[] = $name;
            $update_fields[] = "category = ?"; $params[] = $category;
            $update_fields[] = "description = ?"; $params[] = $description;
            $update_fields[] = "version = ?"; $params[] = $version;
            $update_fields[] = "size_mb = ?"; $params[] = $size_mb;
            $update_fields[] = "developer = ?"; $params[] = $developer;
            $update_fields[] = "os_requirements = ?"; $params[] = $os_requirements;
            $update_fields[] = "license_type = ?"; $params[] = $license_type;
            $update_fields[] = "website_url = ?"; $params[] = $website_url;
            $update_fields[] = "whats_new = ?"; $params[] = $whats_new;
            $update_fields[] = "is_featured = ?"; $params[] = $is_featured;
            $update_fields[] = "is_active = ?"; $params[] = $is_active;
            
            // تحديث ملف التطبيق إذا تم رفعه
            if (isset($_FILES['app_file']) && $_FILES['app_file']['error'] === UPLOAD_ERR_OK) {
                $file_data = uploadFile($_FILES['app_file'], 'app');
                if ($file_data['success']) {
                    // حذف الملف القديم إن وجد
                    $old_stmt = $db->prepare("SELECT file_path FROM apps WHERE id = ?");
                    $old_stmt->execute([$id]);
                    $old_file = $old_stmt->fetch()['file_path'];
                    if ($old_file && file_exists($old_file)) {
                        deleteFile($old_file);
                    }
                    
                    $update_fields[] = "file_name = ?"; $params[] = $file_data['original_name'];
                    $update_fields[] = "file_path = ?"; $params[] = $file_data['file_path'];
                }
            }
            
            // تحديث صورة التطبيق إذا تم رفعها
            if (isset($_FILES['app_image']) && $_FILES['app_image']['error'] === UPLOAD_ERR_OK) {
                $image_data = uploadFile($_FILES['app_image'], 'image');
                if ($image_data['success']) {
                    // حذف الصورة القديمة إن وجدت
                    $old_stmt = $db->prepare("SELECT image_path FROM apps WHERE id = ?");
                    $old_stmt->execute([$id]);
                    $old_image = $old_stmt->fetch()['image_path'];
                    if ($old_image && file_exists($old_image)) {
                        deleteFile($old_image);
                    }
                    
                    $update_fields[] = "image_name = ?"; $params[] = $image_data['original_name'];
                    $update_fields[] = "image_path = ?"; $params[] = $image_data['file_path'];
                }
            }
            
            $params[] = $id; // للمكان في WHERE
            
            $update_query = "UPDATE apps SET " . implode(", ", $update_fields) . ", updated_at = NOW() WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute($params);
            
            // تسجيل النشاط
            $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $log_stmt->execute([
                $_SESSION['user_id'],
                'app_updated',
                'تم تحديث التطبيق: ' . $name,
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $response['success'] = true;
            $response['message'] = 'تم تحديث التطبيق بنجاح';
            break;
            
        case 'delete':
            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك بحذف التطبيقات';
                break;
            }
            
            $id = intval($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                $response['message'] = 'معرف التطبيق غير صالح';
                break;
            }
            
            // الحصول على معلومات التطبيق قبل الحذف
            $stmt = $db->prepare("SELECT name, file_path, image_path FROM apps WHERE id = ?");
            $stmt->execute([$id]);
            $app = $stmt->fetch();
            
            if (!$app) {
                $response['message'] = 'التطبيق غير موجود';
                break;
            }
            
            // حذف الملفات المرتبطة
            if (!empty($app['file_path']) && file_exists($app['file_path'])) {
                deleteFile($app['file_path']);
            }
            
            if (!empty($app['image_path']) && file_exists($app['image_path'])) {
                deleteFile($app['image_path']);
            }
            
            // حذف التطبيق من قاعدة البيانات
            $delete_stmt = $db->prepare("DELETE FROM apps WHERE id = ?");
            $delete_stmt->execute([$id]);
            
            // تسجيل النشاط
            $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $log_stmt->execute([
                $_SESSION['user_id'],
                'app_deleted',
                'تم حذف التطبيق: ' . $app['name'],
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $response['success'] = true;
            $response['message'] = 'تم حذف التطبيق بنجاح';
            break;
            
        case 'download':
            $id = intval($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                $response['message'] = 'معرف التطبيق غير صالح';
                break;
            }
            
            // الحصول على معلومات التطبيق
            $stmt = $db->prepare("SELECT * FROM apps WHERE id = ? AND is_active = 1");
            $stmt->execute([$id]);
            $app = $stmt->fetch();
            
            if (!$app) {
                $response['message'] = 'التطبيق غير موجود';
                break;
            }
            
            if (empty($app['file_path']) || !file_exists($app['file_path'])) {
                $response['message'] = 'ملف التطبيق غير متوفر';
                break;
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
            
            $response['success'] = true;
            $response['message'] = 'بدأ تحميل التطبيق';
            $response['data'] = [
                'download_url' => SITE_URL . 'download_file.php?id=' . $id,
                'app_name' => $app['name']
            ];
            break;
            
        case 'get_categories':
            // الحصول على جميع الفئات
            $stmt = $db->query("SELECT DISTINCT category, COUNT(*) as count FROM apps WHERE is_active = 1 GROUP BY category ORDER BY count DESC");
            $categories = $stmt->fetchAll();
            
            $response['success'] = true;
            $response['data'] = $categories;
            break;
            
        case 'get_stats':
            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك بالوصول إلى الإحصائيات';
                break;
            }
            
            $stats = getDashboardStats();
            $response['success'] = true;
            $response['data'] = $stats;
            break;
            
        default:
            $response['message'] = 'عملية غير معروفة';
    }
    
} catch (Exception $e) {
    error_log("API Error (apps.php): " . $e->getMessage());
    $response['message'] = 'حدث خطأ في النظام';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>