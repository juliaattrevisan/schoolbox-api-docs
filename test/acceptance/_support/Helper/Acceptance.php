<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Codeception\Module\WebDriver;
use Facebook\WebDriver\Remote\RemoteWebDriver;
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


    /**
     * Fill in a rich text area.
     *
     * @param string $field
     * @param string $value
     */
    public function fillRichTextField(string $field, string $value)
    {
        /** @var WebDriver $wd */
        $wd = $this->getModule('WebDriver');
        $wd->executeInSelenium(
            function (RemoteWebDriver $webDriver) use ($field, $value) {
                $webDriver->executeScript(
                    sprintf('CKEDITOR.instances["%s"].setData("%s")', addslashes($field), addslashes($value))
                );
            }
        );
    }


//    /**
//     * Select the given options in a checkbox group.
//     *
//     * @param string $group
//     * @param array  $options
//     */
//    public function checkSelectGroupOptions(string $group, array $options)
//    {
//        // Get ID of all available options
//        $optionIds = $this->grabMultiple(
//            sprintf('input[type=checkbox][data-select-all-group="%s"]', addslashes($group)),
//            'id'
//        );
//
//        // Check every option provided in $options, uncheck any not provided
//        foreach ($optionIds as $optionId) {
//            // Find the matching checkbox, and check/uncheck it
//            $optionLabel = $this->grabTextFrom(sprintf('label[for="%s"]', addslashes($optionId)));
//            if (\in_array($optionLabel, $options, true)) {
//                $this->checkOption('#'.$optionId);
//            } else {
//                $this->uncheckOption('#'.$optionId);
//            }
//        }
//    }
}
