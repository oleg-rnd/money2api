<?php
if(!defined('PHP_MAJOR_VERSION')||PHP_MAJOR_VERSION<8||PHP_MAJOR_VERSION==8&&PHP_MINOR_VERSION<1)die('PHP 8.1 or later is needed!');

define('LANG', preg_match('%^/api/(?:en|fr)/%', $_SERVER['REQUEST_URI'])?substr($_SERVER['REQUEST_URI'], 5, 2):'ru');
const ru=LANG=='ru';
const REST_API=true;
const AUTH_REQUIRED=true;
const AUTH_COOKIE_PATH='/';
const AUTH_TOKEN_NAME='token';
const DB_TABLE_USERS='accounts';
const DB_TABLE_SESSIONS='sessions';
const DB_SESSION_KEEP_ALIVE=86400;
const DIR_SYS=__DIR__.'/';
define('NOW', time());
define('USER_IP', strtok($_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['HTTP_CLIENT_IP']??$_SERVER['REMOTE_ADDR'], ','));
define('CFG', json_decode(file_get_contents((file_exists(DIR_SYS.'.ht-config.json')?DIR_SYS:$_SERVER['DOCUMENT_ROOT'].'/').'.ht-config.json')));
define('SALT', ['user'=>dechex(crc32((CFG->secret_key??'S_______________g').'#user'))]);
define('DB_TABLE_USERS_hash', dechex(crc32(DB_TABLE_USERS)));

class Core {
static public $db, $user, $token='', $eid='', $browser=[];

function __construct(){
  if(!@CFG->browser_cache){
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Expires: '.gmdate('D, d M Y H:i:s',NOW-86400).' GMT');
  }
  if(REST_API){
    foreach(apache_request_headers() as $n=>$v)if(($n=strtolower($n))&&$n=='content-type'&&strpos($v, 'application/json')===0){
      if(in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT']))$_POST=json_decode(file_get_contents('php://input'), true);
      elseif($_SERVER['REQUEST_METHOD']=='DELETE')$_POST=$_GET;
    }elseif($n=='authorization'&&strtok($v, ' '))self::$token=rtrim(strtok(' '));
    elseif($n=='origin'&&strtok($v, '//'))self::$eid=rtrim(strtok(' '));
    elseif($n=='sec-ch-ua'&&(self::$browser['browser']=strtok(str_replace('"', '', $v), ';')))self::$browser['version']=preg_replace('/^v=([\d.]+).+$/', '$1', strtok(' '));
    elseif($n=='sec-ch-ua-mobile')self::$browser['mobile']=$v;
    elseif($n=='sec-ch-ua-platform')self::$browser['platform']=str_replace('"', '', $v);
  }
}
function __destruct(){
}
static function query($s, int $repeat_count=0){
  $o=(object)['rows'=>[],'affected_rows'=>null];
  if(strtolower(CFG->db->engine)=='pgsql'){
    if(!self::$db){
      if(!(self::$db=new PDO(strtolower(CFG->db->engine).':dbname='.CFG->db->database.';host='.CFG->db->host.(@CFG->db->port?';port='.CFG->db->port:''), CFG->db->user, CFG->db->pass)))self::halt('DB connection error!');
      $db=&self::$db;
      $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
      $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // ASSOC|NUM|BOTH
    }else $db=&self::$db;
    $r=@$db->query($s);
//    if($r===false)self::halt('DB query error!');
    $o->affected_rows=$r===false?$r:$r->rowCount();
    $o->rows=$r===false?[]:$r->fetchAll();
  }
  return $o;
}
static function q(){
  return call_user_func_array(['Core', 'query'], func_get_args());
}
static function halt($msg='', $code=500):never{
  $e=[400=>'Bad Request', 401=>'Authorization Required', 402=>'Payment Required', 403=>'Forbidden', 404=>'Not Found', 405=>'Method Not Allowed', 408=>'Request Time-Out', 410=>'Gone', 500=>'Internal Server Error', 503=>'Service Temporarily Unavailable'];
  header("HTTP/1.1 $code ".@$e[$code]);
  echo $msg;
  exit;
}
static function redirect($url, $code=302):never{
  if($url)header('Location: '.$url, !0, (int)$code);
  exit;
}
static function site_url(){
  return 'http'.((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!='off')||$_SERVER['SERVER_PORT']==443||stripos($_SERVER['SERVER_PROTOCOL'], 'https')===0?'s':'').'://'.$_SERVER['HTTP_HOST'];
}
static function get_browser(){
  if(!($b=get_browser()))$b=(object)['platform'=>'', 'browser'=>'', 'version'=>''];
  if(empty($b->platform))$b->platform=Core::$browser['platform']??str_replace('"', '', strtok(preg_replace('/^[^(]+\(([^)]+).+$/', '$1', $_SERVER['HTTP_USER_AGENT']), ' '));
  if(empty($b->browser))$b->browser=Core::$browser['browser']??substr($s=rtrim(preg_replace('/\([^)]+\)$/', '', $_SERVER['HTTP_USER_AGENT'])), ($x=strrpos($s, ' '))?$x+1:0);
  if(empty($b->version))$b->version=Core::$browser['version']??'?';
  return $b;
}
static function sendmail($from, $to, $subject, $msg, $is_html=false){
  $h="From: $from\r\nMIME-Version: 1.0\r\nSensitivity: Personal\r\nContent-type: text/".($is_html?'html':'plain')."; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
  $to=preg_replace_callback('/^([^<]+)(<[^>]>)$/', function($m){return '=?UTF-8?B?'.base64_encode(trim($m[1])).'?= '.$m[2];}, $to);
  return mail($to, '=?UTF-8?B?'.base64_encode(trim($subject)).'?=', chunk_split(base64_encode(trim($msg))), $h);
}
}

error_reporting(null);
set_time_limit(30);
//ini_set('browscap', __DIR__.'/php_browscap.ini'); // download latest version from http://browscap.org/
$core=new Core();
require DIR_SYS.'mvc/.ht-model.php';
if(count($_POST))require DIR_SYS.'mvc/.ht-controller.php';
//require DIR_SYS.'mvc/.ht-view.php';
