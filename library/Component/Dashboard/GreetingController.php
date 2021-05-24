<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 */

class Component_Dashboard_GreetingController extends Component_Dashboard_ControllerAbstract
{

    private $greeting;

    /**
     * Generate a text string for the header, containing a greeting and
     * the current user's full name
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        $tf = new TextFormatter();

        /* What the what - manual template var replacement and stuff?
         * Anyway this adjusts compatibility between userHandler and
         * the Schoolbox\User object's formatName() interfaces
         */
        $greetingString = str_replace('{timeofday}', $tf->greeting(), $this->getConfig('componentname'));

        $this->greeting = Scrub::html($this->getUser()->formatName($greetingString));

        return true;
    }

    public function renderTemplate($templateHandle)
    {
        if (!$this->loadTemplate($templateHandle, 'greeting')) {
            return false;
        }

        $this->setVar('GREETING', $this->greeting);
        return true;
    }
}
