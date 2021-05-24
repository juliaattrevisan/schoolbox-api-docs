<?php

/**
 * This class provides helper functionality for utilising googles ReCapture API
 * https://developers.google.com/recaptcha/intro
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 */

use GuzzleHttp\Client;

class ReCaptcha
{

    /**
     * @var string
     */
    private $public_key;

    /**
     * @var string
     */
    private $private_key;

    /**
     * @var string
     */
    private $api_script = 'https://www.google.com/recaptcha/api.js';

    /**
     * @var string
     */
    private $verify_server = 'https://www.google.com/recaptcha/api/siteverify';


    /**
     * ReCaptcha constructor.
     * @param string $public_key
     * @param string $private_key
     * @throws \DomainException
     */
    public function __construct($public_key, $private_key)
    {
        $this->public_key = $public_key;
        $this->private_key = $private_key;

        if (null === $this->public_key || empty($this->public_key)) {
            throw new \DomainException('To use reCAPTCHA you must have an API key installed');
        }
    }

    /**
     * Provides the HTML form snippet to display for reCapture
     *
     * @return string
     */
    public function form(): string
    {
        return '    
            <script src="' . $this->api_script . '" async defer></script>
            <script>
                function recaptchaCallback() {
                    $("#recaptcha-valid").val(1);
                    $("#recaptcha-valid-error").hide();
                }
                function recaptchaExpiredCallback() {
                    $("#recaptcha-valid").val(0);
                    $("#recaptcha-valid-error").show();
                }
            </script>
            <div class="g-recaptcha" data-sitekey="' . $this->public_key . '" data-callback="recaptchaCallback" data-expired-callback="recaptchaExpiredCallback"></div>
            <input type="hidden" name="recaptcha" id="recaptcha-valid" />
        ';
    }

    /**
     * Checks if what was submitted with form can be verified by Google ReCapture
     *
     * @param string $response
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \DomainException
     */
    public function verify($response): bool
    {

        // discard spam submissions
        if (null === $response || strlen($response) === 0) {
            return false;
        }

        $httpClient = new Client(['curl' => \Proxy\Server::getInstance()->buildCurlOptions()]);
        $response = $httpClient->request('POST', $this->verify_server, [
            'form_params' => [
                'secret'   => $this->private_key,
                'response' => $response,
            ],
        ]);

        return json_decode($response->getBody())->success === true;
    }
}
