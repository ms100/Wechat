<?php
$config['wechat_web'] = array(
    'app_id' => 'wx0565ec',
    'app_secret' => '9060386f1de8690',
    'scope' => 'snsapi_login',
    'redirect_uri' => sprintf('http://www.dev.com/social/oauth?type=wechat'),
);

$config['wechat_app'] = array(
    'app_id' => 'wx021d8089a57',
    'app_secret' => 'f3cd697e88b776cf3cd1dc2',
    'scope' => 'snsapi_userinfo',
    'redirect_uri' => sprintf('http://www.dev.com/social/oauth?type=wechat'),
);

//伟哥
$config['wechat_wap'] = array(
    'app_id' => 'wx7f01616de75',
    'app_secret' => 'd9b67e0934823da33633',
    'scope' => 'snsapi_userinfo',
    'redirect_uri' => sprintf('http://www.dev.com/social/oauth?type=wechat'),
);