-- Bukti migrasi dari baseline 20 tabel. Sudah diterapkan pada situs uji.
-- Jangan jalankan ulang pada database final 26 tabel.
-- Migrasi uji korporat untuk baseline G1.
-- Runner mengganti `kalawa_service_f1_g1_clone_20261110_174035_33f8f960` dengan identifier target yang diverifikasi.
ALTER TABLE `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.work_order
    ADD UNIQUE KEY uq_wo_korporat_pelanggan (no_work_order, id_pelanggan);

CREATE TABLE `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.pelanggan_korporat (
    id_pelanggan VARCHAR(15) NOT NULL,
    nama_pic VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (id_pelanggan),
    CONSTRAINT fk_pk_pelanggan FOREIGN KEY (id_pelanggan)
        REFERENCES `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.pelanggan (id_pelanggan)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.kontrak_korporat (
    id_kontrak BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pelanggan VARCHAR(15) NOT NULL,
    berlaku_mulai DATE NOT NULL,
    berlaku_sampai DATE NOT NULL,
    plafon_kredit DECIMAL(14,2) NOT NULL,
    PRIMARY KEY (id_kontrak),
    UNIQUE KEY uq_kk_kontrak_pelanggan (id_kontrak, id_pelanggan),
    UNIQUE KEY uq_kk_pelanggan_mulai (id_pelanggan, berlaku_mulai),
    CONSTRAINT fk_kk_profil FOREIGN KEY (id_pelanggan)
        REFERENCES `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.pelanggan_korporat (id_pelanggan)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_kk_periode CHECK (berlaku_mulai >= '1000-01-01' AND berlaku_sampai >= berlaku_mulai),
    CONSTRAINT chk_kk_plafon CHECK (plafon_kredit >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.work_order_korporat (
    no_work_order VARCHAR(20) NOT NULL,
    id_pelanggan VARCHAR(15) NOT NULL,
    id_kontrak BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (no_work_order),
    UNIQUE KEY uq_wok_wo_pelanggan (no_work_order, id_pelanggan),
    KEY ix_wok_kontrak_pelanggan (id_kontrak, id_pelanggan),
    CONSTRAINT fk_wok_wo FOREIGN KEY (no_work_order, id_pelanggan)
        REFERENCES `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.work_order (no_work_order, id_pelanggan)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_wok_kontrak FOREIGN KEY (id_kontrak, id_pelanggan)
        REFERENCES `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.kontrak_korporat (id_kontrak, id_pelanggan)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.tagihan_korporat (
    id_tagihan BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pelanggan VARCHAR(15) NOT NULL,
    periode DATE NOT NULL,
    terbit_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status_pembayaran ENUM('BELUM_LUNAS','LUNAS') NOT NULL DEFAULT 'BELUM_LUNAS',
    dibayar_pada DATETIME DEFAULT NULL,
    metode_pembayaran ENUM('TUNAI','TRANSFER','QRIS','KARTU_DEBIT') DEFAULT NULL,
    PRIMARY KEY (id_tagihan),
    UNIQUE KEY uq_tk_tagihan_pelanggan (id_tagihan, id_pelanggan),
    UNIQUE KEY uq_tk_pelanggan_periode (id_pelanggan, periode),
    CONSTRAINT fk_tk_profil FOREIGN KEY (id_pelanggan)
        REFERENCES `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.pelanggan_korporat (id_pelanggan)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_tk_bulan CHECK (periode >= '1000-01-01' AND DAYOFMONTH(periode) = 1),
    CONSTRAINT chk_tk_pelunasan CHECK (
        (status_pembayaran = 'BELUM_LUNAS' AND dibayar_pada IS NULL AND metode_pembayaran IS NULL)
        OR (status_pembayaran = 'LUNAS'
            AND dibayar_pada IS NOT NULL AND dibayar_pada >= terbit_pada
            AND metode_pembayaran IS NOT NULL
            AND metode_pembayaran IN ('TUNAI','TRANSFER','QRIS','KARTU_DEBIT'))
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.detail_tagihan_korporat (
    no_work_order VARCHAR(20) NOT NULL,
    id_pelanggan VARCHAR(15) NOT NULL,
    id_tagihan BIGINT UNSIGNED NOT NULL,
    nilai_final DECIMAL(14,2) NOT NULL,
    PRIMARY KEY (no_work_order),
    KEY ix_dtk_wo_pelanggan (no_work_order, id_pelanggan),
    KEY ix_dtk_tagihan_pelanggan (id_tagihan, id_pelanggan),
    CONSTRAINT fk_dtk_wo FOREIGN KEY (no_work_order, id_pelanggan)
        REFERENCES `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.work_order_korporat (no_work_order, id_pelanggan)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_dtk_tagihan FOREIGN KEY (id_tagihan, id_pelanggan)
        REFERENCES `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.tagihan_korporat (id_tagihan, id_pelanggan)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_dtk_nilai CHECK (nilai_final >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

