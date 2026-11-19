<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/final_common.php';
require __DIR__.'/seed_demo_r03.php';
date_default_timezone_set('Asia/Jakarta');
function kdVerifyFiles(string $base):array {
 $m=json_decode(file_get_contents($base.'/demo/manifest-demo.json'),true,64,JSON_THROW_ON_ERROR);
 ksAssert(($m['format']??'')==='KALAWA_DEMO_PUBLIK_R03'&&($m['application_version']??'')==='CETAK_R05','Manifest demo tidak sesuai.');
 foreach($m['files'] as $rel=>$hash){
  ksAssert(!str_contains($rel,'..')&&!str_contains($rel,'\\')&&!str_starts_with($rel,'/'),'Path manifest demo ditolak.');
  ksAssert(is_file($base.'/'.$rel)&&!is_link($base.'/'.$rel)&&hash_file('sha256',$base.'/'.$rel)===$hash,'Berkas demo berubah: '.$rel);
 }
 ksAssert(count(kfManifest())===66,'Manifest aplikasi bukan 66 berkas.');
 foreach(kfManifest() as $entry)ksAssert(hash_file('sha256',$base.'/website/'.$entry['path'])===$entry['after'],'Source aplikasi bukan final R05: '.$entry['path']);
 return $m;
}
function kdRemoveStage(?string $dir):void {
 if($dir===null||!is_dir($dir))return;
 ksAssert(preg_match('/\/\.kalawa-demo-stage-[a-f0-9]{12}$/',str_replace('\\','/',$dir))===1,'Folder staging tidak sah.');
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($it as $f){if($f->isDir()&&!$f->isLink())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($dir);
}
try{
 $s=kfInput();$base=dirname(__DIR__);$manifest=kdVerifyFiles($base);
 $raw=rtrim(str_replace('\\','/',$s['target']),'/');
 ksAssert(basename($raw)==='kalawa-service-f1-korporat-uji','Nama folder situs harus kalawa-service-f1-korporat-uji.');
 $htdocs=realpath(dirname($raw));ksAssert(is_string($htdocs),'Folder htdocs XAMPP belum tersedia.');
 $htdocs=str_replace('\\','/',$htdocs);$target=$htdocs.'/kalawa-service-f1-korporat-uji';
 $config=dirname($htdocs).'/kalawa-korporat-uji-config.php';$marker=kfMarker($target);
 ksAssert(!file_exists($target)&&!file_exists($config)&&!file_exists($marker),'Kalawa/config/state sudah ada. Installer demo tidak menimpa instalasi aktif.');
 ksAssert(is_writable($htdocs)&&is_writable(dirname($htdocs)),'Folder htdocs/XAMPP tidak dapat ditulis.');
 $port=$s['port']??3306;ksAssert(is_int($port)&&$port>=1&&$port<=65535,'Port MariaDB tidak sah.');
 $cfg=['port'=>$port];$admin=kfAdmin($s,$cfg,'mysql');
 ksAssert((int)ksOne($admin,'SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?',[KS_TEST_SOURCE_DB])===0,'Database Kalawa sudah ada. Data tidak dihapus atau diganti.');
 ksAssert((int)ksOne($admin,"SELECT COUNT(*) FROM mysql.user WHERE User=? AND Host='127.0.0.1'",[KS_TEST_SOURCE_USER])===0,'Akun koneksi sudah ada. Password tidak direset.');
 echo 'DEMO VALID: source R05, schema dan seed cocok; tujuan/database/akun masih kosong.'.PHP_EOL;
 if(($s['mode']??'periksa')!=='pasang'){echo 'Belum memasang. Ulangi dengan -Pasang untuk XAMPP baru/tujuan kosong.'.PHP_EOL;exit(0);}
 $dbCreated=false;$userCreated=false;$configCreated=false;$markerCreated=false;$moved=false;$stage=null;
 try{
  $admin->exec('CREATE DATABASE '.ksName(KS_TEST_SOURCE_DB).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$dbCreated=true;
  $p=kfAdmin($s,$cfg);kfImport($p,$base.'/demo/schema.sql',KS_TEST_SOURCE_DB);$seed=kdSeed($p);
  ksAssert(count(ksBaseTables($p,KS_TEST_SOURCE_DB))===26&&count(kfObjects($p,KS_TEST_SOURCE_DB))===38,'Jumlah tabel/objek demo tidak sesuai.');
  foreach(kdAccounts() as [$name])ksAssert(password_verify(KD_DEMO_PASSWORD,(string)ksOne($p,'SELECT password_hash FROM akun_aplikasi WHERE username=?',[$name])),'Password akun demo tidak sesuai.');
  $password=bin2hex(random_bytes(24));
  $admin->exec('CREATE USER '.$admin->quote(KS_TEST_SOURCE_USER)."@'127.0.0.1' IDENTIFIED BY ".$admin->quote($password));$userCreated=true;
  kfGrantApp($admin);$app=ksTestPdo($cfg,KS_TEST_SOURCE_DB,KS_TEST_SOURCE_USER,$password);kfViewChecks($app);
  $stage=$htdocs.'/.kalawa-demo-stage-'.bin2hex(random_bytes(6));ksAssert(mkdir($stage,0700),'Staging gagal dibuat.');
  foreach(kfManifest() as $entry){
   $dest=$stage.'/'.$entry['path'];if(!is_dir(dirname($dest)))ksAssert(mkdir(dirname($dest),0700,true),'Folder source gagal dibuat.');
   ksAssert(copy($base.'/website/'.$entry['path'],$dest)&&hash_file('sha256',$dest)===$entry['after'],'Penyalinan source gagal: '.$entry['path']);
  }
  $text="<?php\nreturn ".var_export(['database'=>KS_TEST_SOURCE_DB,'username'=>KS_TEST_SOURCE_USER,'password'=>$password,'port'=>$port],true).";\n";
  $handle=fopen($config,'x');ksAssert($handle!==false,'Konfigurasi tujuan sudah ada.');$configCreated=true;
  try{ksWriteBackup($handle,$text);ksAssert(fflush($handle),'Konfigurasi belum tersimpan.');}finally{fclose($handle);}
  @chmod($config,0600);
  $record=['format'=>'KALAWA_FINAL_T4_R01','profile'=>'DEMO_PUBLIK_R03','identity'=>ksIdent($p),'view_hash'=>hash('sha256',ksCreateDefinition($p,'VIEW',KF_VIEW)),'backup'=>'Data contoh fiktif, dibuat dari schema dan seed publik','demo'=>$seed,'waktu'=>date(DATE_ATOM)];
  $handle=fopen($marker,'x');ksAssert($handle!==false,'State tujuan sudah ada.');$markerCreated=true;
  try{ksWriteBackup($handle,json_encode($record,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));ksAssert(fflush($handle),'State belum tersimpan.');}finally{fclose($handle);}
  ksAssert(!file_exists($target)&&rename($stage,$target),'Folder tujuan muncul atau staging gagal dipindah.');$moved=true;
  echo 'PASANG DEMO PASS: http://localhost/kalawa-service-f1-korporat-uji/login.php'.PHP_EOL;
  echo 'Source: 66 berkas CETAK_R05; database: 26 tabel / 38 objek; data fiktif untuk demo lokal.'.PHP_EOL;
  echo 'Login contoh: owner_demo | password demo: '.KD_DEMO_PASSWORD.PHP_EOL;
 }catch(Throwable $e){
  if(!$moved){
   if($markerCreated&&is_file($marker))unlink($marker);
   if($configCreated&&is_file($config))unlink($config);
   kdRemoveStage($stage);
   if($userCreated)$admin->exec('DROP USER '.$admin->quote(KS_TEST_SOURCE_USER)."@'127.0.0.1'");
   if($dbCreated)$admin->exec('DROP DATABASE '.ksName(KS_TEST_SOURCE_DB));
  }
  throw $e;
 }
}catch(Throwable $e){kfFail($e);}
