<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_GreetingController extends Component_Dashboard_ControllerAbstract {

    private $greeting;
    private $name;

    public function execute () {

        $tf = new TextFormatter();
        $this->greeting = $tf->greeting();
        $this->name = $this->user->formatName($this->user->getByID($_SESSION['id']), $this->getConfig('format'));

        return true;
    }

    public function renderTemplate ($templateHandle) {
        if ( !$this->loadTemplate($templateHandle, 'greeting') ) {
            return false;
        }

        $this->setVar('GREETING', $this->greeting);
        $this->setVar('NAME', $this->name);
        
        return true;
    }

}