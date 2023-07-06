<?php

class ModelCommon {
static public $error=null, $result=null, $e_global=[
  1=>['en'=>'Invalid token'],
  2=>['en'=>'User not found'],
  3=>['en'=>'Access deny']
];

static function e($enum_global, $enum_action, $msg=''){
  self::$error=['msg'=>(self::$e_global[$enum_global]['en']??'').'!'.($msg?' '.$msg:''), 'enum_global'=>$enum_global, 'enum_action'=>$enum_action];
  return !1;
}

static function auth_check(){
  if(!preg_match('/^[\da-z]{32}$/', Core::$token))return self::e(1, 1);
  if(!(Core::$user=@Core::q("SELECT * FROM ".DB_TABLE_USERS." WHERE token='".Core::$token."' AND token_eid_hash='".md5(Core::$eid)."'")->rows[0]))return self::e(2, 2);
  return !0;
}

}

class Model extends ModelCommon {

function settings_all(){
  if(!Core::$user&&!($b=self::auth_check()))return $b;
  if(empty(Core::$user['public_key']))return [];
  self::$result=[
    'properties'=>['likes'=>Core::$user['likes'], 'balance'=>Core::$user['balance'], 'balance_not_send'=>Core::$user['balance_not_send'], 'public_key'=>Core::$user['public_key']],
    'settings'=>json_decode(Core::$user['settings'], true),
    'settings_author'=>json_decode(Core::$user['settings_author'], true)
  ];
}

function likes($is_comment=false){
  if(!Core::$user&&!($b=self::auth_check()))return $b;
  if(empty(Core::$user['public_key'])||!preg_match('/^[\da-f,]{8,9000}$/', $_GET['urls32']??''))return [];
  self::$result=['action'=>$_GET['action'], 'urls32'=>[]];
  foreach(Core::q("SELECT * FROM likes WHERE url32 IN ('".str_replace(",", "','", $_GET['urls32'])."') AND type=".($is_comment?2:1))->rows as $row){
    if(!isset(self::$result['urls32'][$row['url32']]))self::$result['urls32'][$row['url32']]=[0, 0];
    self::$result['urls32'][$row['url32']][0]+=$row['count'];
    if($row['account']==Core::$user['rn'])self::$result['urls32'][$row['url32']][1]+=$row['count'];
  }
}

function comment_likes(){
  $this->likes(true);
}

}

$M=new Model;
if(isset($_GET['action'])&&method_exists($M, $_GET['action']))$M->{$_GET['action']}();
