<?php
require 'config/database.php';
$db = getDB();
$settings_query = $db->query("SELECT kunci, nilai FROM pengaturan");
while ($row = $settings_query->fetch()) {
    if (strpos($row['kunci'], 'wa_') !== false) {
        echo $row['kunci'] . ":\n";
        var_dump($row['nilai']);
        echo bin2hex($row['nilai']) . "\n\n";
    }
}
