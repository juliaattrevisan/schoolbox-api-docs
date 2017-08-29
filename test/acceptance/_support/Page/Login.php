<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
    const CHECKBOX_REMEMBER_ME = '#rememberme';
    const BUTTON_LOGIN         = 'password';

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
