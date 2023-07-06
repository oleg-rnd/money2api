<?php
class ControllerCommon {
static public $error=null, $result=null, $salt=SALT['user'], $e_global=[
  0=>['en'=>'No action'],
  1=>['en'=>'Invalid token'],
  2=>['en'=>'Account not found'],
  3=>['en'=>'Access deny'],
  4=>['en'=>'Access delayed'],
  5=>['en'=>'Insert fail'],
  6=>['en'=>'Update fail'],
  7=>['en'=>'Extension ID fail'],
  8=>['en'=>'Create private key fail'],
  9=>['en'=>'Create public key fail'],
  10=>['en'=>'Invalid private key'],
  11=>['en'=>'Invalid public key'],
];
static private $anonym=true, $deposit_url='https://www.tinkoff.ru/';

static function e($enum_global, $enum_action, $msg=''){
  self::$error=['msg'=>(self::$e_global[$enum_global]['en']??'').'!'.($msg?' '.$msg:''), 'enum_global'=>$enum_global, 'enum_action'=>$enum_action];
  return !1;
}

static private function private_key_generate($x=0){
  $s='0123456789abcdefghijkmnpqrstuvwxyzACEFGHJKMNPQRTUVWXYZ';
  $k=@$_POST['private_key']?:'';
  if(empty($k))for($i=0;$i<rand(8,12);$i++)$k.=$s[rand(0, strlen($s)-1)];
  elseif(strlen($k)<5||count(Core::q("SELECT rn FROM ".DB_TABLE_USERS." WHERE private_key_b64='".base64_encode($k)."' LIMIT 1")->rows))return !1;
  if(count(Core::q("SELECT rn FROM ".DB_TABLE_USERS." WHERE private_key_b64='".base64_encode($k)."' LIMIT 1")->rows))if($x<20)self::private_key_generate(++$x);else return !1;
  return $k;
}

static private function public_key_generate($x=0){
  $s='0123456789abcdefghijkmnpqrstuvwxyzACEFGHJKMNPQRTUVWXYZ';
  $k='';for($i=0;$i<rand(10,12);$i++)$k.=$s[rand(0, strlen($s)-1)];
  if(count(Core::q("SELECT rn FROM ".DB_TABLE_USERS." WHERE public_key='$k' LIMIT 1")->rows))if($x<20)self::public_key_generate(++$x);else return !1;
  return $k;
}

static private function token_generate($salt=''){
  return md5(USER_IP.'#'.uniqid($salt?:self::$salt.'-', true));
}

static function auth_check(){
  if(!preg_match('/^[\da-z]{32}$/', Core::$token))return self::e(1, 1);
  if(!(Core::$user=@Core::q("SELECT * FROM ".DB_TABLE_USERS." WHERE token='".Core::$token."' AND token_eid_hash='".md5(Core::$eid)."'")->rows[0]))return self::e(2, 2);
  return !0;
}

static function account_create(){
  if(Core::$user)return self::e(3, 1);
  if(!Core::$eid&&Core::$eid!==@$_POST['eid'])return self::e(7, 2);
  $eid_hash=md5(Core::$eid);
  if(Core::q("SELECT COUNT(*) FROM ".DB_TABLE_USERS." WHERE created>'".date('Y-m-d H:i:s', NOW-1800)."' AND created_eid_hash='$eid_hash'".(self::$anonym?"":" AND created_ip='".substr(USER_IP, 0, 32)."'"))->rows[0]['count']>3)return self::e(4, 3);
  if(!($private_key=self::private_key_generate()))return self::e(8, 4);
  if(!($public_key=self::public_key_generate()))return self::e(9, 5);
  self::$error=null;
  $token=self::token_generate($private_key);
  $b=Core::get_browser();
  $settings=['action'=>'settings', 'settings[private_key]'=>$private_key];
  if(!Core::q("INSERT INTO ".DB_TABLE_USERS." VALUES (DEFAULT, 1, 1, '".date('Y-m-d H:i:s', NOW)."', '$eid_hash', '".(self::$anonym?'':USER_IP)."', '".base64_encode($private_key)."', '$public_key', '', '', '', '', '".$token."', '".date('Y-m-d H:i:s', NOW)."', '$eid_hash', '".(self::$anonym?'':USER_IP)."', ".(self::$anonym?"''":Core::$db->quote(@$b->platform.'/'.@$b->browser.'/'.@$b->version)).", ".NOW.", 0, 0, 0, ".Core::$db->quote(json_encode($settings)).", '')")->affected_rows)return self::e(5, 6);
  self::$result=['token'=>$token, 'settings'=>$settings, 'properties'=>['public_key'=>$public_key]];
}

static function account_repair(){
  global $M;
  if(!Core::$eid)return self::e(7, 1);
  if(!Core::$eid&&Core::$eid!==@$_POST['eid'])return self::e(7, 2);
  if(empty($_POST['private_key'])||!(Core::$user=@Core::q("SELECT * FROM ".DB_TABLE_USERS." WHERE private_key_b64='".base64_encode($_POST['private_key'])."'")->rows[0])&&!sleep(5))return self::e(2, 3);
  self::$error=null;
  $token=self::token_generate($_POST['private_key']);
  $b=Core::get_browser();
  $eid_hash=md5(Core::$eid);
  $M->settings_all();
  if(!Core::q("UPDATE ".DB_TABLE_USERS." SET token='".$token."', token_created='".date('Y-m-d H:i:s', NOW)."', token_eid_hash='$eid_hash', token_ip='".(self::$anonym?'':USER_IP)."', token_ua=".(self::$anonym?"''":Core::$db->quote(@$b->platform.'/'.@$b->browser.'/'.@$b->version)).", last_tstamp=".NOW." WHERE rn=".(int)@Core::$user['rn'])->affected_rows)return self::e(6, 4);
  self::$result=$M::$result;
  self::$result['token']=$token;
  self::$result['settings']['settings[private_key]']=$_POST['private_key'];
  usleep(5E5);
}

static function account_deposit(){
  if(!Core::$user)return self::e(2, 1);
  self::$result=['url'=>self::$deposit_url];
}

}

class Controller extends ControllerCommon {

function settings(){
  if(!Core::$user)return self::e(2, 1);
  $data=@json_decode(Core::$user['settings'], true);
  if(@$data['settings[private_key]']!=@$_POST['settings[private_key]'])$_POST['settings[private_key]']=$data['settings[private_key]'];
  if(!Core::q("UPDATE ".DB_TABLE_USERS." SET settings=".Core::$db->quote(json_encode($_POST))." WHERE rn=".Core::$user['rn'])->affected_rows)return self::e(6, 2);
  self::$result=&$_POST;
  return !0;
}

function settings_author(){
  if(!Core::$user)return self::e(2, 1);
  if(!Core::q("UPDATE ".DB_TABLE_USERS." SET settings_author=".Core::$db->quote(json_encode($_POST))." WHERE rn=".Core::$user['rn'])->affected_rows)return self::e(6, 2);
  self::$result=&$_POST;
  return !0;
}

function multi(){
  if(!Core::$user)return self::e(2, 1);
  if(!isset($_POST['actions']))return self::e(0, 2);
  $result=$_POST;
  foreach($result['actions'] as $n=>$v) {
    if(method_exists($this, $v['action'])){
      if(($_POST=&$v)&&!$this->{$v['action']}())$result['actions'][$n]=[];
    }else $result['actions'][$n]=[];
  }
  self::$result=$result;
  return !0;
}

function add_like($is_comment=false){
  if(!Core::$user)return self::e(2, 1);
  if(!(@$_POST['count']>0)||!preg_match('/^[\da-f]{8}$/', $_POST['url32']??''))return self::e(6, 2);
  if($_POST['url32']!=hash('crc32b', $_POST['url']??''))return self::e(6, 3);
  if(Core::q("INSERT INTO likes VALUES (DEFAULT, '".$_POST['url32']."', ".Core::$user['rn'].", ".($is_comment?2:1).", ".(int)$_POST['count'].")")->affected_rows!=1 &&
     Core::q("UPDATE likes SET count=count+".(int)$_POST['count']." WHERE url32='".$_POST['url32']."' AND account=".Core::$user['rn']." AND type=".($is_comment?2:1))->affected_rows!=1)return self::e(6, 4);
  self::$result=&$_POST;
  return !0;
}

function remove_like($is_comment=false){
  if(!Core::$user)return self::e(2, 1);
  if(!(@$_POST['count']>0)||!preg_match('/^[\da-f]{8}$/', $_POST['url32']??''))return self::e(6, 2);
  if(!($row=@Core::q("SELECT * FROM likes WHERE url32='".$_POST['url32']."' AND account=".Core::$user['rn']." AND type=".($is_comment?2:1))->rows[0]))return self::e(6, 3);
  if($row['count']-(int)$_POST['count']<1&&Core::q("DELETE FROM likes WHERE id=".$row['id'])->affected_rows!=1)return self::e(6, 4);
  if($row['count']-(int)$_POST['count']>0&&Core::q("UPDATE likes SET count=count-".(int)$_POST['count']." WHERE id=".$row['id'])->affected_rows!=1)return self::e(6, 5);
  if($row['count']-(int)$_POST['count']<0)$_POST['count']=$row['count'];
  self::$result=&$_POST;
  return !0;
}

function add_comment_like(){
  return $this->add_like(true);
}

function remove_comment_like(){
  return $this->remove_like(true);
}

}

$C=new Controller;
if(AUTH_REQUIRED&&(Core::$token||isset($_COOKIE[AUTH_TOKEN_NAME])))$C::auth_check();
if(isset($_POST['action'])&&method_exists($C, $_POST['action']))$C->{$_POST['action']}();
elseif(count($_POST)&&(!isset($_POST['action'])||!method_exists($C, $_POST['action'])))$C::e(0, 0);
