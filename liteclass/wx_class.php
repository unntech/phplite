<?php
/*
	[UENEN TECHNOLOGIES] Copyright (c) 2021 unn.tech
	This is a freeware, use is subject to license.txt
*/

namespace LiteClass ;

class wx_class {
    protected $db, $DT_TIME;
    public $encodingAesKey = "";
    public $token = "";
    public $appId = "";
    public $appsecret = "";
    public $GID = '';
    public $ACCESS_TOKEN = '';

    public function __construct() {
        global $db, $DT_TIME;
        $this->db = $db;
        $this->DT_TIME = $DT_TIME;
    }

    public function __destruct() {

    }

    public function do_get( $url ) {
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
        $strRes = curl_exec( $ch );
        curl_close( $ch );
        return $strRes;
    }

    public function do_post( $url, $data ) {
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
        $strRes = curl_exec( $ch );
        curl_close( $ch );
        return $strRes;
    }
    
    public function getAccessToken($getnew=false){
        if($getnew == false && $this->ACCESS_TOKEN != ''){
            return $this->ACCESS_TOKEN;
        }
        $SQLC = "SELECT * FROM `wx_cache` WHERE appid ='".$this->appId."' and type = 'access_token'";
        $row = $this->db -> get_one($SQLC);
        if(!$row){
            $sqlc = "INSERT INTO `wx_cache` (`id`, `appid`, `type`, `value`, `expires_in`) VALUES (NULL, '".$this->appId."', 'access_token', '', '0')";
            $re1 = $this->db->query($sqlc);
            $row['expires_in'] = 0;
            $getnew = true;
        }
        if(($row['expires_in'] > $this->DT_TIME) && ($getnew == false)){
            $this->ACCESS_TOKEN = $row['value'];
            return $row['value'];
        }else{
            $access_token_url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->appId.'&secret='.$this->appsecret;
            $res = $this->do_get($access_token_url);
            $arrResponse=json_decode($res,true);
            $ext = intval( $arrResponse['expires_in'] ) + $this->DT_TIME - 500;
            $SQLC = "UPDATE `wx_cache` SET value ='".$arrResponse['access_token']."', expires_in =".$ext." WHERE appid ='".$this->appId."' and type = 'access_token'";
	        $re0 = $this->db -> query ($SQLC);
            $this->ACCESS_TOKEN = $arrResponse['access_token'];
            return $arrResponse['access_token'];
        }
    }
    
    public function getSignPackage() {
    $jsapiTicket = $this->getJsApiTicket();

    // ?????? URL ?????????????????????????????? hardcode.
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    $timestamp = time();
    $nonceStr = $this->createNonceStr();

    // ?????????????????????????????? key ??? ASCII ???????????????
    $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

    $signature = sha1($string);

    $signPackage = array(
      "appId"     => $this->appId,
      "nonceStr"  => $nonceStr,
      "timestamp" => $timestamp,
      "url"       => $url,
      "signature" => $signature,
      "rawString" => $string
    );
    return $signPackage; 
  }

  private function createNonceStr($length = 16) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $str = "";
    for ($i = 0; $i < $length; $i++) {
      $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
  }

  public function getJsApiTicket() {
    // jsapi_ticket ????????????????????????????????????????????????????????????????????????
	$db = $this->db;
	$sqlc = "SELECT * FROM `wx_cache` WHERE appid ='".$this->appId."' and type = 'jsapi_ticket'";
	$tcas = $db->get_one($sqlc);
    if(!$tcas){
            $sqlc = "INSERT INTO `wx_cache` (`id`, `appid`, `type`, `value`, `expires_in`) VALUES (NULL, '".$this->appId."', 'jsapi_ticket', '', '0')";
            $re1 = $this->db->query($sqlc);
            $tcas['expires_in'] = 0;
            $getnew = true;
    }
    //$data = json_decode(file_get_contents(dirname(__FILE__)."/jsapi_ticket.json"));
    if ($tcas['expires_in'] < time()) {
      $accessToken = $this->getAccessToken();
      // ??????????????????????????? URL ?????? ticket
      // $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
      $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
      $res = json_decode($this->httpGet($url));
      $ticket = $res->ticket;
      if ($ticket) {
		  /*
        $data->expire_time = time() + 7000;
        $data->jsapi_ticket = $ticket;
        $fp = fopen(dirname(__FILE__)."/jsapi_ticket.json", "w");
        fwrite($fp, json_encode($data));
        fclose($fp);
		*/
		  $Expires_New = time() + $res -> expires_in - 500 ;
		  $SQLC = "UPDATE `wx_cache` SET value ='".$ticket."', expires_in =".$Expires_New." WHERE appid ='".$this->appId."' and type = 'jsapi_ticket'";
		  $db->query($SQLC);
		  
      }
    } else {
      $ticket = $tcas['value'];
    }

    return $ticket;
  }


  private function httpGet($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 500);
    // ??????????????????????????????????????????????????????????????????????????????????????????????????????https?????????????????????????????????2???????????????ssl???????????????
    // ???????????????????????????????????????????????????????????? http://curl.haxx.se/ca/cacert.pem ?????????????????????????????????
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_URL, $url);

    $res = curl_exec($curl);
    curl_close($curl);

    return $res;
  }
}