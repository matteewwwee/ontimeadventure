<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE users ADD COLUMN nama VARCHAR(100) DEFAULT '' AFTER id_user");
    echo "Column nama added.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column nama already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
// Set default admin name if exists
$db->exec("UPDATE users SET nama = 'Administrator' WHERE role = 'admin' AND nama = ''");
$db->exec("UPDATE users SET nama = no_hp WHERE role != 'admin' AND nama = ''");
echo "\nDefault names set.";
?>
