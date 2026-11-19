-- Perbaikan fungsi Tugas 3: garansi mensyaratkan SELESAI dan pembayaran efektif LUNAS.
-- Tidak mengubah tabel, tanggal selesai, pembayaran, maupun data garansi.
-- Jalankan melalui Perbaiki-Garansi-Sql-R03.ps1 agar definisi lama dicadangkan.
DELIMITER $$
CREATE OR REPLACE FUNCTION `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`fn_status_garansi_wo` (p_no_work_order VARCHAR(20))
RETURNS VARCHAR(30) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
READS SQL DATA SQL SECURITY INVOKER
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
DELIMITER ;
