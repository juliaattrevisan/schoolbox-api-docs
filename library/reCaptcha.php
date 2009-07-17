<?php

define('RECAPTCHA_PUBLIC_KEY', '6LfRGgcAAAAAANu7KxFfLFK8Z00TIGgJIHgWYKIi');
define('RECAPTCHA_PRIVATE_KEY', '6LfRGgcAAAAAAGRv7liKjwmDwWF5lfh1oDC5953I');

define('RECAPTCHA_VERIFY_URL', 'api-verify.recaptcha.net');
define('RECAPTCHA_SECURE_URL', 'https://api-secure.recaptcha.net');
define('RECAPTCHA_CHALLENGE_URL', RECAPTCHA_SECURE_URL . '/challenge?k=');

class ReCaptcha {

	static private $instance = false;
	static private $challenge = false;

	private function __construct() {
	
	}

	static public function getInstance() {
		if (!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	function getChallenge() {
	
		if (!self::$instance)
			self::$instance = new self();

		if (!self::$challenge) {

			$output = file_get_contents(RECAPTCHA_CHALLENGE_URL . RECAPTCHA_PUBLIC_KEY);
			if (!preg_match("/challenge : '([^']+)',/", $output, $match))
				return false;
			
			self::$challenge = $match[1];
		}
		
		return self::$challenge;
	}
	
	function checkResponse($response, $challenge) {

		if (!self::$instance)
			self::$instance = new self();
				
        if (empty($challenge) || empty($response))
            return false;

		// send verification check
 		$data = 'privatekey=' . urlencode(stripslashes(RECAPTCHA_PRIVATE_KEY)) . '&' .
				'remoteip=' . urlencode(stripslashes($_SERVER['REMOTE_ADDR'])) . '&' .
				'challenge=' . urlencode(stripslashes($challenge)) . '&' .
				'response=' . urlencode(stripslashes($response));

        $http_request  = "POST /verify HTTP/1.0\r\n";
        $http_request .= "Host: " . RECAPTCHA_VERIFY_URL . "\r\n";
        $http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
        $http_request .= "Content-Length: " . strlen($data) . "\r\n";
        $http_request .= "User-Agent: reCAPTCHA/PHP\r\n";
        $http_request .= "\r\n";
        $http_request .= $data;

		
        $response = '';
        if( false == ( $fs = fsockopen(RECAPTCHA_VERIFY_URL, 80, $errno, $errstr, 10) ) ) 
            return false;

        fwrite($fs, $http_request);

        while ( !feof($fs) )
        	$response .= fgets($fs, 1160); // One TCP-IP packet
			
        fclose($fs);
        $response = explode("\r\n\r\n", $response, 2);
		
		$answers = explode ("\n", $response [1]);
        if (trim ($answers [0]) != 'true')
        	return false;

        return true;	
	}
}

?>