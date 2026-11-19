<?php
declare(strict_types=1);
const KS_TEST_SOURCE_DB='kalawa_service_f1_g1_clone_20261110_174035_33f8f960';
const KS_TEST_SOURCE_USER='ks_korp_uji_33f8f960';
function ksTestPdo(array $cfg,string $db,string $user,string $password):PDO {
 $pdo=new PDO('mysql:host=127.0.0.1;port='.$cfg['port'].';dbname='.$db.';charset=utf8mb4',$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>true,PDO::ATTR_TIMEOUT=>5,PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false]);
 $pdo->exec('SET SESSION innodb_lock_wait_timeout=5');return $pdo;
}
function ksIdent(PDO $p):array{return $p->query('SELECT DATABASE() AS db,@@hostname AS hostname,@@port AS port,@@server_id AS server_id,VERSION() AS versi')->fetch();}
function ksName(string $s):string{if(!preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/D',$s))throw new RuntimeException('Identifier SQL tidak valid.');return '`'.$s.'`';}
function ksOne(PDO $p,string $q,array $args=[]):mixed{$s=$p->prepare($q);$s->execute($args);return $s->fetchColumn();}
function ksExec(PDO $p,string $q,array $args=[]):int{$s=$p->prepare($q);$s->execute($args);return $s->rowCount();}
function ksBaseTables(PDO $p,string $db):array{$s=$p->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME");$s->execute([$db]);return $s->fetchAll(PDO::FETCH_COLUMN);}
function ksColumns(PDO $p,string $table):array{return $p->query('SHOW FULL COLUMNS FROM '.ksName($table))->fetchAll();}
function ksTableDigest(PDO $p,string $table):array {
 $cols=ksColumns($p,$table);$names=array_column($cols,'Field');$pk=array_column(array_values(array_filter($cols,fn($c)=>$c['Key']==='PRI')),'Field');
 $s=$p->query('SELECT * FROM '.ksName($table).' ORDER BY '.implode(',',array_map('ksName',$pk?:$names)));$hash=hash_init('sha256');$n=0;
 while($r=$s->fetch()){hash_update($hash,serialize(array_map(fn($v)=>$v===null?null:(string)$v,$r)));$n++;}return ['rows'=>$n,'sha256'=>hash_final($hash)];
}
function ksDigests(PDO $p,string $db):array{$out=[];foreach(ksBaseTables($p,$db) as $t)$out[$t]=ksTableDigest($p,$t);return $out;}
function ksRewriteSql(string $sql,string $old,string $new):string {
 // Hanya identifier backtick; nilai teks pada data atau body string tidak diganti.
 $out='';$n=strlen($sql);
 for($i=0;$i<$n;$i++){
  $c=$sql[$i];
  if($c==="'"||$c==='"'){$quote=$c;$out.=$c;while(++$i<$n){$v=$sql[$i];$out.=$v;if($v==='\\'&&$i+1<$n){$out.=$sql[++$i];continue;}if($v===$quote){if($i+1<$n&&$sql[$i+1]===$quote){$out.=$sql[++$i];continue;}break;}}continue;}
  if($c==='`'){$token='';while(++$i<$n){if($sql[$i]==='`'){if($i+1<$n&&$sql[$i+1]==='`'){$token.='`';$i++;continue;}break;}$token.=$sql[$i];}$out.='`'.str_replace('`','``',$token===$old?$new:$token).'`';continue;}
  $out.=$c;
 }return $out;
}
function ksCreateDefinition(PDO $p,string $type,string $name):string {
 $r=$p->query('SHOW CREATE '.$type.' '.ksName($name))->fetch();
 foreach(['Create Table','Create View','Create Procedure','Create Function','SQL Original Statement'] as $key)if(isset($r[$key]))return $r[$key];
 throw new RuntimeException('Definisi objek tidak dapat dibaca: '.$name);
}
function ksWriteBackup($stream,string $text):void{while($text!==''){$n=fwrite($stream,$text);if($n===false||$n===0)throw new RuntimeException('Penulisan backup SQL tidak lengkap; periksa ruang disk.');$text=substr($text,$n);}}
function ksClone(PDO $source,PDO $write,array $cfg,array $settings,string $dir,array &$session):PDO {
 $name='ks_uji_r04_'.date('Ymd_His').'_'.bin2hex(random_bytes(3));$user='ksut_'.bin2hex(random_bytes(5));$password=bin2hex(random_bytes(24));
 if(!preg_match('/\Aks_uji_r04_[0-9]{8}_[0-9]{6}_[a-f0-9]{6}\z/D',$name)||$name===KS_TEST_SOURCE_DB)throw new RuntimeException('Nama salinan ditolak.');
 $write->exec('CREATE DATABASE '.ksName($name).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
 $session+=['clone_db'=>$name,'clone_user'=>$user,'host'=>'127.0.0.1','identity'=>ksIdent($write)];
 file_put_contents($dir.'/sesi-uji.json',json_encode($session,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
 $write->exec('CREATE USER '.$write->quote($user)."@'127.0.0.1' IDENTIFIED BY ".$write->quote($password));
 $clone=ksTestPdo($cfg,$name,$settings['admin_user'],$settings['admin_password']);
 $clone->exec('SET SESSION FOREIGN_KEY_CHECKS=0');
 $source->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$source->exec('SET TRANSACTION READ ONLY');$source->beginTransaction();
 $dump=fopen($dir.'/backup-sumber.sql','x');if(!$dump)throw new RuntimeException('Backup sumber tidak dapat dibuat.');
 try {
  ksWriteBackup($dump,"-- Backup konsisten database sumber; berisi data dan hash akun. Simpan lokal.\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
  $tables=ksBaseTables($source,KS_TEST_SOURCE_DB);
  foreach($tables as $t){$ddl=ksCreateDefinition($source,'TABLE',$t);ksWriteBackup($dump,"\nDROP TABLE IF EXISTS ".ksName($t).";\n".$ddl.";\n");$clone->exec(ksRewriteSql($ddl,KS_TEST_SOURCE_DB,$name));}
  $snapshot=[];
  foreach($tables as $t){
   $cols=ksColumns($source,$t);$fields=array_column(array_values(array_filter($cols,fn($c)=>stripos($c['Extra'],'GENERATED')===false)),'Field');
   $projection=implode(',',array_map('ksName',$fields));$read=$source->query('SELECT '.$projection.' FROM '.ksName($t));
   $insert=$clone->prepare('INSERT INTO '.ksName($t).' ('.$projection.') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')');
   while($r=$read->fetch(PDO::FETCH_NUM)){$insert->execute($r);$values=array_map(fn($v)=>$v===null?'NULL':$source->quote((string)$v),$r);ksWriteBackup($dump,'INSERT INTO '.ksName($t).' ('.$projection.') VALUES ('.implode(',',$values).");\n");}
   $snapshot[$t]=ksTableDigest($source,$t);
  }
  $routines=$source->prepare('SELECT ROUTINE_NAME,ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=? ORDER BY ROUTINE_TYPE,ROUTINE_NAME');$routines->execute([KS_TEST_SOURCE_DB]);
  foreach($routines->fetchAll() as $r){$ddl=ksCreateDefinition($source,$r['ROUTINE_TYPE'],$r['ROUTINE_NAME']);ksWriteBackup($dump,"\nDELIMITER $$\n".$ddl."$$\nDELIMITER ;\n");$clone->exec(ksRewriteSql($ddl,KS_TEST_SOURCE_DB,$name));}
  $v=$source->prepare("SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME");$v->execute([KS_TEST_SOURCE_DB]);
  foreach($v->fetchAll(PDO::FETCH_COLUMN) as $n){$ddl=ksCreateDefinition($source,'VIEW',$n);ksWriteBackup($dump,"\n".$ddl.";\n");$clone->exec(ksRewriteSql($ddl,KS_TEST_SOURCE_DB,$name));}
  $tr=$source->prepare('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=? ORDER BY ACTION_ORDER,TRIGGER_NAME');$tr->execute([KS_TEST_SOURCE_DB]);
  foreach($tr->fetchAll(PDO::FETCH_COLUMN) as $n){$ddl=ksCreateDefinition($source,'TRIGGER',$n);ksWriteBackup($dump,"\nDELIMITER $$\n".$ddl."$$\nDELIMITER ;\n");$clone->exec(ksRewriteSql($ddl,KS_TEST_SOURCE_DB,$name));}
  ksWriteBackup($dump,"\nSET FOREIGN_KEY_CHECKS=1;\n");if(!fflush($dump))throw new RuntimeException('Backup SQL tidak dapat diselesaikan');$source->commit();
 }catch(Throwable $e){if($source->inTransaction())$source->rollBack();throw $e;}finally{fclose($dump);}
 $clone->exec('SET SESSION FOREIGN_KEY_CHECKS=1');
 if(ksDigests($clone,$name)!==$snapshot)throw new RuntimeException('Hash/baris tabel salinan tidak cocok dengan snapshot sumber.');
 $session['source_snapshot']=$snapshot;file_put_contents($dir.'/sesi-uji.json',json_encode($session,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
 $grants=file_get_contents(__DIR__.'/izin-template.sql');$grants=preg_replace('/^--.*$/m','',$grants);
 $grants=str_replace('`'.KS_TEST_SOURCE_DB.'`','`'.$name.'`',$grants);
 $grants=str_replace("'".KS_TEST_SOURCE_USER."'@'127.0.0.1'",$clone->quote($user)."@'127.0.0.1'",$grants);
 foreach(explode(';',$grants) as $grant){$grant=trim($grant);if($grant==='')continue;if(!str_starts_with($grant,'GRANT ')||!str_contains($grant,'`'.$name.'`.')||preg_match('/\b(ALL|TRIGGER|GRANT OPTION)\b/',$grant))throw new RuntimeException('Template izin salinan ditolak.');$clone->exec($grant);}
 $site=$dir.'/workspace/htdocs/kalawa-service-f1-korporat-uji';mkdir($site,0700,true);
 $manifest=json_decode(file_get_contents(dirname(__DIR__).'/manifest-aplikasi.json'),true,512,JSON_THROW_ON_ERROR);
 foreach($manifest['files'] as $f){$dest=$site.'/'.$f['path'];if(!is_dir(dirname($dest)))mkdir(dirname($dest),0700,true);if(!copy($settings['target'].'/'.$f['path'],$dest))throw new RuntimeException('Penyalinan source uji gagal.');}
 foreach(['app/database_m2.php','app/pembayaran_r01f.php'] as $relative){$path=$site.'/'.$relative;$text=file_get_contents($path);$changed=str_replace(KS_TEST_SOURCE_DB,$name,$text,$count);if($count<1)throw new RuntimeException('Guard database salinan tidak dikenali.');if($relative==='app/database_m2.php'){$changed=str_replace(KS_TEST_SOURCE_USER,$user,$changed,$u);if($u<1)throw new RuntimeException('Guard akun salinan tidak dikenali.');}file_put_contents($path,$changed);}
 $config=['database'=>$name,'username'=>$user,'password'=>$password,'port'=>$cfg['port']];
 file_put_contents($dir.'/workspace/kalawa-korporat-uji-config.php',"<?php\nreturn ".var_export($config,true).";\n");
 return $clone;
}
final class KsCases {
 public array $rows=[];public string $group='Persiapan';
 public function check(string $title,callable $fn):mixed {
  try{$value=$fn();$this->rows[]=['modul'=>$this->group,'kasus'=>$title,'status'=>'PASS','detail'=>'Sesuai asersi'];echo 'PASS | '.$this->group.' | '.$title.PHP_EOL;return $value;}
  catch(Throwable $e){$detail=$e instanceof PDOException?'SQL gagal (kode '.(int)($e->errorInfo[1]??0).')':$e->getMessage();$this->rows[]=['modul'=>$this->group,'kasus'=>$title,'status'=>'FAIL','detail'=>$detail];echo 'FAIL | '.$this->group.' | '.$title.' | '.$detail.PHP_EOL;return null;}
 }
 public function blocked(string $title,string $detail):void{$this->rows[]=['modul'=>$this->group,'kasus'=>$title,'status'=>'BLOCKED','detail'=>$detail];echo 'BLOCKED | '.$this->group.' | '.$title.PHP_EOL;}
 public function save(string $dir,array $meta):array {
  $counts=array_count_values(array_column($this->rows,'status'));$summary=['PASS'=>$counts['PASS']??0,'FAIL'=>$counts['FAIL']??0,'BLOCKED'=>$counts['BLOCKED']??0];
  file_put_contents($dir.'/hasil-uji.json',json_encode(['ringkasan'=>$summary,'keterangan'=>$meta,'kasus'=>$this->rows],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
  $f=fopen($dir.'/hasil-uji.csv','w');fwrite($f,"\xEF\xBB\xBF");fputcsv($f,['Modul','Kasus','Status','Detail'],',','"','');foreach($this->rows as $r)fputcsv($f,array_values($r),',','"','');fclose($f);
  $md="# Hasil uji Kalawa Service\n\nPASS: {$summary['PASS']} · FAIL: {$summary['FAIL']} · BLOCKED: {$summary['BLOCKED']}\n\n";
  $md.="Pengujian transaksi dilakukan pada database salinan, melalui HTTP lokal dan pemeriksaan SQL. Lihat daftar cakupan manual; PASS otomatis bukan bukti kualitas cetak/visual browser.\n\n| Modul | Kasus | Status | Detail |\n|---|---|---|---|\n";
  foreach($this->rows as $r)$md.='| '.implode(' | ',array_map(fn($x)=>str_replace(["\r","\n",'|'],[' ',' ','/'],(string)$x),array_values($r)))." |\n";
  file_put_contents($dir.'/ringkasan.md',$md);return $summary;
 }
}
function ksAssert(bool $v,string $message='Asersi tidak terpenuhi'):void{if(!$v)throw new RuntimeException($message);}
