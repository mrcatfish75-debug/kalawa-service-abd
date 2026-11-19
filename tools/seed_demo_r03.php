<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

const KD_DEMO_PASSWORD='123456781234';
function kdAccounts():array {
 return [
  ['owner_demo','rl_owner_ops',null,null],
  ['admin_demo','rl_branch_admin','ADM001','CBG001'],
  ['kasir_demo','rl_cashier_ops','KSR001','CBG001'],
  ['mekanik_demo','rl_service_tech','MK001','CBG001'],
  ['pembantu_demo','rl_service_tech','MK002','CBG001'],
  ['gudang_demo','rl_inventory_ops','GD001','CBG001'],
  ['auditor_demo','rl_internal_audit',null,null],
  ['admin2_demo','rl_branch_admin','ADM002','CBG002'],
  ['kasir2_demo','rl_cashier_ops','KSR002','CBG002'],
  ['mekanik2_demo','rl_service_tech','MK003','CBG002'],
  ['gudang2_demo','rl_inventory_ops','GD002','CBG002']
 ];
}
function kdInsert(PDO $p,string $table,array $row):void {
 $cols=array_keys($row);
 $sql='INSERT INTO '.ksName($table).' ('.implode(',',array_map('ksName',$cols)).') VALUES ('.implode(',',array_fill(0,count($cols),'?')).')';
 ksExec($p,$sql,array_values($row));
}
function kdSeed(PDO $p):array {
 ksAssert(count(ksBaseTables($p,KS_TEST_SOURCE_DB))===26,'Schema demo belum lengkap.');
 foreach(ksBaseTables($p,KS_TEST_SOURCE_DB) as $t)ksAssert((int)ksOne($p,'SELECT COUNT(*) FROM '.ksName($t))===0,'Seed demo hanya untuk database kosong.');
 $now=new DateTimeImmutable((string)ksOne($p,'SELECT NOW()'),new DateTimeZone('Asia/Jakarta'));
 $today=$now->setTime(0,0);$first=$today->modify('first day of this month');
 $prev=$first->modify('-1 month');$older=$first->modify('-2 months');
 $dt=static fn(DateTimeImmutable $d):string=>$d->format('Y-m-d H:i:s');
 $p->beginTransaction();
 try {
  for($i=1;$i<=4;$i++){
   $branch=sprintf('CBG%03d',$i);
   kdInsert($p,'cabang',['kode_cabang'=>$branch,'nama_cabang'=>'Kalawa Service Demo '.$i,'alamat'=>'Alamat fiktif cabang demo '.$i.', untuk pengujian lokal','no_telepon'=>'0800000000'.sprintf('%02d',$i),'jam_operasional'=>'08.00 - 17.00 WIB','kapasitas_slot_servis'=>8]);
  }
  $staff=[
   ['ADM001','CBG001','Admin Demo Satu','ADMIN'],['KSR001','CBG001','Kasir Demo Satu','KASIR'],
   ['MK001','CBG001','Mekanik Demo Utama','MEKANIK'],['MK002','CBG001','Mekanik Demo Pembantu','MEKANIK'],
   ['GD001','CBG001','Gudang Demo Satu','PETUGAS_GUDANG'],
   ['ADM002','CBG002','Admin Demo Dua','ADMIN'],['KSR002','CBG002','Kasir Demo Dua','KASIR'],
   ['MK003','CBG002','Mekanik Demo Cabang Dua','MEKANIK'],['GD002','CBG002','Gudang Demo Dua','PETUGAS_GUDANG'],
   ['MK004','CBG003','Mekanik Demo Cabang Tiga','MEKANIK'],['MK005','CBG004','Mekanik Demo Cabang Empat','MEKANIK']
  ];
  foreach($staff as $i=>[$id,$branch,$name,$type]){
   kdInsert($p,'pegawai',['id_pegawai'=>$id,'kode_cabang'=>$branch,'nama_pegawai'=>$name,'no_telepon'=>'080000001'.sprintf('%03d',$i+1),'jenis_pegawai'=>$type]);
   if($type==='MEKANIK')kdInsert($p,'mekanik',['id_mekanik'=>$id,'tanggal_mulai_bekerja'=>$first->modify('-1 year')->format('Y-m-d'),'status_aktif'=>1,'bidang_keahlian'=>'Servis motor demonstrasi']);
  }
  foreach(kdAccounts() as $i=>[$username,$role,$staffId,$branch]){
   kdInsert($p,'akun_aplikasi',['id_akun'=>$i+1,'username'=>$username,'password_hash'=>password_hash(KD_DEMO_PASSWORD,PASSWORD_BCRYPT,['cost'=>10]),'role_db'=>$role,'id_pegawai'=>$staffId,'kode_cabang'=>$branch,'aktif'=>1,'dibuat_pada'=>$dt($now)]);
  }
  $customers=[
   ['CUSD001','Pelanggan Demo Satu','CBG001','MK001',false],
   ['CUSD002','Pelanggan Demo Dua','CBG001','MK001',false],
   ['CUSD003','Pelanggan Demo Tiga','CBG002','MK003',false],
   ['KORD001','Perusahaan Demo Kurir','CBG001','MK001',true],
   ['KORD002','Perusahaan Demo Rental','CBG001','MK001',true]
  ];
  foreach($customers as $i=>[$id,$name,$branch,$mechanic,$corp]){
   kdInsert($p,'pelanggan',['id_pelanggan'=>$id,'nama_pelanggan'=>$name,'no_telepon'=>'080000002'.sprintf('%03d',$i+1),'jenis_identitas'=>$corp?'NIB':'KTP','no_identitas'=>'DEMO-ID-'.sprintf('%04d',$i+1),'alamat'=>'Alamat fiktif pelanggan demo '.($i+1),'kode_cabang_favorit'=>$branch,'id_mekanik_pilihan'=>$mechanic]);
   if($corp)kdInsert($p,'pelanggan_korporat',['id_pelanggan'=>$id,'nama_pic'=>'PIC Demo '.($i+1)]);
  }
  $motors=[
   ['DM 1001 DA','CUSD001'],['DM 1002 DA','CUSD001'],['DM 1003 DA','CUSD002'],['DM 1004 DA','CUSD002'],['DM 1005 DA','CUSD002'],
   ['DM 2001 DB','CUSD003'],['DM 2002 DB','CUSD003'],
   ['DM 3001 KC','KORD001'],['DM 3002 KC','KORD001'],['DM 3003 KC','KORD001'],
   ['DM 4001 KR','KORD002'],['DM 4002 KR','KORD002']
  ];
  foreach($motors as $i=>[$plate,$customer]){
   kdInsert($p,'motor',['no_polisi'=>$plate,'no_rangka'=>'DEMO-RANGKA-'.sprintf('%04d',$i+1),'no_mesin'=>'DEMO-MESIN-'.sprintf('%04d',$i+1),'merek'=>'Demo Motor','tipe'=>'Kendaraan Contoh','tahun_produksi'=>2024,'warna'=>'Hitam','kilometer_terakhir'=>10000+$i*100]);
   kdInsert($p,'kepemilikan_motor',['no_polisi'=>$plate,'id_pelanggan'=>$customer,'tanggal_mulai'=>$first->modify('-1 year')->format('Y-m-d'),'tanggal_selesai'=>null]);
  }
  foreach([['JS001','Servis rutin contoh'],['JS002','Pemeriksaan rem contoh'],['JS003','Penyetelan mesin contoh'],['JS004','Pemeriksaan listrik contoh']] as [$code,$name])kdInsert($p,'jasa_servis',['kode_jasa'=>$code,'uraian_jasa_servis'=>$name]);
  foreach([['SP-DEMO01','Oli mesin contoh'],['SP-DEMO02','Busi contoh'],['SP-DEMO03','Filter udara contoh'],['SP-DEMO04','Kampas rem contoh']] as [$code,$name]){
   kdInsert($p,'suku_cadang',['kode_suku_cadang'=>$code,'nama_suku_cadang'=>$name]);
   for($i=1;$i<=4;$i++)kdInsert($p,'stok_cabang',['kode_cabang'=>sprintf('CBG%03d',$i),'kode_suku_cadang'=>$code,'jumlah_stok'=>$code==='SP-DEMO03'?2:50,'stok_minimum'=>5]);
  }
  foreach([['SUPDM01','Supplier Demo Satu'],['SUPDM02','Supplier Demo Dua']] as [$id,$name])kdInsert($p,'supplier',['id_supplier'=>$id,'nama_supplier'=>$name]);
  foreach(['SP-DEMO01','SP-DEMO02','SP-DEMO03','SP-DEMO04'] as $code)kdInsert($p,'supplier_suku_cadang',['id_supplier'=>'SUPDM01','kode_suku_cadang'=>$code]);
  kdInsert($p,'supplier_suku_cadang',['id_supplier'=>'SUPDM02','kode_suku_cadang'=>'SP-DEMO04']);
  foreach(['KORD001','KORD002'] as $i=>$id)kdInsert($p,'kontrak_korporat',['id_kontrak'=>$i+1,'id_pelanggan'=>$id,'berlaku_mulai'=>$first->modify('-1 year')->format('Y-m-d'),'berlaku_sampai'=>$first->modify('+2 years')->format('Y-m-d'),'plafon_kredit'=>'2000000.00']);
  $jobs=[
   [1,$now->modify('-2 hours'),'CBG001','CUSD001','DM 1001 DA','MK001','MENUNGGU',75000,null],
   [2,$now->modify('-6 hours'),'CBG001','CUSD001','DM 1002 DA','MK001','PROSES',50000,45000],
   [3,$now->modify('-1 day'),'CBG001','CUSD002','DM 1003 DA','MK001','SELESAI',100000,45000],
   [4,$now->modify('-3 days'),'CBG001','CUSD002','DM 1004 DA','MK001','SELESAI',75000,50000],
   [5,$now->modify('-4 days'),'CBG001','CUSD002','DM 1005 DA','MK001','DIBATALKAN',0,null],
   [6,$now->modify('-6 days'),'CBG002','CUSD003','DM 2001 DB','MK003','SELESAI',90000,null],
   [7,$prev->modify('+9 days')->setTime(9,0),'CBG001','KORD001','DM 3001 KC','MK001','SELESAI',125000,null],
   [8,$prev->modify('+14 days')->setTime(9,0),'CBG001','KORD001','DM 3002 KC','MK001','SELESAI',200000,null],
   [9,$prev->modify('+6 days')->setTime(9,0),'CBG001','KORD002','DM 4001 KR','MK001','SELESAI',100000,null],
   [10,$prev->modify('+16 days')->setTime(9,0),'CBG002','KORD002','DM 4002 KR','MK003','SELESAI',150000,null],
   [11,$older->modify('+4 days')->setTime(9,0),'CBG001','KORD001','DM 3001 KC','MK001','SELESAI',150000,null],
   [12,$now->modify('-1 hour'),'CBG001','KORD001','DM 3003 KC','MK001','MENUNGGU',60000,null],
   [13,$now->modify('-3 hours'),'CBG002','CUSD003','DM 2002 DB','MK003','MENUNGGU',75000,null]
  ];
  $ids=[];
  foreach($jobs as [$n,$entered,$branch,$customer,$plate,$mechanic,$status,$service,$part]){
   $wo='WO-'.$entered->format('Ymd').'-'.sprintf('D%07X',$n);$ids[$n]=$wo;
   kdInsert($p,'work_order',['no_work_order'=>$wo,'tanggal_masuk'=>$dt($entered),'tanggal_selesai'=>null,'kode_cabang'=>$branch,'id_pelanggan'=>$customer,'no_polisi'=>$plate,'id_mekanik_pj'=>$mechanic,'keluhan'=>'Data demo: pemeriksaan dan servis kendaraan contoh '.$n,'estimasi_biaya'=>$service+($part??0),'status_pekerjaan'=>'MENUNGGU']);
   if(str_starts_with($customer,'KORD'))kdInsert($p,'work_order_korporat',['no_work_order'=>$wo,'id_pelanggan'=>$customer,'id_kontrak'=>$customer==='KORD001'?1:2]);
   if($service>0)kdInsert($p,'detail_wo_jasa',['no_work_order'=>$wo,'kode_jasa'=>'JS001','biaya'=>$service]);
   if($part!==null)kdInsert($p,'detail_wo_suku_cadang',['no_work_order'=>$wo,'kode_suku_cadang'=>'SP-DEMO01','qty'=>1,'harga_satuan'=>$part]);
   if(in_array($status,['PROSES','SELESAI'],true))ksExec($p,"UPDATE work_order SET status_pekerjaan='PROSES' WHERE no_work_order=?",[$wo]);
   if($status==='SELESAI')ksExec($p,"UPDATE work_order SET status_pekerjaan='SELESAI',tanggal_selesai=? WHERE no_work_order=?",[$dt($entered->modify('+2 hours')),$wo]);
   if($status==='DIBATALKAN')ksExec($p,"UPDATE work_order SET status_pekerjaan='DIBATALKAN' WHERE no_work_order=?",[$wo]);
  }
  kdInsert($p,'mekanik_pembantu_wo',['no_work_order'=>$ids[2],'id_mekanik'=>'MK002']);
  foreach([[4,5000,120000,'QRIS',3],[6,0,90000,'TUNAI',9]] as [$n,$discount,$total,$method,$account]){
   kdInsert($p,'pembayaran',['no_work_order'=>$ids[$n],'diskon_member'=>$discount,'total_bayar'=>$total,'metode_pembayaran'=>$method,'status_pembayaran'=>'LUNAS']);
   kdInsert($p,'penerimaan_kas',['no_work_order'=>$ids[$n],'id_tagihan'=>null,'dicatat_pada'=>$dt($now->modify('-1 minute')),'id_akun'=>$account]);
  }
  foreach([3,4,11] as $n)kdInsert($p,'garansi',['no_work_order'=>$ids[$n],'jumlah_hari_garansi'=>30,'catatan_garansi'=>'Garansi contoh untuk demonstrasi status']);
  $invoices=[
   [1,'KORD001',$prev,$first,'BELUM_LUNAS',null,null,[[7,125000],[8,200000]]],
   [2,'KORD002',$prev,$first,'BELUM_LUNAS',null,null,[[9,100000],[10,150000]]],
   [3,'KORD001',$older,$prev,'LUNAS',$now->modify('-2 minutes'),'TRANSFER',[[11,150000]]]
  ];
  foreach($invoices as [$id,$customer,$period,$issued,$paid,$at,$method,$details]){
   kdInsert($p,'tagihan_korporat',['id_tagihan'=>$id,'id_pelanggan'=>$customer,'periode'=>$period->format('Y-m-d'),'terbit_pada'=>$dt($issued),'status_pembayaran'=>$paid,'dibayar_pada'=>$at?$dt($at):null,'metode_pembayaran'=>$method]);
   foreach($details as [$n,$value])kdInsert($p,'detail_tagihan_korporat',['no_work_order'=>$ids[$n],'id_pelanggan'=>$customer,'id_tagihan'=>$id,'nilai_final'=>$value]);
   if($paid==='LUNAS')kdInsert($p,'penerimaan_kas',['no_work_order'=>null,'id_tagihan'=>$id,'dicatat_pada'=>$dt($at),'id_akun'=>3]);
  }
  kdInsert($p,'mutasi_stok_manual',['id_permintaan'=>str_repeat('d',31).'1','kode_cabang'=>'CBG001','kode_suku_cadang'=>'SP-DEMO02','id_akun'=>6,'jenis_mutasi'=>'STOK_AWAL','stok_sebelum'=>0,'stok_sesudah'=>50,'minimum_sebelum'=>0,'minimum_sesudah'=>5,'catatan'=>'Data demo: contoh pencatatan stok awal','dicatat_pada'=>$dt($now->modify('-10 days'))]);
  $p->commit();
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
 ksAssert((int)ksOne($p,'SELECT COUNT(DISTINCT role_db) FROM akun_aplikasi WHERE aktif=1')===6,'Enam role demo belum lengkap.');
 kfViewChecks($p);
 return ['accounts'=>count(kdAccounts()),'work_orders'=>count($ids),'branches'=>4,'customers'=>5,'motors'=>count($motors),'wo_ids'=>$ids,'seed_time'=>$dt($now)];
}
