<?php
/**
 * Sync1C Installation Script
 * Run once: https://stunnerboyone.live/install-sync1c.php?key=ekokrai2024
 * DELETE THIS FILE AFTER USE!
 */

if (!isset($_GET['key']) || $_GET['key'] !== 'ekokrai2024') {
    die('Access denied');
}

require_once('overlord/config.php');

$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);

if ($db->connect_error) {
    die('Database connection failed: ' . $db->connect_error);
}

$db->set_charset('utf8mb4');

echo "<pre style='font-family: monospace; padding: 20px;'>";
echo "Installing Sync1C tables...\n\n";

// Create product_to_1c table
$sql1 = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "product_to_1c` (
    `product_id` int(11) NOT NULL,
    `guid` varchar(36) NOT NULL,
    `date_synced` datetime DEFAULT NULL,
    PRIMARY KEY (`product_id`),
    UNIQUE KEY `guid` (`guid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($db->query($sql1)) {
    echo "OK: product_to_1c table created\n";
} else {
    echo "ERROR: " . $db->error . "\n";
}

// Create category_to_1c table
$sql2 = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "category_to_1c` (
    `category_id` int(11) NOT NULL,
    `guid` varchar(36) NOT NULL,
    `date_synced` datetime DEFAULT NULL,
    PRIMARY KEY (`category_id`),
    UNIQUE KEY `guid` (`guid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($db->query($sql2)) {
    echo "OK: category_to_1c table created\n";
} else {
    echo "ERROR: " . $db->error . "\n";
}

// Create order_to_1c table
$sql3 = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "order_to_1c` (
    `order_id` int(11) NOT NULL,
    `exported` tinyint(1) NOT NULL DEFAULT 0,
    `date_exported` datetime DEFAULT NULL,
    `guid_1c` varchar(36) DEFAULT NULL,
    PRIMARY KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($db->query($sql3)) {
    echo "OK: order_to_1c table created\n";
} else {
    echo "ERROR: " . $db->error . "\n";
}

// Add default settings
$sql4 = "INSERT IGNORE INTO `" . DB_PREFIX . "setting` (`store_id`, `code`, `key`, `value`, `serialized`) VALUES
(0, 'sync1c', 'sync1c_username', 'admin', 0),
(0, 'sync1c', 'sync1c_password', 'ekokrai1c', 0),
(0, 'sync1c', 'sync1c_status', '1', 0)";

if ($db->query($sql4)) {
    echo "OK: Default settings added\n";
} else {
    echo "ERROR: " . $db->error . "\n";
}

// Create storage directory
$storage_dir = DIR_STORAGE . 'sync1c/';
if (!is_dir($storage_dir)) {
    if (mkdir($storage_dir, 0755, true)) {
        echo "OK: Storage directory created\n";
    } else {
        echo "WARNING: Could not create storage directory\n";
    }
} else {
    echo "OK: Storage directory exists\n";
}

$db->close();

echo "\n=================================\n";
echo "INSTALLATION COMPLETE!\n";
echo "=================================\n\n";

echo "Default login: admin\n";
echo "Default password: ekokrai1c\n\n";

echo "1C Exchange URL: https://stunnerboyone.live/sync1c.php\n\n";

echo "Admin panel: /overlord/ -> Extensions -> Modules -> Sync 1C\n\n";

echo "*** DELETE THIS FILE (install-sync1c.php) NOW! ***\n";
echo "</pre>";
