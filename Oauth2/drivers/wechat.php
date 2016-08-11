<?php

class oauth2_wechat extends oauth2_base{

    protected $app_id = '';
    protected $app_secret = '';
    //protected $response_type = '';
    protected $scope = '';
    protected $redirect_uri = '';

    public function __construct($config){
        parent::__construct($config);
    }

    public function authorize_url($state){
        switch($this->scope){
            case 'snsapi_login':
                $url = 'https://open.weixin.qq.com/connect/qrconnect?appid=%s&redirect_uri=%s&response_type=code&scope=%s&state=%s#wechat_redirect';
                break;
            case 'snsapi_userinfo':
            case 'snsapi_base':
                $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=%s&redirect_uri=%s&response_type=code&scope=%s&state=%s#wechat_redirect';
                break;
            default:
                return '';
        }
        return sprintf($url, $this->app_id, urlencode($this->redirect_uri), $this->scope, $state);
    }


    public function access_token($code){
        $url = sprintf('https://api.weixin.qq.com/sns/oauth2/access_token?appid=%s&secret=%s&code=%s&grant_type=authorization_code', $this->app_id, $this->app_secret, $code);
        $response = $this->curl($url);

        return $response;
    }


    public function refresh_token($refresh_token){
        $url = sprintf('https://api.weixin.qq.com/sns/oauth2/refresh_token?appid=%s&grant_type=refresh_token&refresh_token=%s', $this->app_id, $refresh_token);
        $response = $this->curl($url);

        return $response;
    }


    public function auth($access_token, $openid){
        $url = sprintf('https://api.weixin.qq.com/sns/auth?access_token=%s&openid=%s', $access_token, $openid);
        $response = $this->curl($url);

        return $response;
    }


    public function userinfo($access_token, $openid){
        $url = sprintf('https://api.weixin.qq.com/sns/userinfo?access_token=%s&openid=%s', $access_token, $openid);
        $response = $this->curl($url);

        return $response;
    }
}