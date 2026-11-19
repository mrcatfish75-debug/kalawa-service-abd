<?php
declare(strict_types=1);
function ksFixtureAccounts(PDO $p,string $tag):array {
 $defs=['owner'=>['rl_owner_ops',null,null],'admin1'=>['rl_branch_admin','ADMIN','CBG001'],'admin2'=>['rl_branch_admin','ADMIN','CBG002'],
 'cash1'=>['rl_cashier_ops','KASIR','CBG001'],'cash2'=>['rl_cashier_ops','KASIR','CBG002'],'stock1'=>['rl_inventory_ops','PETUGAS_GUDANG','CBG001'],
 'stock2'=>['rl_inventory_ops','PETUGAS_GUDANG','CBG002'],'tech1'=>['rl_service_tech','MEKANIK','CBG001'],'helper'=>['rl_service_tech','MEKANIK','CBG001'],
 'tech2'=>['rl_service_tech','MEKANIK','CBG002'],'audit'=>['rl_internal_audit',null,null]];
 foreach(['CBG001','CBG002'] as $branch)ksAssert((int)ksOne($p,'SELECT COUNT(*) FROM cabang WHERE kode_cabang=?',[$branch])===1,'Cabang fixture yang dibutuhkan tidak tersedia');
 $accounts=[];$i=0;$password=bin2hex(random_bytes(12));
 foreach($defs as $key=>[$role,$type,$branch]){$i++;$staff=$type?'UX'.$tag.strtoupper(dechex($i)):null;
  if($staff){ksExec($p,'INSERT INTO pegawai(id_pegawai,kode_cabang,nama_pegawai,no_telepon,jenis_pegawai) VALUES (?,?,?,?,?)',[$staff,$branch,'Uji '.$key.' '.$tag,'0800000000',$type]);
   if($type==='MEKANIK')ksExec($p,'INSERT INTO mekanik(id_mekanik,tanggal_mulai_bekerja,status_aktif,bidang_keahlian) VALUES (?,CURDATE(),1,?)',[$staff,'Uji servis']);}
  $username='ux_'.strtolower($tag).'_'.$key;ksExec($p,'INSERT INTO akun_aplikasi(username,password_hash,role_db,id_pegawai,kode_cabang,aktif) VALUES (?,?,?,?,?,1)',[$username,password_hash($password,PASSWORD_DEFAULT),$role,$staff,$branch]);
  $accounts[$key]=['username'=>$username,'password'=>$password,'role'=>$role,'id'=>(int)$p->lastInsertId(),'staff'=>$staff,'branch'=>$branch];
 }return $accounts;
}
function ksCustomerData(string $id,string $label):array{return ['id_pelanggan'=>$id,'nama_pelanggan'=>$label,'no_telepon'=>'081234567890','jenis_identitas'=>'KTP','no_identitas'=>'ID-'.$id,'alamat'=>'Alamat data uji terisolasi','kode_cabang_favorit'=>'CBG001'];}
function ksMotorData(string $tag,string $suffix):array{return ['no_polisi'=>'UT '.$tag.' '.$suffix,'no_rangka'=>'RG'.$tag.$suffix,'no_mesin'=>'MS'.$tag.$suffix,'merek'=>'Honda','tipe'=>'Uji Servis','tahun_produksi'=>date('Y'),'warna'=>'Hitam','kilometer_terakhir'=>'100'];}
