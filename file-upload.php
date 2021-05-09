<?php 

$fileName = 'test_file.txt';
$fileFolder = 'sub_folder';	// optianal	 has to be exist if you set.
$overwrite = true;			// optianal
$myResult=uploadLocalFile($fileName,$fileFolder,$overwrite);

header('Content-Type: application/json');
echo json_encode($myResult);

function uploadLocalFile($fileName, $fileFolder="",$overwrite=false){
	$details = 1;	// optianal
	$myResult = array();
	$myResult["job"] = array('status' => 'started');
	if(file_exists($fileName)) {
		require_once 'yandex-disk\yandexdisk.php';
		$token = "XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"; // YOUR TOKEN
		$disk = new yandexdisk($token);

		// get info about disk
		$diskInfo = json_decode($disk->getInfo(), true);
		if($details)
			$myResult["diskInfo"] = $diskInfo;

		// upload
		if ( $diskInfo['total_space'] - $diskInfo['used_space'] > filesize($fileName) ) {
			$isUpload = $disk->uploadFile($fileName,$fileFolder,$overwrite);
			$resUpload = json_decode($isUpload, true);

			if( isset($resUpload['error']) && $resUpload['error'] == 'DiskResourceAlreadyExistsError') {
				$myResult["error"] = "Remote file already exist.";
			}

			if($details)
				$myResult["resUpload"] = $resUpload;

			if( isset($resUpload['created']) && $resUpload['created'] == 'ok') {
				// unlink($fileName);
				$myResult["job"] = array('status' => 'success');
			}
		}
	}
	else
		$myResult["error"] = "Local file not exist.";
	return $myResult;
}

?>
