<?php

require_once 'HTTP/OAuth2.php';
require_once 'HTTP/OAuth2/Storage.php';

define('__OAUTH2_TEST_TMP_DIR__','/tmp/oauth2/');

class HTTP_OAuth2_Storage_Mock extends HTTP_OAuth2_Storage{
	function init(){
		if(!is_dir(__OAUTH2_TEST_TMP_DIR__))mkdir(__OAUTH2_TEST_TMP_DIR__);
	}
	function fini(){
        $files = glob(__OAUTH2_TEST_TMP_DIR__."/*");
        foreach($files as $file){
            unlink($file);
        }
		rmdir(__OAUTH2_TEST_TMP_DIR__);
	}
    function selectClient($sKey){
        $client=null;
		if(is_file(__OAUTH2_TEST_TMP_DIR__.'/'.$sKey)){
			$data = file_get_contents(__OAUTH2_TEST_TMP_DIR__.'/'.$sKey);
		}else{
			$data = '';
		}
        if(!empty($data))$client = unserialize($data);
        return $client;
	}
    function selectUser($sKey){
        $user=null;
		if(is_file(__OAUTH2_TEST_TMP_DIR__.'/'.$sKey)){
			$data = file_get_contents(__OAUTH2_TEST_TMP_DIR__.'/'.$sKey);
		}else{
			$data = '';
		}
        if(!empty($data))$user = unserialize($data);
        return $user;
	}
	function checkClient($sKey,$sSecret){
		return 1;
	}
	function checkUser($sKey,$sSecret){
		return 1;
	}
	function selectVerifier($code){
        $verifier=null;
		if(is_file(__OAUTH2_TEST_TMP_DIR__.'/'.$code)){
			$data = file_get_contents(__OAUTH2_TEST_TMP_DIR__.'/'.$code);
		}else{
			$data = '';
		}
        if(!empty($data))$verifier = unserialize($data);
        return $verifier;
	}
	function createVerifier(HTTP_OAuth2_Credential_Client $client, HTTP_OAuth2_Credential_User $user){
        $code = substr(md5($client->client_id.$user->username.microtime(1)),0,8);
        $verifier=new HTTP_OAuth2_Credential_Verifier();
        $verifier->code = $code;
        $verifier->user = $user;
        $verifier->client = $client;
		$tmpfname = __OAUTH2_TEST_TMP_DIR__.$code;
		file_put_contents($tmpfname,serialize($verifier));
		clearstatcache();
        return $verifier;
	}
	function createRefreshToken(HTTP_OAuth2_Credential_Client $client, HTTP_OAuth2_Credential_User $user){
		$token=new HTTP_OAuth2_Credential_RefreshToken();
		$token->token = md5($client->client_id.$user->username.microtime(1));
		$tmpfname = tempnam(__OAUTH2_TEST_TMP_DIR__, "refresh_token_");
		file_put_contents($tmpfname,serialize($token));
		return $token;
	}
	function createAccessToken(HTTP_OAuth2_Credential_Client $client, HTTP_OAuth2_Credential_User $user){
		$token=new HTTP_OAuth2_Credential_AccessToken();
		$token->token = md5($client->client_id.$user->username.microtime(1));
		$token->secret = md5($client->client_id.$user->username.microtime(1).uniqid());
		$tmpfname = tempnam(__OAUTH2_TEST_TMP_DIR__, "access_token_");
		file_put_contents($tmpfname,serialize($token));

		return $token;
	}
	function createClient(HTTP_OAuth2_Credential_Client $client){
        $client->addFlow(HTTP_OAuth2::CLIENT_FLOW_WEBSERVER);
        $client->addFlow(HTTP_OAuth2::CLIENT_FLOW_USERAGENT);
        $client->addFlow(HTTP_OAuth2::CLIENT_FLOW_USERCREDENTIAL);
        $client->addFlow(HTTP_OAuth2::CLIENT_FLOW_CLIENTCREDENTIAL);
        $client->addFlow(HTTP_OAuth2::CLIENT_FLOW_ASSERTION);
        $client->addFlow(HTTP_OAuth2::CLIENT_FLOW_REFRESHTOKEN);
		$tmpfname = __OAUTH2_TEST_TMP_DIR__.$client->client_id;
		file_put_contents($tmpfname,serialize($client));
		clearstatcache();

		return $client;
	}
	function createUser(HTTP_OAuth2_Credential_User $user){
		$tmpfname = __OAUTH2_TEST_TMP_DIR__.$user->username;
		file_put_contents($tmpfname,serialize($user));
		clearstatcache();

		return $user;
	}
}
