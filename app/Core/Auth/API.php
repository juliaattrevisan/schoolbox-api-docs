<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Core_Auth_API extends Generic_API {

    public function Login() {

        $response = new Response_HTTP();
        $auth = new Authenticator();

        // don't want the login page showing if we're already logged in
        if($auth->isAuthenticated()) {
            $response->addInternalRedirectHeader('');
            return $response;
        }

        // if url points directly to login, we'll be routing to webRoot
        if(($i = strpos($_SERVER['QUERY_STRING'], 'url=m/core/auth/login')) === false) {
            $redirect = substr($_SERVER['QUERY_STRING'], $i + 4);
        }
        else {
            $redirect = '';
        }

        $response->setPage('title', 'Login');
        $response->setResponse('redirect', base64_encode($redirect));
        return $response;
    }

    public function ForgotPassword() {
        $response = new Response_HTTP();
        $response->setPage('title', 'Forgot Password');
        return $response;
    }

    public function LoginForm() {
        $a = new Authenticator();
        $response = new Response_HTTP();
        $response->setResponse('session', $a->isAuthenticated() ? session_id() : false);
        // $response->addInternalRedirectHeader(base64_decode($_POST['page']));
        return $response;
    }

    public function Logout() {
        $auth = new Authenticator();
        $auth->destroySession();
        $response = new Response_HTTP();
        $response->addInternalRedirectHeader('core/auth/login');
        return $response;
    }

	public function AmILoggedIn() {
		$a = new Authenticator();
		$response = new Response_HTTP();
		$response->setResponse('logged_in', $a->isAuthenticated());
		$response->setPage('type', 'json');
		return $response;
	}
}
