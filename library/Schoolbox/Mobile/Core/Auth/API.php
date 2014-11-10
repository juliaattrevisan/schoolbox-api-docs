<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Mobile\Core\Auth;

use Config;
use Schoolbox\Mobile\Generic;
use Response_HTTP;
use Authenticator;

class API extends Generic\API {

    public function Login() {

        $response = new Response_HTTP();
        $auth = new Authenticator();

        // don't want the login page showing if we're already logged in
        if($auth->isAuthenticated()) {
            $response->addInternalRedirectHeader('');
            return $response;
        }

        // if url points directly to login, we'll be routing to webRoot
        parse_str($_SERVER['QUERY_STRING'], $query);
        if ($query['url'] === '/m/core/auth/login') {
            $redirect = '';
        } else {
            $redirect = $query['url'];
        }

        // Is SAML enabled? If so redirect to SAML auth instead
        if (
            Config::get('saml_enabled')
            &&
            strlen(Config::get('saml_sso_url'))
            &&
            $query['samlAuth'] !== '0'
        ) {
            $redirect = '/saml/index.php?page='.rawurlencode($query['url']);
            $response->addHeader('Location', $redirect);
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
