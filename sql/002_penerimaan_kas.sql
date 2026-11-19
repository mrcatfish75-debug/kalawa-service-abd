-- Bukti migrasi 25 menjadi 26 tabel. Sudah diterapkan pada situs uji.
-- Jangan jalankan ulang pada database final.
USE `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`;
CREATE TABLE penerimaan_kas (
    id_penerimaan BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    no_work_order VARCHAR(20) DEFAULT NULL,
    id_tagihan BIGINT UNSIGNED DEFAULT NULL,
    dicatat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_akun INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_penerimaan),
    UNIQUE KEY uq_pk_wo (no_work_order),
    UNIQUE KEY uq_pk_tagihan (id_tagihan),
    CONSTRAINT fk_pkas_pembayaran FOREIGN KEY (no_work_order)
        REFERENCES pembayaran (no_work_order) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_pkas_tagihan FOREIGN KEY (id_tagihan)
        REFERENCES tagihan_korporat (id_tagihan) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_pkas_akun FOREIGN KEY (id_akun)
        REFERENCES akun_aplikasi (id_akun) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_pkas_target CHECK (
        (no_work_order IS NOT NULL AND id_tagihan IS NULL)
        OR (no_work_order IS NULL AND id_tagihan IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
