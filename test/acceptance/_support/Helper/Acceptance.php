<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Codeception\Module\WebDriver;
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
        /** @var WebDriver $wd */
        $wd = $this->getModule('WebDriver');

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

        /** @var WebDriver $wd */
        $wd = $this->getModule('WebDriver');
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
}
