<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/core.php';
try{
 $raw=base64_decode(trim(stream_get_contents(STDIN)),true);if($raw===false)throw new RuntimeException('Input tidak valid');$s=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
 $path=realpath($s['session']??'');ksAssert(is_string($path)&&basename($path)==='sesi-uji.json','Pilih sesi-uji.json yang dibuat alat ini');
 $session=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
 ksAssert(($session['format']??'')==='KALAWA_UJI_LENGKAP_R01'&&($session['source_db']??'')===KS_TEST_SOURCE_DB,'Format sesi tidak sesuai');
 $db=$session['clone_db']??'';$user=$session['clone_user']??'';
 ksAssert(preg_match('/\Aks_uji_r04_[0-9]{8}_[0-9]{6}_[a-f0-9]{6}\z/D',$db)===1&&$db!==KS_TEST_SOURCE_DB,'Database ini tidak boleh dihapus');
 ksAssert(preg_match('/\Aksut_[a-f0-9]{10}\z/D',$user)===1&&$user!==KS_TEST_SOURCE_USER,'Akun ini tidak boleh dihapus');
 $target=realpath($session['target']??'');ksAssert(is_string($target)&&basename($target)==='kalawa-service-f1-korporat-uji','Target sumber tidak sah');
 $cfg=require dirname($target,2).'/kalawa-korporat-uji-config.php';ksAssert(($cfg['database']??'')===KS_TEST_SOURCE_DB,'Config sumber tidak sesuai');
 $p=ksTestPdo($cfg,KS_TEST_SOURCE_DB,$s['admin_user'],$s['admin_password']);$identity=ksIdent($p);
 foreach(['db','hostname','port','server_id','versi'] as $k)ksAssert((string)$identity[$k]===(string)($session['identity'][$k]??''),'Identitas server berbeda dari sesi uji: '.$k);
 echo 'Salinan yang dipilih: '.$db.' | akun: '.$user.PHP_EOL;
 if(($s['mode']??'periksa')!=='hapus'){echo 'Belum dihapus. Tambahkan -Hapus untuk menghapus database dan akun salinan ini.'.PHP_EOL;exit(0);}
 $p->exec('DROP DATABASE IF EXISTS '.ksName($db));$p->exec('DROP USER IF EXISTS '.$p->quote($user)."@'127.0.0.1'");
 $session['dibersihkan']=date(DATE_ATOM);file_put_contents($path,json_encode($session,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
 echo 'Database dan akun salinan dihapus. Berkas laporan, backup, dan workspace tetap tersedia di folder hasil.'.PHP_EOL;
}catch(Throwable $e){$detail=$e instanceof PDOException?'MariaDB kode '.(int)($e->errorInfo[1]??0):$e->getMessage();fwrite(STDERR,'Pembersihan dihentikan: '.$detail.PHP_EOL);exit(1);}
