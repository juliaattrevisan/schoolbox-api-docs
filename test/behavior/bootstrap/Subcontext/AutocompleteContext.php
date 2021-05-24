<?php

/**
 * Contains test steps that relate to autocomplete form inputs.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Behat\Mink\Element;

class Subcontext_AutocompleteContext extends BaseContext
{

    /**
     * Really convoluted process required to initialise stupid autocompletes.
     * The hidden input I use to find the autocomplete doesn't exist until I
     * focus on the element, and there's no other way to find it.
     */
    protected function preconfigureAutocompletes()
    {
        foreach ($this->getSession()->getPage()->findAll('css', 'input[type="text"]') as $autocompleteInput) {
            /** @var Element\NodeElement $autocompleteInput */
            $autocompleteInput->focus();
        }
    }

    /**
     * Gets the container for this autocomplete component.
     *
     * @param  string $field
     * @return Element\NodeElement
     * @throws Exception if the container wasn't found.
     */
    protected function getAutocompleteContainer($field)
    {
        $container = $this->getSession()->getPage()->find('css', 'input[name="' . $field . '"], select[name="' . $field . '"]');
        if (!($container instanceof Element\NodeElement)) {
            throw new Exception('Could not find autocomplete for field named "' . $field . '"');
        }
        return $container->getParent();
    }

    /**
     * Types into an autocomplete box. This should open a list of options, one
     * of which can be selected.
     *
     * @When /^(?:|I )fill in autocomplete "(?P<field>[^"]*)" with "(?P<value>[^"]*)"$/
     */
    public function fillInAutocomplete($field, $value)
    {
        $typeInput = $this->getAutocompleteContainer($field)->find('css', '.selectize-input input[type="text"]');
        if (!($typeInput instanceof Element\NodeElement)) {
            throw new Exception('Could not find autocomplete input for field named "' . $field . '"');
        }
        $typeInput->setValue($value);
    }

    /**
     * Selects an option from an autocomplete popup.
     *
     * @When /^(?:|I )select "(?P<option>[^"]*)" from autocomplete "(?P<field>[^"]*)"$/
     */
    public function selectFromAutocomplete($option, $field)
    {
        // Wait for it to actually show
        $this->getCoreContext()->waitForElementOnPage('.selectize-dropdown-content .option');

        // Iterate over each one, and try to select it.
        /** @var Element\NodeElement[] $optionList */
        $optionList = $this->getSession()->getPage()->findAll('css', '.selectize-dropdown-content .option');
        foreach ($optionList as $optionItem) {
            if (mb_strpos($optionItem->getText(), $option) !== false) {
                $optionItem->click();
                return;
            }
        }
        throw new Exception('Could not find autocomplete option "' . $option . '" for field named "' . $field . '"');
    }

    /**
     * Removes a selected option from an autocomplete.
     *
     * @When /^(?:|I )remove "(?P<option>[^"]*)" from autocomplete "(?P<field>[^"]*)"$/
     */
    public function removeFromAutocomplete($option, $field)
    {
        foreach ($this->getAutocompleteContainer($field)->findAll('css', '.selectize-input .item') as $item) {
            /** @var Element\NodeElement $item */
            if (mb_strpos($item->getText(), $option) !== false) {
                $close = $item->find('css', '.remove');
                if (!($close instanceof Element\NodeElement)) {
                    throw new Exception('Could not remove button for autocomplete option "' . $option . '" for field named "' . $field . '"');
                }
                $close->click();
                return;
            }
        }
        throw new Exception('Could not find autocomplete option "' . $option . '" for field named "' . $field . '"');
    }

    /**
     * Asserts that an autocomplete contains a given selection.
     *
     * @Then /^the autocomplete "(?P<field>[^"]*)" should contain "(?P<option>[^"]*)"$/
     * @Then /^(?:|I )should see "(?P<option>[^"]*)" in the "(?P<field>[^"]*)" autocomplete"$/
     */
    public function assertAutocompleteOptionSelected($option, $field)
    {
        // find the autocomplete
        // Does $option exist within it?
        foreach ($this->getAutocompleteContainer($field)->findAll('css', '.selectize-input .item') as $item) {
            /** @var Element\NodeElement $item */
            if (mb_strpos($item->getText(), $option) !== false) {
                return;
            }
        }
        throw new Exception('Autocomplete option "' . $option . '" for field named "' . $field . '" does not exist');
    }

    /**
     * Asserts that an autocomplete doesn't contain a given selection.
     *
     * @Then /^the autocomplete "(?P<field>[^"]*)" should not contain "(?P<option>[^"]*)"$/
     * @Then /^(?:|I )should not see "(?P<option>[^"]*)" in the "(?P<field>[^"]*)" autocomplete"$/
     */
    public function assertAutocompleteOptionNotSelected($option, $field)
    {
        foreach ($this->getAutocompleteContainer($field)->findAll('css', '.selectize-input .item') as $item) {
            /** @var Element\NodeElement $item */
            if (mb_strpos($item->getText(), $option) !== false) {
                throw new Exception('Autocomplete option "' . $option . '" for field named "' . $field . '" exists when it should not');
            }
        }
    }
}
