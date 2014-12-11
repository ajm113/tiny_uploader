<?php
	require_once("tiny_uploader/tiny_uploader.php");

	$client = new tiny_uploader();

if(!isset($_GET['code']))
{
	if(!$client->get_access_token())
	{
		$url = $client->get_auth_url();
		if($client->last_http_response == 200)
		{
			header('Location: '.$url);
		}
	}
 }
 
 
 if(isset($_GET['code']))
 {
	$client->request_access_token();
 }
 
 $data['title'] = "TEST";
 $data['description'] = "TEST";
 $data['keywords'] = "TEST,LOL,WOW";
 $data['list'] = "denied";
 $response = $client->get_upload_url($data);
 
 if(isset($_GET['status']) && isset($_GET['id']))
 {
 	echo '<p>Cool! Your video has uploaded! <a href="https://www.youtube.com/watch?v='.$_GET['id'].'">Go watch it!</a></p>';
 }
 
echo '
<form method="post" action="'.$response['url'].'" enctype="multipart/form-data">  
   <input name="token" type="hidden" value="'.$response['token'].'"/>   
   <input type=\'file\' id=\'file\' name=\'file\' accept="video/*" />
   <input type="submit" value="Upload">  
 </form>';
 
 print("<br/>Done... <a href=\"/google.php\">Re-run?</a>");
?>