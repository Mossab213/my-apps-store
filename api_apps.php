<?php
require_once 'config_database.php';
require_once 'config_functions.php';

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

        /* ================================
           GET APPS LIST
        ================================= */
        case 'get_all':
        case 'get':

            $category = $_GET['category'] ?? '';
            $search   = $_GET['search'] ?? '';
            $page     = max(1, intval($_GET['page'] ?? 1));
            $limit    = intval($_GET['limit'] ?? 12);
            $offset   = ($page - 1) * $limit;

            $base_query = "FROM apps WHERE is_active = 1";
            $params = [];

            if (!empty($category) && $category !== 'all') {
                $base_query .= " AND category = ?";
                $params[] = $category;
            }

            if (!empty($search)) {
                $base_query .= " AND (name LIKE ? OR description LIKE ? OR developer LIKE ?)";
                $search_term = "%$search%";
                $params[] = $search_term;
                $params[] = $search_term;
                $params[] = $search_term;
            }

            // COUNT WITHOUT ORDER
            $count_stmt = $db->prepare("SELECT COUNT(*) as total $base_query");
            $count_stmt->execute($params);
            $total = $count_stmt->fetch()['total'];

            // MAIN QUERY WITH ORDER
            $query = "SELECT * $base_query ORDER BY is_featured DESC, downloads DESC, created_at DESC LIMIT ? OFFSET ?";
            $params2 = array_merge($params, [$limit, $offset]);

            $stmt = $db->prepare($query);
            $stmt->execute($params2);
            $apps = $stmt->fetchAll();

            foreach ($apps as &$app) {
                $app['image_url'] = !empty($app['image_path'])
                    ? SITE_URL . 'assets/uploads/images/' . basename($app['image_path'])
                    : 'https://images.unsplash.com/photo-1551650975-87deedd944c3?auto=format&fit=crop&w=600&q=80';

                $app['download_url'] = SITE_URL . "download.php?id=" . $app['id'];
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

        /* ================================
           GET APP BY ID
        ================================= */
        case 'get_by_id':

            $id = intval($_GET['id'] ?? 0);

            if ($id <= 0) {
                $response['message'] = 'معرف التطبيق غير صالح';
                break;
            }

            $stmt = $db->prepare("SELECT * FROM apps WHERE id = ? AND is_active = 1");
            $stmt->execute([$id]);
            $app = $stmt->fetch();

            if (!$app) {
                $response['message'] = 'التطبيق غير موجود';
                break;
            }

            $app['image_url'] = !empty($app['image_path'])
                ? SITE_URL . 'assets/uploads/images/' . basename($app['image_path'])
                : 'https://images.unsplash.com/photo-1551650975-87deedd944c3?auto=format&fit=crop&w=600&q=80';

            $app['download_url'] = SITE_URL . "download.php?id=" . $app['id'];

            $response['success'] = true;
            $response['data'] = $app;
            break;

        /* ================================
           ADD NEW APP
        ================================= */
        case 'add':

            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك';
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

            if (!$name || !$category || !$description || !$version || $size_mb <= 0) {
                $response['message'] = 'يرجى ملء جميع الحقول';
                break;
            }

            $file_data = null;
            if (isset($_FILES['app_file']) && $_FILES['app_file']['error'] === UPLOAD_ERR_OK) {
                $file_data = uploadFile($_FILES['app_file'], 'app');
                if (!$file_data['success']) {
                    $response['message'] = $file_data['message'];
                    break;
                }
            }

            $image_data = null;
            if (isset($_FILES['app_image']) && $_FILES['app_image']['error'] === UPLOAD_ERR_OK) {
                $image_data = uploadFile($_FILES['app_image'], 'image');
                if (!$image_data['success']) {
                    $response['message'] = $image_data['message'];
                    break;
                }
            }

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

            $response['success'] = true;
            $response['message'] = 'تم إضافة التطبيق بنجاح';
            break;

        /* ================================
           UPDATE APP
        ================================= */
        case 'update':

            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك';
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

            $stmt = $db->prepare("SELECT * FROM apps WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                $response['message'] = 'التطبيق غير موجود';
                break;
            }

            $update = [];
            $params = [];

            foreach ([
                "name", "category", "description", "version",
                "size_mb", "developer", "os_requirements",
                "license_type", "website_url", "whats_new"
            ] as $field) {
                $update[] = "$field = ?";
                $params[] = sanitize($_POST[$field] ?? '');
            }

            $update[] = "is_featured = ?";
            $params[] = isset($_POST['is_featured']) ? 1 : 0;

            $update[] = "is_active = ?";
            $params[] = isset($_POST['is_active']) ? 1 : 0;

            $params[] = $id;

            $final_query = "UPDATE apps SET " . implode(", ", $update) . " WHERE id = ?";
            $stmt = $db->prepare($final_query);
            $stmt->execute($params);

            $response['success'] = true;
            $response['message'] = 'تم تعديل التطبيق';
            break;

        /* ================================
           DELETE APP
        ================================= */
        case 'delete':

            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك';
                break;
            }

            $id = intval($_GET['id'] ?? 0);

            if ($id <= 0) {
                $response['message'] = 'معرف التطبيق غير صالح';
                break;
            }

            $stmt = $db->prepare("SELECT * FROM apps WHERE id = ?");
            $stmt->execute([$id]);
            $app = $stmt->fetch();

            if (!$app) {
                $response['message'] = 'التطبيق غير موجود';
                break;
            }

            if (!empty($app['file_path'])) {
                deleteFile($app['file_path']);
            }
            if (!empty($app['image_path'])) {
                deleteFile($app['image_path']);
            }

            $stmt = $db->prepare("DELETE FROM apps WHERE id = ?");
            $stmt->execute([$id]);

            $response['success'] = true;
            $response['message'] = 'تم حذف التطبيق';
            break;

        /* ================================
           DOWNLOAD
        ================================= */
        case 'download':

            $id = intval($_GET['id'] ?? 0);

            if ($id <= 0) {
                $response['message'] = 'معرف التطبيق غير صالح';
                break;
            }

            $stmt = $db->prepare("SELECT * FROM apps WHERE id = ?");
            $stmt->execute([$id]);
            $app = $stmt->fetch();

            if (!$app) {
                $response['message'] = 'التطبيق غير موجود';
                break;
            }

            if (empty($app['file_path'])) {
                $response['message'] = 'ملف التطبيق مفقود';
                break;
            }

            $response['success'] = true;
            $response['data'] = [
                'download_url' => SITE_URL . 'download_file.php?id=' . $id
            ];
            break;

        /* ================================
           CATEGORIES
        ================================= */
        case 'get_categories':

            $stmt = $db->query("SELECT DISTINCT category, COUNT(*) as count FROM apps GROUP BY category");
            $response['success'] = true;
            $response['data'] = $stmt->fetchAll();
            break;

        /* ================================
           STATS
        ================================= */
        case 'get_stats':

            if (!isAdmin()) {
                $response['message'] = 'غير مصرح';
                break;
            }

            $response['success'] = true;
            $response['data'] = getDashboardStats();
            break;

        default:
            $response['message'] = 'عملية غير معروفة';
    }

} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    $response['message'] = 'حدث خطأ في النظام';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
