<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();

$db = getDB();
$current_item_id = isset($_GET['id_item']) ? (int)$_GET['id_item'] : 0;

if ($current_item_id == 0) {
    die("Item tidak valid.");
}

// LANGKAH 1: Ambil Semua Item
$stmt_all = $db->query("
    SELECT
        i.id_item,
        i.nama_brand,
        i.nama_seri,
        i.deskripsi_umum,
        i.gambar,
        k.nama_kategori
    FROM item i
    JOIN kategori_item k ON i.id_kategori = k.id_kategori
");
$all_items = $stmt_all->fetchAll();

// LANGKAH 2: Pembuatan Dokumen & Pembobotan
$documents = [];
$item_data = [];
$documents_raw = [];

foreach ($all_items as $row_item) {
    $id = $row_item['id_item'];
    
    $documents_raw[$id] = [
        'kategori' => $row_item['nama_kategori'],
        'brand' => $row_item['nama_brand'],
        'seri' => $row_item['nama_seri'],
        'deskripsi' => $row_item['deskripsi_umum']
    ];

    $kategori_weighted = str_repeat($row_item['nama_kategori'] . ' ', 3);
    $brand_weighted    = str_repeat($row_item['nama_brand'] . ' ', 2);
    $seri              = $row_item['nama_seri'] . ' ';
    $deskripsi         = ($row_item['deskripsi_umum'] ?? '') . ' ';

    $documents[$id] = $kategori_weighted . $brand_weighted . $seri . $deskripsi;
    $item_data[$id] = $row_item;
}

// LANGKAH 3: Tokenisasi
$tokenized_docs = [];
foreach ($documents as $id => $doc) {
    $text = strtolower($doc);
    $text = preg_replace('/[^a-z0-9\s]/u', ' ', $text);
    $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $tokenized_docs[$id] = $tokens;
}

// LANGKAH 5 & 6: Hitung TF dan IDF
$tf = [];
foreach ($tokenized_docs as $id => $tokens) {
    $total_terms = count($tokens);
    $term_counts = array_count_values($tokens);
    $tf[$id] = [];
    foreach ($term_counts as $term => $count) {
        $tf[$id][$term] = $count / $total_terms;
    }
}

$total_documents = count($tokenized_docs);
$idf = [];
$df = [];
foreach ($tokenized_docs as $tokens) {
    $unique_terms = array_unique($tokens);
    foreach ($unique_terms as $term) {
        if (!isset($df[$term])) $df[$term] = 0;
        $df[$term]++;
    }
}
foreach ($df as $term => $doc_freq) {
    $idf[$term] = log($total_documents / $doc_freq);
}

// LANGKAH 7: Hitung TF-IDF Vector
$tfidf_vectors = [];
foreach ($tf as $id => $tf_doc) {
    $tfidf_vectors[$id] = [];
    foreach ($tf_doc as $term => $tf_value) {
        $idf_value = $idf[$term] ?? 0;
        $tfidf_vectors[$id][$term] = $tf_value * $idf_value;
    }
}

// LANGKAH 8: Cosine Similarity
function dotProduct(array $vec_a, array $vec_b, &$breakdown = []) {
    $dot = 0.0;
    $breakdown = [];
    foreach ($vec_a as $term => $val_a) {
        if (isset($vec_b[$term]) && $vec_b[$term] > 0 && $val_a > 0) {
            $prod = $val_a * $vec_b[$term];
            $dot += $prod;
            $breakdown[$term] = [
                'val_a' => $val_a,
                'val_b' => $vec_b[$term],
                'prod' => $prod
            ];
        }
    }
    uasort($breakdown, function($a, $b) { return $b['prod'] <=> $a['prod']; });
    return $dot;
}
function vectorMagnitude(array $vec, &$breakdown = []) {
    $sum_sq = 0.0;
    $breakdown = [];
    foreach ($vec as $term => $val) {
        if ($val > 0) {
            $sq = $val * $val;
            $sum_sq += $sq;
            $breakdown[$term] = [
                'val' => $val,
                'sq' => $sq
            ];
        }
    }
    uasort($breakdown, function($a, $b) { return $b['sq'] <=> $a['sq']; });
    return sqrt($sum_sq);
}

$current_vector = $tfidf_vectors[$current_item_id] ?? [];
$mag_target_breakdown = [];
$mag_current = vectorMagnitude($current_vector, $mag_target_breakdown);
$similarities = [];
$similarity_details = [];

foreach ($tfidf_vectors as $id => $vector) {
    if ($id == $current_item_id) continue;
    
    $breakdown = [];
    $dot = dotProduct($current_vector, $vector, $breakdown);
    
    $mag_other_breakdown = [];
    $mag_other = vectorMagnitude($vector, $mag_other_breakdown);
    $sim = ($mag_current == 0 || $mag_other == 0) ? 0.0 : ($dot / ($mag_current * $mag_other));
    
    $similarities[$id] = $sim;
    $similarity_details[$id] = [
        'dot' => $dot,
        'mag_target' => $mag_current,
        'mag_other' => $mag_other,
        'breakdown' => $breakdown,
        'mag_target_breakdown' => $mag_target_breakdown,
        'mag_other_breakdown' => $mag_other_breakdown
    ];
}

arsort($similarities);
$limit_cbf = 5; // Export top 5 for neatness
$top_ids = array_slice(array_keys($similarities), 0, $limit_cbf, true);

$item_target = $item_data[$current_item_id];
$filename = "Simulasi_CBF_Target_" . preg_replace('/[^a-zA-Z0-9]/', '_', $item_target['nama_brand'] . '_' . $item_target['nama_seri']) . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename={$filename}");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Export CBF</title>
    <style>
        table { border-collapse: collapse; width: 100%; font-family: sans-serif; }
        th, td { border: 1px solid #000000; padding: 5px; text-align: left; vertical-align: top; }
        th { background-color: #d9ead3; font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bg-light { background-color: #f3f3f3; }
        .bg-header { background-color: #2f75b5; color: #ffffff; }
    </style>
</head>
<body>
    <h2>Laporan Hasil Simulasi Content-Based Filtering (CBF)</h2>
    <p><strong>Item Target:</strong> <?= htmlspecialchars($item_target['nama_brand'] . ' ' . $item_target['nama_seri']) ?></p>
    <p><strong>Kategori Target:</strong> <?= htmlspecialchars($documents_raw[$current_item_id]['kategori']) ?></p>
    
    <br>
    <h3>Tabel Peringkat Rekomendasi (Top 5)</h3>
    <table>
        <thead>
            <tr>
                <th class="bg-header">Peringkat</th>
                <th class="bg-header">Item Rekomendasi (Bandingan)</th>
                <th class="bg-header">Kategori</th>
                <th class="bg-header text-right">Dot Product (A&middot;B)</th>
                <th class="bg-header text-right">Magnitude Target (|A|)</th>
                <th class="bg-header text-right">Magnitude Bandingan (|B|)</th>
                <th class="bg-header text-right">Cosine Similarity</th>
                <th class="bg-header text-right">Kecocokan (%)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $rank = 1;
            foreach($top_ids as $id): 
                if ($similarities[$id] == 0) continue;
                $det = $similarity_details[$id];
                $item = $item_data[$id];
            ?>
            <tr>
                <td class="text-center"><?= $rank++ ?></td>
                <td><?= htmlspecialchars($item['nama_brand'].' '.$item['nama_seri']) ?></td>
                <td><?= htmlspecialchars($documents_raw[$id]['kategori']) ?></td>
                <td class="text-right"><?= number_format($det['dot'], 5) ?></td>
                <td class="text-right"><?= number_format($det['mag_target'], 5) ?></td>
                <td class="text-right"><?= number_format($det['mag_other'], 5) ?></td>
                <td class="text-right"><?= number_format($similarities[$id], 5) ?></td>
                <td class="text-right"><?= round($similarities[$id] * 100, 1) ?>%</td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($top_ids) || $similarities[$top_ids[0]] == 0): ?>
            <tr>
                <td colspan="8" class="text-center">Tidak ada item serupa yang ditemukan.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <br><br>
    <h3>Rincian Perhitungan Matematika per Item Rekomendasi</h3>
    <?php foreach($top_ids as $id): 
        if ($similarities[$id] == 0) continue;
        $det = $similarity_details[$id];
        $item = $item_data[$id];
    ?>
    <br>
    <h4>Target VS Bandingan: <?= htmlspecialchars($item['nama_brand'].' '.$item['nama_seri']) ?></h4>
    <table>
        <thead>
            <tr>
                <th class="bg-header">Kata Kunci (Term)</th>
                <th class="bg-header text-right">Bobot TF-IDF A (Target)</th>
                <th class="bg-header text-right">Bobot TF-IDF B (Bandingan)</th>
                <th class="bg-header text-right">A &times; B (Dot Product)</th>
                <th class="bg-header text-right">Pangkat Dua A (A&sup2;)</th>
                <th class="bg-header text-right">Pangkat Dua B (B&sup2;)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            // Gabungkan semua term dari A dan B
            $all_terms = [];
            foreach ($det['mag_target_breakdown'] as $term => $b) $all_terms[$term] = true;
            foreach ($det['mag_other_breakdown'] as $term => $b) $all_terms[$term] = true;
            $all_terms_keys = array_keys($all_terms);
            sort($all_terms_keys);

            $sum_dot = 0;
            $sum_sq_a = 0;
            $sum_sq_b = 0;

            foreach($all_terms_keys as $term):
                $val_a = isset($det['mag_target_breakdown'][$term]) ? $det['mag_target_breakdown'][$term]['val'] : 0;
                $val_b = isset($det['mag_other_breakdown'][$term]) ? $det['mag_other_breakdown'][$term]['val'] : 0;
                $prod = $val_a * $val_b;
                $sq_a = $val_a * $val_a;
                $sq_b = $val_b * $val_b;

                $sum_dot += $prod;
                $sum_sq_a += $sq_a;
                $sum_sq_b += $sq_b;

                if ($val_a == 0 && $val_b == 0) continue;
            ?>
            <tr>
                <td><?= htmlspecialchars($term) ?></td>
                <td class="text-right"><?= number_format($val_a, 5) ?></td>
                <td class="text-right"><?= number_format($val_b, 5) ?></td>
                <td class="text-right"><?= number_format($prod, 5) ?></td>
                <td class="text-right"><?= number_format($sq_a, 5) ?></td>
                <td class="text-right"><?= number_format($sq_b, 5) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="bg-light">
                <th class="text-right">Total Penjumlahan (&Sigma;) =</th>
                <th></th>
                <th></th>
                <th class="text-right"><?= number_format($sum_dot, 5) ?></th>
                <th class="text-right"><?= number_format($sum_sq_a, 5) ?></th>
                <th class="text-right"><?= number_format($sum_sq_b, 5) ?></th>
            </tr>
            <tr class="bg-light">
                <th class="text-right">Akar Kuadrat (&radic;&Sigma;) =</th>
                <th></th>
                <th></th>
                <th></th>
                <th class="text-right"><?= number_format(sqrt($sum_sq_a), 5) ?></th>
                <th class="text-right"><?= number_format(sqrt($sum_sq_b), 5) ?></th>
            </tr>
        </tfoot>
    </table>
    <br>
    <?php endforeach; ?>
</body>
</html>
