<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Facade_Component_API extends Generic_API {

    private $user;

    public function __construct($response, $user = null) {
        parent::__construct($response);

        // do typecheck (cannot be in method signature, will throw fatal error)
        if($user && is_a($user, 'User')) {
            $this->user = $user;
        }
        else {
            $auth = new Authenticator();
            $this->user = $auth->getSessionUser();
        }
    }


    /* Register valid components */

    public function Salutation() {
        return $this->renderComponent('Salutation');
    }

    public function CurrentPeriod() {
        return $this->renderComponent('CurrentPeriod');
    }

    public function SchoolEvents() {
        return $this->renderComponent('SchoolEvents');
    }

    public function SchoolNews() {
        return $this->renderComponent('SchoolNews');
    }

    public function YourNews() {
        return $this->renderComponent('YourNews');
    }


    private function renderComponent($name) {
        $response = new Response_HTTP();

        // just in case
        $name = ucfirst($name);

        $className = "Facade_Component_$name";

        $instance = new $className();
        $response->setResponse($name, $instance->render($this->user));

        return $response;
    }

}
