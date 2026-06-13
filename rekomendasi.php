<?php
/**
 * ═══════════════════════════════════════════════════════════════════════
 * rekomendasi.php - Mesin Rekomendasi Content-Based Filtering (CBF)
 * ═══════════════════════════════════════════════════════════════════════
 *
 * File ini di-include dari detail_item.php.
 * Variabel yang harus sudah tersedia:
 *   - $current_item_id (int)  : ID item yang sedang dilihat
 *   - $db              (PDO)  : Koneksi database
 *
 * ALGORITMA:
 *   1. Ambil semua item beserta nama kategorinya
 *   2. Buat dokumen teks untuk setiap item (dengan pembobotan)
 *   3. Tokenisasi teks menjadi array kata
 *   4. Bangun vocabulary corpus
 *   5. Hitung TF  (Term Frequency) setiap dokumen
 *   6. Hitung IDF (Inverse Document Frequency) seluruh corpus
 *   7. Hitung vektor TF-IDF setiap dokumen
 *   8. Hitung Cosine Similarity antara item saat ini dan semua item lain
 *   9. Urutkan berdasarkan similarity tertinggi
 *  10. Ambil 5 item teratas sebagai rekomendasi
 *
 * Implementasi murni PHP tanpa library eksternal.
 * ═══════════════════════════════════════════════════════════════════════
 */

// ═════════════════════════════════════════════════════════════════════
// LANGKAH 1: Ambil Semua Item dari Database
// ═════════════════════════════════════════════════════════════════════
// Query JOIN dengan kategori_item untuk mendapatkan nama_kategori
// yang akan digunakan sebagai fitur konten dalam perhitungan CBF.

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

// Jika hanya ada 1 item atau kurang, tidak perlu rekomendasi
if (count($all_items) <= 1) {
    $rekomendasi = [];
    return;
}

// ═════════════════════════════════════════════════════════════════════
// LANGKAH 2: Buat Dokumen Teks untuk Setiap Item (dengan Pembobotan)
// ═════════════════════════════════════════════════════════════════════
// Pembobotan dilakukan dengan mengulang teks tertentu:
//   - nama_kategori : diulang 3x (bobot tertinggi karena kategori
//                     sangat menentukan kesamaan jenis barang)
//   - nama_brand    : diulang 2x (brand yang sama sering diminati
//                     oleh pengguna yang sama)
//   - nama_seri     : 1x (fitur pembeda antar item)
//   - deskripsi_umum: 1x (informasi detail yang menambah konteks)

$documents = []; // Array asosiatif: id_item => string dokumen
$item_data = []; // Array asosiatif: id_item => data item lengkap

foreach ($all_items as $row_item) {
    $id = $row_item['id_item'];

    // Buat dokumen gabungan dengan pembobotan melalui pengulangan teks
    $kategori_weighted = str_repeat($row_item['nama_kategori'] . ' ', 3);   // Bobot x3
    $brand_weighted    = str_repeat($row_item['nama_brand'] . ' ', 2);      // Bobot x2
    $seri              = $row_item['nama_seri'] . ' ';                       // Bobot x1
    $deskripsi         = ($row_item['deskripsi_umum'] ?? '') . ' ';          // Bobot x1

    $documents[$id] = $kategori_weighted . $brand_weighted . $seri . $deskripsi;
    $item_data[$id] = $row_item;
}

// ═════════════════════════════════════════════════════════════════════
// LANGKAH 3: Tokenisasi Teks
// ═════════════════════════════════════════════════════════════════════
// Proses tokenisasi:
//   a. Konversi ke huruf kecil (case folding)
//   b. Hapus tanda baca dan karakter non-alfanumerik
//   c. Pecah string menjadi array kata berdasarkan spasi
//   d. Filter kata kosong

$tokenized_docs = []; // Array asosiatif: id_item => array of tokens

foreach ($documents as $id => $doc) {
    // a. Case folding - semua huruf dijadikan lowercase
    $text = strtolower($doc);

    // b. Hapus tanda baca (sisakan huruf, angka, dan spasi)
    $text = preg_replace('/[^a-z0-9\s]/u', ' ', $text);

    // c. Pecah menjadi array kata berdasarkan whitespace
    $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

    // d. Simpan token (sudah terfilter dari kata kosong)
    $tokenized_docs[$id] = $tokens;
}

// ═════════════════════════════════════════════════════════════════════
// LANGKAH 4: Bangun Vocabulary Corpus
// ═════════════════════════════════════════════════════════════════════
// Vocabulary = kumpulan semua term unik dari seluruh dokumen.
// Ini menjadi dimensi dari vektor TF-IDF setiap dokumen.

$vocabulary = [];

foreach ($tokenized_docs as $tokens) {
    foreach ($tokens as $token) {
        if (!isset($vocabulary[$token])) {
            $vocabulary[$token] = true;
        }
    }
}

// Konversi ke indexed array untuk akses yang konsisten
$vocab_list = array_keys($vocabulary);
$vocab_size = count($vocab_list);

// ═════════════════════════════════════════════════════════════════════
// LANGKAH 5: Hitung TF (Term Frequency) untuk Setiap Dokumen
// ═════════════════════════════════════════════════════════════════════
// Formula TF:
//   TF(t, d) = Jumlah kemunculan term t dalam dokumen d
//              ─────────────────────────────────────────────
//              Total jumlah term dalam dokumen d
//
// Normalisasi ini mencegah bias terhadap dokumen yang lebih panjang.

$tf = []; // Array asosiatif: id_item => [term => tf_value]

foreach ($tokenized_docs as $id => $tokens) {
    $total_terms = count($tokens);

    // Hitung frekuensi setiap term dalam dokumen ini
    $term_counts = array_count_values($tokens);

    $tf[$id] = [];
    foreach ($term_counts as $term => $count) {
        // TF = jumlah kemunculan / total term dalam dokumen
        $tf[$id][$term] = $count / $total_terms;
    }
}

// ═════════════════════════════════════════════════════════════════════
// LANGKAH 6: Hitung IDF (Inverse Document Frequency)
// ═════════════════════════════════════════════════════════════════════
// Formula IDF:
//   IDF(t) = log( N / df(t) )
//
// Dimana:
//   N     = Total jumlah dokumen dalam corpus
//   df(t) = Jumlah dokumen yang mengandung term t
//
// IDF memberikan bobot lebih tinggi pada term yang jarang muncul
// (lebih diskriminatif) dan bobot rendah pada term yang umum.

$total_documents = count($tokenized_docs);
$idf = []; // Array asosiatif: term => idf_value

// Hitung DF (Document Frequency) untuk setiap term
$df = [];
foreach ($tokenized_docs as $tokens) {
    // Ambil term unik per dokumen (setiap term dihitung sekali per dokumen)
    $unique_terms = array_unique($tokens);
    foreach ($unique_terms as $term) {
        if (!isset($df[$term])) {
            $df[$term] = 0;
        }
        $df[$term]++;
    }
}

// Hitung IDF menggunakan formula logaritmik
foreach ($df as $term => $doc_freq) {
    // Tambah 1 ke penyebut untuk menghindari division by zero (smoothing)
    $idf[$term] = log($total_documents / $doc_freq);
}

// ═════════════════════════════════════════════════════════════════════
// LANGKAH 7: Hitung Vektor TF-IDF untuk Setiap Item
// ═════════════════════════════════════════════════════════════════════
// Formula TF-IDF:
//   TFIDF(t, d) = TF(t, d) × IDF(t)
//
// Setiap item direpresentasikan sebagai vektor dalam ruang vocabulary.
// Nilai vektor pada dimensi ke-i = TF-IDF dari term ke-i.

$tfidf_vectors = []; // Array asosiatif: id_item => [term => tfidf_value]

foreach ($tf as $id => $tf_doc) {
    $tfidf_vectors[$id] = [];
    foreach ($tf_doc as $term => $tf_value) {
        // TF-IDF = TF × IDF
        $idf_value = $idf[$term] ?? 0;
        $tfidf_vectors[$id][$term] = $tf_value * $idf_value;
    }
}

// ═════════════════════════════════════════════════════════════════════
// LANGKAH 8: Hitung Cosine Similarity
// ═════════════════════════════════════════════════════════════════════
// Formula Cosine Similarity:
//
//                    A · B          Σ(Ai × Bi)
//   cos(θ) = ───────────────── = ─────────────────────
//             |A| × |B|         √(Σ Ai²) × √(Σ Bi²)
//
// Dimana:
//   A · B  = Dot product dari vektor A dan B
//   |A|    = Magnitude (panjang/norm) vektor A
//   |B|    = Magnitude (panjang/norm) vektor B
//
// Nilai cosine similarity berkisar antara 0 (tidak mirip) sampai
// 1 (sangat mirip/identik).

/**
 * Menghitung dot product dari dua vektor sparse.
 * Vektor direpresentasikan sebagai associative array [term => value].
 *
 * @param array $vec_a Vektor pertama
 * @param array $vec_b Vektor kedua
 * @return float Hasil dot product
 */
function dotProduct(array $vec_a, array $vec_b): float {
    $dot = 0.0;
    // Iterasi hanya pada term yang ada di vektor A
    // (term yang tidak ada di B otomatis bernilai 0, jadi tidak perlu dihitung)
    foreach ($vec_a as $term => $value_a) {
        if (isset($vec_b[$term])) {
            $dot += $value_a * $vec_b[$term];
        }
    }
    return $dot;
}

/**
 * Menghitung magnitude (norm/panjang Euclidean) dari sebuah vektor sparse.
 *
 * @param array $vec Vektor
 * @return float Magnitude vektor
 */
function vectorMagnitude(array $vec): float {
    $sum_sq = 0.0;
    foreach ($vec as $value) {
        $sum_sq += $value * $value;
    }
    return sqrt($sum_sq);
}

/**
 * Menghitung Cosine Similarity antara dua vektor.
 *
 * @param array $vec_a Vektor pertama
 * @param array $vec_b Vektor kedua
 * @return float Nilai similarity (0.0 - 1.0)
 */
function cosineSimilarity(array $vec_a, array $vec_b): float {
    $dot  = dotProduct($vec_a, $vec_b);
    $mag_a = vectorMagnitude($vec_a);
    $mag_b = vectorMagnitude($vec_b);

    // Hindari pembagian dengan nol
    if ($mag_a == 0 || $mag_b == 0) {
        return 0.0;
    }

    return $dot / ($mag_a * $mag_b);
}

// Ambil vektor TF-IDF item yang sedang dilihat
$current_vector = $tfidf_vectors[$current_item_id] ?? [];

// Hitung similarity terhadap semua item lain
$similarities = []; // Array: [id_item => similarity_score]

foreach ($tfidf_vectors as $id => $vector) {
    // Skip item yang sedang dilihat (jangan direkomendasikan dirinya sendiri)
    if ($id == $current_item_id) {
        continue;
    }

    $sim = cosineSimilarity($current_vector, $vector);
    $similarities[$id] = $sim;
}

// ═════════════════════════════════════════════════════════════════════
// LANGKAH 9: Urutkan Berdasarkan Similarity Tertinggi
// ═════════════════════════════════════════════════════════════════════
// Sorting descending agar item paling mirip berada di urutan pertama.

arsort($similarities);

// ═════════════════════════════════════════════════════════════════════
// LANGKAH 10: Ambil Top 5 Rekomendasi
// ═════════════════════════════════════════════════════════════════════
// Slice 5 item teratas dan gabungkan dengan data item lengkap
// untuk ditampilkan di halaman detail.

$limit_cbf = isset($app_settings['limit_cbf']) ? (int)$app_settings['limit_cbf'] : 5;
$top_ids = array_slice(array_keys($similarities), 0, $limit_cbf, true);

$rekomendasi = []; // Array hasil rekomendasi dengan data lengkap

foreach ($top_ids as $id) {
    $score = $similarities[$id];

    // Hanya tampilkan item dengan similarity > 0
    // (item yang benar-benar tidak mirip tidak perlu direkomendasikan)
    if ($score > 0) {
        $rec_item = $item_data[$id];
        $rec_item['similarity_score'] = round($score, 4);
        $rekomendasi[] = $rec_item;
    }
}

// ═════════════════════════════════════════════════════════════════════
// LANGKAH 11: $rekomendasi siap digunakan oleh detail_item.php
// ═════════════════════════════════════════════════════════════════════
// Variabel $rekomendasi berisi array item dengan field:
//   - id_item, nama_brand, nama_seri, deskripsi_umum, gambar,
//     nama_kategori, ikon, similarity_score
//
// File detail_item.php akan menggunakan array ini untuk menampilkan
// section "Rekomendasi Item Lainnya" sebagai horizontal scroll cards.
?>
