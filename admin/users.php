<?php
session_start();
require_once '../api/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$db = getDB();

// Handle user status toggle
if (isset($_GET['toggle_status'])) {
    $user_id = (int)$_GET['toggle_status'];
    $stmt = $db->prepare("UPDATE users SET status = NOT status WHERE id = ?");
    $stmt->execute([$user_id]);
    logActivity($_SESSION['admin_id'], $user_id, 'toggle_status', 'User status toggled');
    redirect('users.php');
}

// Get all users
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';

$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM contact_submissions WHERE user_id = u.id) as total_contacts
        FROM users u 
        WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (u.firstname LIKE ? OR u.lastname LIKE ? OR u.middlename LIKE ? OR u.email LIKE ? OR u.company LIKE ? OR u.mobile LIKE ?)";
}

if ($filter !== 'all') {
    $sql .= " AND u.design_template = ?";
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql);

if (!empty($search) && $filter !== 'all') {
    $searchTerm = "%$search%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $filter]);
} elseif (!empty($search)) {
    $searchTerm = "%$search%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
} elseif ($filter !== 'all') {
    $stmt->execute([$filter]);
} else {
    $stmt->execute();
}

$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - BumpCard Admin</title>
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
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Users Management</h1>
                <p class="text-gray-600 mt-1">Manage all digital card users</p>
            </div>
            <a href="user_add.php" class="px-6 py-3 bg-gradient-to-r from-orange-500 to-yellow-500 text-white font-semibold rounded-lg hover:from-orange-600 hover:to-yellow-600 transition">
                + Add New User
            </a>
        </div>

        <!-- Search and Filter -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <form method="GET" id="filterForm" class="flex gap-4">
                <div class="flex-1">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search by name, email, or company..."
                        value="<?php echo htmlspecialchars($search); ?>"
                        onblur="submitForm()"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
                    >
                </div>
                <select name="filter" onchange="submitForm()" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Templates</option>
                    <option value="design-1" <?php echo $filter === 'design-1' ? 'selected' : ''; ?>>Design 1</option>
                    <option value="design-2" <?php echo $filter === 'design-2' ? 'selected' : ''; ?>>Design 2</option>
                </select>
                <button type="submit" class="px-6 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 transition">
                    Search
                </button>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">User</th>
                            <th class="px-10 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Company</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Contact</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Template</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Referrals</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Contacts</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3">
                                <div class="flex items-center space-x-3">
                                    <?php if (!empty($user['photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['photo']); ?>" alt="Profile Photo" class="w-12 h-12 rounded-full object-cover border-2 border-gray-200">
                                    <?php else: ?>
                                    <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center">
                                        <span class="text-sm font-semibold text-gray-600">
                                            <?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800">
                                            <?php
                                            $fullName = $user['firstname'];
                                            if (!empty($user['middlename'])) {
                                                $fullName .= ' ' . substr($user['middlename'], 0, 1) . '.';
                                            }
                                            $fullName .= ' ' . $user['lastname'];
                                            echo htmlspecialchars($fullName);
                                            ?>
                                        </p>
                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-10 py-3">
                                <p class="text-sm text-gray-800 truncate"><?php echo htmlspecialchars($user['company']); ?></p>
                                <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($user['position']); ?></p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm text-gray-800"><?php echo htmlspecialchars($user['mobile']); ?></p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs font-medium rounded-full whitespace-nowrap <?php echo $user['design_template'] === 'design-1' ? 'bg-purple-100 text-purple-700' : 'bg-orange-100 text-orange-700'; ?>">
                                    <?php echo $user['design_template']; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-sm font-semibold text-gray-800"><?php echo $user['referral_count']; ?></span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-sm font-semibold text-gray-800"><?php echo $user['total_contacts']; ?></span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $user['status'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                    <?php echo $user['status'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center space-x-2">
                                    <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <button type="button" onclick="openToggleModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>', <?php echo $user['status'] ? 'true' : 'false'; ?>)" class="p-2 text-yellow-600 hover:bg-yellow-50 rounded-lg transition" title="Toggle Status">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                        </svg>
                                    </button>
                                    <a href="../preview.php?id=<?php echo $user['id']; ?>" target="_blank" class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition" title="Preview Card">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-gray-500">
                                No users found. Try adjusting your search or filter.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Toggle Status Modal -->
    <div id="toggleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg max-w-md w-full mx-4">
            <div class="p-10">
                <h3 class="text-xl font-semibold text-gray-800 mb-8 text-center">Toggle User Status</h3>
                <div class="text-center mb-8">
                    <p class="text-gray-600 mb-6">
                        Are you sure you want to change the status of <strong id="toggleUserName"></strong>?
                    </p>
                    <p class="text-sm text-gray-500">
                        From <span id="currentStatus" class="font-medium"></span> to <span id="newStatus" class="font-medium text-yellow-600"></span>
                    </p>
                </div>
                <div class="flex justify-center space-x-6">
                    <button type="button" onclick="closeToggleModal()" class="px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition">Cancel</button>
                    <form method="GET" style="display: inline;">
                        <input type="hidden" name="toggle_status" id="toggleUserId">
                        <button type="submit" class="px-6 py-3 bg-yellow-500 text-white font-semibold rounded-lg hover:bg-yellow-600 transition">Confirm</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function submitForm() {
            document.getElementById('filterForm').submit();
        }

        function openToggleModal(userId, userName, currentStatus) {
            document.getElementById('toggleUserId').value = userId;
            document.getElementById('toggleUserName').textContent = userName;
            document.getElementById('currentStatus').textContent = currentStatus ? 'Active' : 'Inactive';
            document.getElementById('newStatus').textContent = currentStatus ? 'Inactive' : 'Active';
            document.getElementById('toggleModal').classList.remove('hidden');
        }

        function closeToggleModal() {
            document.getElementById('toggleModal').classList.add('hidden');
        }
    </script>

</body>
</html>