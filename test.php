<?php
	require_once("tiny_uploader.php");

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
 
 	 //Did the user come back from the auth page on Google?
	 if(isset($_GET['code']))
	 {
		$client->request_access_token();
	 }
 
     //If everything seems good generate upload token and URL.
	 $data['title'] = "TEST";
	 $data['description'] = "TEST";
	 $data['keywords'] = "TEST,LOL,WOW";
	 $data['list'] = "denied";
	 $response = $client->get_upload_url($data);
 
	 //After the video uploads and user gets redirected. We let them know the video uploaded.
	 if(isset($_GET['status']) && isset($_GET['id']))
	 {
		echo '<p>Cool! Your video has uploaded! <a href="https://www.youtube.com/watch?v='.$_GET['id'].'">Go watch it!</a></p>';
	 }
 
 	//Display form.
	echo '
	<form method="post" action="'.$response['url'].'" enctype="multipart/form-data">  
	   <input name="token" type="hidden" value="'.$response['token'].'"/>   
	   <input type=\'file\' id=\'file\' name=\'file\' accept="video/*" />
	   <input type="submit" value="Upload">  
	 </form>';
?>