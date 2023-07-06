<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, PUT, GET, DELETE');
require '.ht-system/.ht-core.php';
header('Content-Type: application/json; charset=utf-8');
if(isset($C))echo json_encode($C::$error?['result'=>'fail', 'error'=>$C::$error]:['result'=>'success', 'data'=>$C::$result?$C::$result:[]]);
elseif(isset($M))echo json_encode($M::$error?['result'=>'fail', 'error'=>$M::$error]:['result'=>'success', 'data'=>$M::$result?$M::$result:[]]);
else '{}';
