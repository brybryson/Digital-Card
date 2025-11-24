<?php
require_once '../api/config.php';

try {
    $db = getDB();

    // Create admin_users table if it doesn't exist
    $db->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            role ENUM('admin', 'super_admin') DEFAULT 'admin',
            status TINYINT(1) DEFAULT 1,
            last_login DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Check if admin user exists
    $stmt = $db->prepare("SELECT id FROM admin_users WHERE username = ?");
    $stmt->execute(['admin']);
    $exists = $stmt->fetch();

    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);

    if (!$exists) {
        // Create default admin user
        $stmt = $db->prepare("
            INSERT INTO admin_users (username, password, full_name, email, role)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute(['admin', $hashed_password, 'System Administrator', 'admin@bumpcard.com', 'super_admin']);

        echo "Default admin user created successfully!<br>";
    } else {
        // Update password to ensure it's correct
        $stmt = $db->prepare("UPDATE admin_users SET password = ? WHERE username = ?");
        $stmt->execute([$hashed_password, 'admin']);
        echo "Admin user password updated.<br>";
    }

    echo "Username: admin<br>";
    echo "Password: admin123<br>";

    // Create other necessary tables if needed
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bid VARCHAR(50) UNIQUE NOT NULL,
            firstname VARCHAR(50) NOT NULL,
            lastname VARCHAR(50) NOT NULL,
            company VARCHAR(100),
            position VARCHAR(100),
            mobile VARCHAR(20),
            mobile1 VARCHAR(20),
            email VARCHAR(100),
            address TEXT,
            photo_path VARCHAR(255),
            bio_title VARCHAR(255),
            bio_description TEXT,
            status TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT,
            user_id INT,
            action VARCHAR(50) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");

    echo "Database setup completed successfully!";

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>