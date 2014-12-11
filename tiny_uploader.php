<?php

//Title Tiny Uploader
//@author Andrew McRobb
//@resource http://jasonwritescode.blogspot.com/2013/09/youtube-browser-based-uploading-with.html
//@description Uploads youtube videos.


class Tiny_Uploader
{
	/* SET THESE UP... */
	private $client_key = "";    /* MUST BE THE SAME IN GOOGLE DEV CONSOLE */
	private $client_id = "";     /* MUST BE THE SAME IN GOOGLE DEV CONSOLE */
	private $client_secret = ""; /* MUST BE THE SAME IN GOOGLE DEV CONSOLE */
	private $redirect_uri = "http://localhost/test.php";  /* MUST BE THE SAME IN GOOGLE DEV CONSOLE */
	private $access_type = "online";   /* 'offline' OR 'online' */
	private $scope = "https://www.googleapis.com/auth/youtube https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtubepartner";
	private $use_sessions = TRUE; /* STORE ACCESS TOKEN IN SESSION? */
	private $session_prefix = "ty_";

	private $ch = NULL; /* Curl Handler */
	public  $last_http_response = 0;
	public  $last_result = 0;
	public  $cur_error = "";
	private $token_type = "Bearer";
	private $access_token = FALSE;
	private $refresh_token = "";
	private $token_expires = 0;
	

	function __construct()
	{
		//Check if cURL is installed!
		if(!$this->_is_curl_installed())
		{
			throw new Exception('<a href="http://curl.haxx.se/">cURL</a> is not installed! <a href="http://php.net/manual/en/curl.installation.php">Install it!</a>');
		}
		
		//Init sessions
		if(!session_id() && $this->use_sessions)
		{
			session_start();
		}
		
		if($this->use_sessions)
		{
			if(isset($_SESSION[$this->session_prefix.'youtube_auth']))
			{
				$this->access_token = $_SESSION[$this->session_prefix.'youtube_auth']['access_token'];
				$this->refresh_token = $_SESSION[$this->session_prefix.'youtube_auth']['refresh_token'];
				$this->token_type = $_SESSION[$this->session_prefix.'youtube_auth']['token_type'];
			}
		}
		
		$this->curl_setup();
	}
	
	function __destruct()
	{
		$this->flush_cache();
	}
	
	
	//Returns user's access token if code is empty, the current access token is returned.
	public function get_access_token()
	{
		if($this->use_sessions)
		{
			if(isset($_SESSION[$this->session_prefix.'youtube_auth']['access_token']))
			{
				return $_SESSION[$this->session_prefix.'youtube_auth']['access_token'];
			}
		}
		
		return $this->access_token;
	}
	
	public function set_scope($scope)
	{
		if(!is_string($scope))
		{
			throw new Exception("Scope accepts STRINGs only!");
		}
		
		$this->scope = $scope;
	}
	
	//Set user's access token
	public function set_access_token($token)
	{
		$this->access_token = $token;
		
		if($this->use_sessions)
		{
			$_SESSION[$this->session_prefix.'youtube_auth']['access_token'] = $token;
		}
	}
	
	public function set_token_type($type)
	{
		$this->token_type = $type;
		
		if($this->use_sessions)
		{
			$_SESSION[$this->session_prefix.'youtube_auth']['token_type'] = $type;
		}	
	}
	
	public function get_token_type()
	{
		if($this->use_sessions)
		{
			if(isset($_SESSION[$this->session_prefix.'youtube_auth']['token_type']))
			{
				return $_SESSION[$this->session_prefix.'youtube_auth']['token_type'];
			}
		}
		
		return $this->token_type;	
	}
	
	//Returns user's refresh token.
	public function get_refresh_token()
	{
	
		if($this->use_sessions)
		{
			if(isset($_SESSION[$this->session_prefix.'youtube_auth']['refresh_token']))
			{
				return $_SESSION[$this->session_prefix.'youtube_auth']['refresh_token'];
			}
		}
		
		return $this->refresh_token;
	}
	
	//Set user's refresh token.
	public function set_refresh_token($token)
	{
		$this->refresh_token = $token;
		
		if($this->use_sessions)
		{
			$_SESSION[$this->session_prefix.'youtube_auth']['refresh_token'] = $token;
		}
	}
	
	public function set_redirect_uri($uri)
	{
		$this->redirect_uri = $uri;
	}
	
	public function get_redirect_uri()
	{
		return $this->redirect_uri;
	}
	
	//get upload url for YouTube video. If redirect_uri is empty base url is added.
	public function get_upload_url($data, $redirect_uri = "")
	{
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(
		'Authorization: '.$this->get_token_type().' '.$this->get_access_token(),
		'GData-VersionL 2',
		'X-GData-Key: key=AIzaSyBFl1iShlg2Y-SRGZZdgakhZ4-08FQtsFE',
		'Content-Type: application/atom+xml; charset-UTF-8'
		));
	
	
	 $xml_str = implode('', array(  
		 '<?xml version="1.0"?>',  
		 '<entry xmlns="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/" xmlns:yt="http://gdata.youtube.com/schemas/2007">',  
		   '<media:group>',  
			 '<media:title type="plain">' . $data['title'] . '</media:title>',  
			 '<media:description type="plain">' . $data['description'] . '</media:description>',  
			 '<media:category scheme="http://gdata.youtube.com/schemas/2007/categories.cat">Animals</media:category>',  
			 '<media:keywords>' . $data['keywords'] . '</media:keywords>',
			 '<yt:accessControl action="list" permission="'.$data['list'].'"/>', //causes the video to be unlisted  
		   '</media:group>',  
		 '</entry>'));
		
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $xml_str);  
		$response = $this->execute_cmd("http://gdata.youtube.com/action/GetUploadToken");
		
		if(empty($redirect_url))
		{
			$redirect_url = $this->_url();
		}
		
		$this->curl_setup();
		
		$result = simplexml_load_string( $response ); 
		
		$returnData = array('url' => $result->url.'?nexturl='.urlencode($redirect_url), 'token' => $result->token);
		
		return $returnData;
	}
	
	//Returns auth url for user...
	public function get_auth_url()
	{
		if(empty($this->scope))
		{
			return FALSE;
		}
	
		$fields['response_type'] = 'code';
		$fields['client_id'] = $this->client_id;
		$fields['scope'] = $this->scope; 
		$fields['redirect_uri'] = $this->redirect_uri;
		$fields['access_type'] =  $this->get_token_type();
		$this->_build_post($fields);
		$response = $this->execute_cmd("https://accounts.google.com/o/oauth2/auth");
		
		$data = curl_getinfo ($this->ch);
		
		$this->curl_setup();
		
		return $data['url'];
	}
	
	
	
	//Already called, ONLY CALL WHEN DOING A NEW CALL.
	public function curl_setup()
	{
		//If already init, flush!
		if($this->ch)
		{
			$this->flush_cache();
		}
		
		$this->ch = curl_init();
		
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, FALSE); 
		curl_setopt($this->ch,CURLOPT_FOLLOWLOCATION,true);
		curl_setopt($this->ch, CURLOPT_HEADER, 0);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
	}
	
	//Reset cURL data.
	public function flush_cache()
	{
		$this->cur_error = "";
		if($this->ch)
		{
			curl_close($this->ch);
			$this->ch = NULL;
		}
	}
	
	protected function execute_cmd($url, $isjson = FALSE)
	{
		curl_setopt($this->ch,CURLOPT_URL, $url);
		$result = curl_exec($this->ch);
		
		$this->last_http_response = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
		$this->last_result = $result;
		
		if($isjson)
		{
			return json_decode($result, TRUE);
		}
		
		return $result;
	}
	
	protected function refresh_access_token()
	{
		if(!$this->refresh_access_token)
		{
			return FALSE;
		}
		
		$fields['client_id'] = $this->client_id;
		$fields['client_secret'] = $this->client_secret;
		$fields['refresh_token'] = $this->get_refresh_token();
		$fields['grant_type'] = 'refresh_token';
		$this->_build_post($fields);
		
		$response = $this->execute_cmd("https://accounts.google.com/o/oauth2/token", TRUE);
		$this->curl_setup();
		
		if($this->_is_error($response))
		{
			return FALSE;
		}
		
		$this->set_access_token($response['access_token']);

		$this->set_token_type($response['token_type']);
		$this->token_expires = $response['token_expires'];
		
		return $this->access_token;
	}
	
	public function request_access_token($code = NULL)
	{
		//Build POST BODY.
		if(isset($_GET['code']))
		{
			$code = $_GET['code'];
		}
		
		$fields['code'] = $code;
		$fields['client_id'] = $this->client_id;
		$fields['client_secret'] = $this->client_secret;
		$fields['redirect_uri'] = $this->redirect_uri;
		$fields['grant_type'] = "authorization_code";
		$this->_build_post($fields);
		
		$response = $this->execute_cmd("https://accounts.google.com/o/oauth2/token", TRUE);
		$this->curl_setup();
		
		if($this->_is_error($response))
		{
			return FALSE;
		}
		
		$this->set_access_token($response['access_token']);
		$this->set_token_type($response['token_type']);
		$this->set_refresh_token($response['refresh_token']);
		$this->token_expires = $response['token_expires'];
		
		return $this->access_token;
	}
	
	private function _url(){
	  return sprintf(
		"%s://%s%s",
		isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
		$_SERVER['SERVER_NAME'],
		$_SERVER['REQUEST_URI']
	  );
	}
	
	
	private function _is_error($data)
	{
		if(!is_array($data))
		{
			throw new Exception('Arrays only accepted for error checking!');
		}
		
		if(isset($data['error']))
		{
			$this->cur_error = $data['error'];
			return TRUE;
		}
		
		return FALSE;
	}
	
	private function _build_post($data)
	{
		$fields_string = "";
		foreach($data as $key=>$value) { $fields_string .= $key.'='.urlencode($value).'&'; }
	
		rtrim($fields_string, '&');
	
		if($this->ch)
		{
			curl_setopt($this->ch,CURLOPT_POST, count($data));
			curl_setopt($this->ch,CURLOPT_POSTFIELDS, $fields_string);
		}
	
		return $fields_string;		
	}
	
	
    private function _is_curl_installed() 
    {
        if  (in_array  ('curl', get_loaded_extensions())) 
        {
            return TRUE;
        }
        
        return FALSE;
    }
}