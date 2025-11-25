<?php
session_start();
require_once '../api/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$db = getDB();

// Get all contact submissions
$search = $_GET['search'] ?? '';
$sql = "SELECT cs.*, CONCAT(u.firstname, ' ', u.lastname) as card_owner, u.company
        FROM contact_submissions cs
        JOIN users u ON cs.user_id = u.id 
        WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (cs.firstname LIKE ? OR cs.lastname LIKE ? OR cs.email LIKE ? OR cs.contact_number LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ?)";
}

$sql .= " ORDER BY cs.submitted_at DESC";

$stmt = $db->prepare($sql);

if (!empty($search)) {
    $searchTerm = "%$search%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
} else {
    $stmt->execute();
}

$contacts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Submissions - BumpCard Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div id="main-content" class="ml-64 p-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Contact Submissions</h1>
            <p class="text-gray-600 mt-1">View all contact form submissions from digital cards</p>
        </div>

        <!-- Search -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <form method="GET" class="flex gap-4">
                <div class="flex-1">
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="Search by name, email, or contact number..." 
                        value="<?php echo htmlspecialchars($search); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
                    >
                </div>
                <button type="submit" class="px-6 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 transition">
                    Search
                </button>
                <?php if (!empty($search)): ?>
                <a href="contacts.php" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Clear
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Contacts Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Submitted By</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Contact Info</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">For Card Owner</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Submitted At</th>
                            <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($contacts as $contact): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
                                        <span class="text-sm font-semibold text-gray-600">
                                            <?php echo strtoupper(substr($contact['firstname'], 0, 1) . substr($contact['lastname'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800">
                                            <?php 
                                            $fullname = $contact['firstname'];
                                            if (!empty($contact['middlename'])) $fullname .= ' ' . $contact['middlename'];
                                            $fullname .= ' ' . $contact['lastname'];
                                            echo htmlspecialchars($fullname); 
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if (!empty($contact['contact_number'])): ?>
                                <p class="text-sm text-gray-800">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                    <?php echo htmlspecialchars($contact['contact_number']); ?>
                                </p>
                                <?php endif; ?>
                                <?php if (!empty($contact['email'])): ?>
                                <p class="text-sm text-gray-600 mt-1">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    <?php echo htmlspecialchars($contact['email']); ?>
                                </p>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($contact['card_owner']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($contact['company']); ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-800">
                                    <?php echo date('M j, Y', strtotime($contact['submitted_at'])); ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?php echo date('g:i A', strtotime($contact['submitted_at'])); ?>
                                </p>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center space-x-2">
                                    <a href="user_edit.php?id=<?php echo $contact['user_id']; ?>" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View Card Owner">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($contacts)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                No contact submissions found.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Stats Summary -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Submissions</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo count($contacts); ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-semibold text-gray-600 mb-2">Today</h3>
                <p class="text-3xl font-bold text-gray-800">
                    <?php 
                    $today = array_filter($contacts, function($c) {
                        return date('Y-m-d', strtotime($c['submitted_at'])) === date('Y-m-d');
                    });
                    echo count($today);
                    ?>
                </p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-semibold text-gray-600 mb-2">This Week</h3>
                <p class="text-3xl font-bold text-gray-800">
                    <?php 
                    $week_start = date('Y-m-d', strtotime('monday this week'));
                    $thisWeek = array_filter($contacts, function($c) use ($week_start) {
                        return date('Y-m-d', strtotime($c['submitted_at'])) >= $week_start;
                    });
                    echo count($thisWeek);
                    ?>
                </p>
            </div>
        </div>
    </div>
</body>
</html>