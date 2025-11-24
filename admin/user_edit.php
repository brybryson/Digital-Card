<?php
session_start();
require_once '../api/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$db = getDB();
$user_id = $_GET['id'] ?? null;
$is_edit = !empty($user_id);

$user = $bio = $social = $banks = $videos = $links = $settings = [];

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
    
    $stmt = $db->prepare("SELECT * FROM social_media WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $social = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
    
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
        
        // Save user basic info
        if ($is_edit) {
            $stmt = $db->prepare("
                UPDATE users SET 
                    firstname = ?, lastname = ?, company = ?, position = ?, 
                    address = ?, mobile = ?, mobile1 = ?, mobile2 = ?, email = ?, 
                    design_template = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['firstname'], $_POST['lastname'], $_POST['company'], $_POST['position'],
                $_POST['address'], $_POST['mobile'], $_POST['mobile1'], $_POST['mobile2'], 
                $_POST['email'], $_POST['design_template'], $user_id
            ]);
        } else {
            // Generate agent_id and referral_code
            $agent_id = generateRandomString(8);
            $referral_code = generateRandomString(6);

            $stmt = $db->prepare("
                INSERT INTO users (firstname, lastname, company, position, address, mobile, mobile1, mobile2, email, design_template, agent_id, referral_code)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['firstname'], $_POST['lastname'], $_POST['company'], $_POST['position'],
                $_POST['address'], $_POST['mobile'], $_POST['mobile1'], $_POST['mobile2'],
                $_POST['email'], $_POST['design_template'], $agent_id, $referral_code
            ]);
            $user_id = $db->lastInsertId();
        }
        
        // Save bio
        $stmt = $db->prepare("INSERT INTO user_bio (user_id, title, description) VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description)");
        $stmt->execute([$user_id, $_POST['bio_title'], $_POST['bio_description']]);
        
        // Save social media
        foreach (['FB', 'IG'] as $platform) {
            $link = $_POST['social_' . strtolower($platform)] ?? '';
            if (!empty($link)) {
                $stmt = $db->prepare("INSERT INTO social_media (user_id, platform, link, status) 
                    VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE link = VALUES(link)");
                $stmt->execute([$user_id, $platform, $link]);
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
    <title><?php echo $is_edit ? 'Edit' : 'Add'; ?> User - BumpCard Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'includes/sidebar.php'; ?>

    <div class="ml-64 p-8">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-800"><?php echo $is_edit ? 'Edit' : 'Add New'; ?> User</h1>
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

        <form method="POST" enctype="multipart/form-data">
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
                        <textarea name="address" id="address" rows="2"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        <button type="button" onclick="getCurrentLocation()" class="mt-2 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">Get Current Location</button>
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
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tertiary Mobile</label>
                        <input type="tel" name="mobile2" value="<?php echo htmlspecialchars($user['mobile2'] ?? ''); ?>" 
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
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Social Media Links</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Facebook URL</label>
                        <input type="url" name="social_fb" value="<?php echo htmlspecialchars($social['FB'][0]['link'] ?? ''); ?>" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="https://facebook.com/...">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Instagram URL</label>
                        <input type="url" name="social_ig" value="<?php echo htmlspecialchars($social['IG'][0]['link'] ?? ''); ?>" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="https://instagram.com/...">
                    </div>
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
                    <?php echo $is_edit ? 'Update User' : 'Create User'; ?>
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

    <script>
        // Get current location
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;

                    // Use reverse geocoding to get address (free BigDataCloud API)
                    fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=en`)
                        .then(response => response.json())
                        .then(data => {
                            if (data && data.localityInfo && data.localityInfo.administrative) {
                                const admin = data.localityInfo.administrative;
                                const address = `${admin[admin.length - 1].name}, ${admin[admin.length - 2].name}, ${data.countryName}`;
                                document.getElementById('address').value = address;
                            } else if (data && data.city) {
                                document.getElementById('address').value = `${data.city}, ${data.countryName}`;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Unable to get address from location');
                        });
                }, function(error) {
                    alert('Unable to get your location. Please check your browser settings.');
                });
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        }
    </script>
</body>
</html>