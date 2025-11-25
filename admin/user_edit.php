<?php
session_start();
require_once '../api/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$db = getDB();
$user_id = $_GET['id'] ?? null;

if (empty($user_id)) {
    redirect('users.php');
}

$is_edit = true;

$user = $bio = $social = $banks = $videos = $links = $settings = [];
$upload_errors = [];

if ($is_edit) {
    // Fetch user data
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        redirect('users.php');
    }
    
    // Fetch related data
    $stmt = $db->prepare("SELECT * FROM user_bio WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $bio = $stmt->fetch() ?: [];
    
    $stmt = $db->prepare("SELECT * FROM social_media WHERE agent_id = ?");
    $stmt->execute([$user_id]);
    $social_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $social = [];
    foreach ($social_raw as $row) {
        $social[$row['platform']][] = $row;
    }
    
    $stmt = $db->prepare("SELECT * FROM bank_accounts WHERE agent_id = ?");
    $stmt->execute([$user_id]);
    $banks = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT * FROM videos WHERE agent_id = ?");
    $stmt->execute([$user_id]);
    $videos = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT * FROM custom_links WHERE user_id = ? ORDER BY display_order");
    $stmt->execute([$user_id]);
    $links = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT * FROM design_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch() ?: [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // Handle photo upload
        $photo_path = $user['photo'] ?? null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['photo']);
            if ($upload && isset($upload['success']) && $upload['success']) {
                $photo_path = $upload['url'];
            } else {
                $upload_errors[] = 'Photo upload failed: ' . ($upload['error'] ?? 'Unknown error');
            }
        }


        // Handle company logo upload
        $company_logo_path = $user['company_logo'] ?? null;
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['company_logo']);
            if ($upload && isset($upload['success']) && $upload['success']) {
                $company_logo_path = $upload['url'];
            } else {
                $upload_errors[] = 'Company logo upload failed: ' . ($upload['error'] ?? 'Unknown error');
            }
        }

        // Save user basic info
        if ($is_edit) {
            $stmt = $db->prepare("
                UPDATE users SET
                    firstname = ?, lastname = ?, company = ?, position = ?,
                    company_logo = ?, location = ?, mobile = ?, mobile1 = ?, email = ?,
                    photo = ?, design_template = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['firstname'], $_POST['lastname'], $_POST['company'], $_POST['position'],
                $company_logo_path, $_POST['location'], $_POST['mobile'], $_POST['mobile1'],
                $_POST['email'], $photo_path, $_POST['design_template'], $user_id
            ]);
        } else {
            // Generate agent_id and referral_code
            $agent_id = generateRandomString(8);
            $referral_code = generateRandomString(6);

            $stmt = $db->prepare("
                INSERT INTO users (firstname, lastname, company, position, company_logo, location, mobile, mobile1, email, photo, design_template, agent_id, referral_code)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['firstname'], $_POST['lastname'], $_POST['company'], $_POST['position'],
                $company_logo_path, $_POST['location'], $_POST['mobile'], $_POST['mobile1'],
                $_POST['email'], $photo_path, $_POST['design_template'], $agent_id, $referral_code
            ]);
            $user_id = $db->lastInsertId();
        }
        
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
                    // Check if exists and if changed
                    $stmt = $db->prepare("SELECT link, title FROM social_media WHERE agent_id = ? AND platform = ?");
                    $stmt->execute([$user_id, $platform]);
                    $existing = $stmt->fetch();
                    if ($existing && $existing['link'] == $value && $existing['title'] == $title) {
                        continue; // No changes, skip
                    }
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

        $db->commit();

        logActivity($_SESSION['admin_id'], $user_id, $is_edit ? 'update_user' : 'create_user',
            $is_edit ? 'User updated' : 'User created');

        redirect('users.php');
    } catch(Exception $e) {
        $db->rollBack();
        $error = "Error saving user: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - BumpCard Admin</title>
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
                <h1 class="text-3xl font-bold text-gray-800">Edit User</h1>
                <p class="text-gray-600 mt-1">Manage user information and digital card content</p>
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
            <input type="hidden" id="current_user_id" value="<?php echo $user_id; ?>">
            <!-- Basic Information -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Basic Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">First Name *</label>
                        <input type="text" name="firstname" value="<?php echo htmlspecialchars($user['firstname'] ?? ''); ?>" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Last Name *</label>
                        <input type="text" name="lastname" value="<?php echo htmlspecialchars($user['lastname'] ?? ''); ?>" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Company</label>
                        <input type="text" name="company" value="<?php echo htmlspecialchars($user['company'] ?? ''); ?>" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Position</label>
                        <input type="text" name="position" value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
                        <textarea name="location" rows="2"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"><?php echo htmlspecialchars($user['location'] ?? ''); ?></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Profile Photo</label>
                        <input type="file" name="photo" accept="image/*" onchange="previewImage(this, 'photo-preview')"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <div class="mt-2">
                            <img id="photo-preview" src="<?php echo htmlspecialchars($user['photo'] ?? ''); ?>" alt="Profile Photo" class="w-20 h-20 object-cover rounded-lg border border-gray-200 <?php echo empty($user['photo']) ? 'hidden' : ''; ?>">
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Company Logo</label>
                        <input type="file" name="company_logo" accept="image/*" onchange="previewImage(this, 'logo-preview')"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <div class="mt-2">
                            <img id="logo-preview" src="<?php echo htmlspecialchars($user['company_logo'] ?? ''); ?>" alt="Company Logo" class="w-20 h-20 object-cover rounded-lg border border-gray-200 <?php echo empty($user['company_logo']) ? 'hidden' : ''; ?>">
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
                        <input type="tel" name="mobile" value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Secondary Mobile</label>
                        <input type="tel" name="mobile1" value="<?php echo htmlspecialchars($user['mobile1'] ?? ''); ?>" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email *</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
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
                        <input type="text" name="bio_title" value="<?php echo htmlspecialchars($bio['title'] ?? ''); ?>" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Bio Description</label>
                        <textarea name="bio_description" rows="4" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"><?php echo htmlspecialchars($bio['description'] ?? ''); ?></textarea>
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
                    <?php
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
                    foreach ($social as $platform => $links) {
                        $link = $links[0]['link'] ?? '';
                        $name = $links[0]['title'] ?? $platform;
                        $logo = $links[0]['custom_logo'] ?? $logo_map[$platform] ?? '../images/verified.png';
                        echo "<div class='flex items-center space-x-4 p-4 bg-gray-50 rounded-lg'>
                            <img src='$logo' alt='$name' class='w-8 h-8'>
                            <input type='hidden' name='social_" . strtolower($platform) . "_title' value='$name'>
                            <input type='url' name='social_" . strtolower($platform) . "' value='" . htmlspecialchars($link) . "'
                                class='flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500' placeholder='https://...'>
                            <button type='button' onclick='editSocialMedia(this)' class='text-blue-600 hover:text-blue-700 mr-2'>
                                <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'/>
                                </svg>
                            </button>
                            <button type='button' onclick='removeSocialMedia(this, \"$platform\")' class='text-red-600 hover:text-red-700'>
                                <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'/>
                                </svg>
                            </button>
                        </div>";
                    }
                    ?>
                </div>
            </div>

            <!-- Design Template -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Design Template</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <label class="relative cursor-pointer">
                        <input type="radio" name="design_template" value="design-1" 
                            <?php echo ($user['design_template'] ?? 'design-1') === 'design-1' ? 'checked' : ''; ?> 
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
                            <?php echo ($user['design_template'] ?? '') === 'design-2' ? 'checked' : ''; ?> 
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
                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-orange-500 to-yellow-500 text-white font-semibold rounded-lg hover:from-orange-600 hover:to-yellow-600 transition">
                    Update User
                </button>
            </div>
        </form>

        <?php if ($is_edit): ?>
        <!-- Additional Management Sections -->
        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Bank Accounts -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">Bank Accounts</h2>
                    <button onclick="alert('Feature coming soon')" class="text-sm text-orange-600 hover:text-orange-700">+ Add Bank</button>
                </div>
                <div class="space-y-2">
                    <?php foreach ($banks as $bank): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div>
                            <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($bank['bank_name']); ?></p>
                            <p class="text-xs text-gray-600"><?php echo htmlspecialchars($bank['account_no']); ?></p>
                        </div>
                        <button class="text-red-600 hover:text-red-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($banks)): ?>
                    <p class="text-sm text-gray-500 text-center py-4">No bank accounts added yet</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Videos -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">Videos</h2>
                    <button onclick="alert('Feature coming soon')" class="text-sm text-orange-600 hover:text-orange-700">+ Add Video</button>
                </div>
                <div class="space-y-2">
                    <?php foreach ($videos as $video): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div>
                            <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($video['title_content']); ?></p>
                            <p class="text-xs text-gray-600"><?php echo htmlspecialchars($video['video_type']); ?></p>
                        </div>
                        <button class="text-red-600 hover:text-red-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($videos)): ?>
                    <p class="text-sm text-gray-500 text-center py-4">No videos added yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
            let html = `
                <img src="${logo}" alt="${name}" class="w-8 h-8">
                <input type="hidden" name="social_${platform.toLowerCase()}_title" value="${name}">
                <input type="url" name="social_${platform.toLowerCase()}" value="${url}"
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="https://...">
                <button type="button" onclick="editSocialMedia(this)" class="text-blue-600 hover:text-blue-700 mr-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </button>
                <button type="button" onclick="removeSocialMedia(this, '${platform}')" class="text-red-600 hover:text-red-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            `;
            if (customLogo) {
                html += `<input type="hidden" name="social_${platform.toLowerCase()}_logo" value="${customLogo}">`;
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
    </script>
</body>
</html>