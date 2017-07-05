<?php


/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = NULL)
 *
 * @SuppressWarnings(PHPMD)
*/
class AcceptanceTester extends \Codeception\Actor
{
    use _generated\AcceptanceTesterActions;

   /**
    * Define custom actions here
    */


    public static $loginURL = '/login';
    public static $logoutURL = '/logout';

    public static $usernameField = '#username';
    public static $passwordField = '#password';
    public static $rememberMe = '#rememberme';
    public static $loginButton = 'input[type=submit]';

    /**
     * Login to Schoolbox
     * @param AcceptanceTester $i
     * @param                  $username
     * @param                  $password
     */
    public function login($username, $password) {

        $i = $this;

        // if snapshot exists - skipping login
        if ($i->loadSessionSnapshot('login')) {
            return;
        }

        $i->amOnPage(self::$loginURL);
        $i->fillField(self::$usernameField, $username);
        $i->fillField(self::$passwordField, $password);
        //Can't just check a hidden field $i->checkOption('#rememberme'), keeping this as an example:
        $i->executeJS('$("'.self::$rememberMe.'").val(1);');
        $i->click(self::$loginButton);
        $i->amOnPage('/');

        $i->saveSessionSnapshot('login');

    }

    /**
     * Log out of Schoolbox
     * @param AcceptanceTester $i
     */
    public function logout() {
        $i = $this;
        $i->amOnPage(self::$logoutURL);
    }


    /**
     * Login as Alaress
     * @param AcceptanceTester $i
     */
    public function loginAsAdmin() {
        $i = $this;
        $this->login('alaress', 'alaress');
    }


    /**
     * Impersonate Coles (sales data)
     * @param AcceptanceTester $i
     */
    public function impersonateTeacher() {
        $i = $this;
        $i->click('Alaress Admin');
        $i->click('Mr Jack Coles');
    }

}
