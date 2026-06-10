<?php
$log_file = __DIR__ . '/error_log';
if (file_exists($log_file)) {
    $lines = file($log_file);
    $last = array_slice($lines, -50);
    echo "<pre>";
    foreach ($last as $l) {
        echo htmlspecialchars($l);
    }
    echo "</pre>";
} else {
    echo "No error_log found.";
}
