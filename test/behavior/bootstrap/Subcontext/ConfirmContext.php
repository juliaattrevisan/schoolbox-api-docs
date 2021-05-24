<?php

/**
 * Contains test steps that relate to confirm() dialogs. Copied from
 * https://gist.github.com/benjaminlazarecki/2888851
 *
 * Currently only the Selenium driver supports this.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Behat\Mink\Element;

class Subcontext_ConfirmContext extends BaseContext
{

    /**
     * @return \WebDriver\Session
     * @throws \Behat\Behat\Exception\PendingException if not a Webdriver session
     */
    protected function getWebDriverSession()
    {
        $driver = $this->getSession()->getDriver();
        if (method_exists($driver, 'getWebDriverSession')) {
            return $driver->getWebDriverSession();
        }
        throw new Behat\Behat\Exception\PendingException('getWebDriverSession() is not defined for this driver');
    }

    /**
     * @when /^(?:|I )confirm the popup$/
     */
    public function confirmPopup()
    {
        $this->getWebDriverSession()->accept_alert();
    }

    /**
     * @when /^(?:|I )cancel the popup$/
     */
    public function cancelPopup()
    {
        $this->getWebDriverSession()->dismiss_alert();
    }

    /**
     * @When /^(?:|I )should see "([^"]*)" in popup$/
     *
     * @param  string $message
     * @return bool
     */
    public function assertPopupMessage($message)
    {
        return $message == $this->getWebDriverSession()->getAlert_text();
    }

    /**
     * @When /^(?:|I )fill "([^"]*)" in popup$/
     *
     * @param string $message
     */
    public function setPopupText($message)
    {
        $this->getWebDriverSession()->postAlert_text($message);
    }
}
