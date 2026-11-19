<?php
declare(strict_types=1);
require_once __DIR__.'/operasional_m2.php';
require_once __DIR__.'/validasi_pelanggan_m2.php';
require_once __DIR__.'/validasi_motor_m2.php';

function ksDaftarScope(array $akun): ?string
{
    return opsCabang($akun,['rl_owner_ops','rl_branch_admin']);
}

/** Tambah saja: data bersama yang sudah ada tidak pernah diubah atau diambil alih. */
function ksDaftarSimpan(PDO $db,array $akun,array $post): array
{
    $scope=ksDaftarScope($akun);
    $mode=opsTeks($post,'mode');
    if(!in_array($mode,['baru','terdaftar'],true)) throw new InvalidArgumentException('Pilih jenis pendaftaran.');
    foreach(['no_polisi','no_rangka','no_mesin','merek','tipe','tahun_produksi','warna','kilometer_terakhir'] as $k) opsTeks($post,$k);
    $motor=validasiMotorM2($post);
    if($motor['errors']) throw new InvalidArgumentException(implode(' ',$motor['errors']));
    if($mode==='baru') {
        $raw=$post;$raw['kode_cabang_favorit']=$scope ?? opsTeks($post,'kode_cabang_favorit');
        $p=validasiInputPelangganM2($raw);
        if($p['errors']) throw new InvalidArgumentException(implode(' ',$p['errors']));
    } else {
        $id=opsTeks($post,'id_pelanggan');
        if(!preg_match('/\A[A-Za-z0-9._-]{3,15}\z/D',$id)) throw new InvalidArgumentException('Isi ID pelanggan terdaftar.');
    }
    $db->beginTransaction();
    try {
        if($mode==='baru') {
            $data=$p['data'];$data['kode_cabang_favorit']=$data['kode_cabang_favorit']?:null;
            $st=$db->prepare('INSERT INTO pelanggan (id_pelanggan,nama_pelanggan,no_telepon,jenis_identitas,no_identitas,alamat,kode_cabang_favorit)
                VALUES (:id_pelanggan,:nama_pelanggan,:no_telepon,:jenis_identitas,:no_identitas,:alamat,:kode_cabang_favorit)');
            $st->execute($data);$id=$data['id_pelanggan'];
        } else {
            $st=$db->prepare('SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan=:id LOCK IN SHARE MODE');
            $st->execute(['id'=>$id]);
            if(!$st->fetchColumn()) throw new InvalidArgumentException('ID pelanggan belum terdaftar. Periksa ID sebelum menyimpan.');
        }
        $st=$db->prepare('INSERT INTO motor (no_polisi,no_rangka,no_mesin,merek,tipe,tahun_produksi,warna,kilometer_terakhir)
            VALUES (:no_polisi,:no_rangka,:no_mesin,:merek,:tipe,:tahun_produksi,:warna,:kilometer_terakhir)');
        $st->execute($motor['data']);
        $st=$db->prepare('INSERT INTO kepemilikan_motor (no_polisi,id_pelanggan,tanggal_mulai) VALUES (:motor,:pelanggan,CURDATE())');
        $st->execute(['motor'=>$motor['data']['no_polisi'],'pelanggan'=>$id]);
        $db->commit();
        return ['pelanggan'=>$id,'motor'=>$motor['data']['no_polisi']];
    } catch(Throwable $e) {if($db->inTransaction())$db->rollBack();throw $e;}
}
