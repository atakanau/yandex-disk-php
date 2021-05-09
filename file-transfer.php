<?php 

$fileUrl = 'https://downloads.wordpress.org/release/tr_TR/wordpress-5.6.3.zip';
$fileName = '5.6.3.zip';
$fileFolder = 'wordpress';	// has to be exist if you set. can be empty or like: php/wordpress
// $overwrite = true;			to do
$myResult=uploadUrlFile($fileUrl,$fileName,$fileFolder);

header('Content-Type: application/json');
echo json_encode($myResult);

function uploadUrlFile($fileUrl,$fileName,$fileFolder=""){
	$details = 1;	// optianal
	$myResult = array();
	$myResult["job"] = array('status' => 'started');

	require_once 'yandex-disk\yandexdisk.php';
	$token = "XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"; // YOUR TOKEN
	$disk = new yandexdisk($token);

	// transfer
	$isUpload = $disk->transferUrl($fileUrl,$fileName,$fileFolder);
	$resUpload = json_decode($isUpload, true);

	if($details)
		$myResult["resUpload"] = $resUpload;

	if( isset($resUpload['created']) && $resUpload['created'] == 'ok') {
		$myResult["job"] = array('status' => 'success');
	}

	return $myResult;
}


?>
