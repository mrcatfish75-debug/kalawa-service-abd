<?php
declare(strict_types=1);
function ksPanduanRole(string $role): array
{
    $data=[
    'rl_owner_ops'=>[
        'judul'=>'Owner / Operasional','cakupan'=>'Seluruh cabang',
        'tujuan'=>'Pantau operasi, rawat data bersama, dan tangani kontrak serta tagihan lintas cabang.',
        'langkah'=>[
            ['Ringkasan','Periksa antrean dan pekerjaan yang masih berjalan.','index.php'],
            ['Data bengkel','Kelola pelanggan, motor, jasa, barang, supplier, dan kepemilikan. Data yang memiliki relasi dilindungi dari penghapusan.','pelanggan.php'],
            ['Pelanggan korporat','Daftarkan perusahaan, tetapkan kontrak aktif dan plafon kredit sebelum membuat WO korporat.','pelanggan.php?tipe=korporat'],
            ['Work Order','Buat WO, tentukan mekanik, catat jasa serta barang, dan jalankan tahapan pekerjaan.','work_order.php'],
            ['Tagihan korporat','Setelah bulan layanan berakhir, pratinjau WO selesai, terbitkan tagihan, lalu catat pelunasan saat uang diterima.','tagihan_korporat.php'],
            ['Laporan dan audit','Periksa penerimaan menurut tanggal pelunasan serta jejak perubahan. Pembayaran lama tanpa tanggal dicatat terpisah.','audit.php']],
        'batas'=>'Tetap mengikuti stok tersedia, kontrak, plafon, status pekerjaan, dan larangan pembayaran ganda.'],
    'rl_branch_admin'=>[
        'judul'=>'Admin Cabang','cakupan'=>'Cabang penempatan akun',
        'tujuan'=>'Layani pendaftaran dan atur pekerjaan servis dari awal sampai diserahkan kepada kasir.',
        'langkah'=>[
            ['Daftarkan pelanggan dan motor','Jika belum terdaftar, tambah pelanggan individu beserta motor baru. Untuk pelanggan yang sudah ada, gunakan ID pelanggan.','pendaftaran.php'],
            ['Buat Work Order','Pilih pelanggan, motor dengan pemilik aktif, mekanik cabang, keluhan, dan estimasi biaya.','work_order.php?buat=1#buat-wo'],
            ['Lengkapi pekerjaan','Buka detail WO. Pada tab Jasa dan Suku Cadang, catat item dan harga yang disepakati. Penambahan barang langsung mengurangi stok cabang.','work_order.php'],
            ['Atur mekanik','Tetapkan pembantu dari cabang yang sama melalui tab Mekanik. Mekanik utama mengerjakan dan memperbarui status.','work_order.php'],
            ['Pantau dan serahkan ke kasir','Urutannya Menunggu → Proses → Selesai. Kasir mencatat pembayaran setelah pekerjaan selesai.','laporan.php'],
            ['Periksa riwayat','Pilih laporan riwayat, atur tanggal atau nomor polisi, lalu buka Rincian lengkap dan biaya.','laporan.php?jenis=riwayat']],
        'batas'=>'Data lama tidak diubah melalui pendaftaran. Koreksi identitas, pindah pemilik, kontrak korporat, dan master global ditangani Owner. WO yang memakai barang atau memiliki catatan pembayaran tidak dapat dibatalkan.'],
    'rl_cashier_ops'=>[
        'judul'=>'Kasir','cakupan'=>'Cabang penempatan akun',
        'tujuan'=>'Periksa biaya dan catat uang yang benar-benar telah diterima dari pelanggan.',
        'langkah'=>[
            ['Cari pekerjaan siap dibayar','Buka Pembayaran. Filter awal menampilkan WO individual yang sudah selesai dan belum lunas.','pembayaran.php'],
            ['Periksa item dan total','Klik Catat pembayaran. Cocokkan jasa, kode barang, jumlah, harga satuan, subtotal, dan diskon dengan pelanggan.','pembayaran.php'],
            ['Simpan pelunasan','Pilih Tunai, Transfer, QRIS, atau Kartu Debit. Simpan hanya setelah seluruh nilai diterima. WO belum selesai dan pembayaran ganda ditolak.','pembayaran.php'],
            ['Cetak bukti','Sesudah berhasil, buka bukti pembayaran lalu Cetak bukti pembayaran. Untuk mencari bukti lama, pilih filter Sudah lunas.','pembayaran.php?status=LUNAS'],
            ['Tagihan perusahaan','Buka tagihan yang diterbitkan Owner. Kasir melunasi jika seluruh WO berada di cabangnya; tagihan lintas cabang ditangani Owner.','tagihan_korporat.php'],
            ['Periksa laporan','Pilih jenis laporan dan tanggal. Rincian lengkap menampilkan item, harga, status, dan garansi yang tercatat.','laporan.php']],
        'batas'=>'Tidak membuat atau mengubah WO, tarif item, stok, kontrak, maupun master pelanggan. Diskon harus sesuai kebijakan bengkel dan tidak melebihi subtotal. Tidak tersedia pembayaran sebagian.'],
    'rl_service_tech'=>[
        'judul'=>'Mekanik','cakupan'=>'WO yang ditugaskan di cabang penempatan',
        'tujuan'=>'Kerjakan servis sesuai keluhan, daftar jasa, dan barang yang dicatat Admin.',
        'langkah'=>[
            ['Buka penugasan','Pekerjaan Saya menampilkan WO sebagai mekanik utama atau pembantu.','pekerjaan.php'],
            ['Periksa detail','Buka detail untuk membaca keluhan, jasa, suku cadang beserta jumlahnya, dan nama pembantu.','pekerjaan.php'],
            ['Mulai pekerjaan','Mekanik utama memilih Mulai pengerjaan untuk mengubah Menunggu menjadi Proses.','pekerjaan.php'],
            ['Selesaikan pekerjaan','Setelah servis selesai, mekanik utama memilih Tandai selesai. Waktu selesai dicatat server.','pekerjaan.php']],
        'batas'=>'Mekanik pembantu membaca detail saja. Perubahan daftar jasa, pemakaian barang, dan penugasan diminta ke Admin Cabang. Pembayaran serta harga transaksi tidak ditampilkan pada rencana kerja.'],
    'rl_inventory_ops'=>[
        'judul'=>'Petugas Gudang','cakupan'=>'Stok pada cabang penempatan akun',
        'tujuan'=>'Jaga ketersediaan barang dan catat perubahan stok dengan alasan yang jelas.',
        'langkah'=>[
            ['Periksa persediaan','Pilih filter stok rendah atau habis untuk menentukan barang yang perlu disiapkan.','stok.php'],
            ['Catat perubahan','Pilih barang. Catat stok awal, barang masuk, koreksi fisik, atau batas minimum sesuai kebutuhan. Isi catatan mutasi.','stok.php'],
            ['Periksa riwayat','Riwayat pada barang menunjukkan mutasi manual. Pengurangan karena servis ditelusuri melalui laporan pemakaian per WO.','laporan.php?jenis=pemakaian'],
            ['Periksa supplier','Katalog Bengkel menyediakan daftar jasa, barang, stok cabang, dan nama supplier yang tersimpan.','katalog.php'],
            ['Pantau kebutuhan','Laporan stok minimum menampilkan jumlah saat ini, bukan kondisi historis pada rentang tanggal.','laporan.php?jenis=stok']],
        'batas'=>'Tidak mengubah pembayaran, status WO, atau stok cabang lain. Master supplier dan barang dikelola Owner. Belum ada pesanan pembelian, transfer stok antar cabang, atau penerimaan supplier otomatis.'],
    'rl_internal_audit'=>[
        'judul'=>'Auditor Internal','cakupan'=>'Laporan dan audit seluruh cabang; baca saja',
        'tujuan'=>'Telusuri kesesuaian pekerjaan, pembayaran, persediaan, dan perubahan data.',
        'langkah'=>[
            ['Pilih laporan','Periksa riwayat servis, pendapatan, pemakaian barang, atau stok minimum. Atur cabang dan rentang tanggal.','laporan.php'],
            ['Telusuri rincian','Pada riwayat servis, buka Rincian lengkap dan biaya untuk melihat item serta pembayaran dan garansi yang tercatat.','laporan.php?jenis=riwayat'],
            ['Periksa jejak audit','Cari nomor WO persis untuk memeriksa perubahan dan penerimaan kas. Tanpa pencarian tampil catatan terbaru serta mutasi manual.','audit.php'],
            ['Cetak hasil','Gunakan tombol Cetak laporan atau Cetak jejak audit.','audit.php']],
        'batas'=>'Tidak memiliki tindakan tambah, ubah, hapus, pelunasan, atau mutasi stok. Data kontak pribadi dan nomor identitas pelanggan tidak disediakan pada halaman audit.']];
    return $data[$role]??['judul'=>'Pengguna','cakupan'=>'Tidak tersedia','tujuan'=>'Hubungi pengelola.','langkah'=>[],'batas'=>'Tidak ada kewenangan tambahan.'];
}
