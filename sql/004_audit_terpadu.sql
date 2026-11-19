-- Aditif: view baseline Tugas 3 dipertahankan; tidak mengubah data transaksi.
CREATE ALGORITHM=TEMPTABLE SQL SECURITY DEFINER VIEW `vw_ringkasan_transaksi_terpadu` AS
SELECT w.no_work_order,w.tanggal_masuk,w.tanggal_selesai,w.kode_cabang,w.no_polisi,w.status_pekerjaan,
CASE WHEN wk.no_work_order IS NULL THEN 'INDIVIDUAL' ELSE 'KORPORAT' END AS jenis_transaksi,d.id_tagihan,
CASE WHEN w.status_pekerjaan='DIBATALKAN' THEN 'TIDAK_DITAGIH' ELSE (CASE WHEN wk.no_work_order IS NOT NULL THEN COALESCE(t.status_pembayaran,'BELUM_DITAGIHKAN') ELSE COALESCE(p.status_pembayaran,'BELUM_LUNAS') END) END AS status_pembayaran,
CAST((CASE WHEN wk.no_work_order IS NOT NULL THEN COALESCE(d.nilai_final,(COALESCE((SELECT SUM(j.biaya) FROM detail_wo_jasa j WHERE j.no_work_order=w.no_work_order),0)+COALESCE((SELECT SUM(s.qty*s.harga_satuan) FROM detail_wo_suku_cadang s WHERE s.no_work_order=w.no_work_order),0))) ELSE COALESCE(p.total_bayar,(COALESCE((SELECT SUM(j.biaya) FROM detail_wo_jasa j WHERE j.no_work_order=w.no_work_order),0)+COALESCE((SELECT SUM(s.qty*s.harga_satuan) FROM detail_wo_suku_cadang s WHERE s.no_work_order=w.no_work_order),0))) END) AS DECIMAL(14,2)) AS nilai_wo,
CAST(CASE WHEN w.status_pekerjaan<>'DIBATALKAN' AND (CASE WHEN wk.no_work_order IS NOT NULL THEN COALESCE(t.status_pembayaran,'BELUM_DITAGIHKAN') ELSE COALESCE(p.status_pembayaran,'BELUM_LUNAS') END)='LUNAS' THEN (CASE WHEN wk.no_work_order IS NOT NULL THEN COALESCE(d.nilai_final,(COALESCE((SELECT SUM(j.biaya) FROM detail_wo_jasa j WHERE j.no_work_order=w.no_work_order),0)+COALESCE((SELECT SUM(s.qty*s.harga_satuan) FROM detail_wo_suku_cadang s WHERE s.no_work_order=w.no_work_order),0))) ELSE COALESCE(p.total_bayar,(COALESCE((SELECT SUM(j.biaya) FROM detail_wo_jasa j WHERE j.no_work_order=w.no_work_order),0)+COALESCE((SELECT SUM(s.qty*s.harga_satuan) FROM detail_wo_suku_cadang s WHERE s.no_work_order=w.no_work_order),0))) END) ELSE 0 END AS DECIMAL(14,2)) AS penerimaan_wo,
CASE WHEN wk.no_work_order IS NOT NULL THEN t.metode_pembayaran ELSE p.metode_pembayaran END AS metode_pembayaran,
CASE WHEN wk.no_work_order IS NOT NULL THEN t.dibayar_pada ELSE k.dicatat_pada END AS waktu_penerimaan
FROM work_order w LEFT JOIN work_order_korporat wk ON wk.no_work_order=w.no_work_order
LEFT JOIN detail_tagihan_korporat d ON d.no_work_order=w.no_work_order
LEFT JOIN tagihan_korporat t ON t.id_tagihan=d.id_tagihan
LEFT JOIN pembayaran p ON p.no_work_order=w.no_work_order
LEFT JOIN penerimaan_kas k ON k.no_work_order=w.no_work_order;
