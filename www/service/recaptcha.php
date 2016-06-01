<?php

/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 9/20/11
 * Time: 3:56 PM
 * To change this template use File | Settings | File Templates.
 */
 
require_once '../../library/Bootstrap.php';

class Recaptcha_ServiceController {
    private $recaptcha;

    public function __construct () {
        $this->recaptcha = new ReCaptcha( Config::get('recaptcha_public_api_key'), Config::get('recaptcha_private_api_key') );
    }

    public function check ($challenge, $response) {
        $response = array(
            'valid' => $this->recaptcha->check_answer($_SERVER['REMOTE_ADDR'], $challenge, $response),
            'error' => $this->recaptcha->error_message
        );

        return json_encode($response);
    }
}

if ( !empty($_POST['recaptcha_challenge_field']) && !empty($_POST['recaptcha_response_field']) ) {
    $serviceController = new Recaptcha_ServiceController();

    header('Content-type: application/json');
    echo $serviceController->check($_POST['recaptcha_challenge_field'], $_POST['recaptcha_response_field']);
}
