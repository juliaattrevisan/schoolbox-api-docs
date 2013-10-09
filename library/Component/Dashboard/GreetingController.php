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

	/**
	 * Generate a text string for the header, containing a greeting and
	 * the current user's full name
	 *
	 * @return bool|void
	 */
	public function execute() {
        $tf = new TextFormatter();

		/* What the what - manual template var replacement and stuff?
		 * Anyway this adjusts compatibility between userHandler and
		 * the Schoolbox\User object's formatName() interfaces
		 */
		$greetingString = str_replace('{timeofday}', $tf->greeting(), $this->getConfig('componentname'));

        $this->greeting = Scrub::html($_SESSION['user']->formatName($greetingString));

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