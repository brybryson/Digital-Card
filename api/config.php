 <?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'bumpcard_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

// Timezone
date_default_timezone_set('Asia/Manila');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Base URL configuration
define('BASE_URL', 'http://localhost/bumpcard'); // Change this to your actual URL
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}

// Database connection class
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Helper function to get database connection
function getDB() {
    return Database::getInstance()->getConnection();
}

// Helper function to log activity
function logActivity($admin_id, $user_id, $action, $description, $ip = null) {
    try {
        $db = getDB();
        $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? null;
        
        $stmt = $db->prepare("
            INSERT INTO activity_log (admin_id, user_id, action, description, ip_address) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$admin_id, $user_id, $action, $description, $ip]);
    } catch(PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

// Helper function to sanitize input
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// Helper function to redirect
function redirect($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    } else {
        echo '<script>window.location.href="' . $url . '";</script>';
        exit();
    }
}

// Helper function to generate random string
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Helper function to upload file
function uploadFile($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif'], $max_size = 5242880) {
    // $max_size default is 5MB
    
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid file upload'];
    }
    
    // Check for upload errors
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'error' => 'No file uploaded'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'error' => 'File size exceeds limit'];
        default:
            return ['success' => false, 'error' => 'Unknown upload error'];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File size exceeds ' . ($max_size / 1024 / 1024) . 'MB'];
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_types)];
    }
    
    // Generate unique filename
    $filename = generateRandomString() . '.' . $extension;
    $filepath = UPLOAD_DIR . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }
    
    return [
        'success' => true,
        'filename' => $filename,
        'filepath' => $filepath,
        'url' => UPLOAD_URL . $filename
    ];
}

// Helper function to delete file
function deleteFile($filename) {
    $filepath = UPLOAD_DIR . $filename;
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

// Helper function to upload file and save social media info
function uploadAndSaveSocialMedia($file, $user_id, $platform, $title = null, $link = null, $allowed_types = ['jpg', 'jpeg', 'png', 'gif'], $max_size = 5242880) {
    try {
        $db = getDB();

        // First upload the file
        $upload = uploadFile($file, $allowed_types, $max_size);
        if (!$upload || !isset($upload['success']) || !$upload['success']) {
            return $upload; // Return the error from uploadFile
        }

        $logo_url = $upload['url'];

        // Save to social_media table
        $stmt = $db->prepare("INSERT INTO social_media (agent_id, platform, link, title, custom_logo, status)
            VALUES (?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE link = VALUES(link), title = VALUES(title), custom_logo = VALUES(custom_logo)");
        $stmt->execute([$user_id, $platform, $link, $title, $logo_url]);

        return [
            'success' => true,
            'filename' => $upload['filename'],
            'filepath' => $upload['filepath'],
            'url' => $logo_url,
            'social_media_id' => $db->lastInsertId()
        ];
    } catch(PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

// Helper function to format date
function formatDate($date, $format = 'M j, Y g:i A') {
    return date($format, strtotime($date));
}

// Helper function to get user by ID
function getUserById($user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        return false;
    }
}

// Helper function to validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Helper function to validate Philippine mobile number
function isValidPhilippineMobile($mobile) {
    return preg_match('/^(09|\+639)\d{9}$/', $mobile);
}

// Helper function to format Philippine mobile number
function formatPhilippineMobile($mobile) {
    // Convert +639xxxxxxxxx to 09xxxxxxxxx
    if (strpos($mobile, '+639') === 0) {
        return '0' . substr($mobile, 3);
    }
    return $mobile;
}

// Helper function to generate VCF (vCard) content
function generateVCard($user_data) {
    $vcf = "BEGIN:VCARD\r\n";
    $vcf .= "VERSION:3.0\r\n";
    $vcf .= "FN:" . $user_data['firstname'] . " " . $user_data['lastname'] . "\r\n";
    $vcf .= "N:" . $user_data['lastname'] . ";" . $user_data['firstname'] . ";;;\r\n";
    
    if (!empty($user_data['company'])) {
        $vcf .= "ORG:" . $user_data['company'] . "\r\n";
    }
    
    if (!empty($user_data['position'])) {
        $vcf .= "TITLE:" . $user_data['position'] . "\r\n";
    }
    
    if (!empty($user_data['mobile'])) {
        $vcf .= "TEL;TYPE=CELL:" . $user_data['mobile'] . "\r\n";
    }
    
    if (!empty($user_data['mobile1'])) {
        $vcf .= "TEL;TYPE=HOME:" . $user_data['mobile1'] . "\r\n";
    }
    
    if (!empty($user_data['email'])) {
        $vcf .= "EMAIL:" . $user_data['email'] . "\r\n";
    }
    
    if (!empty($user_data['address'])) {
        $vcf .= "ADR;TYPE=WORK:;;" . $user_data['address'] . ";;;;\r\n";
    }
    
    $vcf .= "END:VCARD\r\n";
    
    return $vcf;
}

// Helper function to send JSON response
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Helper function to check admin permission
function checkAdminPermission($required_role = 'admin') {
    if (!isLoggedIn()) {
        redirect('login.php');
    }

    $roles = ['admin' => 1, 'super_admin' => 2];
    $user_role = $_SESSION['admin_role'] ?? 'admin';

    if ($roles[$user_role] < $roles[$required_role]) {
        die('Access denied. Insufficient permissions.');
    }
}

// Initialize session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>