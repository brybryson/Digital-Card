<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Invalid request method'], 405);
}

if (!isset($_FILES['file'])) {
    jsonResponse(['success' => false, 'error' => 'No file uploaded'], 400);
}

// Check if social media parameters are provided
if (isset($_POST['user_id']) && isset($_POST['platform'])) {
    $user_id = sanitize($_POST['user_id']);
    $platform = sanitize($_POST['platform']);
    $title = isset($_POST['title']) ? sanitize($_POST['title']) : null;
    $link = isset($_POST['link']) ? sanitize($_POST['link']) : null;

    $upload = uploadAndSaveSocialMedia($_FILES['file'], $user_id, $platform, $title, $link);

    if ($upload && isset($upload['success']) && $upload['success']) {
        jsonResponse([
            'success' => true,
            'url' => $upload['url'],
            'social_media_id' => $upload['social_media_id']
        ]);
    } else {
        jsonResponse(['success' => false, 'error' => $upload['error'] ?? 'Upload failed'], 400);
    }
} elseif (isset($_POST['bank_name'])) {
    // Bank logo upload
    $bank_name = sanitize($_POST['bank_name']);
    $account_name = sanitize($_POST['account_name']);
    $account_no = sanitize($_POST['account_no']);
    $account_email = isset($_POST['account_email']) ? sanitize($_POST['account_email']) : null;

    $upload = uploadBankLogo($_FILES['file']);

    if ($upload && isset($upload['success']) && $upload['success']) {
        jsonResponse([
            'success' => true,
            'url' => $upload['url'],
            'bank_name' => $bank_name,
            'account_name' => $account_name,
            'account_no' => $account_no,
            'account_email' => $account_email
        ]);
    } else {
        jsonResponse(['success' => false, 'error' => $upload['error'] ?? 'Upload failed'], 400);
    }
} else {
    // Regular file upload
    $upload = uploadFile($_FILES['file']);

    if ($upload && isset($upload['success']) && $upload['success']) {
        jsonResponse(['success' => true, 'url' => $upload['url']]);
    } else {
        jsonResponse(['success' => false, 'error' => $upload['error'] ?? 'Upload failed'], 400);
    }
}
?>