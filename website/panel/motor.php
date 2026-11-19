<?php
declare(strict_types=1);
require __DIR__ . '/../app/layout_m2.php';
require_once __DIR__ . '/../app/validasi_motor_m2.php';
$akun = akunLoginWajib();
if (($akun['role_db'] ?? '') !== 'rl_owner_ops') {
    http_response_code(403);
    exit('403 — Modul motor hanya tersedia untuk Owner .');
}
$db = koneksiKalawaM2(); // Guard database uji di app/database_m2.php TETAP aktif.
$errorsMotor = [];
$errorsPemilik = [];
$generalError = '';
$oldMotor = [];
$oldPemilik = [];
$postAction = (string)($_POST['aksi'] ?? '');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validasiCsrf();
        if ($postAction === 'tambah' || $postAction === 'ubah') {
            $validation = validasiMotorM2($_POST);
            $v = $validation['data'];
            $oldMotor = $v;
            $errorsMotor = $validation['errors'];
            $original = trim((string)($_POST['no_polisi_asli'] ?? ''));
            if ($postAction === 'ubah' && $original !== $v['no_polisi']) {
                $errorsMotor['no_polisi'] = 'Nomor polisi tidak dapat diganti pada form ini. Buka ulang motor dari daftar.';
            }
            if (!$errorsMotor) {
                // Pesan spesifik per kolom; indeks UNIQUE database tetap penjaga terakhir.
                foreach (['no_polisi','no_rangka','no_mesin'] as $col) {
                    $qUnique = $db->prepare('SELECT no_polisi FROM motor WHERE ' . $col . ' = :v LIMIT 1');
                    $qUnique->execute(['v' => $v[$col]]);
                    $existingPlate = $qUnique->fetchColumn();
                    if ($existingPlate !== false && ($postAction === 'tambah' || (string)$existingPlate !== $v['no_polisi'])) {
                        $errorsMotor[$col] = match($col) {
                            'no_polisi' => 'Nomor polisi sudah digunakan. Gunakan nomor yang berbeda.',
                            'no_rangka' => 'Nomor rangka sudah terdaftar pada motor lain.',
                            default => 'Nomor mesin sudah terdaftar pada motor lain.',
                        };
                    }
                }
            }
            if (!$errorsMotor) {
                if ($postAction === 'tambah') {
                    $st = $db->prepare('INSERT INTO motor
                        (no_polisi,no_rangka,no_mesin,merek,tipe,tahun_produksi,warna,kilometer_terakhir)
                        VALUES (:no_polisi,:no_rangka,:no_mesin,:merek,:tipe,:tahun_produksi,:warna,:kilometer_terakhir)');
                } else {
                    $st = $db->prepare('UPDATE motor SET no_rangka=:no_rangka, no_mesin=:no_mesin,
                        merek=:merek, tipe=:tipe, tahun_produksi=:tahun_produksi,
                        warna=:warna, kilometer_terakhir=:kilometer_terakhir
                        WHERE no_polisi=:no_polisi');
                }
                $st->execute($v);
                if ($postAction === 'ubah' && $st->rowCount() === 0) {
                    $check = $db->prepare('SELECT 1 FROM motor WHERE no_polisi=:p');
                    $check->execute(['p'=>$v['no_polisi']]);
                    if (!$check->fetchColumn()) throw new InvalidArgumentException('Motor tidak ditemukan. Pilih ulang dari daftar.');
                }
                pesanKilAt($postAction === 'tambah' ? 'Motor berhasil disimpan. Langkah berikutnya: pilih pelanggan yang menjadi pemilik motor ini.' : 'Data motor berhasil diperbarui.');
                pindah('motor.php?pilih=' . rawurlencode($v['no_polisi']));
            }
            $generalError = 'Periksa kolom motor yang ditandai. Semua input sebelumnya dipertahankan.';
        } elseif ($postAction === 'hapus') {
            $plate = trim((string)($_POST['no_polisi'] ?? ''));
            if ($plate === '' || strlen($plate) > 15) throw new InvalidArgumentException('Nomor polisi tidak valid.');
            $db->beginTransaction();
            try {
                $st=$db->prepare('SELECT no_polisi FROM motor WHERE no_polisi=:p FOR UPDATE');
                $st->execute(['p'=>$plate]);
                if (!$st->fetch()) throw new InvalidArgumentException('Motor tidak ditemukan.');
                // Cukup baca tabel kepemilikan (hak SELECT sudah tersedia).
                // FK work_order -> motor tetap memblokir DELETE jika motor pernah diservis,
                // sehingga kita tidak perlu memberikan SELECT work_order ke akun uji.
                $st=$db->prepare('SELECT COUNT(*) FROM kepemilikan_motor WHERE no_polisi=:p');
                $st->execute(['p'=>$plate]);
                if ((int)$st->fetchColumn() > 0) {
                    throw new InvalidArgumentException('Motor tidak dapat dihapus karena sudah memiliki riwayat kepemilikan. Riwayat harus dipertahankan.');
                }
                $st=$db->prepare('DELETE FROM motor WHERE no_polisi=:p');
                $st->execute(['p'=>$plate]);
                if ($st->rowCount() !== 1) throw new RuntimeException('Penghapusan motor gagal.');
                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                throw $e;
            }
            pesanKilAt('Motor yang belum memiliki relasi berhasil dihapus.');
            pindah('motor.php');
        } elseif ($postAction === 'tetapkan_pemilik') {
            $validation = validasiKepemilikanM2($_POST);
            $v=$validation['data'];
            $oldPemilik=$v;
            $errorsPemilik=$validation['errors'];
            if (!$errorsPemilik) {
                $db->beginTransaction();
                try {
                    // Mengunci baris motor agar dua perubahan kepemilikan melalui aplikasi ini tidak berjalan bersamaan.
                    $st = $db->prepare('SELECT no_polisi FROM motor WHERE no_polisi=:p FOR UPDATE');
                    $st->execute(['p'=>$v['no_polisi']]);
                    if (!$st->fetch()) throw new InvalidArgumentException('Motor tidak ditemukan. Pilih ulang dari daftar.');
                    $st = $db->prepare('SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan=:id');
                    $st->execute(['id'=>$v['id_pelanggan']]);
                    if (!$st->fetchColumn()) {
                        $errorsPemilik['id_pelanggan']='Pelanggan tidak ditemukan. Pilih pelanggan yang terdaftar.';
                        throw new InvalidArgumentException('Pilih pelanggan yang valid.');
                    }
                    $st=$db->prepare('SELECT id_pelanggan, tanggal_mulai FROM kepemilikan_motor
                        WHERE no_polisi=:p AND tanggal_selesai IS NULL FOR UPDATE');
                    $st->execute(['p'=>$v['no_polisi']]);
                    $active=$st->fetchAll();
                    if (count($active)>1) throw new InvalidArgumentException('Ada lebih dari satu pemilik aktif. Perlu audit histori sebelum bisa diproses.');
                    $st=$db->prepare('SELECT MAX(tanggal_selesai) AS akhir_terakhir,
                             MAX(tanggal_mulai) AS mulai_terakhir FROM kepemilikan_motor WHERE no_polisi=:p');
                    $st->execute(['p'=>$v['no_polisi']]);
                    $history=$st->fetch();
                    if (!empty($active)) {
                        $current=$active[0];
                        if ($current['id_pelanggan'] === $v['id_pelanggan'])
                            throw new InvalidArgumentException('Pelanggan tersebut sudah menjadi pemilik aktif motor ini.');
                        if ($v['tanggal_mulai'] <= $current['tanggal_mulai']) {
                            $errorsPemilik['tanggal_mulai']='Tanggal pemilik baru harus sesudah tanggal mulai pemilik lama.';
                            throw new InvalidArgumentException('Tanggal perpindahan tidak valid.');
                        }
                        $end=(new DateTimeImmutable($v['tanggal_mulai']))->modify('-1 day')->format('Y-m-d');
                        $st=$db->prepare('UPDATE kepemilikan_motor SET tanggal_selesai=:akhir
                            WHERE no_polisi=:p AND id_pelanggan=:id AND tanggal_mulai=:awal
                                  AND tanggal_selesai IS NULL');
                        $st->execute(['akhir'=>$end,'p'=>$v['no_polisi'],'id'=>$current['id_pelanggan'],'awal'=>$current['tanggal_mulai']]);
                        if ($st->rowCount()!==1) throw new RuntimeException('Riwayat pemilik lama gagal ditutup.');
                    } elseif ($history['mulai_terakhir'] !== null) {
                        // Periode baru tidak boleh tumpang tindih dengan periode tertutup terakhir.
                        if ($v['tanggal_mulai'] <= max((string)$history['mulai_terakhir'], (string)$history['akhir_terakhir'])) {
                            $errorsPemilik['tanggal_mulai']='Tanggal mulai pemilik baru harus setelah periode kepemilikan sebelumnya.';
                            throw new InvalidArgumentException('Periode kepemilikan bertumpuk.');
                        }
                    }
                    $st=$db->prepare('INSERT INTO kepemilikan_motor
                          (no_polisi,id_pelanggan,tanggal_mulai,tanggal_selesai)
                          VALUES (:p,:id,:mulai,NULL)');
                    $st->execute(['p'=>$v['no_polisi'],'id'=>$v['id_pelanggan'],'mulai'=>$v['tanggal_mulai']]);
                    $db->commit();
                } catch (Throwable $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    throw $e;
                }
                pesanKilAt('Pemilik aktif dan histori kepemilikan berhasil disimpan.');
                pindah('motor.php?pilih=' . rawurlencode($v['no_polisi']));
            }
            $generalError = 'Periksa informasi kepemilikan yang ditandai.';
        } else {
            throw new InvalidArgumentException('Aksi tidak dikenali.');
        }
    }
} catch (InvalidArgumentException $e) {
    $generalError = $e->getMessage();
} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[Kalawa TEST: motor] ' . $e->getMessage());
    $code=(int)($e->errorInfo[1] ?? 0);
    $generalError = match ($code) {
        1062 => 'Nomor polisi, nomor rangka, atau nomor mesin sudah terdaftar. Periksa kolom yang unik.',
        1451 => 'Operasi ditolak: motor sudah digunakan pada relasi kepemilikan atau work order.',
        1452 => 'Relasi gagal: motor atau pelanggan tidak ditemukan.',
        1142 => 'Privilege database belum sesuai. Periksa GRANT khusus database uji.',
        default => 'Perubahan motor belum tersimpan. Periksa konfigurasi akun uji dan catatan server.',
    };
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[Kalawa TEST: motor] ' . $e->getMessage());
    $generalError='Operasi tidak berhasil. Periksa catatan server.';
}

$selected = trim((string)($_GET['pilih'] ?? $_GET['ubah'] ?? $oldPemilik['no_polisi'] ?? $oldMotor['no_polisi'] ?? ''));
if (strlen($selected)>15) $selected='';
$editing = $postAction === 'ubah' || isset($_GET['ubah']);
$edit = null; $selectedMotor = null; $historyRows = [];
try {
    if ($selected!=='') {
        $st=$db->prepare('SELECT * FROM motor WHERE no_polisi=:p');
        $st->execute(['p'=>$selected]);
        $selectedMotor=$st->fetch() ?: null;
        if ($selectedMotor) {
            $st=$db->prepare('SELECT k.no_polisi, k.id_pelanggan, p.nama_pelanggan,
                     k.tanggal_mulai, k.tanggal_selesai
                     FROM kepemilikan_motor k JOIN pelanggan p ON p.id_pelanggan=k.id_pelanggan
                     WHERE k.no_polisi=:p ORDER BY k.tanggal_mulai DESC, k.id_pelanggan');
            $st->execute(['p'=>$selected]);
            $historyRows=$st->fetchAll();
            if (isset($_GET['ubah'])) $edit=$selectedMotor;
        }
    }
    $customers=$db->query('SELECT id_pelanggan, nama_pelanggan FROM pelanggan ORDER BY nama_pelanggan ASC LIMIT 500')->fetchAll();
    $search=substr(trim((string)($_GET['q'] ?? '')),0,70);
    $sql='SELECT m.no_polisi,m.no_rangka,m.no_mesin,m.merek,m.tipe,m.tahun_produksi,
             m.warna,m.kilometer_terakhir,
             (SELECT COUNT(*) FROM kepemilikan_motor k WHERE k.no_polisi=m.no_polisi AND k.tanggal_selesai IS NULL) AS pemilik_aktif_jumlah,
             (SELECT COUNT(*) FROM kepemilikan_motor k WHERE k.no_polisi=m.no_polisi) AS jumlah_riwayat,
             (SELECT p.nama_pelanggan FROM kepemilikan_motor k JOIN pelanggan p ON p.id_pelanggan=k.id_pelanggan
              WHERE k.no_polisi=m.no_polisi AND k.tanggal_selesai IS NULL ORDER BY k.tanggal_mulai DESC LIMIT 1) AS nama_pemilik
          FROM motor m';
    $args=[];
    if($search!==''){$sql.=' WHERE m.no_polisi LIKE :p OR m.merek LIKE :m OR m.tipe LIKE :t';$term='%'.$search.'%';$args=['p'=>$term,'m'=>$term,'t'=>$term];}
    $motorRows=ksUiList($db,$sql.' ORDER BY m.no_polisi ASC',$args,'hal_motor');
} catch (Throwable $e) {
    error_log('[Kalawa TEST: motor SELECT] '.$e->getMessage());
    http_response_code(503);
    exit('Data motor tidak dapat dibaca. Pastikan hak SELECT akun database uji tersedia.');
}
$form=$oldMotor ?: ($edit ?: []);
$flash=ambilPesanKilAt();
if ($_SERVER['REQUEST_METHOD']==='POST' || $generalError!=='') $flash=null;
function motorHint(string $key,string $hint,array $errors): void {
    echo '<small id="'.amanHtml($key).'-hint" class="field-hint">'.amanHtml($hint).'</small>';
    if (isset($errors[$key])) echo '<small id="'.amanHtml($key).'-error" class="field-error">'.amanHtml($errors[$key]).'</small>';
}
function motorAttrs(string $key,array $errors): string {
    return 'aria-describedby="'.amanHtml($key).'-hint'.(isset($errors[$key])?' '.amanHtml($key).'-error':'').'"'
        . (isset($errors[$key])?' aria-invalid="true" data-server-error="true"':'');
}
// Informasi presentasi saja; seluruh aturan transaksi, privilege dan validasi tetap di atas.
$aktifRows = array_values(array_filter($historyRows, static fn(array $h): bool => $h['tanggal_selesai'] === null));
$aktifJumlah = count($aktifRows);
$pemilikAktif = $aktifJumlah === 1 ? $aktifRows[0] : null;
$pemilikKonflik = $aktifJumlah > 1;
$aksiTampil = $editing ? 'ubah' : 'tambah';
$peringatanTanggal = $pemilikAktif !== null && (string)$pemilikAktif['tanggal_mulai'] === date('Y-m-d');
awalHalamanM2('Data Motor', $akun);
?>
<style>
/* KALAWA MOTOR R02 — hanya mengatur halaman motor; tidak mengganti CSS global. */
.ks-motor{--ks-red:#aa252d;--ks-dark:#17202b;--ks-amber:#e9b736;--ks-slate:#64748b;--ks-line:#e5e7eb;--ks-pale:#fffbeb;color:var(--ks-dark)}
.ks-motor *, .ks-motor *:before, .ks-motor *:after{box-sizing:border-box}
.ks-motor h1{font-size:1.8rem;margin:0 0 7px;letter-spacing:-.035em}
.ks-motor .ks-eyebrow{font-size:.74rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#9c2529;margin:0 0 6px}
.ks-motor .ks-intro{margin:0;color:#556070;line-height:1.65;font-size:.95rem}
.ks-motor .ks-page-head{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:18px}
.ks-motor .ks-head-actions{display:flex;gap:9px;flex-wrap:wrap}
.ks-motor .ks-btn{border:1px solid #cbd5e1;background:white;color:#27364a;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:10px 14px;font-weight:700;font-size:.87rem;text-decoration:none;line-height:1.25;cursor:pointer}
.ks-motor .ks-btn:hover,.ks-motor .ks-btn:focus-visible{text-decoration:none;border-color:#9c2529}
.ks-motor .ks-btn-primary{background:#aa252d;border-color:#aa252d;color:white}
.ks-motor .ks-btn-primary:hover{background:#8d1e26;color:white}
.ks-motor .ks-btn-quiet{background:#fff9eb;border-color:#f2d897;color:#784f10}
.ks-motor .ks-steps{border:1px solid #eadfb6;background:#fffdf6;border-radius:14px;padding:16px 18px;margin:17px 0 18px}
.ks-motor .ks-steps-title{font-size:.88rem;font-weight:750;margin:0 0 12px;color:#523917}
.ks-motor .ks-steps-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.ks-motor .ks-step{display:flex;gap:10px;align-items:flex-start;min-width:0}
.ks-motor .ks-step-num{font-size:.84rem;font-weight:800;width:28px;height:28px;flex:none;display:grid;place-items:center;border-radius:50%;background:#f8e4a3;color:#714d04}
.ks-motor .ks-step strong{display:block;font-size:.88rem;line-height:1.35}
.ks-motor .ks-step small{display:block;color:#685f53;margin-top:2px;line-height:1.5}
.ks-motor .ks-panel{background:white;border:1px solid #e1e5e9;border-radius:15px;padding:20px;margin:18px 0;box-shadow:0 4px 18px #11182707}
.ks-motor .ks-panel h2{font-size:1.18rem;margin:0 0 8px;letter-spacing:-.015em}
.ks-motor .ks-heading-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
.ks-motor .ks-soft-text{font-size:.88rem;line-height:1.6;color:#617083;margin:0}
.ks-motor .ks-pill{display:inline-flex;align-items:center;gap:5px;border-radius:100px;padding:5px 10px;font-size:.76rem;font-weight:750;line-height:1.25;white-space:nowrap}
.ks-motor .ks-pill-ok{background:#eaf6ee;color:#146237}
.ks-motor .ks-pill-wait{background:#fff1c6;color:#774a09}
.ks-motor .ks-pill-bad{background:#ffebe9;color:#972b2d}
.ks-motor .ks-owner-detail{background:#f8fafb;border:1px solid #e6ecf0;border-radius:13px;padding:16px;display:grid;grid-template-columns:1.1fr 1fr;gap:16px;margin:16px 0}
.ks-motor .ks-mini-label{font-size:.76rem;color:#667389;text-transform:uppercase;letter-spacing:.06em;font-weight:750;margin-bottom:4px}
.ks-motor .ks-owner-name{font-size:1.22rem;line-height:1.3;font-weight:800;margin:2px 0 6px}
.ks-motor .ks-owner-helper{font-size:.84rem;color:#526273;line-height:1.6;margin:0}
.ks-motor .ks-inline-actions{display:flex;align-items:center;flex-wrap:wrap;gap:9px;margin:14px 0}
.ks-motor .ks-note{border-radius:11px;border:1px solid #efdaa5;background:#fffaf0;color:#624714;font-size:.89rem;line-height:1.58;padding:12px 14px;margin:14px 0}
.ks-motor .ks-note-error{background:#fff2f1;border-color:#f0c5c3;color:#8d252a}
.ks-motor .ks-subsection{border-top:1px solid #ebedf0;padding-top:17px;margin-top:20px}
.ks-motor .ks-subtitle{font-size:1rem;font-weight:800;margin:0 0 6px}
.ks-motor .ks-form-description{font-size:.86rem;line-height:1.6;color:#657181;margin:4px 0 15px}
.ks-motor .ks-input-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:18px;row-gap:1px}
.ks-motor .ks-input-grid label{font-size:.87rem;margin-bottom:6px}
.ks-motor .ks-input-grid input,.ks-motor .ks-input-grid select{margin-bottom:3px}
.ks-motor .ks-field{padding:2px 0 10px;min-width:0}
.ks-motor .ks-field .field-hint{display:block;margin:4px 0 4px;color:#64748b;font-size:.76rem;line-height:1.5}
.ks-motor .ks-field .field-error{display:block;font-weight:650;color:#a5222a;font-size:.79rem;margin-top:5px}
.ks-motor [aria-invalid="true"]{border-color:#c62830;background:#fff8f7}
.ks-motor .ks-privacy{margin:7px 0 13px;color:#667389;font-size:.78rem;line-height:1.6}
.ks-motor .ks-section-jump{scroll-margin-top:16px}
.ks-motor .ks-controls{display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-top:8px}
.ks-motor .ks-list-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap}
.ks-motor .ks-search{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap}
.ks-motor .ks-search input{width:215px;max-width:100%;margin:0}
.ks-motor .ks-search button{white-space:nowrap}
.ks-motor .ks-list-table{width:100%;min-width:790px;border-collapse:collapse;margin-top:13px}
.ks-motor .ks-list-table th{font-size:.77rem;background:#f5f6f8;color:#4d5b70}
.ks-motor .ks-list-table tr.ks-selected-row{background:#fff9eb}
.ks-motor .ks-list-table td{vertical-align:middle}
.ks-motor .ks-table-main{font-weight:750;color:#192330}
.ks-motor .ks-table-sub{font-size:.76rem;line-height:1.5;color:#667389;margin-top:3px}
.ks-motor .ks-row-actions{display:flex;align-items:center;flex-wrap:wrap;gap:7px}
.ks-motor .ks-action-link{font-weight:700;font-size:.8rem;white-space:nowrap;text-decoration:none;padding:6px 7px;border-radius:7px}
.ks-motor .ks-action-link:hover{background:#fff0ed;text-decoration:none}
.ks-motor .ks-small-button{font-weight:650;font-size:.78rem;padding:6px 9px;background:white;color:#aa252d;border-color:#e8bfc2}
.ks-motor .ks-list-footer{font-size:.78rem;color:#69778b;margin:12px 0 0}
.ks-motor .ks-history-list{margin-top:10px}
.ks-motor .ks-history-list td,.ks-motor .ks-history-list th{padding:11px 10px}
.ks-motor .ks-expand{border:1px solid #e1e6ea;border-radius:13px;background:white;overflow:hidden;margin:18px 0 28px}
.ks-motor .ks-expand summary{list-style:none;cursor:pointer;padding:17px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;font-weight:800}
.ks-motor .ks-expand summary::-webkit-details-marker{display:none}
.ks-motor .ks-expand summary:after{content:'+';font-weight:600;font-size:1.4rem;line-height:1;color:#aa252d}
.ks-motor .ks-expand[open] summary:after{content:'−'}
.ks-motor .ks-expand[open] summary{border-bottom:1px solid #e9edf0}
.ks-motor .ks-expand-body{padding:20px}
.ks-motor .ks-expand .ks-input-grid{margin:12px 0}
.ks-motor .ks-flag{font-size:.74rem;display:block;font-weight:650;color:#936119;margin-top:2px}
.ks-motor .ks-muted-button{color:#718096;font-size:.76rem}
.ks-motor .ks-no-results{padding:23px;color:#566171;text-align:center}
.ks-motor .ks-alert{scroll-margin-top:22px}
@media(max-width:760px){
 .ks-motor .ks-steps-list{grid-template-columns:1fr;gap:12px}
 .ks-motor .ks-owner-detail{grid-template-columns:1fr}
 .ks-motor .ks-input-grid{grid-template-columns:1fr}
 .ks-motor .ks-panel{padding:15px}
 .ks-motor .ks-expand-body{padding:14px}
 .ks-motor .ks-search,.ks-motor .ks-search input{width:100%}
 .ks-motor .ks-search button{flex:1}
 .ks-motor .ks-head-actions{width:100%}
 .ks-motor .ks-btn{flex:1}
}
</style>
<div class="ks-motor">
  <header class="ks-page-head">
    <div>
      <p class="ks-eyebrow">Pengelolaan kendaraan</p>
      <h1>Data motor &amp; kepemilikan</h1>
      <p class="ks-intro">Daftarkan kendaraan, lihat pemiliknya, dan catat perpindahan kepemilikan dalam satu halaman.</p>
    </div>
    <div class="ks-head-actions">
      <a class="ks-btn" href="#daftar-motor">Lihat daftar motor</a>
      <a class="ks-btn ks-btn-primary" href="#form-motor" data-open-motor-form="true">+ Tambah motor</a>
    </div>
  </header>
  <div class="ks-steps" aria-label="Panduan langkah penggunaan">
    <p class="ks-steps-title">Cara mengelola motor</p>
    <div class="ks-steps-list">
      <div class="ks-step"><span class="ks-step-num">1</span><div><strong>Daftarkan motor</strong><small>Isi data kendaraan dan simpan.</small></div></div>
      <div class="ks-step"><span class="ks-step-num">2</span><div><strong>Tentukan pemilik</strong><small>Di daftar motor, klik “Kelola pemilik”.</small></div></div>
      <div class="ks-step"><span class="ks-step-num">3</span><div><strong>Kelola perubahannya</strong><small>Gunakan halaman yang sama saat motor berpindah pemilik.</small></div></div>
    </div>
  </div>
  <?php if ($flash): ?>
    <div class="alert ks-alert" id="flash-motor" role="status">
      <strong><?= amanHtml($flash['pesan']) ?></strong>
      <?php if ($selectedMotor && str_contains((string)$flash['pesan'],'Motor berhasil disimpan')): ?>
        <div style="margin-top:10px"><a class="ks-btn ks-btn-quiet" href="#form-pemilik">Lanjut: tetapkan pemilik motor</a></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($generalError): ?>
    <div class="alert bad error-summary ks-alert" role="alert">
      <strong><?= amanHtml($generalError) ?></strong>
      <?php foreach (array_merge($errorsMotor,$errorsPemilik) as $key=>$value): ?>
        <div><a href="#<?= amanHtml($key) ?>"><?= amanHtml($value) ?></a></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($selectedMotor): ?>
    <section class="ks-panel ks-section-jump" id="detail-motor" aria-labelledby="selected-motor-title">
      <div class="ks-heading-row">
        <div>
          <p class="ks-eyebrow">Motor yang sedang dikelola</p>
          <h2 id="selected-motor-title"><?= amanHtml($selectedMotor['merek'].' '.$selectedMotor['tipe']) ?> — <?= amanHtml($selectedMotor['no_polisi']) ?></h2>
          <p class="ks-soft-text"><?= amanHtml($selectedMotor['tahun_produksi']) ?> · <?= amanHtml($selectedMotor['warna']) ?> · <?= number_format((int)$selectedMotor['kilometer_terakhir'],0,',','.') ?> km</p>
        </div>
        <?php if ($pemilikKonflik): ?>
          <span class="ks-pill ks-pill-bad">Perlu pemeriksaan data</span>
        <?php elseif ($pemilikAktif): ?>
          <span class="ks-pill ks-pill-ok">Pemilik terdaftar</span>
        <?php else: ?>
          <span class="ks-pill ks-pill-wait">Belum ada pemilik aktif</span>
        <?php endif; ?>
      </div>
      <div class="ks-owner-detail">
        <div>
          <div class="ks-mini-label">Pemilik motor saat ini</div>
          <?php if ($pemilikKonflik): ?>
            <div class="ks-owner-name" style="color:#a5222a">Ditemukan lebih dari satu</div>
            <p class="ks-owner-helper">Terdapat lebih dari satu pemilik aktif. Hubungi pengelola database untuk memeriksa riwayat sebelum melanjutkan.</p>
          <?php elseif ($pemilikAktif): ?>
            <div class="ks-owner-name"><?= amanHtml($pemilikAktif['nama_pelanggan']) ?></div>
            <p class="ks-owner-helper">ID pelanggan: <?= amanHtml($pemilikAktif['id_pelanggan']) ?><br>Terdaftar sejak <?= amanHtml(date('d-m-Y',strtotime($pemilikAktif['tanggal_mulai']))) ?></p>
          <?php else: ?>
            <div class="ks-owner-name">Belum ditentukan</div>
            <p class="ks-owner-helper">Kendaraan sudah tersimpan, tetapi belum mempunyai pemilik aktif. Pilih pelanggan yang benar di bagian berikut.</p>
          <?php endif; ?>
        </div>
        <div>
          <div class="ks-mini-label">Apa yang ingin dilakukan?</div>
          <p class="ks-owner-helper">Tindakan di bawah hanya berlaku untuk <strong><?= amanHtml($selectedMotor['no_polisi']) ?></strong>. Untuk motor lain, pilih tombol “Kelola pemilik” pada baris motor tersebut.</p>
          <div class="ks-inline-actions">
            <a class="ks-btn ks-btn-quiet" href="#form-pemilik"><?= $pemilikAktif ? 'Pindahkan pemilik' : 'Tetapkan pemilik' ?></a>
            <a class="ks-btn" href="motor.php?ubah=<?= rawurlencode($selectedMotor['no_polisi']) ?>#form-motor">Ubah data motor</a>
          </div>
        </div>
      </div>
      <?php if ($pemilikKonflik): ?>
        <div class="ks-note ks-note-error">Perubahan kepemilikan dinonaktifkan pada tampilan ini sampai konflik riwayat diperiksa. Jangan menghapus atau menimpa riwayat lama.</div>
      <?php elseif ($pemilikAktif): ?>
        <div class="ks-note">Motor ini sudah memiliki pemilik aktif. <strong>Jangan menggunakan form di bawah untuk mengoreksi salah ketik</strong>; form tersebut khusus mencatat perpindahan kepemilikan yang benar-benar terjadi.</div>
      <?php else: ?>
        <div class="ks-note"><strong>Langkah berikutnya:</strong> pilih pemilik pertama motor ini. Data motor tidak akan dihapus jika kamu belum memilih pemilik.</div>
      <?php endif; ?>
      <div class="ks-subsection ks-section-jump" id="form-pemilik">
        <h3 class="ks-subtitle"><?= $pemilikAktif ? 'Pindahkan ke pemilik baru' : 'Tetapkan pemilik motor ini' ?></h3>
        <p class="ks-form-description">
          <?php if ($pemilikAktif): ?>
            Pilih pelanggan baru dan tanggal perpindahan. Sistem akan menutup periode pemilik sebelumnya sehari sebelum tanggal baru, tanpa menghapus riwayat.
          <?php else: ?>
            Pilih pelanggan yang akan tercatat sebagai pemilik aktif. Jika belum terdaftar, tambahkan dahulu melalui menu <a href="pelanggan.php">Data Pelanggan</a>.
          <?php endif; ?>
        </p>
        <?php if ($peringatanTanggal): ?>
          <div class="ks-note"><strong>Perhatian tanggal:</strong> pemilik aktif baru tercatat hari ini. Sistem saat ini tidak mengizinkan perpindahan dengan tanggal yang sama atau tanggal mendatang; lakukan perpindahan pada tanggal berikutnya ketika tanggal tersebut sudah tiba.</div>
        <?php endif; ?>
        <?php if (!$pemilikKonflik): ?>
        <form id="pemilik-form" method="post" action="motor.php?pilih=<?= rawurlencode($selectedMotor['no_polisi']) ?>">
          <input type="hidden" name="csrf" value="<?= amanHtml(tokenCsrf()) ?>">
          <input type="hidden" name="aksi" value="tetapkan_pemilik">
          <input type="hidden" name="no_polisi" value="<?= amanHtml($selectedMotor['no_polisi']) ?>">
          <div class="ks-input-grid">
            <div class="ks-field">
              <label for="id_pelanggan">Pelanggan pemilik baru *</label>
              <select id="id_pelanggan" name="id_pelanggan" required <?= motorAttrs('id_pelanggan',$errorsPemilik) ?>>
                <option value="">-- Pilih pelanggan terdaftar --</option>
                <?php foreach ($customers as $c): ?>
                  <option value="<?= amanHtml($c['id_pelanggan']) ?>" <?= ($oldPemilik['id_pelanggan'] ?? '') === $c['id_pelanggan'] ? 'selected' : '' ?>><?= amanHtml($c['nama_pelanggan'].' ('.$c['id_pelanggan'].')') ?></option>
                <?php endforeach; ?>
              </select>
              <?php motorHint('id_pelanggan','Pilih satu pelanggan dari daftar yang tersedia.',$errorsPemilik); ?>
            </div>
            <div class="ks-field">
              <label for="tanggal_mulai">Tanggal mulai kepemilikan *</label>
              <input id="tanggal_mulai" name="tanggal_mulai" type="date" min="1900-01-01" max="<?= date('Y-m-d') ?>" required
                     value="<?= amanHtml($oldPemilik['tanggal_mulai'] ?? date('Y-m-d')) ?>" <?= motorAttrs('tanggal_mulai',$errorsPemilik) ?>>
              <?php motorHint('tanggal_mulai','Tidak boleh di masa depan atau bertumpang tindih dengan tanggal pemilik sebelumnya.',$errorsPemilik); ?>
            </div>
          </div>
          <div class="ks-controls">
            <button type="submit"><?= $pemilikAktif ? 'Simpan perpindahan pemilik' : 'Tetapkan pemilik pertama' ?></button>
            <a class="ks-btn" href="#daftar-motor">Pilih motor lain</a>
          </div>
        </form>
        <?php endif; ?>
      </div>
      <div class="ks-subsection ks-section-jump" id="riwayat-pemilik">
        <div class="ks-heading-row">
          <h3 class="ks-subtitle">Riwayat kepemilikan</h3>
          <span class="ks-pill ks-pill-wait"><?= count($historyRows) ?> periode tercatat</span>
        </div>
        <p class="ks-form-description">Riwayat menunjukkan siapa yang pernah memiliki motor ini dan kapan periode kepemilikannya berlangsung.</p>
        <?php if (!$historyRows): ?>
          <p class="ks-no-results">Belum ada riwayat. Catatan pertama akan muncul setelah kamu menetapkan pemilik.</p>
        <?php else: ?>
          <div class="tablewrap"><table class="ks-history-list"><thead><tr><th>Nama pemilik</th><th>Tanggal mulai</th><th>Tanggal selesai</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($historyRows as $h): ?>
              <tr><td><?= amanHtml($h['nama_pelanggan'].' ('.$h['id_pelanggan'].')') ?></td>
              <td><?= amanHtml(date('d-m-Y',strtotime($h['tanggal_mulai']))) ?></td>
              <td><?= $h['tanggal_selesai'] === null ? '—' : amanHtml(date('d-m-Y',strtotime($h['tanggal_selesai']))) ?></td>
              <td><span class="ks-pill <?= $h['tanggal_selesai'] === null ? 'ks-pill-ok' : 'ks-pill-wait' ?>"><?= $h['tanggal_selesai'] === null ? 'Aktif' : 'Selesai' ?></span></td></tr>
            <?php endforeach; ?>
          </tbody></table></div>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>
  <section class="ks-panel ks-section-jump" id="daftar-motor">
    <div class="ks-list-head">
      <div>
        <h2>Daftar motor</h2>
        <p class="ks-soft-text">Pilih <strong>Kelola pemilik</strong> di motor mana pun untuk melihat atau mengubah kepemilikannya.</p>
      </div>
      <form method="get" class="ks-search" action="motor.php" role="search">
        <label class="sr-only" for="q">Cari motor</label>
        <input id="q" name="q" placeholder="Nomor polisi, merek, tipe" maxlength="70" value="<?= amanHtml($search) ?>" aria-label="Cari nomor polisi, merek atau tipe motor">
        <button type="submit">Cari</button>
        <?php if ($search !== ''): ?><a class="ks-btn" href="motor.php#daftar-motor">Reset</a><?php endif; ?>
      </form>
    </div>
    <div class="tablewrap" role="region" aria-label="Daftar motor, dapat digeser ke samping di layar kecil" tabindex="0">
      <table class="ks-list-table"><thead><tr><th>Nomor polisi / motor</th><th>Tahun / kilometer</th><th>Pemilik aktif</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        <?php foreach ($motorRows as $r): ?>
          <?php $adaKonflik=(int)$r['pemilik_aktif_jumlah']>1; $adaPemilik=(int)$r['pemilik_aktif_jumlah']===1; $sudahHistori=(int)$r['jumlah_riwayat']>0; ?>
          <tr class="<?= $selectedMotor && $selectedMotor['no_polisi'] === $r['no_polisi'] ? 'ks-selected-row' : '' ?>">
            <td><div class="ks-table-main"><?= amanHtml($r['no_polisi']) ?></div><div class="ks-table-sub"><?= amanHtml($r['merek'].' '.$r['tipe']) ?></div></td>
            <td><?= amanHtml($r['tahun_produksi']) ?><div class="ks-table-sub"><?= number_format((int)$r['kilometer_terakhir'],0,',','.') ?> km</div></td>
            <td><?= $adaKonflik ? 'Perlu diperiksa' : amanHtml($r['nama_pemilik'] ?: '—') ?></td>
            <td><span class="ks-pill <?= $adaKonflik ? 'ks-pill-bad' : ($adaPemilik ? 'ks-pill-ok' : 'ks-pill-wait') ?>"><?= $adaKonflik ? 'Konflik data' : ($adaPemilik ? 'Ada pemilik' : 'Belum ada pemilik') ?></span></td>
            <td>
              <div class="ks-row-actions">
                <a class="ks-action-link" href="motor.php?pilih=<?= rawurlencode($r['no_polisi']) ?>#detail-motor"><?= $adaPemilik ? 'Kelola pemilik' : 'Tetapkan pemilik' ?></a>
                <a class="ks-action-link" href="motor.php?ubah=<?= rawurlencode($r['no_polisi']) ?>#form-motor">Ubah data</a>
                <?php if (!$sudahHistori): ?>
                  <form method="post" action="motor.php" onsubmit="return confirm('Hapus motor <?= amanHtml($r['no_polisi']) ?>? Hanya motor yang belum memiliki riwayat kepemilikan dan belum tercatat dalam work order yang dapat dihapus.');">
                    <input type="hidden" name="csrf" value="<?= amanHtml(tokenCsrf()) ?>"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="no_polisi" value="<?= amanHtml($r['no_polisi']) ?>"><button type="submit" class="ks-small-button">Hapus</button>
                  </form>
                <?php else: ?>
                  <span class="ks-muted-button" title="Motor memiliki riwayat. Penghapusan dibatasi untuk menjaga integritas data.">Hapus dibatasi</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$motorRows): ?><tr><td class="ks-no-results" colspan="5">Tidak ada motor yang cocok. Coba kata kunci lain atau reset pencarian.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
<?php ksUiPager('hal_motor'); ?>
    <p class="ks-list-footer">Pada ponsel, geser tabel ke samping untuk melihat aksi lengkap. Motor dengan riwayat kepemilikan tidak dapat dihapus dari halaman ini.</p>
  </section>
  <details class="ks-expand ks-section-jump" id="form-motor" <?= ($editing || !empty($errorsMotor) || ($postAction === 'tambah' && $generalError !== '')) ? 'open' : '' ?>>
    <summary><span><?= $editing ? 'Ubah data motor — '.amanHtml((string)($form['no_polisi'] ?? $selected)) : 'Tambah motor baru' ?></span></summary>
    <div class="ks-expand-body">
      <p class="ks-form-description"><?= $editing ? 'Perbarui data kendaraan terpilih. Nomor polisi tidak dapat diganti pada form ini.' : 'Masukkan identitas kendaraan sesuai dokumen. Setelah tersimpan, lanjutkan dengan menetapkan pemilik melalui tombol “Kelola pemilik”.' ?> Semua kolom bertanda * wajib diisi.</p>
      <form id="motor-form" method="post" action="motor.php<?= $editing ? '?ubah='.rawurlencode((string)($form['no_polisi'] ?? $selected)) : '' ?>">
        <input type="hidden" name="csrf" value="<?= amanHtml(tokenCsrf()) ?>">
        <input type="hidden" name="aksi" value="<?= $editing ? 'ubah' : 'tambah' ?>">
        <input type="hidden" name="no_polisi_asli" value="<?= amanHtml($form['no_polisi'] ?? '') ?>">
        <div class="ks-input-grid">
          <div class="ks-field"><label for="no_polisi">Nomor polisi *</label>
            <input id="no_polisi" name="no_polisi" required minlength="4" maxlength="15" pattern="[a-zA-Z0-9][a-zA-Z0-9 -]{2,13}[a-zA-Z0-9]" <?= $editing ? 'readonly' : '' ?> value="<?= amanHtml($form['no_polisi'] ?? '') ?>" <?= motorAttrs('no_polisi',$errorsMotor) ?>>
            <?php motorHint('no_polisi','4–15 karakter. Contoh: KH 4321 AA. Nomor polisi tidak dapat diubah setelah dibuat.',$errorsMotor); ?>
          </div>
          <div class="ks-field"><label for="tipe">Tipe/model *</label>
            <input id="tipe" name="tipe" required maxlength="80" value="<?= amanHtml($form['tipe'] ?? '') ?>" <?= motorAttrs('tipe',$errorsMotor) ?>>
            <?php motorHint('tipe','1–80 karakter. Contoh: Vario 160.',$errorsMotor); ?>
          </div>
          <div class="ks-field"><label for="no_rangka">Nomor rangka *</label>
            <input id="no_rangka" name="no_rangka" required minlength="5" maxlength="30" pattern="[a-zA-Z0-9-]{5,30}" value="<?= amanHtml($form['no_rangka'] ?? '') ?>" <?= motorAttrs('no_rangka',$errorsMotor) ?>>
            <?php motorHint('no_rangka','5–30 huruf/angka/tanda hubung tanpa spasi dan harus unik. Contoh: MH1JFZ110001.',$errorsMotor); ?>
          </div>
          <div class="ks-field"><label for="tahun_produksi">Tahun produksi *</label>
            <input id="tahun_produksi" name="tahun_produksi" type="number" min="1900" max="<?= (int)date('Y') ?>" step="1" required value="<?= amanHtml($form['tahun_produksi'] ?? '') ?>" <?= motorAttrs('tahun_produksi',$errorsMotor) ?>>
            <?php motorHint('tahun_produksi','Tahun 1900 sampai '.date('Y').'. Contoh: 2023.',$errorsMotor); ?>
          </div>
          <div class="ks-field"><label for="no_mesin">Nomor mesin *</label>
            <input id="no_mesin" name="no_mesin" required minlength="5" maxlength="30" pattern="[a-zA-Z0-9-]{5,30}" value="<?= amanHtml($form['no_mesin'] ?? '') ?>" <?= motorAttrs('no_mesin',$errorsMotor) ?>>
            <?php motorHint('no_mesin','5–30 huruf/angka/tanda hubung tanpa spasi dan harus unik. Contoh: ENG-KS-0001.',$errorsMotor); ?>
          </div>
          <div class="ks-field"><label for="warna">Warna *</label>
            <input id="warna" name="warna" required maxlength="30" value="<?= amanHtml($form['warna'] ?? '') ?>" <?= motorAttrs('warna',$errorsMotor) ?>>
            <?php motorHint('warna','1–30 karakter. Contoh: Hitam.',$errorsMotor); ?>
          </div>
          <div class="ks-field"><label for="merek">Merek *</label>
            <input id="merek" name="merek" required maxlength="50" value="<?= amanHtml($form['merek'] ?? '') ?>" <?= motorAttrs('merek',$errorsMotor) ?>>
            <?php motorHint('merek','1–50 karakter. Contoh: Honda.',$errorsMotor); ?>
          </div>
          <div class="ks-field"><label for="kilometer_terakhir">Kilometer terakhir *</label>
            <input id="kilometer_terakhir" name="kilometer_terakhir" type="number" inputmode="numeric" min="0" max="4294967295" step="1" required value="<?= amanHtml($form['kilometer_terakhir'] ?? '0') ?>" <?= motorAttrs('kilometer_terakhir',$errorsMotor) ?>>
            <?php motorHint('kilometer_terakhir','Bilangan bulat minimal 0, tanpa titik/koma. Contoh: 12500.',$errorsMotor); ?>
          </div>
        </div>
        <p class="ks-privacy">Nomor rangka dan nomor mesin wajib unik. Riwayat kepemilikan dicatat terpisah melalui rincian motor.</p>
        <div class="ks-controls">
          <button type="submit"><?= $editing ? 'Simpan perubahan motor' : 'Simpan motor baru' ?></button>
          <?php if ($editing): ?><a class="ks-btn" href="motor.php#daftar-motor">Batal ubah</a><?php endif; ?>
        </div>
      </form>
    </div>
  </details>
</div>
<script src="../assets/js/kalawa_form_m2.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const flash=document.getElementById('flash-motor');
  if(flash){for(const form of document.querySelectorAll('#motor-form,#pemilik-form')){
    for(const ev of ['input','change','invalid']) form.addEventListener(ev,()=>flash.remove(),true);
  }}
  const motorDetails=document.getElementById('form-motor');
  for(const link of document.querySelectorAll('[data-open-motor-form]')) {
    link.addEventListener('click',()=>{ if(motorDetails) motorDetails.open=true; });
  }
  // Jika navigasi menuju form tertutup, buka sebelum fokus/scroll.
  if(location.hash==='#form-motor' && motorDetails) motorDetails.open=true;
  for(const link of document.querySelectorAll('a[href="#form-motor"]')) {
    link.addEventListener('click',()=>{ if(motorDetails) motorDetails.open=true; });
  }
});
</script>
<?php akhirHalamanM2(); ?>
