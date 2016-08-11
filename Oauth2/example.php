<?php
require './Oauth2.php';
require './config.php';

$oauth2 = new Oauth2($config);
try{
    $token = $oauth2->wechat('web')->access_token('aabbcc');
    var_dump($token);
}catch(Exception $e){
    var_dump($e->getMessage(), $e->getCode());
}