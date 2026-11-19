<?php
declare(strict_types=1);
require_once __DIR__ . '/operasional_m2.php';

function laporanFilter(PDO $db, array $akun, array $get): array
{
    $scope = opsCabang($akun, ['rl_owner_ops','rl_internal_audit','rl_branch_admin','rl_cashier_ops','rl_inventory_ops']);
    $jenis = opsTeks($get, 'jenis', $akun['role_db'] === 'rl_inventory_ops' ? 'stok' : 'riwayat');
    if (!in_array($jenis, ['riwayat','pendapatan','pemakaian','stok'], true)) {
        throw new InvalidArgumentException('Pilih jenis laporan yang tersedia.');
    }
    if ($akun['role_db'] === 'rl_inventory_ops' && !in_array($jenis,['pemakaian','stok'],true)) {
        throw new OperasionalDitolak('Laporan ini di luar cakupan petugas gudang.',403);
    }
    $cabang = opsTeks($get,'cabang');
    if ($scope !== null && $cabang !== '' && $cabang !== $scope) {
        throw new OperasionalDitolak('Cabang laporan berada di luar penempatan akun.',403);
    }
    $cabang = $scope ?? $cabang;
    if ($cabang !== '' && !in_array($cabang,array_column(opsDaftarCabang($db,$scope),'kode_cabang'),true)) {
        throw new InvalidArgumentException('Pilih cabang dari daftar.');
    }
    $hari = (string) $db->query('SELECT CURDATE()')->fetchColumn();
    $awal = opsTeks($get,'awal',substr($hari,0,7).'-01');
    $akhir = opsTeks($get,'akhir',$hari);
    if (!korporatTanggalSah($awal) || !korporatTanggalSah($akhir) || $awal > $akhir ||
        $awal < '1900-01-01' || $akhir > '2199-12-31') {
        throw new InvalidArgumentException('Isi rentang tanggal yang valid antara 1900 dan 2199.');
    }
    $polisi = opsTeks($get,'no_polisi');
    $wo = opsTeks($get,'no_work_order');
    if (strlen($polisi)>15 || strlen($wo)>20) {
        throw new InvalidArgumentException('Nomor motor atau WO terlalu panjang.');
    }
    return ['jenis'=>$jenis,'cabang'=>$cabang,'awal'=>$awal,'akhir'=>$akhir,
        'batas'=>(new DateTimeImmutable($akhir))->modify('+1 day')->format('Y-m-d'),
        'no_polisi'=>$polisi,'no_work_order'=>$wo,'scope'=>$scope];
}

function laporanAmbil(PDO $db, array $filter): array
{
    $args = [];
    $branch = '';
    if ($filter['cabang'] !== '') {
        $branch = ' AND w.kode_cabang=:cabang';
        $args['cabang'] = $filter['cabang'];
    }
    if ($filter['jenis'] === 'stok') {
        $sql = 'SELECT c.nama_cabang,s.kode_suku_cadang,p.nama_suku_cadang,
            s.jumlah_stok,s.stok_minimum FROM stok_cabang s
            JOIN cabang c ON c.kode_cabang=s.kode_cabang
            JOIN suku_cadang p ON p.kode_suku_cadang=s.kode_suku_cadang
            WHERE s.jumlah_stok<s.stok_minimum' .
            ($filter['cabang'] !== '' ? ' AND s.kode_cabang=:cabang' : '') .
            ' ORDER BY c.nama_cabang,p.nama_suku_cadang LIMIT 501';
        $headers = ['nama_cabang'=>'Cabang','kode_suku_cadang'=>'Kode','nama_suku_cadang'=>'Suku cadang',
            'jumlah_stok'=>'Stok','stok_minimum'=>'Minimum'];
    } elseif ($filter['jenis'] === 'pendapatan') {
        // Satu nilai per WO atau per detail tagihan, sehingga JOIN tidak menggandakan pemasukan.
        $sql = "SELECT c.kode_cabang,c.nama_cabang,COALESCE(SUM(x.individual),0) AS individual,
            COALESCE(SUM(x.korporat),0) AS korporat,
            COALESCE(SUM(x.individual+x.korporat),0) AS total_pendapatan
            FROM cabang c LEFT JOIN (
                SELECT w.kode_cabang,k.dicatat_pada AS waktu,p.total_bayar AS individual,0 AS korporat
                FROM pembayaran p JOIN work_order w ON w.no_work_order=p.no_work_order
                JOIN penerimaan_kas k ON k.no_work_order=p.no_work_order
                WHERE p.status_pembayaran='LUNAS'
                AND NOT EXISTS (SELECT 1 FROM work_order_korporat wk WHERE wk.no_work_order=w.no_work_order)
                UNION ALL
                SELECT w.kode_cabang,t.dibayar_pada,0,d.nilai_final
                FROM tagihan_korporat t JOIN detail_tagihan_korporat d ON d.id_tagihan=t.id_tagihan
                JOIN work_order w ON w.no_work_order=d.no_work_order
                WHERE t.status_pembayaran='LUNAS'
            ) x ON x.kode_cabang=c.kode_cabang AND x.waktu>=:awal AND x.waktu<:batas
            WHERE 1=1" . ($filter['cabang'] !== '' ? ' AND c.kode_cabang=:cabang' : '') .
            ' GROUP BY c.kode_cabang,c.nama_cabang ORDER BY c.kode_cabang';
        $args += ['awal'=>$filter['awal'],'batas'=>$filter['batas']];
        $headers = ['kode_cabang'=>'Kode','nama_cabang'=>'Cabang','individual'=>'Individual (Rp)',
            'korporat'=>'Korporat (Rp)','total_pendapatan'=>'Pendapatan (Rp)'];
    } elseif ($filter['jenis'] === 'pemakaian') {
        $sql = 'SELECT w.no_work_order,w.tanggal_masuk,c.nama_cabang,d.kode_suku_cadang,
            p.nama_suku_cadang,d.qty,d.harga_satuan,d.qty*d.harga_satuan AS subtotal
            FROM detail_wo_suku_cadang d JOIN work_order w ON w.no_work_order=d.no_work_order
            JOIN cabang c ON c.kode_cabang=w.kode_cabang
            JOIN suku_cadang p ON p.kode_suku_cadang=d.kode_suku_cadang
            WHERE w.tanggal_masuk>=:awal AND w.tanggal_masuk<:batas' . $branch;
        $args += ['awal'=>$filter['awal'],'batas'=>$filter['batas']];
        if ($filter['no_work_order'] !== '') {
            $sql .= ' AND w.no_work_order=:wo';
            $args['wo'] = $filter['no_work_order'];
        }
        $sql .= ' ORDER BY w.tanggal_masuk DESC,w.no_work_order,d.kode_suku_cadang LIMIT 501';
        $headers = ['no_work_order'=>'WO','tanggal_masuk'=>'Masuk','nama_cabang'=>'Cabang',
            'kode_suku_cadang'=>'Kode','nama_suku_cadang'=>'Suku cadang','qty'=>'Jumlah',
            'harga_satuan'=>'Harga satuan (Rp)','subtotal'=>'Subtotal (Rp)'];
    } else {
        $sql = "SELECT w.no_work_order,w.tanggal_masuk,w.no_polisi,m.merek,m.tipe,c.nama_cabang,
            p.nama_pelanggan,pg.nama_pegawai AS mekanik,w.status_pekerjaan,
            COALESCE(t.status_pembayaran,b.status_pembayaran,'BELUM_LUNAS') AS pembayaran,
            COALESCE(j.jasa,'—') AS jasa,COALESCE(s.suku_cadang,'—') AS suku_cadang
            FROM work_order w JOIN motor m ON m.no_polisi=w.no_polisi
            JOIN pelanggan p ON p.id_pelanggan=w.id_pelanggan
            JOIN cabang c ON c.kode_cabang=w.kode_cabang
            JOIN pegawai pg ON pg.id_pegawai=w.id_mekanik_pj
            LEFT JOIN pembayaran b ON b.no_work_order=w.no_work_order
            LEFT JOIN detail_tagihan_korporat dt ON dt.no_work_order=w.no_work_order
            LEFT JOIN tagihan_korporat t ON t.id_tagihan=dt.id_tagihan
            LEFT JOIN (SELECT d.no_work_order,GROUP_CONCAT(j.uraian_jasa_servis ORDER BY j.kode_jasa SEPARATOR ', ') AS jasa
                FROM detail_wo_jasa d JOIN jasa_servis j ON j.kode_jasa=d.kode_jasa GROUP BY d.no_work_order) j
                ON j.no_work_order=w.no_work_order
            LEFT JOIN (SELECT d.no_work_order,GROUP_CONCAT(CONCAT(p.nama_suku_cadang,' × ',d.qty)
                ORDER BY p.kode_suku_cadang SEPARATOR ', ') AS suku_cadang
                FROM detail_wo_suku_cadang d JOIN suku_cadang p ON p.kode_suku_cadang=d.kode_suku_cadang
                GROUP BY d.no_work_order) s ON s.no_work_order=w.no_work_order
            WHERE w.tanggal_masuk>=:awal AND w.tanggal_masuk<:batas" . $branch;
        $args += ['awal'=>$filter['awal'],'batas'=>$filter['batas']];
        if ($filter['no_polisi'] !== '') {
            $sql .= ' AND w.no_polisi=:polisi';
            $args['polisi'] = $filter['no_polisi'];
        }
        $sql .= ' ORDER BY w.tanggal_masuk DESC,w.no_work_order LIMIT 501';
        $headers = ['no_work_order'=>'WO','tanggal_masuk'=>'Masuk','no_polisi'=>'Motor','merek'=>'Merek',
            'tipe'=>'Tipe','nama_cabang'=>'Cabang','nama_pelanggan'=>'Pelanggan','mekanik'=>'Mekanik',
            'status_pekerjaan'=>'Pekerjaan','pembayaran'=>'Pembayaran','jasa'=>'Jasa','suku_cadang'=>'Suku cadang'];
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll();
    return ['headers'=>$headers,'rows'=>array_slice($rows,0,500),'dibatasi'=>count($rows)>500];
}
