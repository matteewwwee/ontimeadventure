-- ============================================================
-- On Time Adventure - Database Schema & Seed Data
-- ============================================================

CREATE DATABASE IF NOT EXISTS `ontimeadventure`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_general_ci;

USE `ontimeadventure`;

-- -----------------------------------------------------------
-- 1. users
-- -----------------------------------------------------------
DROP TABLE IF EXISTS `detail_po`;
DROP TABLE IF EXISTS `pengajuan_po`;
DROP TABLE IF EXISTS `varian_item`;
DROP TABLE IF EXISTS `item`;
DROP TABLE IF EXISTS `kategori_item`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id_user`    INT AUTO_INCREMENT PRIMARY KEY,
  `no_hp`      VARCHAR(15)  NOT NULL UNIQUE,
  `pin`        CHAR(60)     NOT NULL COMMENT 'password_hash()',
  `nama`       VARCHAR(100) NOT NULL,
  `role`       ENUM('admin','pelanggan') NOT NULL DEFAULT 'pelanggan',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 2. kategori_item
-- -----------------------------------------------------------
CREATE TABLE `kategori_item` (
  `id_kategori`   INT AUTO_INCREMENT PRIMARY KEY,
  `nama_kategori` VARCHAR(100) NOT NULL,
  `ikon`          VARCHAR(50)  DEFAULT NULL COMMENT 'Emoji / icon class'
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 3. item
-- -----------------------------------------------------------
CREATE TABLE `item` (
  `id_item`        INT AUTO_INCREMENT PRIMARY KEY,
  `id_kategori`    INT          NOT NULL,
  `nama_brand`     VARCHAR(100) NOT NULL,
  `nama_seri`      VARCHAR(100) NOT NULL,
  `deskripsi_umum` TEXT         NULL,
  `gambar`         VARCHAR(255) DEFAULT NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_kategori`) REFERENCES `kategori_item`(`id_kategori`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 4. varian_item
-- -----------------------------------------------------------
CREATE TABLE `varian_item` (
  `id_varian`            INT AUTO_INCREMENT PRIMARY KEY,
  `id_item`              INT          NOT NULL,
  `keterangan_varian`    VARCHAR(100) NOT NULL DEFAULT 'Standar',
  `harga_sewa_per_hari`  INT          NOT NULL DEFAULT 0,
  `stok_tersedia`        INT          NOT NULL DEFAULT 0,
  `catatan_kondisi`      TEXT         NULL,
  FOREIGN KEY (`id_item`) REFERENCES `item`(`id_item`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 5. pengajuan_po
-- -----------------------------------------------------------
CREATE TABLE `pengajuan_po` (
  `id_po`               INT AUTO_INCREMENT PRIMARY KEY,
  `id_user`             INT  NOT NULL,
  `tgl_pengajuan`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `tgl_mulai_sewa`      DATE NOT NULL,
  `tgl_selesai_sewa`    DATE NOT NULL,
  `estimasi_total_harga` INT NOT NULL DEFAULT 0,
  `status_po`           ENUM('Menunggu Pengecekan','Barang Siap','Ada Barang Kosong','Selesai/Dibatalkan')
                        NOT NULL DEFAULT 'Menunggu Pengecekan',
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 6. detail_po
-- -----------------------------------------------------------
CREATE TABLE `detail_po` (
  `id_detail`              INT AUTO_INCREMENT PRIMARY KEY,
  `id_po`                  INT NOT NULL,
  `id_varian`              INT NOT NULL,
  `jumlah_pesan`           INT NOT NULL DEFAULT 1,
  `harga_satuan_saat_pesan` INT NOT NULL DEFAULT 0,
  FOREIGN KEY (`id_po`)     REFERENCES `pengajuan_po`(`id_po`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`id_varian`) REFERENCES `varian_item`(`id_varian`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ===========================================================
-- SEED DATA
-- ===========================================================

-- Admin default  (PIN: 1234)
INSERT INTO `users` (`no_hp`, `pin`, `nama`, `role`) VALUES
('081200001111', '$2y$12$yUE03ZDP1E95ESqItDpNhOihlBy5CttXMQjdOohKo9Zmi6fUzbM5.', 'Admin OTA', 'admin');

-- Pelanggan demo (PIN: 5678)
INSERT INTO `users` (`no_hp`, `pin`, `nama`, `role`) VALUES
('081299998888', '$2y$12$IEE9H/P6iRYPJHPiRxMwKO1oM0j1zvA1JFvED5aTeocBhieLqzHIS', 'Andi Pendaki', 'pelanggan');

-- Kategori
INSERT INTO `kategori_item` (`nama_kategori`, `ikon`) VALUES
('Tenda',        '⛺'),
('Carrier',      '🎒'),
('Sepatu',       '🥾'),
('Alat Masak',   '🍳'),
('Sleeping Bag', '🛏️'),
('Trekking Pole','🏔️'),
('Headlamp',     '🔦'),
('Jaket',        '🧥');

-- Item: Tenda
INSERT INTO `item` (`id_kategori`, `nama_brand`, `nama_seri`, `deskripsi_umum`, `gambar`) VALUES
(1, 'Eiger',    'Mongoose 2P',   'Tenda camping ultralight untuk 2 orang dengan material ripstop nylon tahan air. Cocok untuk pendakian gunung dan camping di alam terbuka. Mudah dipasang dan ringan dibawa.', 'eiger_mongoose_2p.jpg'),
(1, 'Consina',  'Magnum 4',      'Tenda kapasitas 4 orang berbahan polyester tahan angin dan hujan. Desain dome klasik yang kokoh. Ideal untuk camping keluarga atau kelompok.', 'consina_magnum_4.jpg'),
(1, 'Great Outdoor', 'Java 2P',  'Tenda double layer 2 orang anti bocor dengan ventilasi baik. Frame aluminium ringan dan kuat. Cocok untuk pendakian musim hujan.', 'go_java_2p.jpg');

-- Item: Carrier
INSERT INTO `item` (`id_kategori`, `nama_brand`, `nama_seri`, `deskripsi_umum`, `gambar`) VALUES
(2, 'Eiger',    'Excelsior 75L',  'Carrier kapasitas besar 75 liter cocok untuk ekspedisi panjang. Bahan cordura tahan robek dengan back system ergonomis. Banyak kantong organizer.', 'eiger_excelsior_75.jpg'),
(2, 'Consina',  'Expedition 80L', 'Carrier besar 80 liter untuk pendakian multi-hari. Rain cover bawaan, frame internal kokoh, dan harness yang nyaman dipakai berjam-jam.', 'consina_expedition_80.jpg'),
(2, 'Deuter',   'Aircontact 55L', 'Carrier premium 55 liter dengan sistem ventilasi punggung Aircontact. Material ringan namun kuat. Ideal untuk trekking menengah.', 'deuter_aircontact_55.jpg');

-- Item: Sepatu
INSERT INTO `item` (`id_kategori`, `nama_brand`, `nama_seri`, `deskripsi_umum`, `gambar`) VALUES
(3, 'Eiger',     'Anaconda Mid',  'Sepatu hiking mid-cut waterproof dengan sol Vibram. Grip kuat di medan berbatu dan berlumpur. Ankle support yang baik untuk pendakian.', 'eiger_anaconda.jpg'),
(3, 'Consina',   'Alpine GTX',    'Sepatu gunung Gore-Tex tahan air dan breathable. Sol karet tebal anti slip. Nyaman untuk trekking jarak jauh di berbagai medan.', 'consina_alpine.jpg');

-- Item: Alat Masak
INSERT INTO `item` (`id_kategori`, `nama_brand`, `nama_seri`, `deskripsi_umum`, `gambar`) VALUES
(4, 'Eiger',    'Kompor Portable', 'Kompor gas portable lipat ultralight untuk camping. Api stabil dan mudah diatur. Kompatibel dengan tabung gas standard.', 'eiger_kompor.jpg'),
(4, 'Consina',  'Nesting Set 3P',  'Set alat masak camping untuk 3 orang berisi panci dan wajan. Material aluminium food grade anti lengket. Compact dan mudah dibawa.', 'consina_nesting.jpg');

-- Item: Sleeping Bag
INSERT INTO `item` (`id_kategori`, `nama_brand`, `nama_seri`, `deskripsi_umum`, `gambar`) VALUES
(5, 'Eiger',    'Mummy 500',      'Sleeping bag mummy shape hangat untuk suhu dingin sampai 5 derajat. Isian hollow fiber tebal. Ringan dan bisa dikompres kecil.', 'eiger_mummy_500.jpg'),
(5, 'Consina',  'Sleep Warmer',    'Sleeping bag envelope nyaman dengan bahan polar fleece. Cocok untuk camping di dataran tinggi. Resleting dua arah mudah dibuka.', 'consina_sleepwarmer.jpg');

-- Item: Trekking Pole
INSERT INTO `item` (`id_kategori`, `nama_brand`, `nama_seri`, `deskripsi_umum`, `gambar`) VALUES
(6, 'Eiger',    'Carbon Trek',     'Trekking pole carbon fiber ultralight dengan mekanisme flip lock. Handle cork nyaman digenggam. Adjustable panjang 65-135cm.', 'eiger_carbon_trek.jpg');

-- Item: Headlamp
INSERT INTO `item` (`id_kategori`, `nama_brand`, `nama_seri`, `deskripsi_umum`, `gambar`) VALUES
(7, 'Eiger',    'Bright 300',      'Headlamp LED 300 lumen dengan 3 mode pencahayaan. Tahan air IPX4 dan baterai rechargeable USB. Ringan dan nyaman di kepala.', 'eiger_bright_300.jpg');

-- Item: Jaket
INSERT INTO `item` (`id_kategori`, `nama_brand`, `nama_seri`, `deskripsi_umum`, `gambar`) VALUES
(8, 'Eiger',    'Summit Windproof','Jaket windproof ringan tahan angin kencang di puncak. Bahan ripstop dengan DWR coating. Packable ke dalam kantong sendiri.', 'eiger_summit_wp.jpg'),
(8, 'Consina',  'Raincoat Pro',    'Jaket hujan waterproof dengan sealed seam technology. Material nylon breathable. Hood adjustable dan ventilasi di ketiak.', 'consina_raincoat.jpg');

-- Varian Item
-- Tenda Eiger Mongoose 2P
INSERT INTO `varian_item` (`id_item`, `keterangan_varian`, `harga_sewa_per_hari`, `stok_tersedia`, `catatan_kondisi`) VALUES
(1, '2 Person', 50000, 5, 'Kondisi baik, lengkap frame dan flysheet'),
(1, '2 Person + Footprint', 65000, 3, 'Termasuk footprint tambahan, kondisi aman');

-- Tenda Consina Magnum 4
INSERT INTO `varian_item` (`id_item`, `keterangan_varian`, `harga_sewa_per_hari`, `stok_tersedia`, `catatan_kondisi`) VALUES
(2, '4 Person', 75000, 4, 'Kondisi baik, sedikit noda di flysheet'),
(2, '4 Person + Inner Tent', 85000, 2, 'Full set, kondisi aman');

-- Tenda GO Java 2P
INSERT INTO `varian_item` (`id_item`, `keterangan_varian`, `harga_sewa_per_hari`, `stok_tersedia`, `catatan_kondisi`) VALUES
(3, '2 Person', 45000, 6, 'Kondisi aman, baru dicuci');

-- Carrier Eiger Excelsior
INSERT INTO `varian_item` (`id_item`, `keterangan_varian`, `harga_sewa_per_hari`, `stok_tersedia`, `catatan_kondisi`) VALUES
(4, '75 Liter', 60000, 3, 'Kondisi baik, semua buckle berfungsi');

-- Carrier Consina Expedition
INSERT INTO `varian_item` (`id_item`, `keterangan_varian`, `harga_sewa_per_hari`, `stok_tersedia`, `catatan_kondisi`) VALUES
(5, '80 Liter', 65000, 4, 'Kondisi aman, rain cover lengkap');

-- Carrier Deuter Aircontact
INSERT INTO `varian_item` (`id_item`, `keterangan_varian`, `harga_sewa_per_hari`, `stok_tersedia`, `catatan_kondisi`) VALUES
(6, '55 Liter', 70000, 2, 'Kondisi premium, seperti baru');

-- Sepatu Eiger Anaconda
INSERT INTO `varian_item` (`id_item`, `keterangan_varian`, `harga_sewa_per_hari`, `stok_tersedia`, `catatan_kondisi`) VALUES
(7, 'Ukuran 40', 35000, 3, 'Kondisi baik, sol masih tebal'),
(7, 'Ukuran 42', 35000, 4, 'Kondisi aman'),
(7, 'Ukuran 44', 35000, 2, 'Ada sedikit lecet di bagian toe cap');

-- Sepatu Consina Alpine
INSERT INTO `varian_item` (`id_item`, `keterangan_varian`, `harga_sewa_per_hari`, `stok_tersedia`, `catatan_kondisi`) VALUES
(8, 'Ukuran 41', 40000, 3, 'Kondisi baik'),
(8, 'Ukuran 43', 40000, 2, 'Kondisi aman, waterproofing masih bagus');

-- Kompor Eiger
INSERT INTO `varian_item` (`id_item`, `keterangan_varian`, `harga_sewa_per_hari`, `stok_tersedia`, `catatan_kondisi`) VALUES
(9, 'Standar', 20000, 8, 'Kondisi baik, api stabil');

-- Nesting Consina
INSERT INTO `varian_item` (`id_item`, `keterangan_varian`, `harga_sewa_per_hari`, `stok_tersedia`, `catatan_kondisi`) VALUES
(10, '3 Person Set', 25000, 5, 'Lengkap panci + wajan + tutup');

-- Sleeping Bag Eiger Mummy
INSERT INTO `varian_item` (`id_item`, `keterangan_varian`, `harga_sewa_per_hari`, `stok_tersedia`, `catatan_kondisi`) VALUES
(11, 'Standar', 30000, 6, 'Kondisi baik, bersih dan wangi');

-- Sleeping Bag Consina
INSERT INTO `varian_item` (`id_item`, `keterangan_varian`, `harga_sewa_per_hari`, `stok_tersedia`, `catatan_kondisi`) VALUES
(12, 'Standar', 25000, 5, 'Kondisi aman');

-- Trekking Pole Eiger
INSERT INTO `varian_item` (`id_item`, `keterangan_varian`, `harga_sewa_per_hari`, `stok_tersedia`, `catatan_kondisi`) VALUES
(13, 'Sepasang', 25000, 4, 'Kondisi baik, lock mechanism lancar');

-- Headlamp Eiger
INSERT INTO `varian_item` (`id_item`, `keterangan_varian`, `harga_sewa_per_hari`, `stok_tersedia`, `catatan_kondisi`) VALUES
(14, 'Standar', 15000, 10, 'Kondisi aman, baterai terisi penuh');

-- Jaket Eiger Summit
INSERT INTO `varian_item` (`id_item`, `keterangan_varian`, `harga_sewa_per_hari`, `stok_tersedia`, `catatan_kondisi`) VALUES
(15, 'Size M', 35000, 3, 'Kondisi baik'),
(15, 'Size L', 35000, 4, 'Kondisi aman'),
(15, 'Size XL', 35000, 2, 'Sedikit pilling di bagian lengan');

-- Jaket Consina Raincoat
INSERT INTO `varian_item` (`id_item`, `keterangan_varian`, `harga_sewa_per_hari`, `stok_tersedia`, `catatan_kondisi`) VALUES
(16, 'Size M', 30000, 3, 'Kondisi baik, sealed seam utuh'),
(16, 'Size L', 30000, 4, 'Kondisi aman');
