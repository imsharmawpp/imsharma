<?php
// Ensure JSON response on any PHP fatal error
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $error['message']]);
    }
});

require_once __DIR__ . '/../config/config.php';
handleCors();
requirePost();

if (empty($_FILES['plan'])) {
    jsonResponse(['success' => false, 'message' => 'No file uploaded']);
}

$file = $_FILES['plan'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'message' => 'Upload error code: ' . $file['error']]);
}

if ($file['size'] > MAX_UPLOAD_SIZE) {
    jsonResponse(['success' => false, 'message' => 'File too large (max 10MB)']);
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ALLOWED_EXTENSIONS)) {
    jsonResponse(['success' => false, 'message' => 'Invalid file type. Use JPG, PNG, or PDF.']);
}

// Verify mime type
$mime = mime_content_type($file['tmp_name']) ?: $file['type'];
if (!in_array($mime, ALLOWED_MIMETYPES)) {
    jsonResponse(['success' => false, 'message' => 'Invalid file format']);
}

// Get form fields
$name = clean($_POST['name'] ?? 'Customer');
$email = strtolower(trim($_POST['email'] ?? ''));
$phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
$direction = clean($_POST['direction'] ?? '');
$plotSize = clean($_POST['plot_size'] ?? '');
$floors = clean($_POST['floors'] ?? '');
$concerns = clean($_POST['concerns'] ?? '');
$city = clean($_POST['city'] ?? '');

if (!$email || !isValidEmail($email)) {
    jsonResponse(['success' => false, 'message' => 'Valid email is required']);
}
if (!$direction) {
    jsonResponse(['success' => false, 'message' => 'House facing direction is required']);
}

// Save file
$plansDir = UPLOADS_PATH . '/plans';
if (!is_dir($plansDir)) @mkdir($plansDir, 0755, true);

$filename = 'plan_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 10) . '.' . $ext;
$filepath = $plansDir . '/' . $filename;
$publicUrl = UPLOADS_URL . '/plans/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    jsonResponse(['success' => false, 'message' => 'Failed to save file']);
}

try {
    // Find or create user record (lightweight - based on email)
    $user = Database::row("SELECT id FROM users WHERE email = ?", [$email]);
    $userId = $user['id'] ?? null;

    if (!$userId) {
        $userId = Database::insert('users', [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'city' => $city
        ]);
    }

    // Create report record (pending payment)
    $reportId = Database::insert('reports', [
        'user_id' => $userId,
        'customer_name' => $name,
        'customer_email' => $email,
        'customer_phone' => $phone,
        'image_path' => $filepath,
        'image_url' => $publicUrl,
        'direction' => $direction,
        'plot_size' => $plotSize,
        'floors' => $floors,
        'concerns' => $concerns,
        'city' => $city,
        'status' => 'pending',
        'amount' => floatval(getSetting('report_price', '99'))
    ]);

    // Capture as lead
    @Database::insert('leads', [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'source' => 'report_upload',
        'status' => 'new'
    ]);

    jsonResponse([
        'success' => true,
        'report_id' => $reportId,
        'file_url' => $publicUrl,
        'message' => 'File uploaded successfully'
    ]);

} catch (Exception $e) {
    @unlink($filepath);
    logDebug('Upload error', ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'message' => 'Failed to save report. Please try again.']);
}
