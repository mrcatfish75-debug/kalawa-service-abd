<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/core.php';require dirname(__DIR__,2).'/tools/final_common.php';require __DIR__.'/http.php';require __DIR__.'/fixtures.php';require __DIR__.'/suite.php';require __DIR__.'/ui_suite.php';require __DIR__.'/cetak_suite.php';
function ksInput():array{$raw=base64_decode(trim(stream_get_contents(STDIN)),true);if($raw===false)throw new RuntimeException('Input alat tidak valid');$s=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(!is_array($s))throw new RuntimeException('Input harus objek');return $s;}
function ksSource(array $s):array {
 $target=realpath($s['target']??'');ksAssert(is_string($target)&&basename($target)==='kalawa-service-f1-korporat-uji','Folder target tidak sesuai situs uji');
 $manifest=json_decode(file_get_contents(dirname(__DIR__).'/manifest-aplikasi.json'),true,512,JSON_THROW_ON_ERROR);
 foreach($manifest['files'] as $f){$p=$target.'/'.$f['path'];ksAssert(is_file($p)&&hash_file('sha256',$p)===$f['after'],'Source berbeda dari Cetak R05: '.$f['path'].'. Gunakan ZIP website terbaru untuk menyesuaikan alat; jangan menimpa file secara acak.');}
 require $target.'/app/database_m2.php';$app=koneksiKalawaM2();$cfg=require dirname($target,2).'/kalawa-korporat-uji-config.php';
 ksAssert(ksOne($app,'SELECT DATABASE()')===KS_TEST_SOURCE_DB,'Database sumber tidak sesuai');
 ksAssert(version_compare(PHP_VERSION,'8.0.0','>='),'Gunakan PHP XAMPP 8.0 atau lebih baru');
 ksAssert((bool)ini_get('allow_url_fopen'),'Aktifkan allow_url_fopen pada PHP CLI XAMPP');
 return [$target,$cfg,$app];
}
function ksPermissionChecks(PDO $p,KsCases $t):void {
 $t->group='Izin akun database website asli (PREPARE tanpa EXECUTE)';
 $sql=file_get_contents(__DIR__.'/izin-template.sql');preg_match_all('/GRANT\s+(.+?)\s+ON\s+`[^`]+`\.`([^`]+)`\s+TO[^;]+;/i',$sql,$matches,PREG_SET_ORDER);
 foreach($matches as $m){$table=$m[2];$permission=$m[1];preg_match_all('/(SELECT|INSERT|UPDATE|DELETE)(?:\s*\(([^)]+)\))?/i',$permission,$ops,PREG_SET_ORDER);
  foreach($ops as $op){$verb=strtoupper($op[1]);$cols=isset($op[2])?explode(',',str_replace('`','',$op[2])):[];
   if($verb==='INSERT'){$fields=array_column(array_filter(ksColumns($p,$table),fn($c)=>stripos($c['Extra'],'GENERATED')===false),'Field');$plan='INSERT INTO '.ksName($table).' ('.implode(',',array_map('ksName',$fields)).') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')';}
   elseif($verb==='UPDATE'){$fields=$cols?:array_column(array_filter(ksColumns($p,$table),fn($c)=>stripos($c['Extra'],'GENERATED')===false),'Field');$plan='UPDATE '.ksName($table).' SET '.implode(',',array_map(fn($f)=>ksName(trim($f)).'=?',$fields)).' WHERE 1=0';}
   elseif($verb==='DELETE')$plan='DELETE FROM '.ksName($table).' WHERE 1=0';else $plan='SELECT * FROM '.ksName($table).' WHERE 1=0';
   $t->check($table.' '.$verb.($cols?' kolom terbatas':''),function()use($p,$plan){$q=$p->prepare($plan);$q->closeCursor();});
  }
 }
}
function ksServer(array $s,string $dir,array &$process):string {
 ksAssert(function_exists('proc_open'),'proc_open PHP CLI dibutuhkan untuk server uji');
 $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);ksAssert($socket!==false,'Port HTTP uji tidak dapat dipilih');$address=stream_socket_get_name($socket,false);$port=(int)substr(strrchr($address,':'),1);fclose($socket);
 $sessions=$dir.'/sessions';mkdir($sessions,0700,true);
 $command=[$s['php'],'-d','session.save_path='.$sessions,'-d','display_errors=1','-d','log_errors=1','-S','127.0.0.1:'.$port,'-t',$dir.'/workspace/htdocs'];
 $pipes=[];$proc=proc_open($command,[0=>['pipe','r'],1=>['file',$dir.'/server.stdout.log','a'],2=>['file',$dir.'/server.stderr.log','a']],$pipes,$dir,null,['bypass_shell'=>true]);ksAssert(is_resource($proc),'Server PHP tidak dapat dijalankan');fclose($pipes[0]);$process=['handle'=>$proc];
 for($i=0;$i<60;$i++){$test=@stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$error,.1);if($test){fclose($test);return 'http://127.0.0.1:'.$port.'/kalawa-service-f1-korporat-uji/';}if(!proc_get_status($proc)['running'])break;usleep(100000);}
 throw new RuntimeException('Server PHP uji tidak aktif. Baca server.stderr.log di folder hasil.');
}
$t=new KsCases();$dir='';$session=[];$process=[];$exit=1;
try {
 $s=ksInput();[$target,$cfg,$app]=ksSource($s);$s['target']=$target;
 echo 'Source Cetak R05 cocok; database: '.KS_TEST_SOURCE_DB.PHP_EOL;
 ksPermissionChecks($app,$t);
 if(($s['mode']??'periksa')==='periksa'){
  $roles=$app->query('SELECT role_db,COUNT(*) AS jumlah FROM akun_aplikasi WHERE aktif=1 GROUP BY role_db ORDER BY role_db')->fetchAll();foreach($roles as $r)echo $r['role_db'].' | akun aktif: '.$r['jumlah'].PHP_EOL;
  $n=count(array_filter($t->rows,fn($r)=>$r['status']==='FAIL'));echo 'Pemeriksaan selesai. Izin gagal: '.$n.'. Belum ada salinan atau transaksi dibuat.'.PHP_EOL;exit($n?2:0);
 }
 ksAssert($s['mode']==='jalankan','Mode tidak dikenal');ksAssert(!str_contains((string)ini_get('disable_functions'),'proc_open'),'proc_open dinonaktifkan');
 $dir=$s['run_dir'];ksAssert(!file_exists($dir),'Folder sesi sudah ada; gunakan sesi baru');ksAssert(mkdir($dir,0700,true),'Folder sesi tidak dapat dibuat');
 $session=['format'=>'KALAWA_UJI_LENGKAP_R01','dibuat'=>date(DATE_ATOM),'source_db'=>KS_TEST_SOURCE_DB,'target'=>$target];
 $root=ksTestPdo($cfg,KS_TEST_SOURCE_DB,$s['admin_user'],$s['admin_password']);$source=ksTestPdo($cfg,KS_TEST_SOURCE_DB,$s['admin_user'],$s['admin_password']);
 $originalObjects=kfObjects($root,KS_TEST_SOURCE_DB);$originalConfigHash=hash_file('sha256',dirname($target,2).'/kalawa-korporat-uji-config.php');$originalFiles=[];foreach(json_decode(file_get_contents(dirname(__DIR__).'/manifest-aplikasi.json'),true)['files'] as $f)$originalFiles[$f['path']]=hash_file('sha256',$target.'/'.$f['path']);
 echo 'Membuat backup konsisten, database dan source salinan terisolasi...'.PHP_EOL;
 $db=ksClone($source,$root,$cfg,$s,$dir,$session);unset($root,$source);
 $t->group='Isolasi pengujian';$t->check('Salinan awal identik dengan snapshot sumber',fn()=>ksAssert(ksDigests($db,$session['clone_db'])===$session['source_snapshot']));
 $tag=strtoupper(bin2hex(random_bytes(3)));$accounts=ksFixtureAccounts($db,$tag);$base=ksServer($s,$dir,$process);
 $suite=new KsSuite($db,$t,$accounts,$base,$tag,$dir);$suite->run();ksUiSuite($suite);ksCetakSuite($suite);
 $exit=0;
} catch(Throwable $e){$t->group='Persiapan atau pelaksanaan';$t->check('Alat selesai tanpa gangguan',function()use($e){throw $e;});}
finally {
 if(isset($process['handle'])&&is_resource($process['handle'])){proc_terminate($process['handle']);proc_close($process['handle']);}
 if($dir&&is_dir($dir)){
  if(isset($app,$session['source_snapshot'])){$t->group='Keutuhan sumber';$t->check('Objek SQL sumber tetap sama',fn()=>ksAssert(kfObjects(kfAdmin($s,$cfg),KS_TEST_SOURCE_DB)===$originalObjects));$t->check('Seluruh tabel sumber tetap sesuai snapshot',fn()=>ksAssert(ksDigests($app,KS_TEST_SOURCE_DB)===$session['source_snapshot'],'Sumber berubah selama uji. Jangan pulihkan otomatis; periksa apakah ada transaksi pengguna lain.'));$t->check('Seluruh file website sumber tetap sama',function()use($originalFiles,$target,$originalConfigHash){ksAssert(hash_file('sha256',dirname($target,2).'/kalawa-korporat-uji-config.php')===$originalConfigHash,'Config sumber berubah');foreach($originalFiles as $p=>$hash)ksAssert(hash_file('sha256',$target.'/'.$p)===$hash,'File berubah: '.$p);});}
  $summary=$t->save($dir,['sumber'=>KS_TEST_SOURCE_DB,'salinan'=>$session['clone_db']??null,'versi_php'=>PHP_VERSION,'versi_paket'=>'CETAK_R05','diperiksa'=>date(DATE_ATOM),'source_hash'=>$originalFiles??[],'snapshot_sumber'=>$session['source_snapshot']??[],'objek_sumber'=>$originalObjects??[],'server'=>isset($db)?ksIdent($db):null,'fixture'=>'Akun dan data baru hanya di salinan; tidak memakai atau mereset password akun asli.','batasan'=>'Server PHP CLI, bukan Apache. Penilaian visual/cetak tetap manual. Izin salinan memakai template; kegagalan izin akun website asli tetap FAIL tersendiri.']);
  echo PHP_EOL.'HASIL: PASS '.$summary['PASS'].' | FAIL '.$summary['FAIL'].' | BLOCKED '.$summary['BLOCKED'].PHP_EOL.'Folder hasil: '.$dir.PHP_EOL;
  if($summary['FAIL']||$summary['BLOCKED'])$exit=2;
 }
}
exit($exit);
