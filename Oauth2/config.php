<?php
$config['wechat_web'] = array(
    'app_id' => 'wx0565ec6959199568',
    'app_secret' => '9060386f1de869d66ea181a8c347ffa0',
    'scope' => 'snsapi_login',
    'redirect_uri' => sprintf('http://www.dev.cheng95.com/social/oauth?type=wechat'),
);

$config['wechat_app'] = array(
    'app_id' => 'wx021d8089dd0afa57',
    'app_secret' => 'f3cd697e88b7405c51e8f76cf3cd1dc2',
    'scope' => 'snsapi_userinfo',
    'redirect_uri' => sprintf('http://www.dev.cheng95.com/social/oauth?type=wechat'),
);

//伟哥
$config['wechat_wap'] = array(
    'app_id' => 'wx7f016166d06bde75',
    'app_secret' => 'd9b67e0934831a554fe842123da33633',
    'scope' => 'snsapi_userinfo',
    'redirect_uri' => sprintf('http://www.dev.cheng95.com/social/oauth?type=wechat'),
);