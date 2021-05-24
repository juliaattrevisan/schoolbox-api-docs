<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Homepage;

use AcceptanceTester;
use Codeception\Util\Locator;
use Page\Common;
use Page\Homepage;

/**
 * Test for attaching a textbox to a page.
 */
class TextboxCest
{

    var $createFolderUrl = 'https://vagrant.dev.schoolbox.cloud/resource/folder/0/create';

    /**
     * @param AcceptanceTester $i
     */
    public function createHomepage(AcceptanceTester $i): void
    {
        $i->login("alaress", "alaress");
        $i->amOnUrl($this->createFolderUrl);
        $i->selectOption("template", "Blank");
        $i->fillField(['name' => 'name'], 'Test TextBox Homepage');
        $i->click(['css' => 'label[class=radio-label][for=groupWrite1]']);
        $i->click(['css' => 'label[class=radio-label][for=groupWrite2]']);
        $i->click(['css' => 'label[class=radio-label][for=groupRead3]']);
        $i->click(['css' => 'label[class=radio-label][for=groupRead4]']);
        $i->click(['css' => 'label[class=radio-label][for=groupRead5]']);
        $i->click(['css' => 'label[class=radio-label][for=groupRead6]']);
        $i->click(['css' => 'input[type=submit]']);
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee("Test TextBox Homepage");
    }

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
     * @depends createHomepage
     * @throws \Exception
     */
    public function createTextbox(AcceptanceTester $i, Homepage $homepage, Homepage\TextboxForm $textboxForm): void
    {
        $i->login("jcoles", "password");
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");

        $i->waitForText("Test TextBox Homepage");
        $testHomepage = Locator::contains('a.jstree-anchor', 'Test TextBox Homepage');
        $i->click($testHomepage);
        $i->waitForText("Homepage", 5);
        sleep(1);
        $i->click("a.icon-home");

        $homepage->addComponent(Homepage::ELEMENT_ADD_COMPONENT_SECTION_INFORMATION, 'Text Box');

        // Enter content into the new component
        $layout   = Homepage::ELEMENT_COMPONENT_LAYOUT_TOP;
        $position = 1;

        $homepage->clickComponentEmptyStateLink($layout, $position);
        sleep(2);
        $heading = 'Test Text Box Title';
        $content = 'I am textbox content';
        $textboxForm
            ->fillHeading($heading)
            ->submit()
            ->fillContent($content)
            ->save();

        // Verify what I entered is the heading and body
        $i->canSee("Test Text Box Title");
        $i->canSee("I am textbox content");
    }
}
