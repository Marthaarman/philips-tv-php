<?php

class philips_tv {
	
	private $secret = "JCqdN5AcnAHgJYseUn7ER5k3qgtemfUvMRghQpTfTZq7Cvv8EPQPqfz6dDxPQPSu4gKFPWkJGw32zyASgJkHwCjU"; //secret key for signing
	private $hostdata = array(
		"protocol" => 'https', //TV connection protocol, http or https 
		"host" => 'xxx.xxx.xxx.xxx', //TV IPv4 address
		"port" => '1926', //TV connection port (1925 for http, 1926 for https)
		"apiv" => '6' //API Version. this is the Major given when you browse to $protocol://$ip:$port/system -> https://192.168.1.184:1926/system
	);
	
	private $scope = array("read", "write", "control"); //permissions to request
	private $TV = array(); //will contain connection data for later on
	private $data_set = false; //credentials file has been made if true
	private $data_file = __DIR__."/credentials.json"; //file with credentials for connecting to the TV
	private $commands_file = __DIR__."/commands.json"; //file with all commands to be sent to the TV
	private $auth_key = false; //key that will be used to connect to the TV
	private $timestamp = false; //timestamp used for pairing
	private $pin = false; //pincode used for pairing, displayed on the TV
	
	private $commands = false; //variable that will hold the commands from $commands_file
	private $device_name = "philips-tv-connect"; //name this device will use to connect to the TV
	private $app_name = "philips-tv-connect"; //app name this device uses
	private $debug = false;
	
	public function __construct($protocol = false, $host = false, $port = false, $apiv = false) {
		if($host && $protocol && $port && $apiv) { //if data given, set connection settings
			$this->hostdata['host'] = $host;
			$this->hostdata['protocol'] = $protocol;
			$this->hostdata['port'] = $port;
			$this->hostdata['apiv'] = $apiv;
		}
		$this->obtain_data(); //read from credentials file
		$this->readCommands(); //load commands
	}
	
	public function debug($bool = true) {
		$this->debug = $bool;
	}
	
	private function obtain_data() { //read from credentials file
		if(file_exists($this->data_file)) { //credentials file is present
			$data = $this->readCredentials(); //read from the file
			if(isset($data['TV']['user'])) { //make sure it is not corrupt
				$this->TV = $data['TV']; 
				$this->timestamp = $data['timestamp'];
				$this->auth_key = $data['auth_key'];
				$this->data_set = true; //there is data, so set to true
				return;
			}
		}
		
		$this->data_set = false;
	}
	
	public function set_pin($pin) { //set the pincode provided by the TV
		if($this->data_set) {
			$this->TV['pin'] = $pin;
			$this->pin = $pin;
		}else {
			$this->TV['pin'] = false;
			$this->pin = false;
		}
		$this->writeCredentials(); //store the pin with other credentials
	}
	
	public function pair() {
		$path = 'pair/request'; //path for pairing to the TV
		$data = array();
		$data["scope"] = $this->scope; //permissions
		$data["device"] = $this->getDeviceSpecs(); //load specifications of this device
		$this->throwMessage("start pairing request<br />");
		$response = $this->cURL_request($path, $data); //connect to the TV with the data and get an response
		
		if($this->instring('200 OK', $response) && $this->instring('SUCCESS', $response)) { //scan result for the phrases '200 OK' and 'SUCCESS'
			$response = json_decode($this->getJSON($response), true); //filter and decode result
			$this->TV['auth_key'] = $response['auth_key']; //store the given key as auth key
			$this->TV["pass"] =$response['auth_key']; //store the given key as pass for later authentication
			$this->timestamp = $response['timestamp']; //store the given timestamp for later signing
			$this->writeCredentials(); //store all to the credentials file
			$this->throwMessage("Pairing request succes!");
			return true;
			
		}elseif($this->instring("CONCURRENT_PAIRING", $response)) { //if string contains this, 60s have not past since last try
			$this->throwMessage("Pairing already in progress");
		}
		return false;
	}
	
	public function pair_confirm($pin = false) {
		if($this->data_set && $this->pin) {
			$this->throwMessage("Start confirm pairing");
			$device_id = $this->TV['user'];
			$auth_key = $this->TV['pass'];
			$pin = $this->TV['pin'];
			$timestamp = $this->timestamp;

			$auth = array();
			$auth["pin"] = $pin;
			$auth["auth_AppId"] = 1;
			$auth["auth_timestamp"] = $timestamp;
			$auth["auth_signature"] = $this->create_signature();

			$grant_request = array();
			$grant_request["auth"] = $auth;
			$grant_request["device"]  = $this->TV['device'];


			$path = "pair/grant";
			$response = $this->cURL_request($path, $grant_request);
			if($response !== false) {
				if($this->instring('200 OK', $response) && $this->instring('Pairing completed', $response)) {
					$this->throwMessage("Pairing confirmed");
					return true;
				}
			}
		}else{
			$this->throwMessage("data not set or pin not given");
		}
		return false;
	}
	
	public function command($command = false) {
		$this->throwMessage("Start command");
		if($command) {
			if(isset($this->commands[$command])) {
				$body = $this->commands[$command]['body'];
				$path = $this->commands[$command]['path'];
				$response = $this->cURL_request($path, $body);
				$this->throwMessage("Command succeeded.");
				return true;
			}else {
				$this->throwMessage("Command {$command} not found.");
			}
		}
		return false;
	}
	
	private function getDeviceSpecs() {
		$device_spec =  array("device_name" => $this->device_name, "device_os" => "php", "app_name" => $this->app_name, "type" => "native");
		$device_spec["app_id"] = "app.id";
		$device_spec["id"] = $this->createDeviceID();
		$this->TV["user"] = $device_spec['id'];
		$this->TV["device"] = $device_spec;
		return $device_spec;
	}
	
	private function create_signature() {
		if($this->data_set && $this->pin) {
			$sign = hash_hmac("sha256", base64_decode($this->secret), $this->timestamp.$this->pin);
			return $sign;
		}
		return false;
	}
	
	private function createDeviceID($length = 16) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}
	
	private function cURL_request($path, $data = false) {
		if($this->data_set || $path == 'pair/request') {
			$target = "{$this->hostdata['protocol']}://{$this->hostdata['host']}:{$this->hostdata['port']}/{$this->hostdata['apiv']}/{$path}";
			
			$ch = curl_init($target);

			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
			curl_setopt($ch, CURLOPT_HEADER, 1);
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt($ch, CURLINFO_HEADER_OUT, true);

			if($data) {
				curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($data) );
			}

			if(!($path == 'pair/request')) {
				$credentials = "{$this->TV['user']}:{$this->TV['pass']}";
				curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
				curl_setopt($ch, CURLOPT_USERPWD, $credentials);
			}

			$result = curl_exec($ch);
			if ($result === false) {
				$result = curl_error($ch);
			}

			curl_close($ch); 
			
			$this->throwMessage($result);
			
			return $result;
		}
	}
	
	private function prints($data) {
		echo '<pre>';
			print_r($data);
		echo '</pre>';
	}
	
	private function getJSON($string) {
		preg_match_all('/{(.*?)}/', $string, $matches);
		return isset($matches[0][0]) ? $matches[0][0] : false;
	}
	
	private function instring($q, $string, $allowcaps = true) {
		if(!$allowcaps) {$q = strtolower($q); $string = strtolower($string);}
		return strpos($string, $q) === false ? false : true;
	}
	
	private function writeCredentials() {
		$data = array(
			'TV' => $this->TV,
			'auth_key' => $this->auth_key,
			'timestamp' => $this->timestamp
		);
		if(file_exists($this->data_file)) {unlink($this->data_file);}
		$json = json_encode($data);
		file_put_contents($this->data_file, $json);
	}
	
	private function readCredentials() {
		$raw = file_get_contents($this->data_file);
		return json_decode($raw, true);
	}
	
	private function readCommands() {
		$raw = file_get_contents($this->commands_file);
		$this->commands = json_decode($raw, true);
	}
	
	private function throwMessage($msg = '') {
		if($this->debug) {
			echo $msg.'<br />';
		}
	}
	
	
}