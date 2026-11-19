<?php
declare(strict_types=1);
final class KsHttp {
 public array $cookies=[];public array $last=[];
 public function __construct(public string $base,public string $label){}
 public function resolve(string $path,string $from=''):string {
  $path=html_entity_decode($path,ENT_QUOTES|ENT_HTML5,'UTF-8');$path=explode('#',$path,2)[0];
  if(str_starts_with($path,'http://')){ksAssert(str_starts_with($path,$this->base),'URL keluar dari server uji ditolak');return $this->normalize($path);}
  ksAssert(!str_contains($path,'://')&&!str_starts_with($path,'//'),'URL eksternal ditolak');
  if($path==='')return $from?:$this->base.'login.php';
  if(str_starts_with($path,'/')){$p=parse_url($this->base);return $this->normalize('http://'.$p['host'].':'.$p['port'].$path);}
  if($from!=='')return $this->normalize(substr($from,0,strrpos(explode('?',$from,2)[0],'/')+1).$path);
  return $this->normalize($this->base.$path);
 }
 private function normalize(string $url):string{$p=parse_url($url);$parts=[];foreach(explode('/',$p['path']??'/') as $s){if($s===''||$s==='.')continue;if($s==='..')array_pop($parts);else $parts[]=$s;}return 'http://'.$p['host'].':'.$p['port'].'/'.implode('/',$parts).(isset($p['query'])?'?'.$p['query']:'');}
 public function request(string $method,string $path,array $data=[],bool $follow=true,int $hops=0):array {
  $url=$this->resolve($path);ksAssert(str_starts_with($url,$this->base),'Permintaan di luar aplikasi salinan ditolak');
  $headers=['Accept: text/html','Connection: close'];if($this->cookies)$headers[]='Cookie: '.implode('; ',array_map(fn($k,$v)=>$k.'='.$v,array_keys($this->cookies),array_values($this->cookies)));
  $body=http_build_query($data);if($method==='POST')$headers[]='Content-Type: application/x-www-form-urlencoded';
  $ctx=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$method==='POST'?$body:'','ignore_errors'=>true,'follow_location'=>0,'timeout'=>10]]);
  $html=@file_get_contents($url,false,$ctx);$response=$http_response_header??[];
  if($html===false)throw new RuntimeException('Server HTTP uji tidak menjawab untuk '.$this->label);
  preg_match('~HTTP/\S+\s+(\d+)~',$response[0]??'',$m);$status=(int)($m[1]??0);$location='';
  foreach($response as $line){if(preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i',$line,$c))$this->cookies[$c[1]]=$c[2];if(stripos($line,'Location:')===0)$location=trim(substr($line,9));}
  $r=['status'=>$status,'body'=>$html,'url'=>$url,'location'=>$location];$this->last=$r;
  if($follow&&in_array($status,[301,302,303,307,308],true)&&$location!==''){ksAssert($hops<6,'Redirect berulang');return $this->request('GET',$this->resolve($location,$url),[],true,$hops+1);}
  return $r;
 }
 public function get(string $path,bool $follow=true):array{return $this->request('GET',$path,[],$follow);}
 public static function attr(string $html,string $key,string $default=''):string {
  if(!preg_match('~\b'.preg_quote($key,'~').'\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27|([^\s>]+))~i',$html,$m))return $default;
  foreach([1,2,3] as $i)if(isset($m[$i])&&$m[$i]!=='')return html_entity_decode($m[$i],ENT_QUOTES|ENT_HTML5,'UTF-8');
  return '';
 }
 public function form(array $response,array $match):array {
  preg_match_all('~<form\b([^>]*)>(.*?)</form>~is',$response['body'],$all,PREG_SET_ORDER);
  foreach($all as $f){$fields=[];preg_match_all('~<input\b[^>]*>~is',$f[2],$inputs);
   foreach($inputs[0] as $input){$name=self::attr($input,'name');$type=strtolower(self::attr($input,'type','text'));if($name===''||in_array($type,['checkbox','radio','submit','button'],true))continue;$fields[$name]=self::attr($input,'value');}
   $ok=true;foreach($match as $k=>$v){if($k==='@field'){if(!array_key_exists($v,$fields))$ok=false;}elseif(($fields[$k]??null)!==$v)$ok=false;}
   if($ok)return ['url'=>$this->resolve(self::attr($f[1],'action'),$response['url']),'fields'=>$fields,'html'=>$f[2]];
  }throw new RuntimeException('Form tidak ditemukan untuk '.$this->label.' ('.implode(',',array_keys($match)).')');
 }
 public function postForm(array $form,array $fields=[],bool $follow=true):array{return $this->request('POST',$form['url'],array_replace($form['fields'],$fields),$follow);}
 public function submit(string $getPath,array $match,array $fields=[],bool $follow=true):array{return $this->postForm($this->form($this->get($getPath),$match),$fields,$follow);}
 public function csrf():string{$r=$this->get('panel/panduan.php');if(preg_match('/name="csrf"\s+value="([a-f0-9]+)"/',$r['body'],$m))return $m[1];throw new RuntimeException('Token CSRF tidak ditemukan');}
 public function login(string $username,string $password):array {
  $f=$this->form($this->get('login.php'),[]);return $this->postForm($f,['username'=>$username,'password'=>$password]);
 }
}
