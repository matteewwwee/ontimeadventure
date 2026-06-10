\[DRAF PERANCANGAN] Sistem Informasi Katalog \& PO On Time Adventure



A. Konsep Utama Sistem

Aplikasi web ini dibangun menggunakan PHP native dan MySQL. Sistem ini difokuskan sebagai katalog digital dan validasi Pre-Order (PO) awal, bukan e-commerce transaksi penuh. Tujuannya mempermudah pelanggan mengecek ketersediaan dan booking alat camping/climbing tanpa harus datang langsung ke lokasi untuk mengecek barang.



B. Alur Pengguna (Web Flow)



Login Praktis: Autentikasi hanya menggunakan Nomor HP dan PIN 4-digit. Jika berhasil, langsung masuk ke beranda.



Katalog Kategori: Halaman utama menampilkan pengelompokan kategori item (Tenda, Sepatu, Alat Masak, Carrier, dll).



Detail Item \& Variasi: Saat item diklik, muncul detail brand, seri, dan pilihan variasi (contoh: tenda ada pilihan 2P/4P, kompor pilihan "standar"). Di bagian ini ditampilkan dengan jelas Harga Sewa / Hari, Stok Tersedia, dan Catatan Kondisi Fisik alat.



Sistem Rekomendasi (CBF): Di bagian bawah halaman detail item, otomatis muncul "Rekomendasi Item Lainnya". Skrip PHP menghitung kemiripan konten (kategori, brand, dan deskripsi) menggunakan pembobotan TF-IDF dan Cosine Similarity dari algoritma Content Based Filtering.



Form Pengajuan PO: Pengguna memasukkan Tanggal Mulai dan Tanggal Selesai sewa. Web otomatis menghitung estimasi harga (Selisih Hari x Harga Sewa x Jumlah Pesan).



Pelacakan Status PO: Disediakan halaman khusus agar pelanggan bisa memantau status PO mereka ("Menunggu Pengecekan", "Barang Siap", "Dibatalkan") yang datanya di-update admin setelah validasi ketersediaan di lapangan.



C. Struktur Database (MySQL)

Skema tabel dirancang fleksibel untuk membedakan barang yang memiliki ukuran/kapasitas spesifik dengan barang yang all-size.



users



id\_user (PK, AI)



no\_hp (Varchar, Unique) -> Sebagai ID Login



pin (Char(4)) -> Autentikasi



role (Enum: 'admin', 'pelanggan')



kategori\_item



id\_kategori (PK, AI)



nama\_kategori (Varchar)



item (Data Induk Item)



id\_item (PK, AI)



id\_kategori (FK)



nama\_brand (Varchar) -> (Eiger, Consina, dll)



nama\_seri (Varchar)



deskripsi\_umum (Text) -> Catatan: Atribut ini akan diekstrak untuk perhitungan algoritma CBF.



gambar (Varchar)



varian\_item (Data Ukuran, Harga \& Kondisi)



id\_varian (PK, AI)



id\_item (FK)



keterangan\_varian (Varchar) -> Isi bebas (misal: "Ukuran 42", "60L", atau "Standar").



harga\_sewa\_per\_hari (Int)



stok\_tersedia (Int)



catatan\_kondisi (Text) -> Transparansi kondisi (misal: "Aman", "Ada lecet").



pengajuan\_po (Data Reservasi)



id\_po (PK, AI)



id\_user (FK)



tgl\_pengajuan (Timestamp)



tgl\_mulai\_sewa (Date)



tgl\_selesai\_sewa (Date)



estimasi\_total\_harga (Int)



status\_po (Enum: 'Menunggu Pengecekan', 'Barang Siap', 'Ada Barang Kosong', 'Selesai/Dibatalkan')



detail\_po (Keranjang PO)



id\_detail (PK, AI)



id\_po (FK)



id\_varian (FK) -> Mengikat langsung ke spesifikasi dan kondisi alat.



jumlah\_pesan (Int)



harga\_satuan\_saat\_pesan (Int) -> Mengunci harga sewa per hari untuk validasi arsip.

