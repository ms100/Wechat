<?php
//接受微信消息
require './WechatPublic.php';
require './config.php';

$wechat = new WechatPublic($config);

$msg = $wechat->request_msg();
$this->log->debug(var_export($msg, true));

$str = '';
switch($msg->MsgType){
    case 'event':
        switch($msg->Event){
            case 'subscribe':
                $content = "欢迎关注";
                $str = $wechat->response_msg('text', array('content' => $content));
                break;
        }

        break;
    case 'location':
        break;
    case 'text': // 用户发送文本信息
        $str = $wechat->response_msg('text', array('content' => '收到'));
        break;

}

echo $str;


