<?php
/* 
 Upload or transfer files to Yandex Disk
 
 v.01 beta
 
 to do:
 * return values
 * error catching
 * overwrite option on file transfer
 * auto create sub folder option
 
 author: atakanau
 blog: https://atakanau.blogspot.com
 
 */
class yandexdisk{

	public function __construct($access_token){
		$this->access_token = $access_token;
		$this->upload_file="";
		$this->upload_link="";
		$this->upload_path="";
		$this->remove_file="";
	}
	public function getInfo(){
		return $this->request("info");
	}
	public function uploadFile($file, $path="",$overwrite=false){
		if (is_file($file)){
			$this->upload_path = $path;
			$this->upload_file =  $file;
			$this->overwrite =  $overwrite;
			$res = $this->request("get_upload_link");
			$link = json_decode($res, true);
			if (isset($link['href'])){
				$this->upload_link = $link['href'];
				return $this->request("upload");
			}
			else{
				if (isset($link['error']) && $link['error']=="DiskPathDoesntExistsError"){
					$res = $this->request("mkdir");
					$answer = json_decode($res, true);
					if (isset($answer['created']) or isset($answer['href'])){
						return $this->uploadFile($file,$path,$overwrite);
					}
					else {
						return $res;
					}
				}
				return $res;
			}

		}
		else {
			return json_encode([
					"error" => "FileNotFound"
				]);
		}
	}
	public function getOperation($link){
		$this->operation_link = $link;
		return $this->request("operation");
	}
	public function transferUrl($url, $file, $path="", $trying=1){
		if($this->does_url_exists($url)){
			$this->upload_url =  $url;
			$this->upload_file =  $file;
			$this->upload_path = $path;
			$res = $this->request("get_fetch_link");
			$link = json_decode($res, true);
			if(isset($link['error'])){
				/* 	[message] => 
					[description] => Specified path "..." doesn't exists.
					[error] => DiskPathDoesntExistsError
				*/
				return json_encode($link);
			}
			if(isset($link['href'])){
				$countdown = 10;
				do{
					$countdown--;
					$operation = $this->getOperation($link['href']);
					$operation = json_decode($operation, true);
					if($operation["status"]=="failure"){
						$countdown=0;
					}
					elseif($operation["status"]=="success"){
						$countdown=0;
					}
					elseif($operation["status"]=="in-progress"){
					}
				}while($countdown);
				return $res;
			}
			else{
				if(isset($link['error']) && $link['error']=="DiskPathDoesntExistsError"){
					$res = $this->request("mkdir");
					$answer = json_decode($res, true);
					if (isset($answer['created']) or isset($answer['href'])){
						return $this->transferUrl($url, $file, $path);
					}
					else {
						return $res;
					}
				}
				return $res;
			}
		}
		else {
			return json_encode([
				"error" => "UrlNotFound"
			]);
		}
	}
	public function removeFile($file, $path=""){
		$this->remove_file = $file;
		$this->upload_path = $path;
		return $this->request("remove");
	}
	private function getRequestData($type){
		$data = [];
		switch ($type){
			case "info":
				$data['url'] = "https://cloud-api.yandex.net/v1/disk/";
			break;
			case "mkdir":
				$data['url'] = "https://cloud-api.yandex.net/v1/disk/resources/?path=".urlencode("/".$this->upload_path);
			break;
			case "remove":
				$data['url'] = "https://cloud-api.yandex.net/v1/disk/resources/?permanently=true&path="
					.urlencode("/".(strlen($this->upload_path)>0?$this->upload_path."/":"").$this->remove_file);
			break;
			case "get_upload_link":
				$data['url'] = "https://cloud-api.yandex.net/v1/disk/resources/upload?path="
					.urlencode("/".(strlen($this->upload_path)>0?$this->upload_path."/":"").basename($this->upload_file))
					.($this->overwrite?"&overwrite=true":"");
			break;
			case "get_fetch_link":
			$data['url'] = "https://cloud-api.yandex.net/v1/disk/resources/upload?path=".
				urlencode("/".(strlen($this->upload_path)>0?$this->upload_path."/":"").basename($this->upload_file))
				."&url=".urlencode($this->upload_url)
				;
			break;
			case "upload":
				$data['url'] = $this->upload_link;
			break;
			case "fetch":
				$data['url'] = $this->upload_link;
			break;
			case "operation":
				$data['url'] = $this->operation_link;
			break;
		}
		return $data;
	}

	private function request($type){
		$data = $this->getRequestData($type);
		$url = $data["url"];
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Accept: application/json",
			"Content-Type: application/json",
			"Authorization: OAuth ".$this->access_token
		]);
		if ($type=='mkdir'){
			curl_setopt($ch, CURLOPT_HEADER, 1);
			curl_setopt($ch, CURLOPT_PUT, 1);
		}
		if ($type=='remove'){
			curl_setopt($ch, CURLOPT_HEADER, 1);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		}
		if ($type=='upload'){
			$fp = fopen($this->upload_file, "rb");
			curl_setopt($ch, CURLOPT_HEADER, 1);
			curl_setopt($ch, CURLOPT_PUT, 1);
			curl_setopt($ch, CURLOPT_INFILE, $fp);
			curl_setopt($ch, CURLOPT_INFILESIZE, filesize($this->upload_file));
		}
		if ($type=='get_fetch_link'){
			curl_setopt($ch, CURLOPT_POST, 1);
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		$result = curl_exec($ch);
		curl_close($ch);
		if ($type=='upload'){
			fclose($fp);
		}
		if($type=='fetch' && strstr($result, "202 ACCEPTED")){
			$result = json_encode([
					"started" => "ok",
					"responce" => $result
				]);
		}
		if (strstr($result, "201 Created")){
			$result = json_encode([
					"created" => "ok",
					"responce" => $result
				]);
		}
		if (strstr($result, "201 CREATED")){
			$result = json_encode([
					"created" => "ok",
					"responce" => $result
				]);
		}
		if (strstr($result, "204 NO CONTENT")){
			$result = json_encode([
					"removed" => "ok",
					"responce" => $result
				]);
		}
		return $result;
	}
	private function does_url_exists($url) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($code == 200) {
			$status = true;
		} else {
			$status = false;
		}
		curl_close($ch);
		return $status;
	}

}
?>
