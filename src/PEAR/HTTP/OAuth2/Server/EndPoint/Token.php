<?php

require_once 'HTTP/OAuth2.php';
require_once 'HTTP/OAuth2/Server/Request.php';
require_once 'HTTP/OAuth2/Server/Response.php';
require_once 'HTTP/OAuth2/Credential/Client.php';
require_once 'HTTP/OAuth2/Credential/User.php';

class HTTP_OAuth2_Server_Token extends HTTP_OAuth2
{

    protected $_store;
    
    function __construct(HTTP_OAuth2_Storage_Abstract $store=null){
        $this->_store = $store;
    }
    
    function checkClient(HTTP_OAuth2_Credential_Client $client)
    {
        return $this->_store->checkClient($client->client_id, $client->client_secret);
    }
    
    function checkUser(HTTP_OAuth2_Credential_User $user)
    {
        return $this->_store->checkUser($user->username, $user->password);
    }
    
    function checkVerifier($client_id, $code)
    {
        $verifier = $this->_store->selectVerifier($code);
		if(!empty($verifier)){
	        $client = $verifier->client;
        	return $client_id == $client->client_id;
		}else{
        	return 0;
		}
        
    }
    
    function getVerifier($code)
    {
        $verifier = $this->_store->selectVerifier($code);
        
        return $verifier;
    }

    function checkAssertion($client_id, $assertion_type, $assertion)
    {
        return 1;
    }
    
    function checkRefreshToken($client_id, $refresh_token)
    {
    }
        
    private function _guessGrantType(HTTP_OAuth2_Server_Request $request){

		// we don't have to guess, after draft 08
        $grant_type = $request->getParameter('grant_type');
		if(empty($grant_type)) $grant_type = HTTP_OAuth2::TOKEN_GRANT_TYPE_NONE;

		return $grant_type;

/*
        $params = $request->getParameters();
        $auth = $request->getAuthenParameters();

        if(!empty($params['code'])) // client_id,client_secret,code,redirect_uri
        {
            return HTTP_OAuth2::TOKEN_GRANT_TYPE_AUTHORIZATIONCODE;
        }
        elseif(!empty($params['assertion_type'])) // client_id,client_secret,assertion_type,assertion
        {
            return HTTP_OAuth2::TOKEN_GRANT_TYPE_ASSERTION;
        }
        elseif(!empty($params['refresh_token'])) // client_id,client_secret,refresh_token
        {
            return HTTP_OAuth2::TOKEN_GRANT_TYPE_REFRESHTOKEN;
        }
        elseif(!empty($params['client_id']) && (!empty($params['username']) || !empty($auth))) //client_id,client_secret,username,password
        {
            return HTTP_OAuth2::TOKEN_GRANT_TYPE_USERBASIC;
        }
        elseif(!empty($params['client_id']) || !empty($auth)) //client_id,client_secret
        {
            return HTTP_OAuth2::TOKEN_GRANT_TYPE_NONE;
        }
        else
        {
            throw new HTTP_OAuth2_Exception('unrecognized client profile');
        }
*/
    }
    
    private function _verifyParameter($grant_type, HTTP_OAuth2_Server_Request $request){
        $params = $request->getParameters();
        $auth = $request->getAuthenParameters();
                
        switch($grant_type)
        {
            case HTTP_OAuth2::TOKEN_GRANT_TYPE_AUTHORIZATIONCODE:
                $client_id = $request->getParameter('client_id');
                if(empty($client_id))
                    throw new HTTP_OAuth2_Exception("client_id missing");
                $client_secret = $request->getParameter('client_secret');
                if(empty($client_secret))
                    throw new HTTP_OAuth2_Exception("client_secret missing");
                if(empty($params['code']))
                {
                    throw new HTTP_OAuth2_Exception(HTTP_OAuth2::ERROR_CODE_BAD_AUTHORIZATIONCODE);
                }
                if(empty($params['redirect_uri']))
                {
                    throw new HTTP_OAuth2_Exception("'redirect_uri' empty");
                }
                break;
            case HTTP_OAuth2::TOKEN_GRANT_TYPE_USERBASIC:
                $client_id = $request->getParameter('client_id');
                if(empty($client_id))
                    throw new HTTP_OAuth2_Exception("client_id missing");
                $client_secret = $request->getParameter('client_secret');
                if(empty($client_secret))
                    throw new HTTP_OAuth2_Exception("client_secret missing");
                if((empty($params['username']) || empty($params['password'])) && empty($auth)) //XXX
                {
                	throw new HTTP_OAuth2_Exception(HTTP_OAuth2::ERROR_CODE_INVALID_USERCREDENTIAL);
                }
                break;
            case HTTP_OAuth2::TOKEN_GRANT_TYPE_ASSERTION:
                $client_id = $request->getParameter('client_id');
                if(empty($client_id))
                    throw new HTTP_OAuth2_Exception("client_id missing");
                $client_secret = $request->getParameter('client_secret');
                if(empty($client_secret))
                    throw new HTTP_OAuth2_Exception("client_secret missing");
                if(empty($params['assertion_type']) || empty($params['assertion']))
                {
                    throw new HTTP_OAuth2_Exception(HTTP_OAuth2::ERROR_CODE_INVALID_ASSERTION);
                }
                break;
            case HTTP_OAuth2::TOKEN_GRANT_TYPE_REFRESHTOKEN:
                $client_id = $request->getParameter('client_id');
                if(empty($client_id))
                    throw new HTTP_OAuth2_Exception("client_id missing");
                $client_secret = $request->getParameter('client_secret');
                if(empty($client_secret))
                    throw new HTTP_OAuth2_Exception("client_secret missing");
                if(empty($params['refresh_token']))
                {
                    throw new HTTP_OAuth2_Exception("'refresh_token' empty");
                }
                break;
            case HTTP_OAuth2::TOKEN_GRANT_TYPE_NONE:
                if((empty($params['client_id']) || empty($params['client_secret'])) && empty($auth)) //XXX
                {
                    throw new HTTP_OAuth2_Exception("invalid client_id/client_secret");
                }
                break;
            default:
                throw new HTTP_OAuth2_Exception('should never come here');
                break;
        }

    }
    
    private function _extractClient(HTTP_OAuth2_Server_Request $request){
		$authen_type = $request->getAuthenScheme();
        $client=null;
        $client_id = $request->getParameter('client_id');
        if(empty($client_id)){
            $auth = $request->getAuthenParameters();
            if($authen_type == HTTP_OAuth2_Server_Request::HTTP_AUTHEN_SCHEME_BASIC){
                $client_id = $auth['username'];
                $client_secret = $auth['password'];
                $client = new HTTP_OAuth2_Credential_Client();
				$client->client_id = $client_id;
				$client->client_secret = $client_secret;
            }
        }else{
            $client_secret = $request->getParameter('client_secret');
            $client = new HTTP_OAuth2_Credential_Client();
			$client->client_id = $client_id;
			$client->client_secret = $client_secret;
        }

        return $client;
    }
    
    private function _process($grant_type, $client, $request){

        $user=null;
        
        if(!$client->checkGrantType($grant_type))
            throw new HTTP_OAuth2_Exception(HTTP_OAuth2::ERROR_CODE_UNAUTHORIZED_CLIENT);

       	$refresh_token = null;
        if($grant_type == HTTP_OAuth2::TOKEN_GRANT_TYPE_AUTHORIZATIONCODE)
        {
            if(!$this->checkVerifier($request->getParameter('client_id'), $request->getParameter('code')))
            {
                throw new HTTP_OAuth2_Exception(HTTP_OAuth2::ERROR_CODE_BAD_AUTHORIZATIONCODE);
            }

            $verifier = $this->getVerifier($request->getParameter('code'));
            $user = $verifier->user;
			$this->_store->createAuthorization($client->client_id,$verifier->user->username);
        }
        elseif($grant_type == HTTP_OAuth2::TOKEN_GRANT_TYPE_USERBASIC)
        {
            $user = new HTTP_OAuth2_Credential_User();
            $user->username = $request->getParameter('username');
            $user->password = $request->getParameter('password');
            if(!$this->checkUser($user))
            {
                throw new HTTP_OAuth2_Exception(HTTP_OAuth2::ERROR_CODE_INVALID_USERCREDENTIAL);
            }
			$this->_store->createAuthorization($client->client_id,$user->username);
        }
        elseif($grant_type == HTTP_OAuth2::TOKEN_GRANT_TYPE_ASSERTION)
        {
            if(!$this->checkAssertion($request->getParameter('assertion_type'), $request->getParameter('coassertionde')))
            {
                throw new HTTP_OAuth2_Exception(HTTP_OAuth2::ERROR_CODE_INVALID_ASSERTION);
            }
//			$this->_store->createAuthorization($client->client_id);
        	$refresh_token=$this->_store->selectRefreshToken($request->getParameter('refresh_token'));
        }
        elseif($grant_type == HTTP_OAuth2::TOKEN_GRANT_TYPE_REFRESHTOKEN)
        {
            throw new HTTP_OAuth2_Exception("to be implemented");
        }
        elseif($grant_type == HTTP_OAuth2::TOKEN_GRANT_TYPE_NONE)
        {
            $user = new HTTP_OAuth2_Credential_User();
            $user->username = '';
            $user->password = '';
            if(!$this->checkClient($client))
            {
                throw new HTTP_OAuth2_Exception("invalid client");
            }
			$this->_store->createAuthorization($client->client_id);
        }
        else
        {
            throw new HTTP_OAuth2_Exception('params error');
        }

        if(is_null($refresh_token))$refresh_token=$this->_store->createRefreshToken($client, $user);
        $access_token=$this->_store->createAccessToken($client, $user);

        $ret = array('access_token'=>$access_token->token,'refresh_token'=>$refresh_token->token);
        $ret['expires_in'] = 3600;
        
        return $ret;

    }

    function handle()
    {

        try{
            $response=new HTTP_OAuth2_Server_Response();

            $request=new HTTP_OAuth2_Server_Request();
            $request->build();
        
            // do not permit other method
            if($request->getMethod() != 'POST')
            {
                throw new HTTP_OAuth2_Exception('method not allowed');
            }

            $grant_type = $this->_guessGrantType($request);
            
            $this->_verifyParameter($grant_type, $request);

            $params = $request->getParameters();

            $client=$this->_extractClient($request);
            
            if(empty($client))
                throw new HTTP_OAuth2_Exception(HTTP_OAuth2::ERROR_CODE_INCORRECT_CLIENT_CREDENTIAL);
            
            if(!$this->checkClient($client))
                throw new HTTP_OAuth2_Exception(HTTP_OAuth2::ERROR_CODE_INCORRECT_CLIENT_CREDENTIAL);

            $client=$this->_store->selectClient($client->client_id);
                
            $ret = $this->_process($grant_type, $client, $request);

            if($grant_type == HTTP_OAuth2::TOKEN_GRANT_TYPE_AUTHORIZATIONCODE)
            {
				if(isset($ret['error'])){
            		$response->setStatus(HTTP_OAuth2_Server_Response::STATUS_MISSING_REQUIRED_PARAMETER);
	                $response->setHeader("Location",$params['redirect_uri']."?access_token=".$ret['access_token']);
                	$response->setHeader("Content-Type",'application/json');
					$response->send();
				}else{
                	$response->setHeader("Content-Type",'application/json');
	                $response->setHeader("Location",$params['redirect_uri']."?access_token=".$ret['access_token']);
					$response->send();
				}
            }
            else
            {
                $response->setHeader("Content-Type",'application/json');
                $response->setParameters($ret);
                $response->send();
            }

        }catch(PEAR_Exception $e){
            $ret = array('error' => $e->getMessage());
            $response->setStatus(HTTP_OAuth2_Server_Response::STATUS_MISSING_REQUIRED_PARAMETER);
            $response->setHeader("Content-Type",'application/json');
            $response->setParameters($ret);
            $response->send();
        }
    }

}