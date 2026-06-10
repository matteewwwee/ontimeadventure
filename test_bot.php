<?php
// FILE TESTING PALING SEDERHANA DI DUNIA
// File ini sama sekali tidak memuat database, tidak memproses logika, dan tidak ngapa-ngapain.
// Tujuannya hanya satu: Membuktikan apakah Telegram diizinkan menyentuh file apa pun di hosting ini.

$log_entry = "[" . date('H:i:s') . "] TELEGRAM BERHASIL MASUK!\n";
file_put_contents('log_testing.txt', $log_entry, FILE_APPEND);

http_response_code(200);
echo "OK";
?>
