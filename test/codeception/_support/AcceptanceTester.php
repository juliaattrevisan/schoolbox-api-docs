<?php

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Codeception\Module;

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
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = null)
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
     * Log out of Schoolbox
     */
    public function logout()
    {
        $i = $this;
        $i->amOnPage(self::$logoutURL);
    }

    /**
     * Login as Alaress
     */
    public function loginAsAdmin()
    {
        $this->login('alaress', 'alaress');
    }

    /**
     * Login to Schoolbox
     *
     * @param $username
     * @param $password
     */
    public function login($username, $password)
    {

        $i = $this;

        // if snapshot exists - skipping login
        $snapshotName = 'login:'.$username;
        if ($i->loadSessionSnapshot($snapshotName)) {
            return;
        }

        $i->amOnPage(self::$loginURL);
        $i->fillField(self::$usernameField, $username);
        $i->fillField(self::$passwordField, $password);
        $i->checkOption(self::$rememberMe);
        $i->click(self::$loginButton);
        $i->amOnPage('/');

        $i->saveSessionSnapshot($snapshotName);

    }

    /**
     * Impersonate Coles (sales data)
     */
    public function impersonateTeacher()
    {
        $i = $this;
        $i->click('Alaress Admin');
        $i->click('Mr Jack Coles');
    }

    /**
     * Impersonate
     * @param $fullname
     */
    public function impersonate($fullname)
    {
        $i = $this;
        $i->amOnPage('/user/recoverSession');
        $i->click('a#profile-drop');
        $i->click($fullname, '#account-content');
    }


    /**
     * Fill in CKEditor
     * @param $element_id
     * @param $content
     */
    public function fillCkEditorById($element_id, $content)
    {
        $i = $this;
        $i->waitForElement('#cke_' . $element_id);
        $this->fillRteEditor(
            \Facebook\WebDriver\WebDriverBy::cssSelector(
                '#cke_' . $element_id . ' .cke_wysiwyg_frame'
            ),
            $content
        );
    }

    /**
     * Fill in CKEditor
     * @param $element_name
     * @param $content
     */
    public function fillCkEditorByName($element_name, $content)
    {
        $this->fillRteEditor(
            \Facebook\WebDriver\WebDriverBy::cssSelector(
                'textarea[name="' . $element_name . '"] + .cke .cke_wysiwyg_frame'
            ),
            $content
        );
    }

    /**
     * Fill in TinyMice editor
     * @param $id
     * @param $content
     */
    public function fillTinyMceEditorById($id, $content)
    {
        $this->fillTinyMceEditor('id', $id, $content);
    }

    /**
     * Fill in TinyMice editor
     * @param $name
     * @param $content
     */
    public function fillTinyMceEditorByName($name, $content)
    {
        $this->fillTinyMceEditor('name', $name, $content);
    }

    /**
     * Fill in TinyMice editor
     * @param $attribute
     * @param $value
     * @param $content
     */
    private function fillTinyMceEditor($attribute, $value, $content)
    {
        $this->fillRteEditor(
            \Facebook\WebDriver\WebDriverBy::xpath(
                '//textarea[@' . $attribute . '=\'' . $value . '\']/../div[contains(@class, \'mce-tinymce\')]//iframe'
            ),
            $content
        );
    }

    /**
     * Fill in RteEditor
     * @param $selector
     * @param $content
     */
    private function fillRteEditor($selector, $content)
    {
        $this->executeInSelenium(
            function (\Facebook\WebDriver\Remote\RemoteWebDriver $webDriver)
            use ($selector, $content) {
                $webDriver->switchTo()->frame(
                    $webDriver->findElement($selector)
                );

                $webDriver->executeScript(
                    'arguments[0].innerHTML = "' . addslashes($content) . '"',
                    [$webDriver->findElement(\Facebook\WebDriver\WebDriverBy::tagName('body'))]
                );

                $webDriver->switchTo()->defaultContent();
            });
    }

    /**
     * Get current url from WebDriver
     * @return mixed
     * @throws \Codeception\Exception\ModuleException
     */
    public function getCurrentUrl()
    {
        return $this->executeJS("return location.href");
    }
}
