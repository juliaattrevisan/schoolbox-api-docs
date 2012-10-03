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

    public function execute () {

        $tf = new TextFormatter();
        $greeting = $tf->greeting();

        $user = $this->user->getByID($_SESSION['id']);

        $availableFields = array(
            'timeofday' => $greeting,
            'title' => $user['title'],
            'firstname' => $user['firstname'],
            'lastname' => $user['lastname']
        );

        $output = $this->user->formatName($availableFields, $this->getConfig('componentname'));

        $this->greeting = Scrub::html($output);

        return true;
    }

    public function renderTemplate ($templateHandle) {
        if ( !$this->loadTemplate($templateHandle, 'greeting') ) {
            return false;
        }

        $this->setVar('GREETING', $this->greeting);
        return true;
    }

}