<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Homepage;

use AcceptanceTester;
use Page\Common;
use Page\Homepage;

/**
 * Test for attaching a textbox to a page.
 */
class TextboxCest
{
    /**
     * Test that a news item I create retains the settings I chose
     *
     * as zsmith:
     * * go to http://192.168.36.10/homepage/code/FREA1
     * * add a text box component
     * * add text to it
     * * enter:
     * ** a heading
     * ** content
     * *** including uploaded images
     * * save it
     * * verify that:
     * ** what I entered is there, including images
     * ** component's heading is what I entered
     * * modify it again
     * * verify that:
     * ** the heading is as I entered it
     * ** the content is as I entered it
     *
     * @param AcceptanceTester     $i
     * @param Homepage             $homepage
     * @param Homepage\TextboxForm $textboxForm
     */
    public function createTextbox(AcceptanceTester $i, Homepage $homepage, Homepage\TextboxForm $textboxForm): void
    {
        $i->login('zsmith', 'password');

        $i->amOnPage($homepage->generateRoute(Common::CLASS_ID_CHEMISTRY_1_3));

        $homepage->addComponent(Homepage::ELEMENT_ADD_COMPONENT_SECTION_INFORMATION, 'Text Box');

        // Enter content into the new component
        $homepage->clickComponentEmptyStateLink(Homepage::ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        $heading = 'Heading';
        $content = '<p>I am textbox content</p>';
        $textboxForm
            ->fillHeading($heading)
            ->fillContent($content)
            ->submit();
    }
}
