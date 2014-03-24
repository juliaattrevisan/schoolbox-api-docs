<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Web\Controller;

use Aura\Web\Request;
use Aura\Web\Response;
use Aura\Router\Router;
use Alaress\Session\Session;
use Alaress\Authorizer\Authorizer;
use Schoolbox\User;

abstract class Base {
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Authorizer
     */
    protected $authorizer;

    /**
     * @param  Request $request
     * @return $this
     */
    public function setRequest($request) {
        $this->request = $request;
        return $this;
    }

    /**
     * @return Request
     */
    public function getRequest() {
        return $this->request;
    }

    /**
     * @param  Response $response
     * @return $this
     */
    public function setResponse($response) {
        $this->response = $response;
        return $this;
    }

    /**
     * @return Response
     */
    public function getResponse() {
        return $this->response;
    }

    /**
     * @param  Router $router
     * @return $this
     */
    public function setRouter($router) {
        $this->router = $router;
        return $this;
    }

    /**
     * @return Router
     */
    public function getRouter() {
        return $this->router;
    }

    /**
     * @param  Session $session
     * @return $this
     */
    public function setSession($session) {
        $this->session = $session;
        return $this;
    }

    /**
     * @return Session
     */
    public function getSession() {
        return $this->session;
    }

    /**
     * @param  Authorizer $authorizer
     * @return $this
     */
    public function setAuthorizer($authorizer) {
        $this->authorizer = $authorizer;
        return $this;
    }

    /**
     * @return Authorizer
     */
    public function getAuthorizer() {
        return $this->authorizer;
    }

    /**
     * Gets the current user out of the session.
     *
     * @return User
     */
    public function getUser() {
        return $this->getSession()->getUser();
    }

}
