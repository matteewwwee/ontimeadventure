<?php
require 'config/database.php';
$app_settings = [
    'wa_template_siap' => 'DEFAULT SIAP',
    'wa_template_batal' => 'DEFAULT BATAL',
];
$db = getDB();
$settings_query = $db->query("SELECT kunci, nilai FROM pengaturan");
while ($row = $settings_query->fetch()) {
    if (in_array($row['kunci'], ['wa_template_siap', 'wa_template_batal', 'wa_template_pengembalian', 'telegram_message_template']) && trim($row['nilai']) === '') {
        continue;
    }
    $app_settings[$row['kunci']] = $row['nilai'];
}
print_r($app_settings);
