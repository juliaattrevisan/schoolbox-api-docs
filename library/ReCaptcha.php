<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class ReCaptcha {

    private $public_key;

    private $private_key;

    var $api_create = 'https://www.google.com/recaptcha/admin/create';

    var $api_server = 'https://www.google.com/recaptcha/api';

    var $verify_server = 'www.google.com';

    var $is_valid;

    var $error_message;

    public function __construct ($public_key, $private_key) {
        $this->public_key =  $public_key;
        $this->private_key = $private_key;
    }

    private function encode_query ($data) {

        $request = '';

        foreach ( $data as $key => $value ) {
            $request .= $key . '=' . urlencode(stripslashes($value)) . '&';
        }

        // cut the last '&'
        $request = substr($request, 0, strlen($request) - 1);

        return $request;

    }

    private function post ($host, $path, $data, $port = 80) {

        $request = $this->encode_query($data);

        $http_request = "POST $path HTTP/1.0\r\n";
        $http_request .= "Host: $host\r\n";
        $http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
        $http_request .= "Content-Length: " . strlen($request) . "\r\n";
        $http_request .= "User-Agent: reCAPTCHA/PHP\r\n";
        $http_request .= "\r\n";
        $http_request .= $request;

        $response = '';

        if ( false == ( $fs = fsockopen($host, $port, $errno, $errstr, 10) ) ) {
            exit( 'reCaptcha: Could not open socket' );
        }

        fwrite($fs, $http_request);

        while ( !feof($fs) ) {
            $response .= fgets($fs, 1160);
        } // One TCP-IP packet

        fclose($fs);

        $response = explode("\r\n\r\n", $response, 2);

        return $response;

    }

    public function form ($error = false, $use_ssl = false) {

        if ( is_null($this->public_key) || empty( $this->public_key ) ) {
            exit( 'To use reCAPTCHA you must get an API key from <a href="' . $this->api_create . '">' . $this->api_create . '</a>' );
        }

        $errorpart = '';

        if ( $error ) {
            $errorpart = '&amp;error=' . $error;
        }

        return '    
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            <div class="g-recaptcha" data-sitekey="'.$this->public_key.'"></div>
  ';

    }

    public function check_answer ($remote_ip, $response, $extra_params = array()) {

        if ( is_null($this->private_key) || empty( $this->private_key ) ) {
            exit( 'To use reCAPTCHA you must get an API key from <a href="' . $this->api_create . '">' . $this->api_create . '</a>' );
        }

        if ( is_null($remote_ip) || empty( $remote_ip ) ) {
            exit( 'For security reasons, you must pass the remote IP to reCAPTCHA' );
        }

        // discard spam submissions
        if ( is_null($response) || strlen($response) == 0 ) {
            $this->is_valid = false;
            $this->error_message = 'incorrect-captcha-sol';
            return false;
        }

        $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=".$this->private_key."&response=".$response);

        return json_decode($response)->success;

    }

    public function get_signup_url ($domain = false, $appname = false) {

        return $this->api_create . '?' . $this->encode_query(array( 'domains' => $domain, 'app' => $appname ));

    }

}