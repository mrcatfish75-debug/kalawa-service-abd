<?php
declare(strict_types=1);

/** Presentation only. Call after the endpoint has authorized and loaded its data. */
function ksCetakSiapkan(PDO $db,array $akun,string $jenis,array $ops=[]):void
{
    $jenisList=[
        'riwayat'=>['Laporan Riwayat Servis','Riwayat pekerjaan, mekanik, jasa, dan suku cadang kendaraan.','landscape'],
        'pendapatan'=>['Laporan Penerimaan Pembayaran','Penerimaan pembayaran individual dan korporat menurut cabang.','portrait'],
        'pemakaian'=>['Laporan Pemakaian Suku Cadang','Rincian pemakaian dan nilai suku cadang pada Work Order.','landscape'],
        'stok'=>['Laporan Stok di Bawah Minimum','Persediaan tercatat dengan jumlah stok lebih kecil dari batas minimum.','portrait'],
        'bukti_pembayaran'=>['Bukti Pembayaran Servis','Bukti pelunasan satu Work Order beserta rincian jasa dan suku cadang.','portrait'],
        'rincian_tagihan'=>['Rincian Tagihan Servis','Rincian biaya Work Order; dokumen ini bukan bukti pelunasan.','portrait'],
        'tagihan_korporat'=>['Tagihan Servis Korporat','Tagihan kolektif Work Order korporat untuk satu bulan layanan.','portrait'],
        'bukti_korporat'=>['Bukti Pelunasan Korporat','Bukti pelunasan tagihan kolektif beserta alokasi nilai per Work Order.','portrait'],
        'rincian_servis'=>['Rincian Servis Kendaraan','Identitas pekerjaan, penugasan mekanik, jasa, dan suku cadang.','portrait'],
        'audit'=>['Laporan Jejak Audit Operasional','Ringkasan pembayaran, perubahan data, penerimaan kas, dan mutasi stok.','landscape'],
    ];
    if(!isset($jenisList[$jenis]))throw new InvalidArgumentException('Jenis dokumen cetak tidak dikenal.');
    [$judul,$ringkas,$orientasi]=$jenisList[$jenis];
    $global=in_array($akun['role_db'],['rl_owner_ops','rl_internal_audit'],true);
    $codes=$ops['cabang']??null;
    if(is_string($codes))$codes=$codes===''?null:[$codes];
    // A non-global account can never expand the branch directory through print metadata.
    if(!$global)$codes=[(string)$akun['kode_cabang']];
    if($codes!==null){$codes=array_values(array_unique(array_filter($codes,'is_string')));sort($codes);}
    $sql='SELECT kode_cabang,nama_cabang,alamat,no_telepon FROM cabang';$args=[];
    if($codes!==null){$sql.=$codes?' WHERE kode_cabang IN ('.implode(',',array_fill(0,count($codes),'?')).')':' WHERE 1=0';$args=$codes;}
    $q=$db->prepare($sql.' ORDER BY kode_cabang');$q->execute($args);$branches=$q->fetchAll(PDO::FETCH_ASSOC);
    $cakupan=$codes===null?'Seluruh cabang':(count($branches)===1?$branches[0]['nama_cabang']:(count($branches)>1?'Lintas cabang':'Cabang belum tersedia'));
    $kodeFile=$codes===null?'Semua-Cabang':(count($branches)===1?$branches[0]['kode_cabang']:(count($branches)>1?'Lintas-Cabang':'Cabang'));
    $ref=(string)($ops['referensi']??'');$periode=(string)($ops['periode']??'');
    $suffix=(string)($ops['nama_file']??$ref);
    $file=$judul.' - '.$kodeFile.($suffix!==''?' - '.$suffix:'').' - Kalawa Service';
    $file=preg_replace('~[\\\\/:*?"<>|\x00-\x1f]+~u','-',$file);
    $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta'));
    $GLOBALS['ks_cetak']=['jenis'=>$jenis,'judul'=>$judul,'ringkas'=>$ringkas,'orientasi'=>$orientasi,
        'cabang'=>$branches,'cakupan'=>$cakupan,'referensi'=>$ref,'periode'=>$periode,'nama_file'=>$file,
        'metadata'=>$ops['metadata']??[],'catatan'=>(string)($ops['catatan']??''),
        'pencetak'=>(string)($akun['username']??'Petugas').' ('.(ROLE_KALAWA[$akun['role_db']]??'Petugas').')',
        'waktu'=>$now->format('Y-m-d H:i:s'),'epoch'=>$now->getTimestamp()*1000];
}

function ksCetakBulan(string $bulan):string
{
    if(!preg_match('/\A([0-9]{4})-(0[1-9]|1[0-2])\z/D',$bulan,$m))return $bulan;
    $nama=[1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $nama[(int)$m[2]].' '.$m[1];
}

function ksCetakHead():void
{
    if(empty($GLOBALS['ks_cetak']))return;
    $m=$GLOBALS['ks_cetak'];
    // Both CSS values are from the fixed document map, never from request text.
    echo '<style media="print">@page{size:A4 '.$m['orientasi'].';@bottom-left{content:'.json_encode('Kalawa Service - '.$m['judul'],JSON_UNESCAPED_UNICODE).';}}</style>';
}

function ksCetakKop():void
{
    $m=$GLOBALS['ks_cetak']??null;if(!$m)return;
    ?>
    <header class="ks-cetak-kop" data-ks-print-epoch="<?=$m['epoch']?>">
      <div class="ks-cetak-brand-row">
        <div class="ks-cetak-brand"><img src="../logo/kalawa-service-logo.svg" alt="Kalawa Service" width="84" height="84"><div><strong>Kalawa Service</strong><span>Sistem Operasional Bengkel</span></div></div>
        <div class="ks-cetak-scope"><span>Cakupan dokumen</span><strong><?=amanHtml($m['cakupan'])?></strong></div>
      </div>
      <div class="ks-cetak-contacts">
        <?php foreach($m['cabang'] as $c): ?><div><strong><?=amanHtml($c['nama_cabang'])?> <span>(<?=amanHtml($c['kode_cabang'])?>)</span></strong><span><?=amanHtml($c['alamat']!==''?$c['alamat']:'Alamat belum dicatat')?><?= $c['no_telepon']!==''?' | Tel. '.amanHtml($c['no_telepon']):'' ?></span></div><?php endforeach; ?>
      </div>
      <div class="ks-cetak-heading"><h1><?=amanHtml($m['judul'])?></h1><p><?=amanHtml($m['ringkas'])?></p></div>
      <dl class="ks-cetak-meta">
        <?php if($m['periode']!==''): ?><div><dt>Periode</dt><dd><?=amanHtml($m['periode'])?></dd></div><?php endif; ?>
        <?php if($m['referensi']!==''): ?><div><dt>Referensi</dt><dd><?=amanHtml($m['referensi'])?></dd></div><?php endif; ?>
        <?php foreach($m['metadata'] as $label=>$value): ?><div><dt><?=amanHtml((string)$label)?></dt><dd><?=amanHtml((string)$value)?></dd></div><?php endforeach; ?>
        <div><dt>Waktu cetak</dt><dd><time data-ks-print-time datetime="<?=amanHtml(str_replace(' ','T',$m['waktu']).'+07:00')?>"><?=amanHtml(ksUiTanggal($m['waktu'],true))?> WIB</time></dd></div>
        <div><dt>Dicetak oleh</dt><dd><?=amanHtml($m['pencetak'])?></dd></div>
      </dl>
      <?php if($m['catatan']!==''): ?><p class="ks-cetak-note"><?=amanHtml($m['catatan'])?></p><?php endif; ?>
    </header>
    <?php
}
