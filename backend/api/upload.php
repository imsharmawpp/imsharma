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

// Handle JSON lead capture request (from Step 1)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input = jsonInput();
    if (($input['action'] ?? '') === 'capture_lead') {
        try {
            @Database::insert('leads', [
                'name' => clean($input['name'] ?? ''),
                'phone' => preg_replace('/\D/', '', $input['phone'] ?? ''),
                'email' => strtolower(trim($input['email'] ?? '')),
                'source' => 'questionnaire_step1',
                'message' => json_encode([
                    'property_category' => $input['property_category'] ?? '',
                    'property_subtype' => $input['property_subtype'] ?? '',
                    'problem_areas' => $input['problem_areas'] ?? [],
                    'size_sqft' => $input['size_sqft'] ?? ''
                ]),
                'status' => 'new'
            ]);
        } catch (Exception $e) {
            // Silent - don't fail
        }
        jsonResponse(['success' => true, 'message' => 'Lead captured']);
    }
}

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
$propertyCategory = clean($_POST['property_category'] ?? '');
$propertySubtype = clean($_POST['property_subtype'] ?? '');
$sizeSqft = clean($_POST['size_sqft'] ?? '');
$problemAreas = $_POST['problem_areas'] ?? '[]';
$otherProblemText = clean($_POST['other_problem_text'] ?? '');
$markersRaw = $_POST['markers'] ?? '[]';

// Validate/normalize markers JSON: keep only well-formed entries.
$markersJson = null;
if (is_string($markersRaw) && $markersRaw !== '') {
    $decodedMarkers = json_decode($markersRaw, true);
    if (is_array($decodedMarkers)) {
        $cleanMarkers = [];
        foreach ($decodedMarkers as $m) {
            if (!is_array($m) || !isset($m['type'], $m['nx'], $m['ny'])) continue;
            $cleanMarkers[] = [
                'type'  => preg_replace('/[^a-z_]/', '', strtolower((string)$m['type'])),
                'label' => substr(clean($m['label'] ?? ''), 0, 60),
                'nx'    => max(0, min(1, floatval($m['nx']))),
                'ny'    => max(0, min(1, floatval($m['ny']))),
            ];
        }
        if ($cleanMarkers) $markersJson = json_encode($cleanMarkers);
    }
}

// Try to decode problem_areas if it's JSON
if (is_string($problemAreas)) {
    $decoded = json_decode($problemAreas, true);
    $problemAreas = is_array($decoded) ? implode(', ', $decoded) : $problemAreas;
}

if (!$name || $name === 'Customer') {
    $name = 'Customer';
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
    // Find or create user record (by phone first, then email)
    $userId = null;
    
    if ($phone) {
        $user = Database::row("SELECT id FROM users WHERE phone = ?", [$phone]);
        $userId = $user['id'] ?? null;
    }
    if (!$userId && $email) {
        $user = Database::row("SELECT id FROM users WHERE email = ?", [$email]);
        $userId = $user['id'] ?? null;
    }

    if (!$userId) {
        $insertData = ['name' => $name, 'phone' => $phone];
        if ($email) $insertData['email'] = $email;
        if ($city) $insertData['city'] = $city;
        $userId = Database::insert('users', $insertData);
    }

    // Create report record (pending payment)
    // Base fields (always exist)
    $reportData = [
        'user_id' => $userId,
        'customer_name' => $name,
        'customer_email' => $email,
        'customer_phone' => $phone,
        'image_path' => $filepath,
        'image_url' => $publicUrl,
        'direction' => $direction,
        'plot_size' => $plotSize ?: $sizeSqft,
        'floors' => $floors,
        'concerns' => $concerns,
        'city' => $city,
        'status' => 'pending',
        'amount' => floatval(getSetting('report_price', '99'))
    ];

    // Try adding new questionnaire fields (graceful if columns don't exist yet)
    // Check if the column exists before adding
    try {
        $colCheck = Database::row("SHOW COLUMNS FROM reports LIKE 'property_category'");
        if ($colCheck) {
            $reportData['property_category'] = $propertyCategory;
            $reportData['property_subtype'] = $propertySubtype;
            $reportData['problem_areas'] = $problemAreas;
            $reportData['other_problem_text'] = $otherProblemText;
        }
    } catch (Exception $e) {
        // Columns don't exist yet - skip gracefully
        logDebug('New columns not yet migrated', ['error' => $e->getMessage()]);
    }

    // markers column (migration_v4) - store gracefully if present
    try {
        $markersCol = Database::row("SHOW COLUMNS FROM reports LIKE 'markers'");
        if ($markersCol && $markersJson !== null) {
            $reportData['markers'] = $markersJson;
        }
    } catch (Exception $e) {
        logDebug('markers column not yet migrated', ['error' => $e->getMessage()]);
    }

    $reportId = Database::insert('reports', $reportData);

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
