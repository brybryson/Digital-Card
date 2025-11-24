<?php
require_once 'api/config.php';

$user_id = $_GET['id'] ?? null;

if (empty($user_id)) {
    die('User ID is required');
}

$db = getDB();
$stmt = $db->prepare("SELECT design_template FROM users WHERE id = ? AND status = 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die('User not found');
}

// Redirect to appropriate design template
$template = $user['design_template'] ?? 'design-1';
$template_file = $template . '.html';

// Check if template exists
if (!file_exists($template_file)) {
    die('Template not found');
}

// Load template and inject user ID for API call
$html = file_get_contents($template_file);

// Replace API URL with user-specific URL
$api_url = "api/get_user.php?id=" . $user_id;
$html = preg_replace(
    '/const apiUrl = [\'"].*?[\'"]/s',
    "const apiUrl = '" . $api_url . "'",
    $html
);

echo $html;
?>