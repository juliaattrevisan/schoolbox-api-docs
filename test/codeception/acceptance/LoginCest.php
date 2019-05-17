<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Page\Login as LoginPage;

class LoginCest
{
    /**
     * Go to the login page and configure SAML before every test.
     *
     * @param AcceptanceTester $i
     */
    public function _before(AcceptanceTester $i)
    {
        $i->runShellCommand('./test/acceptance/_support/BashScripts/disable_ssl_SAML.sh');
        $i->changeDBConfig('saml_sso_url', 'https://ad.dev.schoolbox.cloud/adfs/ls');
        $i->changeDBConfig('saml_logout_url', 'https://ad.dev.schoolbox.cloud/adfs/ls/IdpInitiatedSignon.aspx');
        $i->changeDBConfig('saml_idp_metadata_url', 'https://ad.dev.schoolbox.cloud/FederationMetadata/2007-06/FederationMetadata.xml');
        $i->amOnPage(LoginPage::URL);
    }

    /**
     * At the end of every test remove SAML config.
     *
     * @param AcceptanceTester $i
     */
    public function _after(AcceptanceTester $i)
    {
        $i->runShellCommand('./test/acceptance/_support/BashScripts/enable_ssl_SAML.sh');
        $i->changeDBConfig('saml_sso_url', '');
        $i->changeDBConfig('saml_logout_url', '');
        $i->changeDBConfig('saml_idp_metadata_url', '');
        $i->logout();
    }

    /**
     * Test for SAML login success
     *
     * @param AcceptanceTester $i
     * @param LoginPage        $loginPage
     * @throws Exception
     */
    public function loginSAMLSuccessfully(AcceptanceTester $i, LoginPage $loginPage)
    {
        $loginPage->loginSAML('AD\drhodes', 'Password2000');
        $loginPage->verifyLoginSuccess();
    }

    /**
     * Test for SAML login failed !!Issue with test, SAML keeps you logged in.
     *
     * @param AcceptanceTester $i
     * @param LoginPage        $loginPage
     * @throws Exception
     */
    #public function loginSAMLFailure(AcceptanceTester $i, LoginPage $loginPage)
    #{
        #$loginPage->loginSAML('AD\drhodes', 'wrong');
        #$loginPage->verifyLoginFailure();

        // Test DB contents
        #$i->dontSeeInDatabase('user', ['username' => 'wrong']);
    #}

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
