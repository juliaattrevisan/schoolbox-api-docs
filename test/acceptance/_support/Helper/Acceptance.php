<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Codeception\Module\WebDriver;
use Codeception\Util\Locator;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;

class Acceptance extends \Codeception\Module
{
    /**
     * Find a checkbox to "click".
     *
     * @param $option
     * @return WebDriverElement
     */
    private function findCheckbox($option)
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
    private function findLabelForCheckbox(WebDriverElement $checkbox)
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
    public function checkOption($option)
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
    public function uncheckOption($option)
    {
        $checkbox = $this->findCheckbox($option);
        if ($checkbox->isSelected()) {
            $this->findLabelForCheckbox($checkbox)->click();
        }
    }


    /**
     * Fill in a rich text area.
     *
     * @param string $field
     * @param string $value
     */
    public function fillRichTextField(string $field, string $value)
    {
        $wd = $this->getWebDriverModule();
        $wd->executeInSelenium(
            function (RemoteWebDriver $webDriver) use ($field, $value) {
                $webDriver->executeScript(
                    sprintf('CKEDITOR.instances["%s"].setData("%s")', addslashes($field), addslashes($value))
                );
            }
        );
    }


    /**
     * Select the given options in a checkbox group.
     *
     * @param string $group
     * @param array  $options
     */
    public function checkSelectGroupOptions(string $group, array $options)
    {
        $wd = $this->getWebDriverModule();

        // Get ID of all available options
        /** @var WebDriverElement[] $elements */
        $elements  = $wd->_findElements([
            'css' => sprintf('input[type=checkbox][data-select-all-group="%s"]', addslashes($group))
        ]);
        $optionIds = [];
        foreach ($elements as $element) {
            $optionIds[] = $element->getAttribute('id');
        }

        // Check every option provided in $options, uncheck any not provided
        foreach ($optionIds as $optionId) {
            // Find the matching checkbox, and check/uncheck it
            $optionLabel = $wd->grabTextFrom(sprintf('label[for="%s"]', addslashes($optionId)));
            if (\in_array($optionLabel, $options, true)) {
                $this->checkOption('#' . $optionId);
            } else {
                $this->uncheckOption('#' . $optionId);
            }
        }
    }


    /**
     * Fill a date field, and waits for the strottime thing it has to finish
     * processing (and re-enable the element).
     *
     * @param string|array $field A field locator
     * @param string       $value The value to enter
     * @throws \Codeception\Exception\ModuleException
     */
    public function fillDateField($field, $value): void
    {
        $wd = $this->getWebDriverModule();
        $wd->fillField($field, $value);

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
     * Removes all selected options from a selectize.
     *
     * @param string $field Currently, must be a name
     * @param string $selectizeSearch
     */
    public function clearSelectize(string $field)
    {
        $wd = $this->getWebDriverModule();

        // Find all elements in the selectize; click remove button
        foreach ($wd->_findElements($this->getSelectizeItemsSelector($field)) as $item) {
            /** @var RemoteWebElement $item */
            $item->findElement(WebDriverBy::className('remove'))->click();
        }
    }


    /**
     * Removes a selected option from a selectize.
     *
     * @param string $field Currently, must be a name
     * @param string $selectizeSearch
     */
    public function unselectSelectizeOption(string $field, string $selectizeSearch)
    {
        $wd = $this->getWebDriverModule();

        // Find all elements in the selectize; click remove button for those who match
        foreach ($wd->_findElements($this->getSelectizeItemsSelector($field)) as $item) {
            /** @var RemoteWebElement $item */
            if (strpos($item->getText(), $selectizeSearch) !== false) {
                $item->findElement(WebDriverBy::className('remove'))->click();
            }
        }
    }


    /**
     * Searches for a selectize option, and adds it. If it can't find it,
     * raises exception.
     *
     * @param string $field Currently, must be a name
     * @param string $selectizeSearch
     */
    public function selectSelectizeOption(string $field, string $selectizeSearch)
    {
        $wd = $this->getWebDriverModule();

        // Fill the selectize, wait for its result, select the first
        $wd->fillField($this->getSelectizeInputSelector($field), $selectizeSearch);
        $wd->waitForElement(['css' => '.selectize-dropdown-content .option.active']);
        $wd->click(['css' => '.selectize-dropdown-content .option.active']);
    }


    /**
     * Wait for all AJAX requests to complete.
     *
     * Relies on `jQuery.active` being set.
     *
     * @param int $timeout
     * @throws \Codeception\Exception\ModuleException
     */
    public function waitForAjaxDone($timeout = 30): void
    {
        $this->getWebDriverModule()->waitForJS("return jQuery.active === 0;", $timeout);
    }


    /**
     * Return webdriver module in a type-safe manner.
     *
     * @return WebDriver
     * @throws \Codeception\Exception\ModuleException
     */
    private function getWebDriverModule(): WebDriver
    {
        return $this->getModule('WebDriver');
    }


//    private function findField(WebDriver $webDriver, $field): ?WebDriverElement
//    {
//        // Are you kidding me
//        $reflectionObject = new \ReflectionObject($webDriver);
//        $reflectionMethod = $reflectionObject->getMethod('findField');
//        $reflectionMethod->setAccessible(true);
//        return $reflectionMethod->invoke($field);
//    }
}
