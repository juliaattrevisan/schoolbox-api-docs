<?php

use Codeception\Actor;
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
class AcceptanceTester extends Actor
{
    use _generated\AcceptanceTesterActions;
    use \Codeception\Lib\Actor\Shared\Friend;

    /**
     * Define custom actions here
     */
    public static string $loginURL  = '/login';
    public static string $logoutURL = '/logout';
    public static string $usernameField = '#username';
    public static string $passwordField = '#password';
    public static string $rememberMe  = '#rememberme';
    public static string $loginButton = 'input[type=submit]';
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
        $snapshotName = 'login:' . $username;
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
        $i->deleteSessionSnapshot('Mr Jack Coles');
        $i->deleteSessionSnapshot('login:alaress');
    }

    /**
     * Impersonate
     * @param $fullname
     * @throws Exception
     */
    public function impersonate($fullname)
    {
        $i = $this;
        $i->amOnPage('/user/recoverSession');
        $i->click(['css' => 'a[title=Schoolbox\ Admin]']);
        $i->executeJS("var scrollableDiv = document.getElementById('account-content');
        scrollableDiv.scrollTop=scrollableDiv.scrollHeight - scrollableDiv.offsetHeight;");
        $i->waitForText($fullname, 5);
        $i->click($fullname, '#switch-user');
        $i->deleteSessionSnapshot($fullname);
        $i->deleteSessionSnapshot('login:alaress');
    }

    /**
     * Impersonate
     * @param $fullname
     * @throws Exception
     */
    public function impersonateAnyUser($fullname)
    {
        $i = $this;
       // $i->amOnPage('/user/recoverSession');
        $i->click('#profile-drop');
        $i->executeJS("var scrollableDiv = document.getElementById('account-content');
        scrollableDiv.scrollTop=scrollableDiv.scrollHeight - scrollableDiv.offsetHeight;");
        $i->waitForText($fullname, 5);
        $i->click($fullname, '#switch-user');
        $i->deleteSessionSnapshot($fullname);
        $i->deleteSessionSnapshot('login:alaress');
    }

    /**
     * Fill in CKEditor
     * @param $element_id
     * @param $content
     * @throws Exception
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
            function (\Facebook\WebDriver\Remote\RemoteWebDriver $webDriver) use ($selector, $content) {
                $webDriver->switchTo()->frame(
                    $webDriver->findElement($selector)
                );

                $webDriver->executeScript(
                    'arguments[0].innerHTML = "' . addslashes($content) . '"',
                    [$webDriver->findElement(\Facebook\WebDriver\WebDriverBy::tagName('body'))]
                );

                $webDriver->switchTo()->defaultContent();
            }
        );
    }
    /**
     * Get current url from WebDriver
     * @return mixed
     */
    public function getCurrentUrl()
    {
        return $this->executeJS("return location.href");
    }
    /**
     * @throws Exception
     */
    public function scrollAndClick($selector)
    {
        $this->scrollTo($selector);
        $this->waitForElementVisible($selector, 5);
        $this->click($selector);
    }
}
