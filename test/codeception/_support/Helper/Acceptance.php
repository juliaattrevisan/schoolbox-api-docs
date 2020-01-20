<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Codeception\Exception\ModuleException;
use Codeception\Module\Db;
use Codeception\Module\WebDriver;
use Codeception\Module\Cli;
use Codeception\TestInterface;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;
use Schoolbox\Resource\Storage\HashGenerator;

class Acceptance extends \Codeception\Module
{

    private $originalConfig = [];

    /**
     * Executed before suite
     * @param array $settings
     */
    public function _beforeSuite($settings = [])
    {
        parent::_beforeSuite($settings);
        $this->changeDBConfig('debug_mode', 0);
    }

    /**
     * Executed after suite
     */
    public function _afterSuite()
    {
        $this->restoreDBConfig();
        parent::_afterSuite();
    }

    /**
     * Executed before test
     * @param TestInterface $test
     */
    public function _before(TestInterface $test)
    {
        parent::_before($test);
        $this->changeDBConfig('debug_mode', 0);
        exec('sudo php /vagrant/test/socket/destroy-rabbit-data.php;');
    }

    /**
     * Executed after test
     * @param TestInterface $test
     */
    public function _after(TestInterface $test)
    {
        $this->restoreDBConfig();
        parent::_after($test);
    }

    /**
     * Sets database config value temporally
     * @param string $name
     * @param string $value
     */
    public function changeDBConfig(string $name, string $value)
    {
        $db = $this->getDbModule();
        if (key_exists($name, $this->originalConfig)) {
            // already stored
            return;
        }

        $currentValue = $db->grabFromDatabase('config', 'value', ['name' => $name]);
        if ($value === $currentValue) {
            // no need to change
            return;
        }

        $this->originalConfig[$name] = $currentValue;
        $db->updateInDatabase('config', ['value' => $value], ['name' => 'debug_mode']);

    }
    
    /**
     * Adds a user to the schoolbox if he doesn't already exist.
     * @param string $username
     * @param string $password
     */
    public function addUser(string $username, string $password)
    {
        $db = $this->getDbModule();
        $currentValue = $db->grabFromDatabase('user', 'username', ['username' => $username]);
        if ($currentValue===NULL)
        {
            $db->_insertInDatabase('user', ['username' => $username], ['password' => $password]);
        }
    }

    /**
     * Resets database value back
     */
    public function restoreDBConfig()
    {
        $db = $this->getDbModule();
        foreach ($this->originalConfig as $name => $value) {
            $db->updateInDatabase('config', ['value' => $value], ['name' => $name]);
        }
    }

    /**
     * Run command on Bash
     * @param string $command
     */
    public function runShellCommand(string $command)
    {
        $cli=$this->getCliModule();
        $cli->runShellCommand($command, false);
    }

    /**
     * Find a checkbox to "click".
     *
     * @param $option
     * @return WebDriverElement
     */
    private function findCheckbox($option): WebDriverElement
    {
        $wd = $this->getWebDriverModule();

        /** @var WebDriverElement[] $elements */
        $elements = $wd->_findElements($option);
        $this->assertNotEmpty($elements);

        return array_shift($elements);
    }

    /**
     * Find the label for this checkbox, and click that instead.
     *
     * @param WebDriverElement $checkbox
     * @return WebDriverElement
     */
    private function findLabelForCheckbox(WebDriverElement $checkbox): WebDriverElement
    {
        $id = $checkbox->getAttribute('id');

        // Must assume that all checkboxes have ID attributes

        $wd = $this->getWebDriverModule();
        /** @var WebDriverElement[] $elements */
        $elements = $wd->_findElements(['css' => "label[for='{$id}']"]);
        $this->assertNotEmpty($elements);

        return array_shift($elements);
    }

    /**
     * Workaround for Selenium refusing to interact with hidden elements.
     *
     * Instead of clicking the checkbox itself, we find and click its label.
     *
     * @param $option
     * @see \Codeception\Module\WebDriver::checkOption()
     */
    public function checkOption($option): void
    {
        $checkbox = $this->findCheckbox($option);
        if (!$checkbox->isSelected()) {
            $this->findLabelForCheckbox($checkbox)->click();
        }
    }

    /**
     * Workaround for Selenium refusing to interact with hidden elements.
     *
     * Instead of clicking the checkbox itself, we find and click its label.
     *
     * @param $option
     * @see \Codeception\Module\WebDriver::uncheckOption()
     */
    public function uncheckOption($option): void
    {
        $checkbox = $this->findCheckbox($option);
        if ($checkbox->isSelected()) {
            $this->findLabelForCheckbox($checkbox)->click();
        }
    }


    /**
     * Normalize space characters to no more than a single space.
     * Used for rich text comparisons.
     *
     * @param string $value
     * @return string
     */
    private function normalizeSpace(string $value): string
    {
        return trim(preg_replace('/\\s+/', ' ', $value));
    }


    /**
     * Fill in a rich text area.
     *
     * @param string $field
     * @param string $value
     */
    public function fillRichTextField(string $field, string $value): void
    {
        $wd = $this->getWebDriverModule();
        $wd->executeJS(
            sprintf('CKEDITOR.instances["%s"].setData("%s")', addslashes($field), addslashes($value))
        );
    }


    /**
     * Assert content of rich text area is equivalent to a value.
     *
     * @param string $field
     * @param string $value
     */
    public function seeInRichTextField(string $field, string $value): void
    {
        // $field could've been set as plain text, without <p> tags.
        // Test both the raw field, and the normalized value too.
        $expectedValue = $this->getWebDriverModule()->executeJS(
            sprintf('return CKEDITOR.instances["%s"].getData()', addslashes($field))
        );
        $this->assertEquals($this->normalizeSpace($value), $this->normalizeSpace($expectedValue));
    }


    /**
     * Finds all available options within a select group.
     *
     * @param string $group
     * @return array key: the checkbox's ID, value: the checkbox label's text
     */
    private function findSelectGroupOptions(string $group): array
    {
        $wd = $this->getWebDriverModule();

        // Get ID of all available options
        /** @var WebDriverElement[] $elements */
        $elements  = $wd->_findElements([
            'css' => sprintf('input[type=checkbox][data-select-all-group="%s"]', addslashes($group))
        ]);
        $options = [];
        foreach ($elements as $element) {
            $id           = $element->getAttribute('id');
            $options[$id] = $wd->grabTextFrom(sprintf('label[for="%s"]', addslashes($id)));
        }
        return $options;
    }


    /**
     * Select the given options in a checkbox group.
     *
     * @param string $group
     * @param array  $options
     */
    public function checkSelectGroupOptions(string $group, array $options): void
    {
        // Check every option provided in $options, uncheck any not provided
        foreach ($this->findSelectGroupOptions($group) as $id => $label) {
            // Find the matching checkbox, and check/uncheck it
            if (\in_array($label, $options, true)) {
                $this->checkOption('#' . $id);
            } else {
                $this->uncheckOption('#' . $id);
            }
        }
    }


    /**
     * Assert selected select group options are exactly as selected.
     *
     * @param string $group
     * @param array  $options
     */
    public function seeSelectGroupOptionsAreSelected(string $group, array $options): void
    {
        $wd = $this->getWebDriverModule();

        // Check every option provided in $options, verify/don't verify all are selected
        foreach ($this->findSelectGroupOptions($group) as $id => $label) {
            if (\in_array($label, $options, true)) {
                $wd->seeCheckboxIsChecked('#' . $id);
            } else {
                $wd->dontSeeCheckboxIsChecked('#' . $id);
            }
        }
    }


    /**
     * Fill a date field, and waits for the strottime thing it has to finish
     * processing (and re-enable the element).
     *
     * @param string|array $field A field locator
     * @param string       $value The value to enter
     */
    public function fillDateField($field, $value): void
    {
        $wd = $this->getWebDriverModule();
        $wd->fillField($field, $value);

        // Trigger blur on whatever I just typed into, to trigger strtotime
        $wd->executeJS("jQuery(':focus').blur()");

        // WebDriver::findField isn't public, and waitForElement can't find by field name.
        // Just wait for AJAX to do stuff.
        $this->waitForAjaxDone(5);
    }


    /**
     * Gets the XPath selector for a selectize container, so the selectize can
     * be interacted with.
     *
     * @param string $field Currently, must be a name
     * @return string
     */
    private function getSelectizeContainerXpath($field): string
    {
        return sprintf('//*[@name="%s"]/..//div[contains(@class, "selectize-input")]', addslashes($field));
    }


    /**
     * Gets the selector for a selectize input.
     *
     * @param string $field Currently, must be a name
     * @return array
     */
    private function getSelectizeInputSelector($field): array
    {
        return [
            'xpath' => $this->getSelectizeContainerXpath($field) . '/input',
        ];
    }


    /**
     * Gets the selector for a selectize's selected items.
     *
     * @param string $field Currently, must be a name
     * @return array
     */
    private function getSelectizeItemsSelector($field): array
    {
        return [
            'xpath' => $this->getSelectizeContainerXpath($field) . '//div[@data-value]',
        ];
    }


    /**
     * Removes a selected option from a selectize by callback
     *
     * @param string   $field    Currently, must be a name
     * @param callable $callback If this returns true, unselect the option
     *                           Takes a `RemoteWebElement` as its parameter.
     */
    public function unselectSelectizeOptionsByCallback(string $field, callable $callback): void
    {
        $wd = $this->getWebDriverModule();

        // Find all elements in the selectize; click remove button for those who match
        foreach ($wd->_findElements($this->getSelectizeItemsSelector($field)) as $item) {
            /** @var RemoteWebElement $item */
            if ($callback($item)) {
                $item->findElement(WebDriverBy::className('remove'))->click();
            }
        }
    }


    /**
     * Removes all selected options from a selectize.
     *
     * @param string $field Currently, must be a name
     */
    public function clearSelectize(string $field): void
    {
        $this->unselectSelectizeOptionsByCallback($field, function () {
            return true;
        });
    }


    /**
     * Removes a selected option from a selectize.
     *
     * @param string $field           Currently, must be a name
     * @param string $selectizeSearch Text in the selected option to match
     */
    public function unselectSelectizeOption(string $field, string $selectizeSearch): void
    {
        $this->unselectSelectizeOptionsByCallback(
            $field,
            function (RemoteWebElement $element) use ($selectizeSearch) {
                return strpos($element->getText(), $selectizeSearch) !== false;
            }
        );
    }


    /**
     * Clears selectize selected options, then searches for all options in
     * `$options`, and for each adds the first match it finds.
     *
     * @param string $field
     * @param array  $options
     * @throws \Exception If waiting for the dropdown timed out
     */
    public function selectSelectizeOptions(string $field, array $options): void
    {
        $this->clearSelectize($field);
        foreach ($options as $option) {
            $this->selectSelectizeOption($field, $option);
        }
    }


    /**
     * Searches for a selectize option, and adds the first one it finds.
     *
     * @param string $field           Currently, must be a name
     * @param string $selectizeSearch Text in the selected option to match
     * @throws \Exception If waiting for the dropdown timed out
     */
    public function selectSelectizeOption(string $field, string $selectizeSearch): void
    {
        $wd = $this->getWebDriverModule();

        // Fill the selectize, wait for its result, select the first
        $wd->fillField($this->getSelectizeInputSelector($field), $selectizeSearch);
        $wd->waitForElement(['css' => '.selectize-dropdown-content .option.active']);
        $wd->click(['css' => '.selectize-dropdown-content .option.active']);
    }


    /**
     * Asserts that selectize field identified by `$field` only has the options
     * in `$options` selected.
     *
     * @param string $field
     * @param array  $options
     */
    public function seeSelectizeOptionsAreSelected(string $field, array $options): void
    {
        $wd = $this->getWebDriverModule();

        $actualOptions = [];
        foreach ($wd->_findElements($this->getSelectizeItemsSelector($field)) as $item) {
            /** @var RemoteWebElement $item */
            // Selectize elements have got a '×' text at the end, for their
            // remove element link: strip it
            $actualOptions[] = preg_replace('/×$/u', '', $item->getText());
        }

        sort($options);
        sort($actualOptions);
        $this->assertEquals($options, $actualOptions);
    }


    /**
     * Attach a file to an attachzone.
     *
     * @param string|array $field Currently, must be the ID of the attachzone
     * @param string       $file
     */
    public function attachAttachzoneFile($field, $file): void
    {
        // Create a file programatically
        $fileScript = sprintf(
            "new File([new Uint8Array([%s])], '%s', {type: '%s'})",
            // Convert this file to a list of bytes
            addslashes(implode(',', array_map('\ord', str_split(file_get_contents($file), 1)))),
            addslashes(basename($file)),
            addslashes(mime_content_type($file))
        );

        // And attach it to the attachzone
        $script = sprintf(
            "jQuery('%s').data('plugin_resourceAttach').dropzone.addFile(%s)",
            addslashes($field),
            $fileScript
        );
        $this->getWebDriverModule()->executeJS($script);
    }


    /**
     * Asserts that an attachzone field has a given file attached.
     *
     * @param string|array $field Currently, must be the ID of the attachzone
     * @param string       $file
     */
    public function seeAttachzoneFile($field, $file): void
    {
        // Extract attached file hashes
        $actualFileHashes = [];
        $script           = sprintf(
            "return jQuery('%s').data('plugin_resourceAttach').dropzone.files.map(function (f) { return f.hash; })",
            addslashes($field)
        );
        foreach ($this->getWebDriverModule()->executeJS($script) as $fileHashes) {
            // Hashes are in the form "source:hash"
            [, $actualFileHash] = explode(':', $fileHashes);
            $actualFileHashes[] = $actualFileHash;
        }

        // determine hash of $file
        $hashGenerator    = new HashGenerator();
        $fp               = fopen($file, 'rb');
        $expectedFileHash = $hashGenerator->generateHash($fp);
        fclose($fp);

        $this->assertContains($expectedFileHash, $actualFileHashes);
    }


    /**
     * Wait for all AJAX requests to complete.
     *
     * Relies on `jQuery.active` being set.
     *
     * @param int $timeout
     */
    public function waitForAjaxDone($timeout = 30): void
    {
        $this->getWebDriverModule()->waitForJS("return jQuery.active === 0;", $timeout);
    }


    /**
     * Switch off jQuery animations.
     *
     * This may be required for tests which test elements that are displayed
     * with `jQuery.fadeIn` or similar methods; they don't yet fully exist
     * whilst animated, so a test which checks for their existence will fail.
     */
    public function disableAnimation(): void
    {
        $this->getWebDriverModule()->executeJS("$.fx.off = true");
    }


    /**
     * Switch on jQuery animations.
     */
    public function enableAnimation(): void
    {
        $this->getWebDriverModule()->executeJS("$.fx.off = false");
    }


    /**
     * Return webdriver module in a type-safe manner.
     *
     * @return WebDriver
     */
    private function getWebDriverModule(): WebDriver
    {
        try {
            return $this->getModule('WebDriver');
        } catch (ModuleException $e) {
            // Wasn't found: return null, let TypeError handle it
        }
        return null;
    }

    /**
     * Get current url from WebDriver
     * @return mixed
     * @throws \Codeception\Exception\ModuleException
     */
    public function getCurrentUrl()
    {
        return $this->getWebDriverModule()->_getCurrentUri();
    }

    /**
     * Return DB module in a type-safe manner.
     *
     * @return Db
     */
    private function getDbModule(): Db
    {
        try {
            return $this->getModule('Db');
        } catch (ModuleException $e) {
            // Wasn't found: return null, let TypeError handle it
        }
        return null;
    }

    /**
     * Return CLI module in a type-safe manner.
     *
     * @return Cli
     */
    private function getCliModule(): Cli
    {
        try {
            return $this->getModule('Cli');
        } catch (ModuleException $e) {
            // Wasn't found: return null, let TypeError handle it
        }
        return null;
    }


    /**
     * Verifies that the notification exists
     * @param $notif
     */
    public function assertNotifExists($notif)
    {
        $i = $this->getWebDriverModule();
        $i->amOnPage('/messages');
        $i->see($notif);
    }

    /**
     * Verifies that the notification does not exist
     * @param $notif
     */
    public function assertNotifNotExists($notif)
    {
        $i = $this->getWebDriverModule();
        $i->amOnPage('/messages');
        $i->dontSee($notif);
    }

//    private function findField(WebDriver $webDriver, $field): ?WebDriverElement
//    {
//        // Are you kidding me
//        $reflectionObject = new \ReflectionObject($webDriver);
//        $reflectionMethod = $reflectionObject->getMethod('findField');
//        $reflectionMethod->setAccessible(true);
//        return $reflectionMethod->invoke($field);
//    }

    /**
     * Restarts the Webdriver's browser.
     *
     * @param array $config Optional extra configuration for the browser to
     *                      restart with.
     */
    public function restartBrowser(array $config = []): void
    {
        $i = $this->getWebDriverModule();
        $i->_restart($config);
    }
}
