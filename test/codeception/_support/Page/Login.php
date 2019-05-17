<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Page;

use AcceptanceTester;

/**
 * Login page object
 */
class Login
{
    const URL                  = '/login/';
    const FIELD_USERNAME       = 'username';
    const FIELD_PASSWORD       = 'password';
    const SAML_FIELD_USERNAME  = 'UserName';
    const SAML_FIELD_PASSWORD  = 'Password';
    const CHECKBOX_REMEMBER_ME = '#rememberme';
    const BUTTON_LOGIN         = 'Submit';
    const SAML_BUTTON          = 'Login using Single Sign-On';
    const SAML_LOGIN_BUTTON    = ['xpath' => '//span[text()="Sign in"]'];

    /**
     * @var AcceptanceTester
     */
    protected $tester;

    /**
     * Constructor
     *
     * @param AcceptanceTester $i
     */
    public function __construct(AcceptanceTester $i)
    {
        $this->tester = $i;
    }

    /**
     * Log in as this user via SAML
     *
     * @param $username
     * @param $password
     * @param $rememberMe
     * @throws \Exception
     */
    public function loginSAML($username, $password)
    {
        $this->tester->click(self::SAML_BUTTON);
        $this->tester->waitForText("ADFS Schoolbox",5);
        $this->tester->fillField(self::SAML_FIELD_USERNAME, $username);
        $this->tester->fillField(self::SAML_FIELD_PASSWORD, $password);
        $this->tester->click(self::SAML_LOGIN_BUTTON);
    }

    /**
     * Log in as this user
     *
     * @param $username
     * @param $password
     * @param $rememberMe
     */
    public function login($username, $password, $rememberMe)
    {
        $this->tester->fillField(self::FIELD_USERNAME, $username);
        $this->tester->fillField(self::FIELD_PASSWORD, $password);
        if ($rememberMe) {
            $this->tester->checkOption(self::CHECKBOX_REMEMBER_ME);
        } else {
            $this->tester->uncheckOption(self::CHECKBOX_REMEMBER_ME);
        }
        $this->tester->click(self::BUTTON_LOGIN);
    }

    /**
     * Test that this user is logged in.
     */
    public function verifyLoginSuccess()
    {
        $this->tester->see("NEWS HEADLINES");
    }

    /**
     * Test that this user is not logged in.
     */
    public function verifyLoginFailure()
    {
        $this->tester->see("The username or password you typed is incorrect, please try again.");
    }

}