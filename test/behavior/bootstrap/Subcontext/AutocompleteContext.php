<?php
/**
 * Contains test steps that relate to autocomplete form inputs.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Behat\Mink\Element;

class Subcontext_AutocompleteContext extends BaseContext {

    /**
     * Types into an autocomplete box. This should open a list of options, one
     * of which can be selected.
     *
     * @When /^(?:|I )fill in autocomplete "(?P<field>[^"]*)" with "(?P<value>[^"]*)"$/
     */
    public function fillInAutocomplete($field, $value) {

        // Really convoluted process required to initialise stupid autocompletes.
        // The hidden input I use to find the autocomplete doesn't exist until I
        // focus on the element, and there's no other way to find it.
        foreach ($this->getSession()->getPage()->findAll('css', '.ms-ctn input') as $autocompleteInput) {
            /** @var Element\NodeElement $autocompleteInput */
            $autocompleteInput->focus();
        }

        // Find $field
        // Find .ms-sel-ctn above that
        // Type into text input inside that
        /** @var Element\NodeElement $autocompleteBase */
        $autocompleteBase = $this->getSession()->getPage()->find('css', 'input[name="'.$field.'"]');
        if (!($autocompleteBase instanceof Element\NodeElement)) {
            throw new Exception('Could not find autocomplete for field named "'.$field.'"');
        }
        $typeInput = $autocompleteBase->getParent()->find('css', 'input[type="text"]');
        if (!($typeInput instanceof Element\NodeElement)) {
            throw new Exception('Could not find autocomplete input for field named "'.$field.'"');
        }
        $typeInput->setValue($value);
    }

    /**
     * Selects an option from an autocomplete popup.
     *
     * @When /^(?:|I )select "(?P<option>[^"]*)" from autocomplete "(?P<field>[^"]*)"$/
     */
    public function selectFromAutocomplete($option, $field) {
        // Wait for it to actually show
        $this->getCoreContext()->waitForElementOnPage('.ms-res-ctn');

        // Iterate over each one, and try to select it.
        /** @var Element\NodeElement[] $optionList */
        $optionList = $this->getSession()->getPage()->findAll('css', '.ms-res-ctn .ms-res-item');
        foreach ($optionList as $optionItem) {
            if (mb_strpos($optionItem->getText(), $option) !== false) {
                $optionItem->click();
                return;
            }
        }
        throw new Exception('Could not find autocomplete option "'.$option.'" for field named "'.$field.'"');
    }

}