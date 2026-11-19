<?php
declare(strict_types=1);
/** Read-only list pagination. Caller owns authorization, filters, and ordering. */
function ksUiPageMeta(int $total, string $key, int $size=25): array
{
    $raw=$_GET[$key]??'1';
    $page=is_string($raw)&&preg_match('/\A[1-9][0-9]{0,8}\z/D',$raw)?(int)$raw:1;
    $pages=max(1,(int)ceil($total/$size));
    $page=min($pages,$page);
    return $GLOBALS['ks_ui_pages'][$key]=['key'=>$key,'total'=>$total,'size'=>$size,
        'page'=>$page,'pages'=>$pages,'offset'=>($page-1)*$size];
}
function ksUiList(PDO $db, string $sql, array $params, string $key): array
{
    $count=$db->prepare('SELECT COUNT(*) FROM ('.$sql.') AS ks_ui_count');
    $count->execute($params);
    $meta=ksUiPageMeta((int)$count->fetchColumn(),$key);
    $q=$db->prepare($sql.' LIMIT '.$meta['size'].' OFFSET '.$meta['offset']);
    $q->execute($params);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
function ksUiPageUrl(string $key, int $page): string
{
    $query=[];
    foreach($_GET as $k=>$v)if(is_string($k)&&is_string($v))$query[$k]=$v;
    $query[$key]=(string)$page;
    return basename((string)($_SERVER['SCRIPT_NAME']??'')).'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986).'#konten-utama';
}
function ksUiPager(string $key): void
{
    $m=$GLOBALS['ks_ui_pages'][$key]??null;
    if(!$m)return;
    $first=$m['total']?$m['offset']+1:0;
    $last=min($m['offset']+$m['size'],$m['total']);
    echo '<div class="ks-pagination" id="daftar-'.amanHtml($key).'"><p class="ks-page-summary">'.
        $first.'–'.$last.' dari '.number_format($m['total'],0,',','.').' hasil <span>· Halaman '.$m['page'].' dari '.$m['pages'].'</span></p>';
    if($m['pages']>1){
        echo '<nav class="ks-page-links" aria-label="Halaman daftar '.amanHtml(str_replace('_',' ',$key)).'">';
        $link=static function(int $p,string $label,string $aria='')use($key,$m):void{
            echo '<a href="'.amanHtml(ksUiPageUrl($key,$p)).'"'.($p===$m['page']?' aria-current="page"':'').($aria!==''?' aria-label="'.amanHtml($aria).'"':'').'>'.amanHtml($label).'</a>';
        };
        if($m['page']>1)$link($m['page']-1,'‹','Halaman sebelumnya');
        $window=array_unique(array_merge([1,$m['pages']],range(max(1,$m['page']-1),min($m['pages'],$m['page']+1))));
        sort($window);$prev=0;
        foreach($window as $p){if($prev&&$p>$prev+1)echo '<span class="ks-page-gap" aria-hidden="true">…</span>';$link($p,(string)$p,'Halaman '.$p);$prev=$p;}
        if($m['page']<$m['pages'])$link($m['page']+1,'›','Halaman berikutnya');
        echo '</nav><form class="ks-page-jump" method="get" action="'.amanHtml(basename((string)$_SERVER['SCRIPT_NAME'])).'#konten-utama'.'">';
        foreach($_GET as $k=>$v)if(is_string($k)&&is_string($v)&&$k!==$key)echo '<input type="hidden" name="'.amanHtml($k).'" value="'.amanHtml($v).'">';
        echo '<label for="lompat-'.amanHtml($key).'">Ke halaman</label><input id="lompat-'.amanHtml($key).'" name="'.amanHtml($key).'" type="number" inputmode="numeric" min="1" max="'.$m['pages'].'" value="'.$m['page'].'" required><button type="submit" class="light">Buka</button></form>';
    }
    echo '</div>';
}
