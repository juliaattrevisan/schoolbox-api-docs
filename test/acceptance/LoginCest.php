<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Page\Login as LoginPage;

class LoginCest
{
    /**
     * Go to the login page before every test
     *
     * @param AcceptanceTester $i
     */
    public function _before(AcceptanceTester $i)
    {
        $i->amOnPage(LoginPage::URL);
    }

    /**
     * Test for login success
     *
     * @param AcceptanceTester $i
     * @param LoginPage        $loginPage
     */
    public function loginSuccessfully(AcceptanceTester $i, LoginPage $loginPage)
    {
        $loginPage->login('alaress', 'alaress', true);
        $loginPage->verifyLoginSuccess();
    }

    /**
     * Test for login failure
     *
     * @param AcceptanceTester $i
     * @param LoginPage        $loginPage
     */
    public function loginWithInvalidPassword(AcceptanceTester $i, LoginPage $loginPage)
    {
        $loginPage->login('wrong', 'wrong', true);
        $loginPage->verifyLoginFailure();

        // Test DB contents
        $i->dontSeeInDatabase('user', ['username' => 'wrong']);
    }
}
