-- TEMPLATE INTERNAL SALINAN. Jangan jalankan berkas ini pada database sumber.
-- jalankan.php mengganti database dan akun tujuan menjadi salinan sesi.
-- Hanya untuk akun koneksi website uji yang SUDAH ADA.
-- Tidak mengubah password, role aplikasi, tabel, data, atau trigger.
-- Akun ini koneksi bersama; pemisahan enam role tetap dilakukan aplikasi.
-- Jalankan hanya jika preflight melaporkan privilege belum lengkap.
-- Tidak ada CREATE USER, ALL PRIVILEGES, GRANT OPTION, hak global, atau REVOKE.
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`akun_aplikasi` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`pegawai` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`cabang` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`pelanggan` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`motor` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`kepemilikan_motor` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`mekanik` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`mekanik_pembantu_wo` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`jasa_servis` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`suku_cadang` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`supplier` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`supplier_suku_cadang` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`stok_cabang` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`work_order` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`detail_wo_jasa` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`detail_wo_suku_cadang` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`pelanggan_korporat` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`kontrak_korporat` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`work_order_korporat` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`tagihan_korporat` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`detail_tagihan_korporat` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`pembayaran` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`penerimaan_kas` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`mutasi_stok_manual` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`log_audit_operasional` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`garansi` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`pelanggan` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`motor` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT, UPDATE ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`kepemilikan_motor` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`jasa_servis` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`suku_cadang` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`supplier` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`work_order` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`detail_wo_jasa` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT, DELETE ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`mekanik_pembantu_wo` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`detail_wo_suku_cadang` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`stok_cabang` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`mutasi_stok_manual` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`pelanggan_korporat` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`kontrak_korporat` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`work_order_korporat` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`tagihan_korporat` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`detail_tagihan_korporat` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`pembayaran` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`penerimaan_kas` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT UPDATE (`status_pekerjaan`,`tanggal_selesai`) ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`work_order` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT UPDATE (`jumlah_stok`,`stok_minimum`) ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`stok_cabang` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT UPDATE (`diskon_member`,`total_bayar`,`metode_pembayaran`,`status_pembayaran`) ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`pembayaran` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT UPDATE (`status_pembayaran`,`dibayar_pada`,`metode_pembayaran`) ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`tagihan_korporat` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
GRANT INSERT, DELETE ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`supplier_suku_cadang` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';

GRANT SELECT ON `kalawa_service_f1_g1_clone_20261110_174035_33f8f960`.`vw_ringkasan_transaksi_terpadu` TO 'ks_korp_uji_33f8f960'@'127.0.0.1';
