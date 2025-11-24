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
            <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-gray-700' : 'hover:bg-gray-700'; ?> rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span>Dashboard</span>
            </a>
            <a href="users.php" class="flex items-center space-x-3 px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' || basename($_SERVER['PHP_SELF']) === 'user_edit.php' ? 'bg-gray-700' : 'hover:bg-gray-700'; ?> rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                <span>Users</span>
            </a>
            <a href="contacts.php" class="flex items-center space-x-3 px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) === 'contacts.php' ? 'bg-gray-700' : 'hover:bg-gray-700'; ?> rounded-lg transition">
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