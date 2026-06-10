<?php
require 'config/database.php';

$app_settings = [
    'wa_template_siap' => "Halo Kak [NAMA_PELANGGAN]! 👋\nKami dari pihak On Time Adventure mengucapkan terima kasih banyak karena sudah mempercayakan kebutuhan camping Kakak pada kami. 😊\n\nYeay, kabar gembira nih! Perlengkapan alat camping untuk pesanan Kakak ([NOMOR_PO]) sudah siap dan bisa langsung diambil di basecamp On Time Adventure ya. ⛺✨\n\nOiya, untuk pembayarannya nanti mohon siapkan uang pas (cash) ya, Kak! 💵 Tapi tenang aja, kalau nggak bawa cash, kami juga menyediakan pembayaran via QRIS dan Transfer Bank kok! 💳📱\n\nBiar nggak nyasar, Kakak bisa cek lokasi kami di sini ya:\n📍 https://maps.app.goo.gl/nwTFhVah2qhet7so8\n\nDitunggu kedatangannya, Kak! Semoga petualangannya nanti seru dan lancar jaya! ⛰️🎒",
];
$db = getDB();
$settings_query = $db->query("SELECT kunci, nilai FROM pengaturan");
while ($row = $settings_query->fetch()) {
    if (in_array($row['kunci'], ['wa_template_siap']) && trim($row['nilai']) === '') {
        continue;
    }
    $app_settings[$row['kunci']] = $row['nilai'];
}

echo "DB FETCH RESULT:\n";
var_dump($app_settings['wa_template_siap']);
