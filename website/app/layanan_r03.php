<?php
declare(strict_types=1);
require_once __DIR__.'/operasional_m2.php';
require_once __DIR__.'/presentasi_ui.php';

/** Otorisasi mendahului seluruh query rincian. Tidak ada endpoint tulis di modul ini. */
function ksLayananAmbil(PDO $db, array $akun, string $nomor): array|false
{
    if (!preg_match('/\A[A-Za-z0-9-]{1,20}\z/D', $nomor)) {
        throw new InvalidArgumentException('Nomor Work Order tidak valid.');
    }
    $scope=opsCabang($akun,['rl_owner_ops','rl_internal_audit','rl_branch_admin','rl_cashier_ops','rl_inventory_ops','rl_service_tech']);
    $sql='SELECT w.*,m.merek,m.tipe,c.nama_cabang,p.nama_pelanggan,pg.nama_pegawai AS mekanik
        FROM work_order w JOIN motor m ON m.no_polisi=w.no_polisi
        JOIN cabang c ON c.kode_cabang=w.kode_cabang JOIN pelanggan p ON p.id_pelanggan=w.id_pelanggan
        JOIN pegawai pg ON pg.id_pegawai=w.id_mekanik_pj WHERE w.no_work_order=:wo';
    $args=['wo'=>$nomor];
    if ($scope!==null) {$sql.=' AND w.kode_cabang=:cab';$args['cab']=$scope;}
    if ($akun['role_db']==='rl_service_tech') {
        $sql.=' AND EXISTS (SELECT 1 FROM mekanik me WHERE me.id_mekanik=:aktif AND me.status_aktif=1)
            AND (w.id_mekanik_pj=:pj OR EXISTS (SELECT 1 FROM mekanik_pembantu_wo mp
            WHERE mp.no_work_order=w.no_work_order AND mp.id_mekanik=:helper))';
        $args+=['aktif'=>$akun['id_pegawai'],'pj'=>$akun['id_pegawai'],'helper'=>$akun['id_pegawai']];
    }
    $st=$db->prepare($sql);$st->execute($args);$w=$st->fetch();
    if (!$w) return false;
    $st=$db->prepare('SELECT d.kode_jasa,j.uraian_jasa_servis,d.biaya FROM detail_wo_jasa d
        JOIN jasa_servis j ON j.kode_jasa=d.kode_jasa WHERE d.no_work_order=:wo ORDER BY d.kode_jasa');
    $st->execute(['wo'=>$nomor]);$w['jasa']=$st->fetchAll();
    $st=$db->prepare('SELECT d.kode_suku_cadang,s.nama_suku_cadang,d.qty,d.harga_satuan,
        d.qty*d.harga_satuan AS subtotal FROM detail_wo_suku_cadang d
        JOIN suku_cadang s ON s.kode_suku_cadang=d.kode_suku_cadang WHERE d.no_work_order=:wo ORDER BY d.kode_suku_cadang');
    $st->execute(['wo'=>$nomor]);$w['barang']=$st->fetchAll();
    $st=$db->prepare('SELECT p.nama_pegawai FROM mekanik_pembantu_wo mp JOIN pegawai p ON p.id_pegawai=mp.id_mekanik
        WHERE mp.no_work_order=:wo ORDER BY p.nama_pegawai');
    $st->execute(['wo'=>$nomor]);$w['pembantu']=$st->fetchAll(PDO::FETCH_COLUMN);
    // Mekanik dan gudang tidak memerlukan rincian penerimaan uang pelanggan.
    $w['keuangan']=!in_array($akun['role_db'],['rl_service_tech','rl_inventory_ops'],true);
    if ($w['keuangan']) {
        $w['bayar']=opsStatusPembayaran($db,$nomor);
        $st=$db->prepare('SELECT b.diskon_member,b.total_bayar,b.metode_pembayaran,b.status_pembayaran,
            k.dicatat_pada FROM pembayaran b LEFT JOIN penerimaan_kas k ON k.no_work_order=b.no_work_order
            WHERE b.no_work_order=:wo');
        $st->execute(['wo'=>$nomor]);$w['pembayaran']=$st->fetch() ?: null;
        $st=$db->prepare('SELECT g.*,DATE_ADD(DATE(w.tanggal_selesai),INTERVAL g.jumlah_hari_garansi DAY) AS sampai,
            CURDATE() AS hari_ini FROM garansi g JOIN work_order w ON w.no_work_order=g.no_work_order WHERE g.no_work_order=:wo');
        $st->execute(['wo'=>$nomor]);$w['garansi']=$st->fetch() ?: null;
    }
    return $w;
}

function ksLayananItem(array $w, bool $harga=true): void
{
    ?>
    <div class="ks-r03-items">
    <section><h3>Jasa servis</h3><div class="tablewrap"><table><thead><tr><th>Kode / jasa</th><?php if($harga): ?><th class="ks-ui-number">Biaya</th><?php endif; ?></tr></thead><tbody>
    <?php foreach($w['jasa'] as $j): ?><tr><td><?=amanHtml($j['kode_jasa'])?> · <?=amanHtml($j['uraian_jasa_servis'])?></td><?php if($harga): ?><td class="ks-ui-number"><?=amanHtml(ksUiRupiah($j['biaya']))?></td><?php endif; ?></tr><?php endforeach; ?>
    <?php if(!$w['jasa']): ?><tr><td colspan="<?=$harga?2:1?>">Belum ada jasa yang dicatat</td></tr><?php endif; ?></tbody></table></div></section>
    <section><h3>Suku cadang</h3><div class="tablewrap"><table><thead><tr><th>Kode / barang</th><th class="ks-ui-number">Jumlah</th><?php if($harga): ?><th class="ks-ui-number">Harga satuan</th><th class="ks-ui-number">Subtotal</th><?php endif; ?></tr></thead><tbody>
    <?php foreach($w['barang'] as $s): ?><tr><td><?=amanHtml($s['kode_suku_cadang'])?> · <?=amanHtml($s['nama_suku_cadang'])?></td><td class="ks-ui-number"><?=amanHtml($s['qty'])?></td><?php if($harga): ?><td class="ks-ui-number"><?=amanHtml(ksUiRupiah($s['harga_satuan']))?></td><td class="ks-ui-number"><?=amanHtml(ksUiRupiah($s['subtotal']))?></td><?php endif; ?></tr><?php endforeach; ?>
    <?php if(!$w['barang']): ?><tr><td colspan="<?=$harga?4:2?>">Belum ada suku cadang yang dicatat</td></tr><?php endif; ?></tbody></table></div></section></div>
    <?php
}
