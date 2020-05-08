<?php
	function request($URL, $params)
	{
		return json_decode(file_get_contents($URL . '?' . http_build_query($params)), true);
	}

	function upload_file($url, $path, $field) {
		$file = new CURLFile(realpath($path));
		$curl = curl_init( $url );
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, array($field => $file));	
		$response = curl_exec( $curl );
		curl_close( $curl ); 

		return json_decode($response, true);
	}

	if(extension_loaded("pthreads")) {
		class Thread extends Threaded {
			var $func;
			function __construct($func, $args) {
				$this->func = $func;
				$this->args = $args;
			}
			public function run() {
				$this->func(...$this->args);
			}
		}
	}
?>
