<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();

$db = getDB();

// Ambil semua item untuk dropdown
$stmt = $db->query("SELECT id_item, nama_brand, nama_seri FROM item ORDER BY nama_brand, nama_seri");
$dropdown_items = $stmt->fetchAll();

$current_item_id = isset($_GET['id_item']) ? (int)$_GET['id_item'] : 0;

$pageTitle = "Simulasi CBF";
require_once __DIR__ . '/../includes/header.php';
?>
<style>
/* Sembunyikan scrollbar tapi tetap bisa di-scroll */
.hide-scrollbar::-webkit-scrollbar {
    display: none;
}
.hide-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="ri-test-tube-line text-primary me-2"></i>Simulasi Rekomendasi (CBF)</h4>
            <p class="text-muted mb-0 fs-14">Bedah cara kerja algoritma Content-Based Filtering menggunakan metode TF-IDF dan Cosine Similarity.</p>
        </div>
    </div>

    <!-- Pilihan Item -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form action="" method="GET" class="row g-3 align-items-center">
                <div class="col-md-8">
                    <label class="form-label fw-bold">Pilih Item untuk Disimulasikan:</label>
                    <select name="id_item" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Pilih Item --</option>
                        <?php foreach($dropdown_items as $di): ?>
                            <option value="<?= $di['id_item'] ?>" <?= ($di['id_item'] == $current_item_id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($di['nama_brand'] . ' ' . $di['nama_seri']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php
    if ($current_item_id > 0) {
        // ==========================================
        // ALGORITMA CBF STEP-BY-STEP
        // ==========================================

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
            
            // Raw values for display
            $documents_raw[$id] = [
                'kategori' => $row_item['nama_kategori'],
                'brand' => $row_item['nama_brand'],
                'seri' => $row_item['nama_seri'],
                'deskripsi' => $row_item['deskripsi_umum']
            ];

            // Pembobotan: Kategori(3x), Brand(2x), Seri(1x), Deskripsi(1x)
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

        // LANGKAH 4: Vocabulary
        $vocabulary = [];
        foreach ($tokenized_docs as $tokens) {
            foreach ($tokens as $token) {
                $vocabulary[$token] = true;
            }
        }
        $vocab_list = array_keys($vocabulary);

        // LANGKAH 5: Hitung TF
        $tf = [];
        foreach ($tokenized_docs as $id => $tokens) {
            $total_terms = count($tokens);
            $term_counts = array_count_values($tokens);
            $tf[$id] = [];
            foreach ($term_counts as $term => $count) {
                $tf[$id][$term] = $count / $total_terms;
            }
        }

        // LANGKAH 6: Hitung IDF
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
            // Sort breakdown descending by prod to show most impactful terms first
            uasort($breakdown, function($a, $b) {
                return $b['prod'] <=> $a['prod'];
            });
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
            // Sort breakdown descending by sq
            uasort($breakdown, function($a, $b) {
                return $b['sq'] <=> $a['sq'];
            });
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
        $limit_cbf = isset($app_settings['limit_cbf']) ? (int)$app_settings['limit_cbf'] : 5;
        $top_ids = array_slice(array_keys($similarities), 0, $limit_cbf, true);

        // ==========================================
        // UI RENDERING
        // ==========================================
        ?>
        
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm overflow-hidden mb-4">
                    <div class="card-header bg-primary text-white pb-0 border-0">
                        <ul class="nav nav-tabs nav-tabs-white border-0" id="cbfTabs" role="tablist">
                            <li class="nav-item"><a class="nav-link active fw-semibold" data-bs-toggle="tab" href="#tab-dokumen">1. Dokumen & Bobot</a></li>
                            <li class="nav-item"><a class="nav-link fw-semibold" data-bs-toggle="tab" href="#tab-vocab">2. Token & Vocab</a></li>
                            <li class="nav-item"><a class="nav-link fw-semibold" data-bs-toggle="tab" href="#tab-tf">3. TF (Term Frequency)</a></li>
                            <li class="nav-item"><a class="nav-link fw-semibold" data-bs-toggle="tab" href="#tab-idf">4. IDF (Inverse DF)</a></li>
                            <li class="nav-item"><a class="nav-link fw-semibold" data-bs-toggle="tab" href="#tab-tfidf">5. TF-IDF & Cosine</a></li>
                            <li class="nav-item"><a class="nav-link fw-semibold" data-bs-toggle="tab" href="#tab-hasil">6. Hasil Rekomendasi</a></li>
                        </ul>
                    </div>
                    <div class="card-body p-0">
                        <div class="tab-content p-4">
                            
                            <!-- TAB 1: Dokumen & Bobot -->
                            <div class="tab-pane fade show active" id="tab-dokumen">
                                <h5 class="fw-bold mb-3">Langkah 1 & 2: Pembuatan Dokumen dan Pembobotan Fitur</h5>
                                <p class="text-muted">Setiap item diubah menjadi sebuah dokumen teks. Agar algoritma tahu atribut mana yang paling penting, kita menggunakan trik <strong>pengulangan kata (pembobotan)</strong>: Kategori diulang 3x, Brand diulang 2x, Seri dan Deskripsi 1x.</p>
                                
                                <div class="table-responsive hide-scrollbar">
                                    <table class="table table-bordered table-striped">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Item (ID)</th>
                                                <th>Data Mentah</th>
                                                <th>Hasil Dokumen Teks (Berbobot)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach([$current_item_id] as $id): ?>
                                                <tr class="table-primary">
                                                    <td class="fw-bold"><?= htmlspecialchars($item_data[$id]['nama_brand'].' '.$item_data[$id]['nama_seri']) ?> (Target)</td>
                                                    <td class="fs-13">
                                                        <span class="badge bg-secondary mb-1">Kat: <?= htmlspecialchars($documents_raw[$id]['kategori']) ?></span><br>
                                                        <span class="badge bg-info mb-1">Brnd: <?= htmlspecialchars($documents_raw[$id]['brand']) ?></span><br>
                                                        <span class="badge bg-dark">Seri: <?= htmlspecialchars($documents_raw[$id]['seri']) ?></span>
                                                    </td>
                                                    <td class="fs-13 text-wrap" style="max-width: 400px;"><?= htmlspecialchars($documents[$id]) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php foreach($top_ids as $id): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item_data[$id]['nama_brand'].' '.$item_data[$id]['nama_seri']) ?></td>
                                                    <td class="fs-13">
                                                        <span class="badge bg-secondary mb-1">Kat: <?= htmlspecialchars($documents_raw[$id]['kategori']) ?></span><br>
                                                        <span class="badge bg-info mb-1">Brnd: <?= htmlspecialchars($documents_raw[$id]['brand']) ?></span>
                                                    </td>
                                                    <td class="fs-13 text-wrap" style="max-width: 400px;"><?= htmlspecialchars($documents[$id]) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- TAB 2: Token & Vocab -->
                            <div class="tab-pane fade" id="tab-vocab">
                                <h5 class="fw-bold mb-3">Langkah 3 & 4: Tokenisasi dan Pembentukan Vocabulary</h5>
                                <p class="text-muted">Teks dibersihkan (huruf kecil, hapus simbol), lalu dipecah menjadi kata-kata tunggal (Token). Kumpulan seluruh kata unik dari semua dokumen membentuk <strong>Vocabulary</strong>.</p>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="card border">
                                            <div class="card-header bg-light fw-bold">Token Item Target</div>
                                            <div class="card-body p-2 fs-13 hide-scrollbar" style="max-height: 200px; overflow-y: auto;">
                                                <?php 
                                                $counts = array_count_values($tokenized_docs[$current_item_id]);
                                                foreach($counts as $tok => $c) {
                                                    echo "<span class='badge bg-primary-transparent text-primary m-1 border border-primary'>$tok ($c)</span> ";
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card border">
                                            <div class="card-header bg-light fw-bold">Vocabulary Global (Total: <?= count($vocab_list) ?> kata)</div>
                                            <div class="card-body p-2 fs-13 hide-scrollbar" style="max-height: 200px; overflow-y: auto;">
                                                <?php 
                                                foreach($vocab_list as $tok) {
                                                    echo "<span class='badge bg-light text-dark m-1 border'>$tok</span> ";
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 3: TF -->
                            <div class="tab-pane fade" id="tab-tf">
                                <h5 class="fw-bold mb-3">Langkah 5: Term Frequency (TF)</h5>
                                <p class="text-muted">TF adalah rasio kemunculan sebuah kata dalam satu dokumen. Semakin sering kata muncul di dokumen itu, semakin besar nilai TF-nya. <br><code>TF = Jumlah kemunculan kata / Total kata dalam dokumen</code></p>
                                
                                <div class="table-responsive hide-scrollbar">
                                    <table class="table table-bordered table-sm fs-13">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Term (Kata)</th>
                                                <th>TF Target (<?= htmlspecialchars($item_data[$current_item_id]['nama_brand']) ?>)</th>
                                                <?php foreach($top_ids as $id): ?>
                                                    <th style="min-width:120px;">TF #<?= htmlspecialchars($item_data[$id]['nama_brand']) ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            // Tampilkan beberapa kata penting dari target
                                            $target_terms = array_keys(array_count_values($tokenized_docs[$current_item_id]));
                                            $tot_target = count($tokenized_docs[$current_item_id]);
                                            foreach($target_terms as $term): 
                                                $c_target = array_count_values($tokenized_docs[$current_item_id])[$term] ?? 0;
                                            ?>
                                            <tr>
                                                <td class="fw-bold text-primary"><?= htmlspecialchars($term) ?></td>
                                                <td>
                                                    <span class="fw-bold"><?= number_format($tf[$current_item_id][$term] ?? 0, 4) ?></span>
                                                    <div class="text-muted" style="font-size: 11px;">(<?= $c_target ?> / <?= $tot_target ?>)</div>
                                                </td>
                                                <?php foreach($top_ids as $id): 
                                                    $c_other = array_count_values($tokenized_docs[$id])[$term] ?? 0;
                                                    $tot_other = count($tokenized_docs[$id]);
                                                ?>
                                                    <td>
                                                        <span class="fw-bold"><?= number_format($tf[$id][$term] ?? 0, 4) ?></span>
                                                        <div class="text-muted" style="font-size: 11px;">(<?= $c_other ?> / <?= $tot_other ?>)</div>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- TAB 4: IDF -->
                            <div class="tab-pane fade" id="tab-idf">
                                <h5 class="fw-bold mb-3">Langkah 6: Inverse Document Frequency (IDF)</h5>
                                <p class="text-muted">IDF menilai seberapa "langka" sebuah kata di seluruh dokumen. Kata yang umum (misal: "dan", "yang") akan memiliki IDF rendah. Kata unik (misal: "eiger", "waterproof") akan memiliki IDF tinggi.<br><code>IDF = log(Total Dokumen / Jumlah Dokumen yang mengandung kata tsb)</code></p>
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="table-responsive hide-scrollbar">
                                            <table class="table table-bordered table-sm fs-13">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Term (Kata)</th>
                                                        <th>Doc Freq (DF)</th>
                                                        <th>IDF Score</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    arsort($idf); // Urutkan dari IDF tertinggi (paling langka)
                                                    foreach($idf as $term => $val): 
                                                    ?>
                                                    <tr>
                                                        <td class="fw-bold text-danger"><?= htmlspecialchars($term) ?></td>
                                                        <td>Muncul di <?= $df[$term] ?> item</td>
                                                        <td>
                                                            <span class="fw-bold"><?= number_format($val, 4) ?></span>
                                                            <div class="text-muted" style="font-size: 11px;">log(<?= $total_documents ?> / <?= $df[$term] ?>)</div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 5: TF-IDF & Cosine -->
                            <div class="tab-pane fade" id="tab-tfidf">
                                <h5 class="fw-bold mb-3">Langkah 7 & 8: Vektor TF-IDF dan Cosine Similarity</h5>
                                <p class="text-muted mb-3">
                                    <strong>TF-IDF = TF × IDF</strong> (Membentuk vektor untuk setiap item).<br>
                                    <strong>Cosine Similarity</strong> menghitung sudut antara dua vektor. Jika sudutnya kecil (mirip), nilainya mendekati 1. Jika sudutnya besar (berbeda), nilainya mendekati 0.
                                </p>

                                <div class="alert alert-info bg-info-transparent border-info mb-4">
                                    <h6 class="fw-bold mb-2"><i class="ri-information-line me-1"></i> Penjelasan Rumus (A dan B):</h6>
                                    <ul class="mb-0 fs-13" style="line-height: 1.6;">
                                        <li><strong>Vektor A (Target) & Vektor B (Bandingan):</strong> Adalah kumpulan nilai skor kata (TF-IDF) dari masing-masing item.</li>
                                        <li><strong>Dot Product (A·B):</strong> Mengalikan skor kata yang <u>sama-sama muncul</u> di kedua item, lalu menjumlahkannya. Semakin banyak kata penting yang sama, nilainya semakin besar.</li>
                                        <li><strong>Magnitude (|A| atau |B|):</strong> Menghitung "panjang" atau total bobot keseluruhan teks dalam satu item. (Didapat dari akar kuadrat jumlah pangkat dua semua skor katanya).</li>
                                        <li><strong>Kenapa harus dibagi ( / |A|×|B| )?</strong> Agar perhitungannya adil! Membagi Dot Product dengan Magnitude akan menetralkan perbedaan panjang teks, sehingga kemiripan murni dinilai dari proporsi kecocokan konten (skala 0 sampai 1).</li>
                                    </ul>
                                </div>                                <div class="table-responsive hide-scrollbar">
                                    <table class="table table-bordered table-striped align-middle">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th>Item Bandingan</th>
                                                <th>Dot Product (A·B)</th>
                                                <th>Magnitude Target (|A|)</th>
                                                <th>Magnitude Bandingan (|B|)</th>
                                                <th>Cosine Similarity</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach(array_slice($similarities, 0, $limit_cbf, true) as $id => $sim): 
                                                $det = $similarity_details[$id];
                                            ?>
                                            <tr>
                                                <td class="fw-bold"><?= htmlspecialchars($item_data[$id]['nama_brand'].' '.$item_data[$id]['nama_seri']) ?></td>
                                                <td class="text-center font-monospace">
                                                    <?= number_format($det['dot'], 5) ?>
                                                    <div class="text-muted mt-1 mb-2" style="font-size: 11px;">Σ(A × B)</div>
                                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fs-11" data-bs-toggle="modal" data-bs-target="#modalDot<?= $id ?>">Lihat Rincian</button>
                                                </td>
                                                <td class="text-center font-monospace">
                                                    <?= number_format($det['mag_target'], 5) ?>
                                                    <div class="text-muted mt-1 mb-2" style="font-size: 11px;">√(Σ A²)</div>
                                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fs-11" data-bs-toggle="modal" data-bs-target="#modalDot<?= $id ?>">Lihat Rincian</button>
                                                </td>
                                                <td class="text-center font-monospace">
                                                    <?= number_format($det['mag_other'], 5) ?>
                                                    <div class="text-muted mt-1 mb-2" style="font-size: 11px;">√(Σ B²)</div>
                                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fs-11" data-bs-toggle="modal" data-bs-target="#modalDot<?= $id ?>">Lihat Rincian</button>
                                                </td>
                                                <td class="text-center fw-bold fs-15 text-success">
                                                    <?= number_format($sim, 5) ?>
                                                    <div class="text-muted fw-normal mt-1" style="font-size: 11px;">(A·B) / (|A|×|B|)</div>
                                                    <div class="text-muted fw-normal mt-1" style="font-size: 11px;">= <?= number_format($det['dot'], 5) ?> / (<?= number_format($det['mag_target'], 5) ?> × <?= number_format($det['mag_other'], 5) ?>)</div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- TAB 6: Hasil -->
                            <div class="tab-pane fade" id="tab-hasil">
                                <h5 class="fw-bold mb-4">Hasil Akhir Rekomendasi</h5>
                                
                                <div class="row">
                                    <?php foreach($top_ids as $index => $id): 
                                        $score = $similarities[$id];
                                        if($score == 0) continue;
                                        $item = $item_data[$id];
                                        $gambarPath = empty($item['gambar']) ? $base_url . 'assets/images/placeholder.jpg' : $base_url . 'assets/img/' . $item['gambar'];
                                    ?>
                                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-4">
                                            <div class="card h-100 border-0 shadow-sm position-relative">
                                                <div class="position-absolute top-0 start-0 m-2">
                                                    <span class="badge bg-danger rounded-pill shadow-sm">Rank #<?= $index + 1 ?></span>
                                                </div>
                                                <img src="<?= htmlspecialchars($gambarPath) ?>" class="card-img-top p-3" alt="Produk" style="height:140px; object-fit:contain;">
                                                <div class="card-body p-3 border-top bg-light text-center">
                                                    <h6 class="fw-bold text-dark fs-14 mb-1 text-truncate"><?= htmlspecialchars($item['nama_brand']) ?></h6>
                                                    <p class="text-muted fs-12 mb-2 text-truncate"><?= htmlspecialchars($item['nama_seri']) ?></p>
                                                    <div class="badge bg-success-transparent text-success border border-success px-2 py-1 w-100">
                                                        <i class="ri-percent-line me-1"></i> Mirip: <?= round($score * 100, 1) ?>%
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if(empty($top_ids) || $similarities[$top_ids[0]] == 0): ?>
                                        <div class="col-12">
                                            <div class="alert alert-warning text-center">Tidak ada item serupa yang ditemukan.</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php foreach(array_slice($similarities, 0, $limit_cbf, true) as $id => $sim): 
            $det = $similarity_details[$id];
        ?>
        <div class="modal fade" id="modalDot<?= $id ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fs-15 fw-bold">Rincian Perhitungan Matematika</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="p-3 bg-light border-bottom">
                            <p class="mb-1 fs-13 text-muted">Item yang dibandingkan:</p>
                            <p class="mb-0 fw-bold text-primary"><?= htmlspecialchars($item_data[$id]['nama_brand'].' '.$item_data[$id]['nama_seri']) ?></p>
                        </div>
                        
                        <div class="px-3 pt-3">
                            <ul class="nav nav-pills mb-3" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active py-1 px-3 fs-13" data-bs-toggle="pill" data-bs-target="#pills-dot-<?= $id ?>" type="button" role="tab">Dot Product (A·B)</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link py-1 px-3 fs-13" data-bs-toggle="pill" data-bs-target="#pills-maga-<?= $id ?>" type="button" role="tab">Magnitude Target (|A|)</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link py-1 px-3 fs-13" data-bs-toggle="pill" data-bs-target="#pills-magb-<?= $id ?>" type="button" role="tab">Magnitude Bandingan (|B|)</button>
                                </li>
                            </ul>
                        </div>

                        <div class="tab-content pb-3">
                            <!-- Tab Dot Product -->
                            <div class="tab-pane fade show active" id="pills-dot-<?= $id ?>" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm align-middle mb-0 text-center">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-start ps-4">Kata Kunci (Sama-sama ada)</th>
                                                <th>TF-IDF A</th>
                                                <th>TF-IDF B</th>
                                                <th class="pe-4">A × B</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($det['breakdown'])): ?>
                                            <tr><td colspan="4" class="text-muted py-4"><i class="ri-information-line me-1"></i>Tidak ada satu pun kata kunci yang cocok</td></tr>
                                            <?php else: ?>
                                                <?php foreach($det['breakdown'] as $term => $b): ?>
                                                <tr>
                                                    <td class="text-start ps-4 fw-bold fs-12"><?= htmlspecialchars($term) ?></td>
                                                    <td class="font-monospace fs-12 text-muted"><?= number_format($b['val_a'], 4) ?></td>
                                                    <td class="font-monospace fs-12 text-muted"><?= number_format($b['val_b'], 4) ?></td>
                                                    <td class="font-monospace fs-12 fw-bold text-success pe-4"><?= number_format($b['prod'], 5) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <th colspan="3" class="text-end pe-3 text-dark">Total Penjumlahan Σ (A × B) =</th>
                                                <th class="font-monospace fw-bold text-primary fs-14 bg-primary-transparent pe-4"><?= number_format($det['dot'], 5) ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Tab Magnitude Target -->
                            <div class="tab-pane fade" id="pills-maga-<?= $id ?>" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm align-middle mb-0 text-center">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-start ps-4">Kata Kunci (Target)</th>
                                                <th>Bobot TF-IDF (A)</th>
                                                <th class="pe-4">Pangkat Dua (A²)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($det['mag_target_breakdown'] as $term => $b): ?>
                                            <tr>
                                                <td class="text-start ps-4 fw-bold fs-12"><?= htmlspecialchars($term) ?></td>
                                                <td class="font-monospace fs-12 text-muted"><?= number_format($b['val'], 4) ?></td>
                                                <td class="font-monospace fs-12 text-muted pe-4"><?= number_format($b['sq'], 5) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <th colspan="2" class="text-end pe-3 text-dark">1. Total Penjumlahan Σ (A²) =</th>
                                                <th class="font-monospace fw-bold text-dark fs-13 pe-4"><?= number_format(pow($det['mag_target'], 2), 5) ?></th>
                                            </tr>
                                            <tr>
                                                <th colspan="2" class="text-end pe-3 text-dark border-bottom-0">2. Akar Kuadrat √(Σ A²) =</th>
                                                <th class="font-monospace fw-bold text-primary fs-14 bg-primary-transparent pe-4 border-bottom-0"><?= number_format($det['mag_target'], 5) ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <!-- Tab Magnitude Bandingan -->
                            <div class="tab-pane fade" id="pills-magb-<?= $id ?>" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm align-middle mb-0 text-center">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-start ps-4">Kata Kunci (Bandingan)</th>
                                                <th>Bobot TF-IDF (B)</th>
                                                <th class="pe-4">Pangkat Dua (B²)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($det['mag_other_breakdown'] as $term => $b): ?>
                                            <tr>
                                                <td class="text-start ps-4 fw-bold fs-12"><?= htmlspecialchars($term) ?></td>
                                                <td class="font-monospace fs-12 text-muted"><?= number_format($b['val'], 4) ?></td>
                                                <td class="font-monospace fs-12 text-muted pe-4"><?= number_format($b['sq'], 5) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <th colspan="2" class="text-end pe-3 text-dark">1. Total Penjumlahan Σ (B²) =</th>
                                                <th class="font-monospace fw-bold text-dark fs-13 pe-4"><?= number_format(pow($det['mag_other'], 2), 5) ?></th>
                                            </tr>
                                            <tr>
                                                <th colspan="2" class="text-end pe-3 text-dark border-bottom-0">2. Akar Kuadrat √(Σ B²) =</th>
                                                <th class="font-monospace fw-bold text-primary fs-14 bg-primary-transparent pe-4 border-bottom-0"><?= number_format($det['mag_other'], 5) ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer p-2">
                        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>


    <?php } else { ?>
        <div class="alert alert-info border-0 shadow-sm d-flex align-items-center p-4">
            <i class="ri-information-line fs-24 me-3 text-info"></i>
            <div>Silakan pilih salah satu item dari menu <em>dropdown</em> di atas untuk memulai simulasi perhitungan Content-Based Filtering.</div>
        </div>
    <?php } ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
