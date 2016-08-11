<?php

class Oauth2{
    private $_provider = array();
    private $config = array();

    public function __construct($config){
        $this->config = $config;
    }

    private function load($provider, $config_item){
        if(!isset($this->config[$provider . '_' . $config_item])){
            throw new Exception(sprintf('Unable to load the oauth2 config:%s', $provider . '_' . $config_item), -1);
        }

        $class = 'oauth2_' . $provider;

        if(!isset($this->_provider[$class])){
            if(file_exists($file = __DIR__ . '/drivers/' . $provider . '.php')){
                require($file);
            }

            if(!class_exists($class, false)){
                throw new Exception(sprintf('Unable to locate the oauth2 class:%s you have specified', $class), -1);
            }

            $this->_provider[$class] = new $class($this->config[$provider . '_' . $config_item]);
        }else{
            $this->_provider[$class]->load_config($this->config[$provider . '_' . $config_item]);
        }

        return $this->_provider[$class];
    }

    public function __call($func, $args){
        return $this->load($func, $args[0]);
    }
}


class oauth2_base{
    private $CURL;

    public function __construct($config = array()){
        if(!empty($config)){
            $this->load_config($config);
        }
    }

    protected function load_config($config){
        foreach($config as $key => $value){
            isset($this->{$key}) && $this->{$key} = $value;
        }
    }

    public function curl($url, $param = array()){
        if(!isset($this->CURL)){
            require dirname(__DIR__) . '/Curl.php';
            $this->CURL = new Curl();
        }

        $mothed = empty($param) ? 'GET' : 'POST';
        $content = $this->CURL->request($mothed, $url, $param);

        if(empty($content) || $content->headers['Status-Code'] != 200){
            $err_no = empty($content) ? $this->CURL->error()['err_no'] : $content->headers['Status-Code'];
            $err_msg = empty($content) ? $this->CURL->error()['err_msg'] : $content->headers['Status'];

            throw new Exception($err_msg, $err_no);
        }
        $response = json_decode($content->body, true);


        if(isset($response['errcode']) && $response['errcode']){
            throw new Exception($response['errmsg'], $response['errcode']);
        }

        return $response;
    }
}