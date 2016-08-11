<?php

/**
 *    微信公众平台消息接口
 *   http://mp.weixin.qq.com/wiki
 *
 * @package    WechatPublic
 * @subpackage Libraries
 * @category    API
 * @link
 */
class WechatPublic{
    protected $_token = '';
    protected $_app_id = '';
    protected $_app_secret = '';
    protected $_aes_key = '';

    protected $cache;
    protected $servers = array();

    protected $_request_msg = null;

    public function __construct($config){
        $this->cache = new Memcached();

        foreach(array('token', 'app_id', 'app_secret', 'aes_key') as $v){
            if(isset($config[$v])){
                $this->{'_' . $v} = $config[$v];
            }
        }

        foreach($config['servers'] as $item){
            $server = array();
            $server[] = $item['hostname'];
            $server[] = $item['port'];
            $server[] = $item['weight'];
            $servers[] = $server;
        }
        $this->cache->addServers($servers);
    }


    protected function _check_signature(){
        // 时间戳
        $timestamp = $_GET['timestamp'];
        // 随机数
        $nonce = $_GET['nonce'];
        // 微信加密签名
        $signature = $_GET['signature'];
        $array = array($this->_token, $timestamp, $nonce);
        sort($array, SORT_STRING);
        $my_signature = sha1(implode($array));

        if($signature != $my_signature){
            throw new Exception ('WechatPublic Class check signature error.', -1);
        }

        if($echostr = $_GET['echostr']){
            log_message('debug', 'WechatPublic Class valid ok');
            exit($echostr);
        }
    }

    public function request_msg(){
        $this->_check_signature();
        if(empty($GLOBALS['HTTP_RAW_POST_DATA'])){
            throw new Exception ('WechatPublic Class get HTTP_RAW_POST_DATA error.', -1);
        }

        $msg = $this->extract($GLOBALS['HTTP_RAW_POST_DATA']);
        if($_GET['encrypt_type'] == 'aes'){
            return $this->_request_msg = $this->decrypt_msg($msg);
        }else{
            return $this->_request_msg = $msg;
        }
    }

    public function get_request_msg(){
        return $this->_request_msg;
    }

    /**
     * 检验消息的真实性，并且获取解密后的明文.
     * <ol>
     *    <li>利用收到的密文生成安全签名，进行签名验证</li>
     *    <li>若验证通过，则提取xml中的加密消息</li>
     *    <li>对消息进行解密</li>
     * </ol>
     * @param $msg
     * @return array 解密后的原文
     */
    protected function decrypt_msg($msg){
        //$msg = $this->extract($GLOBALS['HTTP_RAW_POST_DATA']);
        //if($msg['AppId'] != $this->_app_id)
        //throw new Exception ('WechatPublic Class appid is different.', -1);
        //$msg = (array)simplexml_load_string($post_data, 'SimpleXMLElement', LIBXML_NOCDATA);
        //ToUserName, Encrypt

        $this->_check_msg_signature($msg->Encrypt);

        $result = $this->decrypt($msg->Encrypt);

        return $this->extract($result);
        /*}else{
            throw new Exception ('WechatPublic get encrypt_type is not aes', -1);
        }*/
    }

    /**
     * 对密文进行解密
     * @param string $encrypted 需要解密的密文
     * @return string 解密得到的明文
     * @throws Exception
     */
    protected function decrypt($encrypted){
        $key = base64_decode($this->_aes_key . '=');
        //使用BASE64对需要解密的字符串进行解码
        $ciphertext_dec = base64_decode($encrypted);
        $module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
        $iv = substr($key, 0, 16);
        mcrypt_generic_init($module, $key, $iv);

        //解密
        $decrypted = mdecrypt_generic($module, $ciphertext_dec);
        mcrypt_generic_deinit($module);
        mcrypt_module_close($module);

        //去除补位字符
        $pad = ord(substr($decrypted, -1));
        if($pad < 1 || $pad > 32){
            $pad = 0;
        }
        $result = substr($decrypted, 0, (strlen($decrypted) - $pad));

        //去除16位随机字符串,网络字节序和AppId
        if(strlen($result) < 16)
            throw new Exception ('WechatPublic Class result\'s length is shorter than 16', -1);
        $content = substr($result, 16, strlen($result));
        $len_list = unpack('N', substr($content, 0, 4));
        $xml_len = $len_list[1];
        $xml_content = substr($content, 4, $xml_len);
        $from_appid = substr($content, $xml_len + 4);

        if($from_appid != $this->_app_id)
            throw new Exception ('WechatPublic Class appid is different', -1);

        return $xml_content;
    }

    /**
     * 提取出xml数据包中的加密消息
     * @param string $xmltext 待提取的xml字符串
     * @return string 提取出的加密消息字符串
     */
    protected function extract($xmltext){
        $msg = simplexml_load_string($xmltext, 'SimpleXMLElement', LIBXML_NOCDATA);
        return $msg;
    }

    protected function _check_msg_signature($encrypt_msg){
        $msg_signature = $_GET['msg_signature'];
        $timestamp = $_GET['timestamp'];
        $nonce = $_GET['nonce'];

        $array = array($this->_token, $encrypt_msg, $timestamp, $nonce);
        sort($array, SORT_STRING);
        $my_signature = sha1(implode($array));

        if($my_signature != $msg_signature){
            throw new Exception ('WechatPublic Class check msg signature error', -1);
        }
    }

    public function response_msg($tpl, array $param){
        $msg_tpl = array(
            'text' => array(
                'arg' => array('content'),
                'tpl' => '<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%d</CreateTime>
<MsgType><![CDATA[text]]></MsgType>
<Content><![CDATA[%s]]></Content>
</xml>',
            ),

        );
        $new_param = array();
        foreach($msg_tpl[$tpl]['arg'] as $v){
            if(isset($param[$v])){
                $new_param[] = $param[$v];
            }else{
                throw new Exception ('WechatPublic Class response msg is missing parameter:' . $v, -1);
            }
        }

        array_unshift($new_param, $this->_request_msg->FromUserName, $this->_request_msg->ToUserName, time());
        $msg = vsprintf($msg_tpl[$tpl]['tpl'], $new_param);
        if($_GET['encrypt_type'] == 'aes'){
            $msg = $this->encrypt_msg($msg);
        }

        return $msg;
    }


    /**
     * 将公众平台回复用户的消息加密打包.
     * <ol>
     *    <li>对要发送的消息进行AES-CBC加密</li>
     *    <li>生成安全签名</li>
     *    <li>将消息密文和安全签名打包成xml格式</li>
     * </ol>
     *
     * @param $reply_msg string 公众平台待回复用户的消息，xml格式的字符串
     * @param $timestamp string 时间戳，可以自己生成，也可以用URL参数的timestamp
     * @param $nonce string 随机串，可以自己生成，也可以用URL参数的nonce
     *
     * @return string 加密后的可以直接回复用户的密文，包括msg_signature, timestamp, nonce, encrypt的xml格式的字符串
     */
    public function encrypt_msg($reply_msg){
        //加密
        $encrypt = $this->encrypt($reply_msg);

        $timestamp = isset($_GET['timestamp']) ? $_GET['timestamp'] : time();
        $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : mt_rand(100000000, 9999999999);

        $signature = $this->_get_sha1($timestamp, $nonce, $encrypt);

        //生成发送的xml
        return $this->generate($encrypt, $signature, $timestamp, $nonce);
    }

    /**
     * 对明文进行加密
     * @param string $text 需要加密的明文
     * @return string 加密后的密文
     */
    public function encrypt($text){
        //获得16位随机字符串，填充到明文之前
        $random = $this->_get_random_str();
        $text = $random . pack('N', strlen($text)) . $text . $this->_app_id;
        // 网络字节序
        $size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
        $module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
        $key = base64_decode($this->_aes_key . '=');
        $iv = substr($key, 0, 16);


        //使用自定义的填充方式对明文进行补位填充
        $text_length = strlen($text);
        //计算需要填充的位数
        $amount_to_pad = 32 - ($text_length % 32);
        if($amount_to_pad == 0){
            $amount_to_pad = 32;
        }
        //获得补位所用的字符
        $pad_chr = chr($amount_to_pad);
        $tmp = '';
        for($index = 0; $index < $amount_to_pad; $index++){
            $tmp .= $pad_chr;
        }
        $text .= $tmp;

        mcrypt_generic_init($module, $key, $iv);
        //加密
        $encrypted = mcrypt_generic($module, $text);
        mcrypt_generic_deinit($module);
        mcrypt_module_close($module);

        //使用BASE64对加密后的字符串进行编码
        return base64_encode($encrypted);
    }

    /**
     * 随机生成16位字符串
     * @return string 生成的字符串
     */
    protected function _get_random_str(){

        $str = '';
        $str_pol = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
        $max = strlen($str_pol) - 1;
        for($i = 0; $i < 16; $i++){
            $str .= $str_pol[mt_rand(0, $max)];
        }

        return $str;
    }

    /**
     * 用SHA1算法生成安全签名
     * @param string $token 票据
     * @param string $timestamp 时间戳
     * @param string $nonce 随机字符串
     * @param string $encrypt 密文消息
     */
    protected function _get_sha1($timestamp, $nonce, $encrypt){
        //排序
        $array = array($encrypt, $this->_token, $timestamp, $nonce);
        sort($array, SORT_STRING);

        return sha1(implode($array));
    }


    /**
     * 生成xml消息
     * @param string $encrypt 加密后的消息密文
     * @param string $signature 安全签名
     * @param string $timestamp 时间戳
     * @param string $nonce 随机字符串
     */
    public function generate($encrypt, $signature, $timestamp, $nonce){
        $format = '<xml>
<Encrypt><![CDATA[%s]]></Encrypt>
<MsgSignature><![CDATA[%s]]></MsgSignature>
<TimeStamp>%s</TimeStamp>
<Nonce><![CDATA[%s]]></Nonce>
</xml>';

        return sprintf($format, $encrypt, $signature, $timestamp, $nonce);
    }

    public function get_config(){
        return array(
            'token' => $this->_token,
            'app_id' => $this->_app_id,
            'app_secret' => $this->_app_secret,
        );
    }

    protected function get_access_token_key(){
        return sprintf('WECHAT_PUBLIC_ACCESS_TOKEN_app_id:%s', $this->_app_id);
    }

    protected function get_access_token(){
        $key = $this->get_access_token_key();

        return $this->cache->get($key);
    }

    protected function save_access_token($access_token, $time){
        $key = $this->get_access_token_key();

        return $this->cache->set($key, $access_token, $time);
    }

    public function delete_access_token(){
        $this->cache->delete($this->get_access_token_key());

        return true;
    }

    function access_token(){
        $access_token = $this->get_access_token();
        if(!$access_token){
            $url = sprintf('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s', $this->_app_id, $this->_app_secret);
            $options = array(
                'http' => array(
                    'method' => 'GET',
                    'timeout' => 60,
                )
            );

            $response = $this->call_api($url, $options);
            $access_token = $response['access_token'];
            $this->save_access_token($access_token, $response['expires_in'] - 200);
        }

        log_message('debug', sprintf('app_id: %s \'s access_token: %s', $this->_app_id, $access_token));

        return $access_token;
    }


    public function ticket($scene_id){
        $url = sprintf('https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=%s', $this->access_token());
        $post_data = array(
            'expire_seconds' => 1800,
            'action_name' => 'QR_SCENE',
            'action_info' => array('scene' => array('scene_id' => $scene_id))
        );
        $options = array(
            'http' => array(
                'method' => 'POST',
                'timeout' => 60,
                'content' => json_encode($post_data),
                'header' => 'Content-type: application/x-www-form-urlencoded\r\n',
            )
        );

        $response = $this->call_api($url, $options);

        return $response['ticket'];
    }

    public function ticket_forever($scene_id){
        $url = sprintf('https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=%s', $this->access_token());
        $post_data = array(
            'action_name' => 'QR_LIMIT_SCENE',
            'action_info' => array('scene' => array('scene_id' => $scene_id))
        );
        $options = array(
            'http' => array(
                'method' => 'POST',
                'timeout' => 60,
                'content' => json_encode($post_data),
                'header' => 'Content-type: application/x-www-form-urlencoded\r\n',
            )
        );

        $response = $this->call_api($url, $options);

        return $response['ticket'];
    }

    public function create_menu($menu){
        $url = sprintf('https://api.weixin.qq.com/cgi-bin/menu/create?access_token=%s', $this->access_token());
        $options = array(
            'http' => array(
                'method' => 'POST',
                'timeout' => 60,
                'content' => json_encode($menu, JSON_UNESCAPED_UNICODE),
                'header' => 'Content-type: application/x-www-form-urlencoded\r\n',
            )
        );

        $response = $this->call_api($url, $options);

        return $response['errcode'];
    }

    function sendall($news){
        $url = sprintf('https://api.weixin.qq.com/cgi-bin/message/mass/sendall?access_token=%s', $this->access_token());
        $options = array(
            'http' => array(
                'method' => 'POST',
                'timeout' => 60,
                'content' => json_encode($news, JSON_UNESCAPED_UNICODE),
                'header' => 'Content-type: application/x-www-form-urlencoded\r\n',
            )
        );

        $response = $this->call_api($url, $options);

        return $response['errcode'];
    }

    /*public function get_group() {
        $url = sprintf('https://api.weixin.qq.com/cgi-bin/groups/get?access_token=%s', $this->access_token());
        $options = array(
            'http'=>array(
                'method'  => 'GET',
                'timeout' => 60,
                //'content' => json_encode($group, JSON_UNESCAPED_UNICODE),
                //'header'  => 'Content-type: application/x-www-form-urlencoded\r\n',
            )
        );

        $response = $this->call_api($url, $options);
        return $response['groups'];
    }*/

    public function delete_menu(){
        $url = sprintf('https://api.weixin.qq.com/cgi-bin/menu/delete?access_token=%s', $this->access_token());
        $options = array(
            'http' => array(
                'method' => 'GET',
                'timeout' => 60,
                // 'content' => json_encode($menu, JSON_UNESCAPED_UNICODE),
                //'header'  => 'Content-type: application/x-www-form-urlencoded\r\n',
            )
        );

        $response = $this->call_api($url, $options);

        return $response['errcode'];
    }

    public function js_ticket(){
        $url = sprintf('https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=%s&type=jsapi', $this->access_token());
        $options = array(
            'http' => array(
                'method' => 'GET',
                'timeout' => 60,
            )
        );

        $response = $this->call_api($url, $options);

        return $response['ticket'];
    }


    public function get_groups(){
        $url = sprintf('https://api.weixin.qq.com/cgi-bin/groups/get?access_token=%s', $this->access_token());
        $options = array(
            'http' => array(
                'method' => 'GET',
                'timeout' => 60,
            )
        );

        $response = $this->call_api($url, $options);

        return $response['groups'];
    }

    protected function call_api($url, $options){
        $context = stream_context_create($options);
        $content = file_get_contents($url, false, $context);
        log_message('debug', $content);
        $response = json_decode($content, true);
        if(isset($response['errcode']) && $response['errcode']){
            in_array($response['errcode'], array(40001, 40014, 42001)) && $this->delete_access_token();

            throw new Exception($response['errmsg'], $response['errcode']);
        }

        return $response;
    }

    function get_media_url($media_id){
        return sprintf('http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=%s&media_id=%s', $this->access_token(), $media_id);
    }
}
