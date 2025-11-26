<?php
session_start();
require_once '../api/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$db = getDB();
$is_edit = false;

$user = $bio = $social = $banks = $videos = $links = $settings = [];
$upload_errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valid = true;
    $error = '';

    // Validate mobile numbers
    if (!preg_match('/^09\d{9}$/', $_POST['mobile'])) {
        $valid = false;
        $error = "Primary mobile must be 11 digits starting with 09";
    }
    if (!empty($_POST['mobile1']) && !preg_match('/^09\d{9}$/', $_POST['mobile1'])) {
        $valid = false;
        $error = "Secondary mobile must be 11 digits starting with 09";
    }
    // Validate required fields
    if (empty(trim($_POST['firstname']))) {
        $valid = false;
        $error = "First name is required";
    } elseif (empty(trim($_POST['lastname']))) {
        $valid = false;
        $error = "Last name is required";
    } elseif (empty(trim($_POST['middlename']))) {
        $valid = false;
        $error = "Middle name is required";
    } elseif (empty($_POST['design_template'])) {
        $valid = false;
        $error = "Design template is required";
    }

    // Validate mobile numbers
    if (!preg_match('/^09\d{9}$/', $_POST['mobile'])) {
        $valid = false;
        $error = "Primary mobile must be 11 digits starting with 09";
    }
    if (!empty($_POST['mobile1']) && !preg_match('/^09\d{9}$/', $_POST['mobile1'])) {
        $valid = false;
        $error = "Secondary mobile must be 11 digits starting with 09";
    }
    // Validate email
    if (!preg_match('/@.*\.com$/', $_POST['email'])) {
        $valid = false;
        $error = "Email must contain @ and end with .com";
    }

    if ($valid) {
        try {
            $db->beginTransaction();

            // Handle photo upload
            $photo_path = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['photo']);
                if ($upload && isset($upload['success']) && $upload['success']) {
                    $photo_path = $upload['url'];
                } else {
                    $upload_errors[] = 'Photo upload failed: ' . ($upload['error'] ?? 'Unknown error');
                }
            }


            // Handle company logo upload
            $company_logo_path = null;
            if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['company_logo']);
                if ($upload && isset($upload['success']) && $upload['success']) {
                    $company_logo_path = $upload['url'];
                } else {
                    $upload_errors[] = 'Company logo upload failed: ' . ($upload['error'] ?? 'Unknown error');
                }
            }

            // Generate agent_id and referral_code
            $agent_id = generateRandomString(8);
            $referral_code = generateRandomString(6);

            $stmt = $db->prepare("
                INSERT INTO users (firstname, lastname, middlename, home_address, company, position, company_logo, work_location, mobile, mobile1, email, photo, design_template, agent_id, referral_code)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['firstname'], $_POST['lastname'], $_POST['middlename'], $_POST['home_address'], $_POST['company'], $_POST['position'],
                $company_logo_path, $_POST['work_location'], $_POST['mobile'], $_POST['mobile1'],
                $_POST['email'], $photo_path, $_POST['design_template'], $agent_id, $referral_code
            ]);
            $user_id = $db->lastInsertId();

            // Save bio
            $stmt = $db->prepare("INSERT INTO user_bio (user_id, title, description) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description)");
            $stmt->execute([$user_id, $_POST['bio_title'], $_POST['bio_description']]);

            // Save social media
            $social_titles = [];
            $logo_map = [
                'FB' => '/Digital-Card/images/social-media-logo/FACEBOOK-LOGO.png',
                'IG' => '/Digital-Card/images/social-media-logo/INSTAGRAM-LOGO.png',
                'GH' => '/Digital-Card/images/social-media-logo/GITHUB-LOGO.png',
                'LI' => '/Digital-Card/images/social-media-logo/LINKEDIN-LOGO.png',
                'TG' => '/Digital-Card/images/social-media-logo/TELEGRAM-LOGO.png',
                'TT' => '/Digital-Card/images/social-media-logo/TIKTOK-LOGO.png',
                'TW' => '/Digital-Card/images/social-media-logo/TWITTER-LOGO.png',
                'VB' => '/Digital-Card/images/social-media-logo/VIBER-LOGO.png',
                'YT' => '/Digital-Card/images/social-media-logo/YOUTUBE-LOGO.png',
            ];
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'social_') === 0) {
                    if (strpos($key, '_title') !== false) {
                        $plat = strtoupper(substr($key, 7, -6)); // social_xx_title -> xx
                        $social_titles[$plat] = $value;
                    } elseif (!empty($value)) {
                        $platform = strtoupper(substr($key, 7));
                        $title = $social_titles[$platform] ?? null;
                        // Determine custom_logo
                        $custom_logo_path = null;
                        if (isset($logo_map[$platform])) {
                            $custom_logo_path = $logo_map[$platform];
                        } elseif (!isset($logo_map[$platform])) {
                            $custom_logo_path = '../images/verified.png';
                        }
                        // Check for custom logo
                        $custom_logo_key = 'social_' . strtolower($platform) . '_logo';
                        if (isset($_POST[$custom_logo_key])) {
                            $custom_logo_path = $_POST[$custom_logo_key];
                        }
                        $stmt = $db->prepare("INSERT INTO social_media (agent_id, platform, link, title, custom_logo, status)
                            VALUES (?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE link = VALUES(link), title = VALUES(title), custom_logo = VALUES(custom_logo)");
                        $stmt->execute([$user_id, $platform, $value, $title, $custom_logo_path]);
                    }
                }
            }

            // Handle social media deletions
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'delete_social_') === 0 && $value == '1') {
                    $plat = strtoupper(substr($key, 14)); // delete_social_xx -> xx
                    $stmt = $db->prepare("DELETE FROM social_media WHERE agent_id = ? AND platform = ?");
                    $stmt->execute([$user_id, $plat]);
                }
            }

            // Save bank accounts
            $bank_data = [];
            $logo_map = [
                'GCash' => '/Digital-Card/images/banks-logo/GCASH-LOGO.png',
                'Maya' => '/Digital-Card/images/banks-logo/MAYA-LOGO.png',
                'Paypal' => '/Digital-Card/images/banks-logo/PAYPAL-LOGO.png',
                'GoTyme' => '/Digital-Card/images/banks-logo/GOTYME-LOGO.png',
            ];
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'bank_') === 0 && strpos($key, '_name') !== false && strpos($key, '_account_name') === false) {
                    $bank_key = substr($key, 5, -5); // bank_xx_name -> xx
                    $bank_data[$bank_key]['name'] = $value;
                } elseif (strpos($key, 'bank_') === 0 && strpos($key, '_account_name') !== false) {
                    $bank_key = substr($key, 5, -13); // bank_xx_account_name -> xx
                    $bank_data[$bank_key]['account_name'] = $value;
                } elseif (strpos($key, 'bank_') === 0 && strpos($key, '_account_no') !== false) {
                    $bank_key = substr($key, 5, -11); // bank_xx_account_no -> xx
                    $bank_data[$bank_key]['account_no'] = $value;
                } elseif (strpos($key, 'bank_') === 0 && strpos($key, '_account_email') !== false) {
                    $bank_key = substr($key, 5, -14); // bank_xx_account_email -> xx
                    $bank_data[$bank_key]['account_email'] = $value;
                } elseif (strpos($key, 'bank_') === 0 && strpos($key, '_logo') !== false) {
                    $bank_key = substr($key, 5, -5); // bank_xx_logo -> xx
                    $bank_data[$bank_key]['logo'] = $value;
                }
            }
            foreach ($bank_data as $bank_key => $data) {
                $bank_name = $data['name'] ?? '';
                $account_name = $data['account_name'] ?? '';
                $account_no = $data['account_no'] ?? '';
                $account_email = $data['account_email'] ?? '';
                $logo_path = $data['logo'] ?? $logo_map[$bank_name] ?? '';

                if (!empty($bank_name) && !empty($account_name) && !empty($account_no) && !empty($logo_path)) {
                    $stmt = $db->prepare("INSERT INTO bank_accounts (agent_id, bank_name, account_no, account_type, account_email, logo_path)
                        VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $bank_name, $account_no, $account_name, $account_email, $logo_path]);
                }
            }

            $db->commit();

            logActivity($_SESSION['admin_id'], $user_id, 'create_user',
                'User created');

            redirect('users.php');
        } catch(Exception $e) {
            $db->rollBack();
            $error = "Error saving user: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - BumpCard Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'includes/sidebar.php'; ?>

    <div id="main-content" class="ml-64 p-8">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Add New User</h1>
                <p class="text-gray-600 mt-1">Create a new digital card user</p>
            </div>
            <a href="users.php" class="px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition">
                ← Back to Users
            </a>
        </div>

        <?php if (isset($error)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($upload_errors)): ?>
        <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 text-yellow-700 rounded-lg">
            <strong>Upload Issues:</strong>
            <ul class="mt-2 list-disc list-inside">
                <?php foreach ($upload_errors as $upload_error): ?>
                <li><?php echo htmlspecialchars($upload_error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" id="current_user_id" value="">
            <!-- Basic Information -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Basic Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">First Name *</label>
                        <input type="text" name="firstname" value=""
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Middle Name *</label>
                        <input type="text" name="middlename" value=""
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Last Name *</label>
                        <input type="text" name="lastname" value=""
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Home Address</label>
                        <textarea name="home_address" rows="2"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"></textarea>
                    </div>
                </div>
            </div>

            <!-- Work Information -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Work Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Company</label>
                        <input type="text" name="company" value=""
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Position</label>
                        <input type="text" name="position" value=""
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Work Location</label>
                        <textarea name="work_location" rows="2"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Profile Photo</label>
                        <input type="file" name="photo" accept="image/*" onchange="previewImage(this, 'photo-preview')"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <div class="mt-2">
                            <img id="photo-preview" src="" alt="Profile Photo" class="w-20 h-20 object-cover rounded-lg border border-gray-200 hidden">
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Company Logo</label>
                        <input type="file" name="company_logo" accept="image/*" onchange="previewImage(this, 'logo-preview')"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <div class="mt-2">
                            <img id="logo-preview" src="" alt="Company Logo" class="w-20 h-20 object-cover rounded-lg border border-gray-200 hidden">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Contact Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Primary Mobile *</label>
                        <input type="tel" name="mobile" value="" onfocus="autoFill09(this)" oninput="validateMobile(this)" onblur="checkMobile(this)"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" required maxlength="11" placeholder="09XXXXXXXXX">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Secondary Mobile</label>
                        <input type="tel" name="mobile1" value="" onfocus="autoFill09(this)" oninput="validateMobile(this)" onblur="checkMobile(this)"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" maxlength="11" placeholder="09XXXXXXXXX">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email *</label>
                        <input type="email" name="email" value="" onblur="validateEmail(this)"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                    </div>
                </div>
            </div>

            <!-- Bio Section -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Bio Section</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Bio Title</label>
                        <input type="text" name="bio_title" value="" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Bio Description</label>
                        <textarea name="bio_description" rows="4" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"></textarea>
                    </div>
                </div>
            </div>

            <!-- Social Media -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Social Media Links</h2>
                    <button type="button" onclick="openSocialMediaModal()" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                        + Add Social Media
                    </button>
                </div>
                <div id="socialMediaContainer" class="space-y-4">
                </div>
            </div>

            <!-- Bank Accounts -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Bank Accounts</h2>
                    <button type="button" onclick="openBankModal()" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                        + Add Bank
                    </button>
                </div>
                <div id="bankContainer" class="space-y-4">
                </div>
            </div>

            <!-- Design Template -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Design Template</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <label class="relative cursor-pointer">
                        <input type="radio" name="design_template" value="design-1" checked required
                            class="peer sr-only">
                        <div class="border-2 border-gray-300 rounded-lg p-4 peer-checked:border-orange-500 peer-checked:bg-orange-50 transition cursor-pointer">
                            <div class="mb-2">
                                <span class="font-semibold text-gray-800">Design 1</span>
                            </div>
                            <p class="text-sm text-gray-600">Classic layout with gradient overlays</p>
                        </div>
                    </label>
                    
                    <label class="relative cursor-pointer">
                        <input type="radio" name="design_template" value="design-2" 
                            class="peer sr-only">
                        <div class="border-2 border-gray-300 rounded-lg p-4 peer-checked:border-orange-500 peer-checked:bg-orange-50 transition cursor-pointer">
                            <div class="mb-2">
                                <span class="font-semibold text-gray-800">Design 2</span>
                            </div>
                            <p class="text-sm text-gray-600">Modern layout with clean aesthetics</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex items-center justify-end space-x-4">
                <a href="users.php" class="px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition">
                    Cancel
                </a>
                <button type="button" onclick="confirmSave()" class="px-6 py-3 bg-gradient-to-r from-orange-500 to-yellow-500 text-white font-semibold rounded-lg hover:from-orange-600 hover:to-yellow-600 transition">
                    Create User
                </button>
            </div>
        </form>
    </div>

    <!-- Social Media Selection Modal -->
    <div id="socialMediaModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg max-w-2xl w-full mx-4 max-h-[80vh] overflow-y-auto">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Add Social Media</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <button type="button" onclick="selectSocialMedia('FB', 'Facebook', '/Digital-Card/images/social-media-logo/FACEBOOK-LOGO.png')" class="flex flex-col items-center p-4 border border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition">
                        <img src="/Digital-Card/images/social-media-logo/FACEBOOK-LOGO.png" alt="Facebook" class="w-12 h-12 mb-2">
                        <span class="text-sm font-medium">Facebook</span>
                    </button>
                    <button type="button" onclick="selectSocialMedia('IG', 'Instagram', '/Digital-Card/images/social-media-logo/INSTAGRAM-LOGO.png')" class="flex flex-col items-center p-4 border border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition">
                        <img src="/Digital-Card/images/social-media-logo/INSTAGRAM-LOGO.png" alt="Instagram" class="w-12 h-12 mb-2">
                        <span class="text-sm font-medium">Instagram</span>
                    </button>
                    <button type="button" onclick="selectSocialMedia('GH', 'GitHub', '/Digital-Card/images/social-media-logo/GITHUB-LOGO.png')" class="flex flex-col items-center p-4 border border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition">
                        <img src="/Digital-Card/images/social-media-logo/GITHUB-LOGO.png" alt="GitHub" class="w-12 h-12 mb-2">
                        <span class="text-sm font-medium">GitHub</span>
                    </button>
                    <button type="button" onclick="selectSocialMedia('LI', 'LinkedIn', '/Digital-Card/images/social-media-logo/LINKEDIN-LOGO.png')" class="flex flex-col items-center p-4 border border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition">
                        <img src="/Digital-Card/images/social-media-logo/LINKEDIN-LOGO.png" alt="LinkedIn" class="w-12 h-12 mb-2">
                        <span class="text-sm font-medium">LinkedIn</span>
                    </button>
                    <button type="button" onclick="selectSocialMedia('TG', 'Telegram', '/Digital-Card/images/social-media-logo/TELEGRAM-LOGO.png')" class="flex flex-col items-center p-4 border border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition">
                        <img src="/Digital-Card/images/social-media-logo/TELEGRAM-LOGO.png" alt="Telegram" class="w-12 h-12 mb-2">
                        <span class="text-sm font-medium">Telegram</span>
                    </button>
                    <button type="button" onclick="selectSocialMedia('TT', 'TikTok', '/Digital-Card/images/social-media-logo/TIKTOK-LOGO.png')" class="flex flex-col items-center p-4 border border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition">
                        <img src="/Digital-Card/images/social-media-logo/TIKTOK-LOGO.png" alt="TikTok" class="w-12 h-12 mb-2">
                        <span class="text-sm font-medium">TikTok</span>
                    </button>
                    <button type="button" onclick="selectSocialMedia('TW', 'Twitter', '/Digital-Card/images/social-media-logo/TWITTER-LOGO.png')" class="flex flex-col items-center p-4 border border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition">
                        <img src="/Digital-Card/images/social-media-logo/TWITTER-LOGO.png" alt="Twitter" class="w-12 h-12 mb-2">
                        <span class="text-sm font-medium">Twitter</span>
                    </button>
                    <button type="button" onclick="selectSocialMedia('VB', 'Viber', '/Digital-Card/images/social-media-logo/VIBER-LOGO.png')" class="flex flex-col items-center p-4 border border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition">
                        <img src="/Digital-Card/images/social-media-logo/VIBER-LOGO.png" alt="Viber" class="w-12 h-12 mb-2">
                        <span class="text-sm font-medium">Viber</span>
                    </button>
                    <button type="button" onclick="selectSocialMedia('YT', 'YouTube', '/Digital-Card/images/social-media-logo/YOUTUBE-LOGO.png')" class="flex flex-col items-center p-4 border border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition">
                        <img src="/Digital-Card/images/social-media-logo/YOUTUBE-LOGO.png" alt="YouTube" class="w-12 h-12 mb-2">
                        <span class="text-sm font-medium">YouTube</span>
                    </button>
                    <button type="button" onclick="openCustomSocialModal()" class="flex flex-col items-center p-4 border border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition">
                        <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center mb-2">
                            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium">Others</span>
                    </button>
                </div>
                <div class="flex justify-end mt-6">
                    <button type="button" onclick="closeSocialMediaModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Social Media Modal -->
    <div id="customSocialModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Add Custom Social Media</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Social Media Name</label>
                        <input type="text" id="customSocialName" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="e.g., Snapchat">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">URL</label>
                        <input type="url" id="customSocialUrl" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="https://...">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Logo *</label>
                        <input type="file" id="customSocialLogo" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeCustomSocialModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                    <button type="button" onclick="addCustomSocialMedia()" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">Add</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bank Selection Modal -->
    <div id="bankModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg max-w-2xl w-full mx-4 max-h-[80vh] overflow-y-auto">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Add Bank Account</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <button type="button" onclick="selectBank('GCash', 'GCash', '/Digital-Card/images/banks-logo/GCASH-LOGO.png')" class="flex flex-col items-center p-4 border border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition">
                        <img src="/Digital-Card/images/banks-logo/GCASH-LOGO.png" alt="GCash" class="w-12 h-12 mb-2">
                        <span class="text-sm font-medium">GCash</span>
                    </button>
                    <button type="button" onclick="selectBank('Maya', 'Maya', '/Digital-Card/images/banks-logo/MAYA-LOGO.png')" class="flex flex-col items-center p-4 border border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition">
                        <img src="/Digital-Card/images/banks-logo/MAYA-LOGO.png" alt="Maya" class="w-12 h-12 mb-2">
                        <span class="text-sm font-medium">Maya</span>
                    </button>
                    <button type="button" onclick="selectBank('Paypal', 'Paypal', '/Digital-Card/images/banks-logo/PAYPAL-LOGO.png')" class="flex flex-col items-center p-4 border border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition">
                        <img src="/Digital-Card/images/banks-logo/PAYPAL-LOGO.png" alt="Paypal" class="w-12 h-12 mb-2">
                        <span class="text-sm font-medium">Paypal</span>
                    </button>
                    <button type="button" onclick="selectBank('GoTyme', 'GoTyme', '/Digital-Card/images/banks-logo/GOTYME-LOGO.png')" class="flex flex-col items-center p-4 border border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition">
                        <img src="/Digital-Card/images/banks-logo/GOTYME-LOGO.png" alt="GoTyme" class="w-12 h-12 mb-2">
                        <span class="text-sm font-medium">GoTyme</span>
                    </button>
                    <button type="button" onclick="openCustomBankModal()" class="flex flex-col items-center p-4 border border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition">
                        <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center mb-2">
                            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium">Others</span>
                    </button>
                </div>
                <div class="flex justify-end mt-6">
                    <button type="button" onclick="closeBankModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bank Details Modal -->
    <div id="bankDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Add Bank Account Details</h3>
                <div class="space-y-4">
                    <div class="flex items-center space-x-3">
                        <img id="selectedBankLogo" src="" alt="Bank Logo" class="w-12 h-12">
                        <span id="selectedBankName" class="text-lg font-medium"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Account Name *</label>
                        <input type="text" id="bankAccountName" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="Account holder name" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Account Number *</label>
                        <input type="text" id="bankAccountNo" onfocus="autoFill09Bank(this)" oninput="validateBankAccountNo(this)" onblur="checkBankAccountNo(this)" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" maxlength="11" placeholder="09XXXXXXXXX" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email (optional)</label>
                        <input type="email" id="bankAccountEmail" onblur="validateBankEmail(this)" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="email@example.com">
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeBankDetailsModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                    <button type="button" onclick="addBankDetails()" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">Add</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Bank Modal -->
    <div id="customBankModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Add Custom Bank</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Bank Name *</label>
                        <input type="text" id="customBankName" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="e.g., BDO" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Account Name *</label>
                        <input type="text" id="customBankAccountName" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="Account holder name" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Account Number *</label>
                        <input type="text" id="customBankAccountNo" onfocus="autoFill09Bank(this)" oninput="validateBankAccountNo(this)" onblur="checkBankAccountNo(this)" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" maxlength="11" placeholder="09XXXXXXXXX" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email (optional)</label>
                        <input type="email" id="customBankAccountEmail" onblur="validateBankEmail(this)" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="email@example.com">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Logo *</label>
                        <input type="file" id="customBankLogo" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeCustomBankModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                    <button type="button" onclick="addCustomBank()" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">Add</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openSocialMediaModal() {
            document.getElementById('socialMediaModal').classList.remove('hidden');
        }

        function closeSocialMediaModal() {
            document.getElementById('socialMediaModal').classList.add('hidden');
        }

        function selectSocialMedia(platform, name, logo) {
            addSocialMediaField(platform, name, logo);
            closeSocialMediaModal();
        }

        function openCustomSocialModal() {
            closeSocialMediaModal();
            document.getElementById('customSocialModal').classList.remove('hidden');
        }

        function closeCustomSocialModal() {
            document.getElementById('customSocialModal').classList.add('hidden');
        }

        async function addCustomSocialMedia() {
            const name = document.getElementById('customSocialName').value.trim();
            const url = document.getElementById('customSocialUrl').value.trim();
            const logoFile = document.getElementById('customSocialLogo').files[0];

            if (!name || !url || !logoFile) {
                alert('Please fill in all fields');
                return;
            }

            // Upload logo
            const formData = new FormData();
            formData.append('file', logoFile);
            formData.append('user_id', document.getElementById('current_user_id').value);
            formData.append('platform', name.substr(0,2).toUpperCase());
            formData.append('title', name);
            formData.append('link', url);

            try {
                const response = await fetch('../api/upload.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (!result.success) {
                    alert('Logo upload failed: ' + result.error);
                    return;
                }

                const platform = name.substr(0,2).toUpperCase();
                const logo = result.url;
                addSocialMediaField(platform, name, logo, url, logo);

                // Reset form
                document.getElementById('customSocialName').value = '';
                document.getElementById('customSocialUrl').value = '';
                document.getElementById('customSocialLogo').value = '';
                closeCustomSocialModal();
            } catch (error) {
                alert('Upload error: ' + error.message);
            }
        }

        function addSocialMediaField(platform, name, logo, url = '', customLogo = null) {
            const container = document.getElementById('socialMediaContainer');
            const div = document.createElement('div');
            div.className = 'flex items-center space-x-4 p-4 bg-gray-50 rounded-lg';
            const standardPlatforms = ['FB', 'IG', 'GH', 'LI', 'TG', 'TT', 'TW', 'VB', 'YT'];
            const isCustom = !standardPlatforms.includes(platform);
            let html = '<img src="' + logo + '" alt="' + name + '" class="w-8 h-8">' +
                '<input type="hidden" name="social_' + platform.toLowerCase() + '_title" value="' + name + '">' +
                '<input type="url" name="social_' + platform.toLowerCase() + '" value="' + url + '" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="https://...">' +
                '<button type="button" onclick="editSocialMedia(this)" class="text-blue-600 hover:text-blue-700 mr-2">' +
                '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>' +
                '</svg>' +
                '</button>' +
                '<button type="button" onclick="removeSocialMedia(this, \'' + platform + '\')" class="text-red-600 hover:text-red-700">' +
                '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>' +
                '</svg>' +
                '</button>';
            if (customLogo) {
                html += '<input type="hidden" name="social_' + platform.toLowerCase() + '_logo" value="' + customLogo + '">';
            }
            div.innerHTML = html;
            container.appendChild(div);
        }

        function editSocialMedia(button) {
            const input = button.parentElement.querySelector('input[type="url"]');
            input.focus();
        }

        function removeSocialMedia(button, platform) {
            if (confirm('Are you sure you want to remove this social media link?')) {
                // Add hidden input to mark for deletion
                const form = document.querySelector('form');
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'delete_social_' + platform.toLowerCase();
                hidden.value = '1';
                form.appendChild(hidden);
                button.closest('.flex').remove();
                // Submit the form to apply the deletion
                form.submit();
            }
        }

        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.src = '';
                preview.classList.add('hidden');
            }
        }

        function autoFill09(input) {
            if (input.value === '') {
                input.value = '09';
            }
        }

        function validateMobile(input) {
            // Remove non-numeric characters
            input.value = input.value.replace(/[^0-9]/g, '');
            // Ensure starts with 09
            if (input.value.length >= 2 && input.value.substring(0, 2) !== '09') {
                input.value = '09' + input.value.replace(/^09/, '');
            }
            // Limit to 11 digits
            if (input.value.length > 11) {
                input.value = input.value.substring(0, 11);
            }
        }

        function checkMobile(input) {
            const value = input.value;
            if (value.length !== 11 || !value.startsWith('09')) {
                alert('Mobile number must be 11 digits and start with 09');
                input.focus();
            }
        }

        function validateEmail(input) {
            const value = input.value;
            if (!value.includes('@') || !value.endsWith('.com')) {
                alert('Email must contain @ and end with .com');
                input.focus();
            }
        }

        function autoFill09Bank(input) {
            if (input.value === '') {
                input.value = '09';
            }
        }

        function validateBankAccountNo(input) {
            // Remove non-numeric characters
            input.value = input.value.replace(/[^0-9]/g, '');
            // Ensure starts with 09
            if (input.value.length >= 2 && input.value.substring(0, 2) !== '09') {
                input.value = '09' + input.value.replace(/^09/, '');
            }
            // Limit to 11 digits
            if (input.value.length > 11) {
                input.value = input.value.substring(0, 11);
            }
        }

        function checkBankAccountNo(input) {
            const value = input.value;
            if (value.length !== 11 || !value.startsWith('09')) {
                alert('Account number must be 11 digits and start with 09');
                input.focus();
            }
        }

        function validateBankEmail(input) {
            const value = input.value;
            if (value && (!value.includes('@') || !value.endsWith('.com'))) {
                alert('Email must contain @ and end with .com');
                input.focus();
            }
        }

        function openBankModal() {
            document.getElementById('bankModal').classList.remove('hidden');
        }

        function closeBankModal() {
            document.getElementById('bankModal').classList.add('hidden');
        }

        function selectBank(bankName, displayName, logo) {
            document.getElementById('selectedBankName').textContent = displayName;
            document.getElementById('selectedBankLogo').src = logo;
            document.getElementById('bankDetailsModal').dataset.bankName = bankName;
            document.getElementById('bankDetailsModal').dataset.displayName = displayName;
            document.getElementById('bankDetailsModal').dataset.logo = logo;
            closeBankModal();
            document.getElementById('bankDetailsModal').classList.remove('hidden');
        }

        function openCustomBankModal() {
            closeBankModal();
            document.getElementById('customBankModal').classList.remove('hidden');
        }

        function closeBankDetailsModal() {
            document.getElementById('bankDetailsModal').classList.add('hidden');
        }

        function closeCustomBankModal() {
            document.getElementById('customBankModal').classList.add('hidden');
        }

        function addBankDetails() {
            const modal = document.getElementById('bankDetailsModal');
            const bankName = modal.dataset.bankName;
            const displayName = modal.dataset.displayName;
            const logo = modal.dataset.logo;
            const accountName = document.getElementById('bankAccountName').value.trim();
            const accountNo = document.getElementById('bankAccountNo').value.trim();
            const accountEmail = document.getElementById('bankAccountEmail').value.trim();

            if (!accountName || !accountNo) {
                alert('Please fill in account name and account number');
                return;
            }

            addBankField(bankName, displayName, logo, accountName, accountNo, accountEmail);

            // Reset form
            document.getElementById('bankAccountName').value = '';
            document.getElementById('bankAccountNo').value = '';
            document.getElementById('bankAccountEmail').value = '';
            closeBankDetailsModal();
        }

        async function addCustomBank() {
            const bankName = document.getElementById('customBankName').value.trim();
            const accountName = document.getElementById('customBankAccountName').value.trim();
            const accountNo = document.getElementById('customBankAccountNo').value.trim();
            const accountEmail = document.getElementById('customBankAccountEmail').value.trim();
            const logoFile = document.getElementById('customBankLogo').files[0];

            if (!bankName || !accountName || !accountNo || !logoFile) {
                alert('Please fill in all required fields');
                return;
            }

            // Upload logo
            const formData = new FormData();
            formData.append('file', logoFile);
            formData.append('user_id', document.getElementById('current_user_id').value);
            formData.append('bank_name', bankName);
            formData.append('account_name', accountName);
            formData.append('account_no', accountNo);
            formData.append('account_email', accountEmail);

            try {
                const response = await fetch('../api/upload.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (!result.success) {
                    alert('Logo upload failed: ' + result.error);
                    return;
                }

                const logo = result.url;
                addBankField(bankName, bankName, logo, accountName, accountNo, accountEmail, logo);

                // Reset form
                document.getElementById('customBankName').value = '';
                document.getElementById('customBankAccountName').value = '';
                document.getElementById('customBankAccountNo').value = '';
                document.getElementById('customBankAccountEmail').value = '';
                document.getElementById('customBankLogo').value = '';
                closeCustomBankModal();
            } catch (error) {
                alert('Upload error: ' + error.message);
            }
        }

        function addBankField(bankName, displayName, logo, accountName = '', accountNo = '', customLogo = null) {
            const container = document.getElementById('bankContainer');
            const div = document.createElement('div');
            div.className = 'flex items-center space-x-4 p-4 bg-gray-50 rounded-lg';
            const bankKey = bankName.toLowerCase().replace(/\s+/g, '');
            let html = `
                <img src="${logo}" alt="${displayName}" class="w-8 h-8">
                <input type="hidden" name="bank_${bankKey}_name" value="${displayName}">
                <input type="text" name="bank_${bankKey}_account_name" value="${accountName}"
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="Account Name" required>
                <input type="text" name="bank_${bankKey}_account_no" value="${accountNo}"
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="Account Number" required>
                <button type="button" onclick="removeBank(this)" class="text-red-600 hover:text-red-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            `;
            if (customLogo) {
                html += `<input type="hidden" name="bank_${bankKey}_logo" value="${customLogo}">`;
            } else {
                html += `<input type="hidden" name="bank_${bankKey}_logo" value="${logo}">`;
            }
            div.innerHTML = html;
            container.appendChild(div);
        }

        function removeBank(button) {
            if (confirm('Are you sure you want to remove this bank account?')) {
                button.closest('.flex').remove();
            }
        }

        function confirmSave() {
            // Validate bank accounts
            const bankFields = document.querySelectorAll('#bankContainer input[type="text"]');
            for (let field of bankFields) {
                if (!field.value.trim()) {
                    alert('Please fill in all bank account fields');
                    field.focus();
                    return;
                }
            }

            if (confirm('Are you sure you want to create this user?')) {
                document.querySelector('form').submit();
            }
        }
    </script>
</body>
</html>