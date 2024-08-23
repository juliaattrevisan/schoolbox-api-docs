<?php

namespace Tests\Support;

use Codeception\Actor;
use Exception;
use SensitiveParameter;

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
    public function logout(): void
    {
        $i = $this;
        $i->amGoingTo('logout');
        $i->deleteSessionSnapshot('login:' . 'alaress');
        $i->amOnPage(self::$logoutURL);
        $i->waitForText('Parents, forgotten your password?', 3);
    }

    /**
     * Login as Alaress
     * @throws Exception
     */
    public function loginAsAdmin(): void
    {
        $this->amGoingTo('get logged in as alaress admin');
        $this->login('alaress', 'alaress');
        $this->waitForElementVisible(['css' => 'a[title=Schoolbox\ Admin]']);
    }

    /**
     * Login to Schoolbox
     *
     * @param string $username
     * @param string $password
     * @throws Exception
     */
    public function login(string $username, #[SensitiveParameter] string $password): void
    {
        $i = $this;
        $i->amGoingTo('get logged  in as' . $username);

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
        $i->waitForText('SCHOOLBOX [VAGRANT]');

        $i->saveSessionSnapshot($snapshotName);
        $i->skipWhatsNewTour();
    }

    /**
     * Impersonate Coles (sales data)
     * @throws Exception
     */
    public function impersonateTeacher(): void
    {
        $i = $this;
        $i->click('Alaress Admin');
        $i->click('Mr Jack Coles');
        $i->deleteSessionSnapshot('Mr Jack Coles');
        $i->deleteSessionSnapshot('login:alaress');
        $i->skipWhatsNewTour();
    }

    /**
     * Impersonate a user via user profile drop when alaress is presented in the drop
     * @param $fullname
     * @throws Exception
     */
    public function impersonate($fullname): void
    {
        $i = $this;
        $i->amGoingTo('impersonate a user' . $fullname);
        $i->logout();
        $i->loginAsAdmin();
        $i->click(['css' => 'a[title=Schoolbox\ Admin]']);
        $i->executeJS("var scrollableDiv = document.getElementById('account-content');
        scrollableDiv.scrollTop=scrollableDiv.scrollHeight - scrollableDiv.offsetHeight;");
        $i->waitForText($fullname, 5);
        $i->click($fullname, '#switch-user');
        $i->deleteSessionSnapshot($fullname);
        $i->deleteSessionSnapshot('login:alaress');
        $i->skipWhatsNewTour();
    }

    /**
     * Impersonate a user via user profile drop
     * @param $fullname
     * @throws Exception
     */
    public function impersonateAnyUser($fullname): void
    {
        $i = $this;
        // $i->amOnPage('/user/recoverSession');
        $i->amGoingTo('impersonate a user' . $fullname);
        $i->click('#profile-drop');
        $i->executeJS("var scrollableDiv = document.getElementById('account-content');
        scrollableDiv.scrollTop=scrollableDiv.scrollHeight - scrollableDiv.offsetHeight;");
        $i->waitForText($fullname, 5);
        $i->click($fullname, '#switch-user');
        $i->deleteSessionSnapshot($fullname);
        $i->deleteSessionSnapshot('login:alaress');
        $i->skipWhatsNewTour();
    }

    /**
     * Impersonate a user via modify screen by passing their title and fullname
     * @param $userId
     * @throws Exception
     */
    public function impersonateViaModifyUserPage($fullname): void
    {
        $i = $this;
        $i->amGoingTo('impersonate a user' . $fullname);
        $i->logout();
        $i->loginAsAdmin();
        $i->searchBar($fullname);
        $i->click('a[title="Modify User"]');
        $i->clickElementInWebComponent('[data-test=sbx-pop-out-header-options]', '[data-test=sbx-pop-out-header-options-button]');
        $i->click('a[title="Login as user"]');
        $i->waitForText($fullname, 5);
        $i->deleteSessionSnapshot($fullname);
        $i->deleteSessionSnapshot('login:alaress');
        $i->skipWhatsNewTour();
    }

    /**
     * search for an user using the search bar
     * @param $fullname
     * @throws Exception
     */
    public function searchBar($fullname): void
    {
        $i = $this;
        $i->amGoingTo('search for an user' . $fullname);
        $i->click('button.icon-search.no-text');
        $i->fillField('input#search-field', $fullname);
        $i->click(['css' => 'a[data-test="header-search-user-result-link-' . $fullname . '"]']);
        $i->waitForText($fullname, 5);
    }

    /**
     * Fill in CKEditor 5
     * @param $elementId
     * @param $content
     * @throws Exception
     */
    public function fillCkEditorById($elementId, $content): void
    {
        $this->executeJS(sprintf('window.sbProps.editors["%s"].setData("<p>%s</p>");', $elementId, $content));
    }

    /**
     * Fill in CKEditor - this is for CKEditor 4 and will not work for CkEditor 5
     * @param $element_name
     * @param $content
     */
    public function fillCkEditorByName($element_name, $content): void
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
    public function fillTinyMceEditorById($id, $content): void
    {
        $this->fillTinyMceEditor('id', $id, $content);
    }
    /**
     * Fill in TinyMice editor
     * @param $name
     * @param $content
     */
    public function fillTinyMceEditorByName($name, $content): void
    {
        $this->fillTinyMceEditor('name', $name, $content);
    }
    /**
     * Fill in TinyMice editor
     * @param $attribute
     * @param $value
     * @param $content
     */
    private function fillTinyMceEditor($attribute, $value, $content): void
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
    private function fillRteEditor($selector, $content): void
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
    public function getCurrentUrl(): mixed
    {
        return $this->executeJS("return location.href");
    }
    /**
     * @throws Exception
     */
    public function scrollAndClick($selector): void
    {
        $this->scrollTo($selector);
        $this->waitForElementVisible($selector, 5);
        $this->click($selector);
    }

    /**
     * @throws Exception
     */
    public function skipWhatsNewTour(): void
    {
        try {
            $this->seeElement('div.introjs-tooltipbuttons a');
            $this->click('a.introjs-button introjs-skipbutton');
        } catch (Exception $e) {
        }
    }

    /**
     * To click an element inside a web component
     * @param $selector - the web component to target
     * @param $inSelector - the element within the web component to click
     */
    public function clickElementInWebComponent($selector, $inSelector): void
    {
        $this->executeJS('document.querySelector("' . $selector . '").shadowRoot.querySelector("' . $inSelector . '").click();');
    }


    /**
     * Generate a data-test query selector for the given base format and optional IDs.
     *
     * @param string $baseFormat The base data-test value (e.g., "sp-results-result").
     * @param bool $useStartsWith Optional whether to write a selector for elements starting with baseFormat, default false
     * @param array $ids Optional array of identifiers (e.g., [1, 2] for suffixes of "-1" and "-2").
     * @return string The query selector. (e.g. "[data-test="sp-results-result-1-2"]")
     */
    public static function getTestSelector(string $baseFormat, bool $useStartsWith = false, array $ids = []): string
    {
        $selector = "[data-test";
        if ($useStartsWith) {
            $selector .= "^";
        }
        $dataValue = $baseFormat;
        foreach ($ids as $id) {
            $dataValue .= "-$id";
        }
        $selector .= "=\"$dataValue\"]";

        return $selector;
    }
}
