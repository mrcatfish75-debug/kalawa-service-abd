<?php
declare(strict_types=1);
/** Transaksi pembayaran individual pada situs korporat uji. */
require_once __DIR__ . '/operasional_m2.php';
const KSPAY_TEST_DB = 'kalawa_service_f1_g1_clone_20261110_174035_33f8f960';
const KSPAY_METODE = ['TUNAI', 'TRANSFER', 'QRIS', 'KARTU_DEBIT'];

function ksPayUjiDb(PDO $db): void {
    if ((string) $db->query('SELECT DATABASE()')->fetchColumn() !== KSPAY_TEST_DB) {
        throw new RuntimeException('STOP: koneksi bukan database pengujian R01E.');
    }
}
function ksPayScope(array $akun): ?string {
    $role=(string)($akun['role_db'] ?? '');
    if ($role==='rl_owner_ops') return null;
    if ($role!=='rl_cashier_ops') throw new DomainException('Role tidak diizinkan mengakses pembayaran.');
    $id=(string)($akun['id_pegawai'] ?? '');
    $cab=(string)($akun['kode_cabang'] ?? '');
    $emp=(string)($akun['cabang_pegawai'] ?? '');
    if ($id==='' || !preg_match('/\ACBG[0-9]{3}\z/D',$cab)
        || !hash_equals($cab,$emp) || ($akun['jenis_pegawai'] ?? '')!=='KASIR') {
        throw new DomainException('Identitas atau penempatan kasir tidak valid.');
    }
    return $cab;
}
function ksPayCabangSesuai(?string $scope, string $cab): bool {
    return $scope===null || ($cab!=='' && hash_equals($scope,$cab));
}
function ksPayCents(string $number): int {
    $number=trim($number);
    if (!preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?\z/D',$number,$m)) {
        throw new InvalidArgumentException('Nominal harus angka nonnegatif, tanpa pemisah ribuan, maksimal dua desimal.');
    }
    $parts=explode('.',$number,2);
    return (int)$parts[0]*100+(int)str_pad($parts[1]??'0',2,'0');
}
function ksPayDecimal(int $cents): string {
    if ($cents<0 || $cents>999999999999) throw new InvalidArgumentException('Nominal melampaui batas DECIMAL(12,2).');
    return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
}
function ksPayRupiah(int $cents): string {return 'Rp '.number_format($cents/100,2,',','.');}
function ksPayHitung(int $jasaCents,int $spCents,int $diskonCents): array {
    if ($jasaCents<0 || $spCents<0 || $diskonCents<0) throw new InvalidArgumentException('Nominal tidak boleh negatif.');
    $gross=$jasaCents+$spCents;
    if ($gross<=0) throw new DomainException('Belum terdapat biaya jasa atau spare part yang dapat dibayar.');
    if ($diskonCents>$gross) throw new DomainException('Diskon tidak boleh melebihi subtotal.');
    $net=$gross-$diskonCents;
    ksPayDecimal($net);
    return ['jasa'=>$jasaCents,'spare'=>$spCents,'subtotal'=>$gross,'diskon'=>$diskonCents,'net'=>$net];
}
function ksPayValidasiInput(array $post): array {
    $wo=opsTeks($post,'no_work_order');
    $metode=opsTeks($post,'metode_pembayaran');
    $token=opsTeks($post,'token_bayar');
    if (!preg_match('/\A[A-Za-z0-9-]{5,20}\z/D',$wo)) throw new InvalidArgumentException('Nomor WO tidak valid.');
    if (!in_array($metode,KSPAY_METODE,true)) throw new InvalidArgumentException('Metode pembayaran tidak tersedia.');
    if (!preg_match('/\A[a-f0-9]{48}\z/D',$token)) throw new InvalidArgumentException('Token transaksi tidak valid.');
    $diskon=ksPayCents(str_replace(',', '.', opsTeks($post,'diskon_member')));
    return ['wo'=>$wo,'metode'=>$metode,'token'=>$token,'diskon'=>$diskon];
}
function ksPayAmbilTagihan(PDO $db,string $wo,bool $locking): array {
    // Kunci baca setiap detail: mencegah update/delete saat pembayaran; parent WO FOR UPDATE membatasi INSERT detail lewat FK.
    $suffix=$locking?' LOCK IN SHARE MODE':'';
    $st=$db->prepare('SELECT biaya FROM detail_wo_jasa WHERE no_work_order=:wo ORDER BY kode_jasa'.$suffix);
    $st->execute(['wo'=>$wo]);$jasa=0;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $v) $jasa+=ksPayCents((string)$v);
    $st=$db->prepare('SELECT qty,harga_satuan FROM detail_wo_suku_cadang WHERE no_work_order=:wo ORDER BY kode_suku_cadang'.$suffix);
    $st->execute(['wo'=>$wo]);$sp=0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $v) {
        $qty=(int)$v['qty'];if ($qty<=0) throw new RuntimeException('Qty suku cadang di database tidak valid.');
        $sp+=$qty*ksPayCents((string)$v['harga_satuan']);
    }
    if ($jasa>999999999999 || $sp>999999999999 || $jasa+$sp>999999999999) {
        throw new DomainException('Total detail melampaui batas nominal yang didukung.');
    }
    return ['jasa'=>$jasa,'spare'=>$sp];
}
function ksPayAmbilWO(PDO $db,string $wo,?string $scope,bool $locking=false): array|false {
    if ($locking) {
        // Kunci hanya tabel WO; tidak meminta lock UPDATE pada tabel pelanggan/cabang.
        $sql='SELECT no_work_order,kode_cabang,status_pekerjaan,tanggal_selesai,tanggal_masuk,no_polisi
              FROM work_order w WHERE no_work_order=:wo
              AND NOT EXISTS (SELECT 1 FROM work_order_korporat k WHERE k.no_work_order=w.no_work_order)';
        $args=['wo'=>$wo];
        if ($scope!==null) {$sql.=' AND kode_cabang=:cab';$args['cab']=$scope;}
        $sql.=' FOR UPDATE';
        $st=$db->prepare($sql);$st->execute($args);return $st->fetch(PDO::FETCH_ASSOC);
    }
    $sql='SELECT w.no_work_order,w.kode_cabang,w.status_pekerjaan,w.tanggal_selesai,w.tanggal_masuk,
                 w.no_polisi,p.nama_pelanggan,c.nama_cabang
          FROM work_order w JOIN pelanggan p ON p.id_pelanggan=w.id_pelanggan
          JOIN cabang c ON c.kode_cabang=w.kode_cabang WHERE w.no_work_order=:wo
          AND NOT EXISTS (SELECT 1 FROM work_order_korporat k WHERE k.no_work_order=w.no_work_order)';
    $args=['wo'=>$wo];
    if ($scope!==null) {$sql.=' AND w.kode_cabang=:cab';$args['cab']=$scope;}
    if ($locking) $sql.=' FOR UPDATE';
    $st=$db->prepare($sql);$st->execute($args);return $st->fetch(PDO::FETCH_ASSOC);
}
/**
 * Memproses LUNAS atomik. Satu WO = satu record pembayaran (PK schema Tugas 3).
 * Harus dipanggil setelah validasi CSRF + token 1x sesi oleh controller.
 */
function ksPayLunasi(PDO $db,array $akun,array $input): array {
    ksPayUjiDb($db);$scope=ksPayScope($akun);$wo=(string)$input['wo'];
    $db->beginTransaction();
    try {
        $row=ksPayAmbilWO($db,$wo,$scope,true);
        if (!$row) throw new OperasionalDitolak('Work Order tidak tersedia untuk pembayaran individual akun ini.',403);
        if (!ksPayCabangSesuai($scope,(string)$row['kode_cabang'])) throw new DomainException('Cabang tidak sesuai izin akun.');
        if ($row['status_pekerjaan']!=='SELESAI' || $row['tanggal_selesai']===null) {
            throw new DomainException('Pembayaran hanya untuk Work Order yang sudah SELESAI.');
        }
        $st=$db->prepare('SELECT no_work_order,diskon_member,total_bayar,metode_pembayaran,status_pembayaran
                          FROM pembayaran WHERE no_work_order=:wo FOR UPDATE');
        $st->execute(['wo'=>$wo]);$old=$st->fetch(PDO::FETCH_ASSOC);
        if ($old && $old['status_pembayaran']==='LUNAS') throw new DomainException('Work Order ini sudah LUNAS. Tidak boleh dibayar dua kali.');
        if ($old && $old['status_pembayaran']!=='BELUM_LUNAS') throw new RuntimeException('Status pembayaran lama tidak dikenal.');
        $detail=ksPayAmbilTagihan($db,$wo,true);
        if (!isset($input['expected_gross']) || !is_int($input['expected_gross']) || $input['expected_gross']!==($detail['jasa']+$detail['spare'])) {
            throw new DomainException('Rincian biaya berubah sejak formulir dibuka. Muat ulang sebelum melakukan pembayaran.');
        }
        $calc=ksPayHitung($detail['jasa'],$detail['spare'],(int)$input['diskon']);
        $total=ksPayDecimal($calc['net']);$discount=ksPayDecimal((int)$input['diskon']);
        if ($old && (ksPayCents($old['total_bayar']) !== $calc['net'] ||
            ksPayCents($old['diskon_member']) !== $input['diskon'])) {
            throw new DomainException('Tagihan lama berbeda dari rincian dan diskon ini. Periksa tagihan sebelum melunasi.');
        }
        if ($old) {
            $st=$db->prepare("UPDATE pembayaran SET diskon_member=:diskon,total_bayar=:total,
                              metode_pembayaran=:metode,status_pembayaran='LUNAS'
                              WHERE no_work_order=:wo AND status_pembayaran='BELUM_LUNAS'");
        } else {
            $st=$db->prepare("INSERT INTO pembayaran
                (no_work_order,diskon_member,total_bayar,metode_pembayaran,status_pembayaran)
                VALUES (:wo,:diskon,:total,:metode,'LUNAS')");
        }
        $st->execute(['wo'=>$wo,'diskon'=>$discount,'total'=>$total,'metode'=>$input['metode']]);
        if ($st->rowCount()!==1) throw new RuntimeException('Pembayaran tidak tersimpan tepat satu kali.');
        $st=$db->prepare('INSERT INTO penerimaan_kas (no_work_order,id_akun) VALUES (:wo,:akun)');
        $st->execute(['wo'=>$wo,'akun'=>$akun['id_akun']]);
        $db->commit();
        return ['wo'=>$wo,'created'=>!$old,'amount'=>$total,'method'=>$input['metode']];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
