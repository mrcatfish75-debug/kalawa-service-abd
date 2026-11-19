-- Schema demo publik: hanya definisi, tanpa baris data dari backup.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
DELIMITER $$
CREATE TABLE `akun_aplikasi` (
  `id_akun` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_db` enum('rl_owner_ops','rl_branch_admin','rl_cashier_ops','rl_service_tech','rl_inventory_ops','rl_internal_audit') NOT NULL,
  `id_pegawai` varchar(10) DEFAULT NULL,
  `kode_cabang` varchar(10) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_akun`),
  UNIQUE KEY `uq_akun_username` (`username`),
  UNIQUE KEY `uq_akun_pegawai` (`id_pegawai`),
  KEY `idx_akun_cabang` (`kode_cabang`),
  CONSTRAINT `fk_akun_cabang` FOREIGN KEY (`kode_cabang`) REFERENCES `cabang` (`kode_cabang`) ON UPDATE CASCADE,
  CONSTRAINT `fk_akun_pegawai` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawai` (`id_pegawai`) ON UPDATE CASCADE,
  CONSTRAINT `chk_akun_aktif` CHECK (`aktif` in (0,1)),
  CONSTRAINT `chk_akun_staf_cabang` CHECK (`role_db` in ('rl_owner_ops','rl_internal_audit') or `id_pegawai` is not null and `kode_cabang` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Akun login aplikasi terpisah dari user MariaDB'$$

CREATE TABLE `cabang` (
  `kode_cabang` varchar(10) NOT NULL,
  `nama_cabang` varchar(100) NOT NULL,
  `alamat` varchar(255) NOT NULL,
  `no_telepon` varchar(20) NOT NULL,
  `jam_operasional` varchar(50) NOT NULL,
  `kapasitas_slot_servis` int(10) unsigned NOT NULL,
  PRIMARY KEY (`kode_cabang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$$

CREATE TABLE `detail_tagihan_korporat` (
  `no_work_order` varchar(20) NOT NULL,
  `id_pelanggan` varchar(15) NOT NULL,
  `id_tagihan` bigint(20) unsigned NOT NULL,
  `nilai_final` decimal(14,2) NOT NULL,
  PRIMARY KEY (`no_work_order`),
  KEY `ix_dtk_wo_pelanggan` (`no_work_order`,`id_pelanggan`),
  KEY `ix_dtk_tagihan_pelanggan` (`id_tagihan`,`id_pelanggan`),
  CONSTRAINT `fk_dtk_tagihan` FOREIGN KEY (`id_tagihan`, `id_pelanggan`) REFERENCES `tagihan_korporat` (`id_tagihan`, `id_pelanggan`),
  CONSTRAINT `fk_dtk_wo` FOREIGN KEY (`no_work_order`, `id_pelanggan`) REFERENCES `work_order_korporat` (`no_work_order`, `id_pelanggan`),
  CONSTRAINT `chk_dtk_nilai` CHECK (`nilai_final` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$$

CREATE TABLE `detail_wo_jasa` (
  `no_work_order` varchar(20) NOT NULL,
  `kode_jasa` varchar(10) NOT NULL,
  `biaya` decimal(12,2) NOT NULL,
  PRIMARY KEY (`no_work_order`,`kode_jasa`),
  KEY `fk_dwj_jasa` (`kode_jasa`),
  CONSTRAINT `fk_dwj_jasa` FOREIGN KEY (`kode_jasa`) REFERENCES `jasa_servis` (`kode_jasa`) ON UPDATE CASCADE,
  CONSTRAINT `fk_dwj_wo` FOREIGN KEY (`no_work_order`) REFERENCES `work_order` (`no_work_order`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$$

CREATE TABLE `detail_wo_suku_cadang` (
  `no_work_order` varchar(20) NOT NULL,
  `kode_suku_cadang` varchar(15) NOT NULL,
  `qty` int(10) unsigned NOT NULL,
  `harga_satuan` decimal(12,2) NOT NULL,
  PRIMARY KEY (`no_work_order`,`kode_suku_cadang`),
  KEY `fk_dwsc_suku_cadang` (`kode_suku_cadang`),
  CONSTRAINT `fk_dwsc_suku_cadang` FOREIGN KEY (`kode_suku_cadang`) REFERENCES `suku_cadang` (`kode_suku_cadang`) ON UPDATE CASCADE,
  CONSTRAINT `fk_dwsc_wo` FOREIGN KEY (`no_work_order`) REFERENCES `work_order` (`no_work_order`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$$

CREATE TABLE `garansi` (
  `no_work_order` varchar(20) NOT NULL,
  `jumlah_hari_garansi` smallint(5) unsigned NOT NULL DEFAULT 30 COMMENT 'Durasi garansi untuk kebutuhan function status garansi',
  `catatan_garansi` varchar(255) NOT NULL,
  PRIMARY KEY (`no_work_order`),
  CONSTRAINT `fk_garansi_wo` FOREIGN KEY (`no_work_order`) REFERENCES `work_order` (`no_work_order`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$$

CREATE TABLE `jasa_servis` (
  `kode_jasa` varchar(10) NOT NULL,
  `uraian_jasa_servis` varchar(150) NOT NULL,
  PRIMARY KEY (`kode_jasa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master jenis jasa servis'$$

CREATE TABLE `kepemilikan_motor` (
  `no_polisi` varchar(15) NOT NULL,
  `id_pelanggan` varchar(15) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  PRIMARY KEY (`no_polisi`,`id_pelanggan`,`tanggal_mulai`),
  KEY `fk_kepemilikan_pelanggan` (`id_pelanggan`),
  CONSTRAINT `fk_kepemilikan_motor` FOREIGN KEY (`no_polisi`) REFERENCES `motor` (`no_polisi`) ON UPDATE CASCADE,
  CONSTRAINT `fk_kepemilikan_pelanggan` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id_pelanggan`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$$

CREATE TABLE `kontrak_korporat` (
  `id_kontrak` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_pelanggan` varchar(15) NOT NULL,
  `berlaku_mulai` date NOT NULL,
  `berlaku_sampai` date NOT NULL,
  `plafon_kredit` decimal(14,2) NOT NULL,
  PRIMARY KEY (`id_kontrak`),
  UNIQUE KEY `uq_kk_kontrak_pelanggan` (`id_kontrak`,`id_pelanggan`),
  UNIQUE KEY `uq_kk_pelanggan_mulai` (`id_pelanggan`,`berlaku_mulai`),
  CONSTRAINT `fk_kk_profil` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan_korporat` (`id_pelanggan`),
  CONSTRAINT `chk_kk_periode` CHECK (`berlaku_mulai` >= '1000-01-01' and `berlaku_sampai` >= `berlaku_mulai`),
  CONSTRAINT `chk_kk_plafon` CHECK (`plafon_kredit` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$$

CREATE TABLE `log_audit_operasional` (
  `id_audit` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `objek_data` varchar(64) NOT NULL,
  `kunci_rekam` varchar(64) NOT NULL,
  `jenis_operasi` varchar(16) NOT NULL,
  `atribut_berubah` varchar(255) NOT NULL,
  `nilai_sebelum` text DEFAULT NULL,
  `nilai_sesudah` text DEFAULT NULL,
  `akun_sesi` varchar(150) NOT NULL,
  `waktu_kejadian` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_audit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Jejak perubahan penting pada work order dan pembayaran'$$

CREATE TABLE `mekanik` (
  `id_mekanik` varchar(10) NOT NULL COMMENT 'ID mekanik sekaligus FK ke pegawai sesuai relasi akhir 3NF',
  `tanggal_mulai_bekerja` date NOT NULL,
  `status_aktif` tinyint(1) NOT NULL DEFAULT 1,
  `bidang_keahlian` varchar(100) NOT NULL,
  PRIMARY KEY (`id_mekanik`),
  CONSTRAINT `fk_mekanik_pegawai` FOREIGN KEY (`id_mekanik`) REFERENCES `pegawai` (`id_pegawai`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Data khusus pegawai yang menjalankan fungsi mekanik'$$

CREATE TABLE `mekanik_pembantu_wo` (
  `no_work_order` varchar(20) NOT NULL,
  `id_mekanik` varchar(10) NOT NULL,
  PRIMARY KEY (`no_work_order`,`id_mekanik`),
  KEY `fk_mpw_mekanik` (`id_mekanik`),
  CONSTRAINT `fk_mpw_mekanik` FOREIGN KEY (`id_mekanik`) REFERENCES `mekanik` (`id_mekanik`) ON UPDATE CASCADE,
  CONSTRAINT `fk_mpw_wo` FOREIGN KEY (`no_work_order`) REFERENCES `work_order` (`no_work_order`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mekanik tambahan yang membantu pengerjaan suatu work order'$$

CREATE TABLE `motor` (
  `no_polisi` varchar(15) NOT NULL,
  `no_rangka` varchar(30) NOT NULL,
  `no_mesin` varchar(30) NOT NULL,
  `merek` varchar(50) NOT NULL,
  `tipe` varchar(80) NOT NULL,
  `tahun_produksi` smallint(5) unsigned NOT NULL,
  `warna` varchar(30) NOT NULL,
  `kilometer_terakhir` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`no_polisi`),
  UNIQUE KEY `no_rangka` (`no_rangka`),
  UNIQUE KEY `no_mesin` (`no_mesin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master kendaraan pelanggan'$$

CREATE TABLE `mutasi_stok_manual` (
  `id_mutasi` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_permintaan` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `kode_cabang` varchar(10) NOT NULL,
  `kode_suku_cadang` varchar(15) NOT NULL,
  `id_akun` int(10) unsigned NOT NULL,
  `jenis_mutasi` enum('STOK_AWAL','BARANG_MASUK','KOREKSI','UBAH_MINIMUM') NOT NULL,
  `stok_sebelum` int(10) unsigned NOT NULL,
  `stok_sesudah` int(10) unsigned NOT NULL,
  `minimum_sebelum` int(10) unsigned NOT NULL,
  `minimum_sesudah` int(10) unsigned NOT NULL,
  `catatan` varchar(255) NOT NULL,
  `dicatat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_mutasi`),
  UNIQUE KEY `uq_mutasi_permintaan` (`id_permintaan`),
  KEY `idx_mutasi_cabang_barang_tanggal` (`kode_cabang`,`kode_suku_cadang`,`dicatat_pada`),
  KEY `idx_mutasi_akun` (`id_akun`),
  CONSTRAINT `fk_mutasi_stok_akun` FOREIGN KEY (`id_akun`) REFERENCES `akun_aplikasi` (`id_akun`) ON UPDATE CASCADE,
  CONSTRAINT `fk_mutasi_stok_cabang_barang` FOREIGN KEY (`kode_cabang`, `kode_suku_cadang`) REFERENCES `stok_cabang` (`kode_cabang`, `kode_suku_cadang`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Jejak perubahan stok MANUAL melalui modul admin; tidak mencakup pemakaian WO'$$

CREATE TABLE `pegawai` (
  `id_pegawai` varchar(10) NOT NULL,
  `kode_cabang` varchar(10) NOT NULL,
  `nama_pegawai` varchar(100) NOT NULL,
  `no_telepon` varchar(20) NOT NULL,
  `jenis_pegawai` enum('ADMIN','KASIR','MEKANIK','PETUGAS_GUDANG') NOT NULL,
  PRIMARY KEY (`id_pegawai`),
  KEY `fk_pegawai_cabang` (`kode_cabang`),
  CONSTRAINT `fk_pegawai_cabang` FOREIGN KEY (`kode_cabang`) REFERENCES `cabang` (`kode_cabang`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Data pegawai berdasarkan cabang penempatan utama'$$

CREATE TABLE `pelanggan` (
  `id_pelanggan` varchar(15) NOT NULL,
  `nama_pelanggan` varchar(100) NOT NULL,
  `no_telepon` varchar(20) NOT NULL,
  `jenis_identitas` varchar(30) NOT NULL,
  `no_identitas` varchar(30) NOT NULL,
  `alamat` varchar(255) NOT NULL,
  `kode_cabang_favorit` varchar(10) DEFAULT NULL,
  `id_mekanik_pilihan` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id_pelanggan`),
  UNIQUE KEY `no_identitas` (`no_identitas`),
  KEY `fk_pelanggan_cabang_favorit` (`kode_cabang_favorit`),
  KEY `fk_pelanggan_mekanik_pilihan` (`id_mekanik_pilihan`),
  CONSTRAINT `fk_pelanggan_cabang_favorit` FOREIGN KEY (`kode_cabang_favorit`) REFERENCES `cabang` (`kode_cabang`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pelanggan_mekanik_pilihan` FOREIGN KEY (`id_mekanik_pilihan`) REFERENCES `mekanik` (`id_mekanik`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master pelanggan beserta preferensi cabang dan mekanik'$$

CREATE TABLE `pelanggan_korporat` (
  `id_pelanggan` varchar(15) NOT NULL,
  `nama_pic` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_pelanggan`),
  CONSTRAINT `fk_pk_pelanggan` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id_pelanggan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$$

CREATE TABLE `pembayaran` (
  `no_work_order` varchar(20) NOT NULL,
  `diskon_member` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_bayar` decimal(12,2) NOT NULL,
  `metode_pembayaran` enum('TUNAI','TRANSFER','QRIS','KARTU_DEBIT') NOT NULL,
  `status_pembayaran` enum('BELUM_LUNAS','LUNAS') NOT NULL DEFAULT 'BELUM_LUNAS',
  PRIMARY KEY (`no_work_order`),
  CONSTRAINT `fk_pembayaran_wo` FOREIGN KEY (`no_work_order`) REFERENCES `work_order` (`no_work_order`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$$

CREATE TABLE `penerimaan_kas` (
  `id_penerimaan` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `no_work_order` varchar(20) DEFAULT NULL,
  `id_tagihan` bigint(20) unsigned DEFAULT NULL,
  `dicatat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `id_akun` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_penerimaan`),
  UNIQUE KEY `uq_pk_wo` (`no_work_order`),
  UNIQUE KEY `uq_pk_tagihan` (`id_tagihan`),
  KEY `fk_pkas_akun` (`id_akun`),
  CONSTRAINT `fk_pkas_akun` FOREIGN KEY (`id_akun`) REFERENCES `akun_aplikasi` (`id_akun`),
  CONSTRAINT `fk_pkas_pembayaran` FOREIGN KEY (`no_work_order`) REFERENCES `pembayaran` (`no_work_order`),
  CONSTRAINT `fk_pkas_tagihan` FOREIGN KEY (`id_tagihan`) REFERENCES `tagihan_korporat` (`id_tagihan`),
  CONSTRAINT `chk_pkas_target` CHECK (`no_work_order` is not null and `id_tagihan` is null or `no_work_order` is null and `id_tagihan` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$$

CREATE TABLE `stok_cabang` (
  `kode_cabang` varchar(10) NOT NULL,
  `kode_suku_cadang` varchar(15) NOT NULL,
  `jumlah_stok` int(10) unsigned NOT NULL DEFAULT 0,
  `stok_minimum` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`kode_cabang`,`kode_suku_cadang`),
  KEY `fk_stok_suku_cadang` (`kode_suku_cadang`),
  CONSTRAINT `fk_stok_cabang` FOREIGN KEY (`kode_cabang`) REFERENCES `cabang` (`kode_cabang`) ON UPDATE CASCADE,
  CONSTRAINT `fk_stok_suku_cadang` FOREIGN KEY (`kode_suku_cadang`) REFERENCES `suku_cadang` (`kode_suku_cadang`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$$

CREATE TABLE `suku_cadang` (
  `kode_suku_cadang` varchar(15) NOT NULL,
  `nama_suku_cadang` varchar(150) NOT NULL,
  PRIMARY KEY (`kode_suku_cadang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master suku cadang'$$

CREATE TABLE `supplier` (
  `id_supplier` varchar(10) NOT NULL,
  `nama_supplier` varchar(120) NOT NULL,
  PRIMARY KEY (`id_supplier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master supplier suku cadang'$$

CREATE TABLE `supplier_suku_cadang` (
  `id_supplier` varchar(10) NOT NULL,
  `kode_suku_cadang` varchar(15) NOT NULL,
  PRIMARY KEY (`id_supplier`,`kode_suku_cadang`),
  KEY `fk_ssc_suku_cadang` (`kode_suku_cadang`),
  CONSTRAINT `fk_ssc_suku_cadang` FOREIGN KEY (`kode_suku_cadang`) REFERENCES `suku_cadang` (`kode_suku_cadang`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ssc_supplier` FOREIGN KEY (`id_supplier`) REFERENCES `supplier` (`id_supplier`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relasi supplier terhadap suku cadang yang dapat dipasok'$$

CREATE TABLE `tagihan_korporat` (
  `id_tagihan` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_pelanggan` varchar(15) NOT NULL,
  `periode` date NOT NULL,
  `terbit_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `status_pembayaran` enum('BELUM_LUNAS','LUNAS') NOT NULL DEFAULT 'BELUM_LUNAS',
  `dibayar_pada` datetime DEFAULT NULL,
  `metode_pembayaran` enum('TUNAI','TRANSFER','QRIS','KARTU_DEBIT') DEFAULT NULL,
  PRIMARY KEY (`id_tagihan`),
  UNIQUE KEY `uq_tk_tagihan_pelanggan` (`id_tagihan`,`id_pelanggan`),
  UNIQUE KEY `uq_tk_pelanggan_periode` (`id_pelanggan`,`periode`),
  CONSTRAINT `fk_tk_profil` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan_korporat` (`id_pelanggan`),
  CONSTRAINT `chk_tk_bulan` CHECK (`periode` >= '1000-01-01' and dayofmonth(`periode`) = 1),
  CONSTRAINT `chk_tk_pelunasan` CHECK (`status_pembayaran` = 'BELUM_LUNAS' and `dibayar_pada` is null and `metode_pembayaran` is null or `status_pembayaran` = 'LUNAS' and `dibayar_pada` is not null and `dibayar_pada` >= `terbit_pada` and `metode_pembayaran` is not null and `metode_pembayaran` in ('TUNAI','TRANSFER','QRIS','KARTU_DEBIT'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$$

CREATE TABLE `work_order` (
  `no_work_order` varchar(20) NOT NULL,
  `tanggal_masuk` datetime NOT NULL,
  `tanggal_selesai` datetime DEFAULT NULL COMMENT 'Atribut implementasi untuk kebutuhan perhitungan garansi',
  `kode_cabang` varchar(10) NOT NULL,
  `id_pelanggan` varchar(15) NOT NULL,
  `no_polisi` varchar(15) NOT NULL,
  `id_mekanik_pj` varchar(10) NOT NULL COMMENT 'Mekanik utama yang bertanggung jawab atas work order',
  `keluhan` text NOT NULL,
  `estimasi_biaya` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status_pekerjaan` enum('MENUNGGU','PROSES','SELESAI','DIBATALKAN') NOT NULL DEFAULT 'MENUNGGU',
  PRIMARY KEY (`no_work_order`),
  UNIQUE KEY `uq_wo_korporat_pelanggan` (`no_work_order`,`id_pelanggan`),
  KEY `fk_wo_cabang` (`kode_cabang`),
  KEY `fk_wo_pelanggan` (`id_pelanggan`),
  KEY `fk_wo_motor` (`no_polisi`),
  KEY `fk_wo_mekanik` (`id_mekanik_pj`),
  CONSTRAINT `fk_wo_cabang` FOREIGN KEY (`kode_cabang`) REFERENCES `cabang` (`kode_cabang`) ON UPDATE CASCADE,
  CONSTRAINT `fk_wo_mekanik` FOREIGN KEY (`id_mekanik_pj`) REFERENCES `mekanik` (`id_mekanik`) ON UPDATE CASCADE,
  CONSTRAINT `fk_wo_motor` FOREIGN KEY (`no_polisi`) REFERENCES `motor` (`no_polisi`) ON UPDATE CASCADE,
  CONSTRAINT `fk_wo_pelanggan` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id_pelanggan`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$$

CREATE TABLE `work_order_korporat` (
  `no_work_order` varchar(20) NOT NULL,
  `id_pelanggan` varchar(15) NOT NULL,
  `id_kontrak` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`no_work_order`),
  UNIQUE KEY `uq_wok_wo_pelanggan` (`no_work_order`,`id_pelanggan`),
  KEY `ix_wok_kontrak_pelanggan` (`id_kontrak`,`id_pelanggan`),
  CONSTRAINT `fk_wok_kontrak` FOREIGN KEY (`id_kontrak`, `id_pelanggan`) REFERENCES `kontrak_korporat` (`id_kontrak`, `id_pelanggan`),
  CONSTRAINT `fk_wok_wo` FOREIGN KEY (`no_work_order`, `id_pelanggan`) REFERENCES `work_order` (`no_work_order`, `id_pelanggan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$$

CREATE FUNCTION `fn_status_garansi_wo`(p_no_work_order VARCHAR(20)) RETURNS varchar(30) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
    READS SQL DATA
    SQL SECURITY INVOKER
BEGIN
    DECLARE v_selesai DATE DEFAULT NULL;
    DECLARE v_hari INT DEFAULT 0;
    DECLARE v_pekerjaan VARCHAR(20) DEFAULT '';
    DECLARE v_bayar VARCHAR(20) DEFAULT '';
    DECLARE v_ada INT DEFAULT 0;
    SELECT COUNT(*) INTO v_ada FROM garansi WHERE no_work_order=p_no_work_order;
    IF v_ada=0 THEN RETURN 'TIDAK ADA GARANSI'; END IF;
    SELECT DATE(w.tanggal_selesai),g.jumlah_hari_garansi,w.status_pekerjaan,
        CASE WHEN wk.no_work_order IS NOT NULL THEN COALESCE(t.status_pembayaran,'BELUM_LUNAS')
             ELSE COALESCE(p.status_pembayaran,'BELUM_LUNAS') END
        INTO v_selesai,v_hari,v_pekerjaan,v_bayar
        FROM garansi g JOIN work_order w ON w.no_work_order=g.no_work_order
        LEFT JOIN work_order_korporat wk ON wk.no_work_order=w.no_work_order
        LEFT JOIN pembayaran p ON p.no_work_order=w.no_work_order
        LEFT JOIN detail_tagihan_korporat d ON d.no_work_order=w.no_work_order
        LEFT JOIN tagihan_korporat t ON t.id_tagihan=d.id_tagihan
        WHERE g.no_work_order=p_no_work_order LIMIT 1;
    IF v_pekerjaan<>'SELESAI' OR v_selesai IS NULL OR v_bayar<>'LUNAS' OR CURDATE()<v_selesai
        THEN RETURN 'BELUM BERLAKU'; END IF;
    IF CURDATE()<=DATE_ADD(v_selesai,INTERVAL v_hari DAY) THEN RETURN 'AKTIF'; END IF;
    RETURN 'KEDALUWARSA';
END$$

CREATE PROCEDURE `sp_bukti_trigger_tanggal`()
BEGIN
    DECLARE v_ditolak TINYINT DEFAULT 0;
    DECLARE v_errno INT DEFAULT NULL;
    DECLARE v_pesan TEXT DEFAULT NULL;

    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000'
    BEGIN
        GET DIAGNOSTICS CONDITION 1
            v_errno = MYSQL_ERRNO,
            v_pesan = MESSAGE_TEXT;

        SET v_ditolak = 1;
    END;

    DELETE FROM work_order
    WHERE no_work_order = 'WO-TEST-FUTURE';

    INSERT INTO work_order
    (
        no_work_order,
        tanggal_masuk,
        tanggal_selesai,
        kode_cabang,
        id_pelanggan,
        no_polisi,
        id_mekanik_pj,
        keluhan,
        estimasi_biaya,
        status_pekerjaan
    )
    VALUES
    (
        'WO-TEST-FUTURE',
        DATE_ADD(NOW(), INTERVAL 2 DAY),
        NULL,
        'CBG001',
        'CUSD001',
        'DM 1001 DA',
        'MK001',
        'Pengujian trigger tanggal',
        100000,
        'MENUNGGU'
    );

    SELECT
        'Trigger Validasi Tanggal WO' AS pengujian,
        DATE_ADD(CURDATE(), INTERVAL 2 DAY) AS tanggal_yang_dicoba,
        CASE
            WHEN v_ditolak = 1 THEN 'BERHASIL - DITOLAK'
            ELSE 'GAGAL - TIDAK DITOLAK'
        END AS status_trigger,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM work_order
                WHERE no_work_order = 'WO-TEST-FUTURE'
            )
            THEN 'YA'
            ELSE 'TIDAK'
        END AS data_tersimpan,
        v_errno AS kode_error,
        v_pesan AS pesan_error;

    DELETE FROM work_order
    WHERE no_work_order = 'WO-TEST-FUTURE';
END$$

CREATE PROCEDURE `sp_daftar_wo_cabang`(IN `p_tanggal_awal` DATE, IN `p_tanggal_akhir` DATE, IN `p_kode_cabang` VARCHAR(10))
    SQL SECURITY INVOKER
BEGIN
    IF p_tanggal_awal IS NULL
       OR p_tanggal_akhir IS NULL
       OR p_tanggal_awal > p_tanggal_akhir THEN

        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'Rentang tanggal work order tidak valid.';
    END IF;

    SELECT
        no_work_order,
        tanggal_masuk,
        tanggal_selesai,
        kode_cabang,
        id_pelanggan,
        no_polisi,
        id_mekanik_pj,
        keluhan,
        estimasi_biaya,
        status_pekerjaan
    FROM work_order
    WHERE kode_cabang = p_kode_cabang
      AND DATE(tanggal_masuk)
          BETWEEN p_tanggal_awal AND p_tanggal_akhir
    ORDER BY tanggal_masuk, no_work_order;
END$$

CREATE PROCEDURE `sp_rincian_tagihan_wo`(IN `p_no_work_order` VARCHAR(20))
    SQL SECURITY INVOKER
BEGIN
    DECLARE v_wo_ada INT DEFAULT 0;
    DECLARE v_total_jasa DECIMAL(12,2) DEFAULT 0;
    DECLARE v_total_suku_cadang DECIMAL(12,2) DEFAULT 0;

    SELECT COUNT(*)
      INTO v_wo_ada
      FROM work_order
     WHERE no_work_order = p_no_work_order;

    IF v_wo_ada = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Nomor work order tidak ditemukan.';
    END IF;

    SELECT COALESCE(SUM(biaya), 0)
      INTO v_total_jasa
      FROM detail_wo_jasa
     WHERE no_work_order = p_no_work_order;

    SELECT COALESCE(SUM(qty * harga_satuan), 0)
      INTO v_total_suku_cadang
      FROM detail_wo_suku_cadang
     WHERE no_work_order = p_no_work_order;

    SELECT
        p_no_work_order AS no_work_order,
        v_total_jasa AS total_jasa,
        v_total_suku_cadang AS total_suku_cadang,
        (v_total_jasa + v_total_suku_cadang) AS total_tagihan;
END$$

CREATE PROCEDURE `sp_riwayat_servis_motor`(IN `p_no_polisi` VARCHAR(15))
    SQL SECURITY INVOKER
BEGIN
    SELECT
        wo.no_work_order,
        wo.tanggal_masuk,
        wo.tanggal_selesai,
        wo.kode_cabang,
        wo.no_polisi,
        wo.id_mekanik_pj,
        wo.keluhan,
        wo.status_pekerjaan,

        COALESCE(
            (
                SELECT GROUP_CONCAT(
                    CONCAT(js.uraian_jasa_servis, ' [Rp ', dj.biaya, ']')
                    ORDER BY js.uraian_jasa_servis
                    SEPARATOR '; '
                )
                FROM detail_wo_jasa AS dj
                JOIN jasa_servis AS js
                  ON js.kode_jasa = dj.kode_jasa
                WHERE dj.no_work_order = wo.no_work_order
            ),
            '-'
        ) AS jasa_dikerjakan,

        COALESCE(
            (
                SELECT GROUP_CONCAT(
                    CONCAT(sc.nama_suku_cadang, ' x', ds.qty)
                    ORDER BY sc.nama_suku_cadang
                    SEPARATOR '; '
                )
                FROM detail_wo_suku_cadang AS ds
                JOIN suku_cadang AS sc
                  ON sc.kode_suku_cadang = ds.kode_suku_cadang
                WHERE ds.no_work_order = wo.no_work_order
            ),
            '-'
        ) AS suku_cadang_digunakan

    FROM work_order AS wo
    WHERE wo.no_polisi = p_no_polisi
    ORDER BY wo.tanggal_masuk DESC;
END$$

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vw_pelanggan_akses_terbatas` AS select `pelanggan`.`id_pelanggan` AS `id_pelanggan`,`pelanggan`.`nama_pelanggan` AS `nama_pelanggan`,concat(left(`pelanggan`.`no_telepon`,4),repeat('*',greatest(0,char_length(`pelanggan`.`no_telepon`) - 7)),right(`pelanggan`.`no_telepon`,3)) AS `no_telepon_masked`,`pelanggan`.`jenis_identitas` AS `jenis_identitas`,concat(left(`pelanggan`.`no_identitas`,4),repeat('*',greatest(0,char_length(`pelanggan`.`no_identitas`) - 8)),right(`pelanggan`.`no_identitas`,4)) AS `no_identitas_masked`,`pelanggan`.`kode_cabang_favorit` AS `kode_cabang_favorit` from `pelanggan`$$

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vw_ringkasan_transaksi_audit` AS select `wo`.`no_work_order` AS `no_work_order`,`wo`.`tanggal_masuk` AS `tanggal_masuk`,`wo`.`tanggal_selesai` AS `tanggal_selesai`,`wo`.`kode_cabang` AS `kode_cabang`,`wo`.`no_polisi` AS `no_polisi`,`wo`.`status_pekerjaan` AS `status_pekerjaan`,`p`.`total_bayar` AS `total_bayar`,`p`.`metode_pembayaran` AS `metode_pembayaran`,`p`.`status_pembayaran` AS `status_pembayaran` from (`work_order` `wo` left join `pembayaran` `p` on(`p`.`no_work_order` = `wo`.`no_work_order`))$$

CREATE ALGORITHM=TEMPTABLE SQL SECURITY DEFINER VIEW `vw_ringkasan_transaksi_terpadu` AS select `w`.`no_work_order` AS `no_work_order`,`w`.`tanggal_masuk` AS `tanggal_masuk`,`w`.`tanggal_selesai` AS `tanggal_selesai`,`w`.`kode_cabang` AS `kode_cabang`,`w`.`no_polisi` AS `no_polisi`,`w`.`status_pekerjaan` AS `status_pekerjaan`,case when `wk`.`no_work_order` is null then 'INDIVIDUAL' else 'KORPORAT' end AS `jenis_transaksi`,`d`.`id_tagihan` AS `id_tagihan`,case when `w`.`status_pekerjaan` = 'DIBATALKAN' then 'TIDAK_DITAGIH' else case when `wk`.`no_work_order` is not null then coalesce(`t`.`status_pembayaran`,'BELUM_DITAGIHKAN') else coalesce(`p`.`status_pembayaran`,'BELUM_LUNAS') end end AS `status_pembayaran`,cast(case when `wk`.`no_work_order` is not null then coalesce(`d`.`nilai_final`,coalesce((select sum(`j`.`biaya`) from `detail_wo_jasa` `j` where `j`.`no_work_order` = `w`.`no_work_order`),0) + coalesce((select sum(`s`.`qty` * `s`.`harga_satuan`) from `detail_wo_suku_cadang` `s` where `s`.`no_work_order` = `w`.`no_work_order`),0)) else coalesce(`p`.`total_bayar`,coalesce((select sum(`j`.`biaya`) from `detail_wo_jasa` `j` where `j`.`no_work_order` = `w`.`no_work_order`),0) + coalesce((select sum(`s`.`qty` * `s`.`harga_satuan`) from `detail_wo_suku_cadang` `s` where `s`.`no_work_order` = `w`.`no_work_order`),0)) end as decimal(14,2)) AS `nilai_wo`,cast(case when `w`.`status_pekerjaan` <> 'DIBATALKAN' and case when `wk`.`no_work_order` is not null then coalesce(`t`.`status_pembayaran`,'BELUM_DITAGIHKAN') else coalesce(`p`.`status_pembayaran`,'BELUM_LUNAS') end = 'LUNAS' then case when `wk`.`no_work_order` is not null then coalesce(`d`.`nilai_final`,coalesce((select sum(`j`.`biaya`) from `detail_wo_jasa` `j` where `j`.`no_work_order` = `w`.`no_work_order`),0) + coalesce((select sum(`s`.`qty` * `s`.`harga_satuan`) from `detail_wo_suku_cadang` `s` where `s`.`no_work_order` = `w`.`no_work_order`),0)) else coalesce(`p`.`total_bayar`,coalesce((select sum(`j`.`biaya`) from `detail_wo_jasa` `j` where `j`.`no_work_order` = `w`.`no_work_order`),0) + coalesce((select sum(`s`.`qty` * `s`.`harga_satuan`) from `detail_wo_suku_cadang` `s` where `s`.`no_work_order` = `w`.`no_work_order`),0)) end else 0 end as decimal(14,2)) AS `penerimaan_wo`,case when `wk`.`no_work_order` is not null then `t`.`metode_pembayaran` else `p`.`metode_pembayaran` end AS `metode_pembayaran`,case when `wk`.`no_work_order` is not null then `t`.`dibayar_pada` else `k`.`dicatat_pada` end AS `waktu_penerimaan` from (((((`work_order` `w` left join `work_order_korporat` `wk` on(`wk`.`no_work_order` = `w`.`no_work_order`)) left join `detail_tagihan_korporat` `d` on(`d`.`no_work_order` = `w`.`no_work_order`)) left join `tagihan_korporat` `t` on(`t`.`id_tagihan` = `d`.`id_tagihan`)) left join `pembayaran` `p` on(`p`.`no_work_order` = `w`.`no_work_order`)) left join `penerimaan_kas` `k` on(`k`.`no_work_order` = `w`.`no_work_order`))$$

CREATE TRIGGER `trg_audit_perubahan_pembayaran` AFTER UPDATE ON `pembayaran` FOR EACH ROW BEGIN
    IF NOT (OLD.status_pembayaran <=> NEW.status_pembayaran)
       OR NOT (OLD.total_bayar <=> NEW.total_bayar)
       OR NOT (OLD.metode_pembayaran <=> NEW.metode_pembayaran) THEN

        INSERT INTO log_audit_operasional
        (
            objek_data,
            kunci_rekam,
            jenis_operasi,
            atribut_berubah,
            nilai_sebelum,
            nilai_sesudah,
            akun_sesi
        )
        VALUES
        (
            'pembayaran',
            NEW.no_work_order,
            'UPDATE',
            'status_pembayaran,total_bayar,metode_pembayaran',
            CONCAT(
                'status=', OLD.status_pembayaran,
                '; total=', OLD.total_bayar,
                '; metode=', OLD.metode_pembayaran
            ),
            CONCAT(
                'status=', NEW.status_pembayaran,
                '; total=', NEW.total_bayar,
                '; metode=', NEW.metode_pembayaran
            ),
            USER()
        );
    END IF;
END$$

CREATE TRIGGER `trg_audit_perubahan_status_wo` AFTER UPDATE ON `work_order` FOR EACH ROW BEGIN
    IF NOT (OLD.status_pekerjaan <=> NEW.status_pekerjaan) THEN
        INSERT INTO log_audit_operasional
        (
            objek_data,
            kunci_rekam,
            jenis_operasi,
            atribut_berubah,
            nilai_sebelum,
            nilai_sesudah,
            akun_sesi
        )
        VALUES
        (
            'work_order',
            NEW.no_work_order,
            'UPDATE',
            'status_pekerjaan',
            OLD.status_pekerjaan,
            NEW.status_pekerjaan,
            USER()
        );
    END IF;
END$$

CREATE TRIGGER `trg_pemakaian_spare_kurangi_stok` BEFORE INSERT ON `detail_wo_suku_cadang` FOR EACH ROW BEGIN
    DECLARE v_kode_cabang VARCHAR(10);
    DECLARE v_jumlah_stok INT DEFAULT 0;
    DECLARE v_ada_stok INT DEFAULT 0;

    SELECT MAX(kode_cabang)
      INTO v_kode_cabang
      FROM work_order
     WHERE no_work_order = NEW.no_work_order;

    IF v_kode_cabang IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Work order tidak ditemukan.';
    END IF;

    SELECT COUNT(*), COALESCE(MAX(jumlah_stok), 0)
      INTO v_ada_stok, v_jumlah_stok
      FROM stok_cabang
     WHERE kode_cabang = v_kode_cabang
       AND kode_suku_cadang = NEW.kode_suku_cadang;

    IF v_ada_stok = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'Stok suku cadang belum tersedia pada cabang work order.';
    END IF;

    IF NEW.qty > v_jumlah_stok THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'Jumlah suku cadang yang digunakan melebihi stok cabang.';
    END IF;

    UPDATE stok_cabang
       SET jumlah_stok = jumlah_stok - NEW.qty
     WHERE kode_cabang = v_kode_cabang
       AND kode_suku_cadang = NEW.kode_suku_cadang;
END$$

CREATE TRIGGER `trg_wo_validasi_tanggal_masuk` BEFORE INSERT ON `work_order` FOR EACH ROW BEGIN
    IF DATE(NEW.tanggal_masuk) > CURDATE() THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'Tanggal masuk work order tidak boleh melebihi tanggal hari ini.';
    END IF;
END$$
DELIMITER ;
SET FOREIGN_KEY_CHECKS=1;
