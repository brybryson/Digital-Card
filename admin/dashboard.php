<?php
session_start();
require_once '../api/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$db = getDB();

// Get statistics
$stats = [];

// Total users
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE status = 1");
$stats['total_users'] = $stmt->fetch()['count'];

// Total contacts submitted
$stmt = $db->query("SELECT COUNT(*) as count FROM contact_submissions");
$stats['total_contacts'] = $stmt->fetch()['count'];

// Users by design template
$stmt = $db->query("SELECT design_template, COUNT(*) as count FROM users GROUP BY design_template");
$stats['by_template'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Recent users
$stmt = $db->query("
    SELECT id, CONCAT(firstname, ' ', lastname) as name, company, email, design_template, created_at 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recent_users = $stmt->fetchAll();

// Recent contact submissions
$stmt = $db->query("
    SELECT cs.*, CONCAT(u.firstname, ' ', u.lastname) as card_owner 
    FROM contact_submissions cs
    JOIN users u ON cs.user_id = u.id
    ORDER BY cs.submitted_at DESC 
    LIMIT 5
");
$recent_contacts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BumpCard Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 w-64 bg-gradient-to-b from-gray-900 to-gray-800 text-white z-50">
        <div class="p-6">
            <div class="flex items-center space-x-3 mb-8">
                <div class="w-10 h-10 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 2a8 8 0 100 16 8 8 0 000-16zM9 9a1 1 0 012 0v4a1 1 0 11-2 0V9zm1-5a1 1 0 100 2 1 1 0 000-2z"/>
                    </svg>
                </div>
                <span class="text-xl font-bold">BumpCard</span>
            </div>

            <nav class="space-y-2">
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 bg-gray-700 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span>Dashboard</span>
                </a>
                <a href="users.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-gray-700 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <span>Users</span>
                </a>
                <a href="contacts.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-gray-700 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <span>Contact Submissions</span>
                </a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-gray-700 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span>Logout</span>
                </a>
            </nav>
        </div>

        <div class="absolute bottom-0 left-0 right-0 p-6 border-t border-gray-700">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-gray-700 rounded-full flex items-center justify-center">
                    <span class="text-sm font-semibold"><?php echo strtoupper(substr($_SESSION['admin_name'], 0, 2)); ?></span>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></p>
                    <p class="text-xs text-gray-400"><?php echo ucfirst($_SESSION['admin_role']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="ml-64 p-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
            <p class="text-gray-600 mt-1">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>!</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                </div>
                <h3 class="text-gray-600 text-sm font-medium">Total Users</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['total_users']; ?></p>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
                <h3 class="text-gray-600 text-sm font-medium">Contact Submissions</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['total_contacts']; ?></p>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                        </svg>
                    </div>
                </div>
                <h3 class="text-gray-600 text-sm font-medium">Design 1</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['by_template']['design-1'] ?? 0; ?></p>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                        </svg>
                    </div>
                </div>
                <h3 class="text-gray-600 text-sm font-medium">Design 2</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['by_template']['design-2'] ?? 0; ?></p>
            </div>
        </div>

        <!-- Recent Users and Contacts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Users -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-6 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Recent Users</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <?php foreach ($recent_users as $user): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
                                    <span class="text-sm font-semibold text-gray-600"><?php echo strtoupper(substr($user['name'], 0, 2)); ?></span>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($user['name']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user['company']); ?></p>
                                </div>
                            </div>
                            <span class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded"><?php echo $user['design_template']; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="users.php" class="block text-center text-sm text-blue-600 hover:text-blue-700 mt-4">View all users →</a>
                </div>
            </div>

            <!-- Recent Contacts -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-6 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Recent Contact Submissions</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <?php foreach ($recent_contacts as $contact): ?>
                        <div class="border-b border-gray-100 pb-3 last:border-0">
                            <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($contact['firstname'] . ' ' . $contact['lastname']); ?></p>
                            <p class="text-xs text-gray-500">For: <?php echo htmlspecialchars($contact['card_owner']); ?></p>
                            <p class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($contact['email'] ?? $contact['contact_number']); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="contacts.php" class="block text-center text-sm text-blue-600 hover:text-blue-700 mt-4">View all contacts →</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>