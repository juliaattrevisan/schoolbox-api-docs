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
class HomepageInitialCest
{
    public function createHomepageFail(AcceptanceTester $i): void
    {
        $i->login("alaress", "alaress");
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resource/folder/create/0");
        $i->selectOption("template", "Blank");
        $i->fillField(['name'=>'name'], 'Test Homepage');
        $i->click(['css'=>'label[class=radio-label][for=groupWrite1]']);
        $i->click(['css'=>'label[class=radio-label][for=groupWrite2]']);
        $i->click(['css'=>'label[class=radio-label][for=groupRead3]']);
        $i->click(['css'=>'label[class=radio-label][for=groupRead4]']);
        $i->click(['css'=>'label[class=radio-label][for=groupRead5]']);
        echo "Forget to submit \n";
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->cantSee("Test Homepage");
    }

    public function createHomepage(AcceptanceTester $i): void
    {
        $i->login("alaress", "alaress");
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resource/folder/create/0");
        $i->selectOption("template", "Blank");
        $i->fillField(['name'=>'name'], 'Test Homepage');
        $i->click(['css'=>'label[class=radio-label][for=groupWrite1]']);
        $i->click(['css'=>'label[class=radio-label][for=groupWrite2]']);
        $i->click(['css'=>'label[class=radio-label][for=groupRead3]']);
        $i->click(['css'=>'label[class=radio-label][for=groupRead4]']);
        $i->click(['css'=>'label[class=radio-label][for=groupRead5]']);
        $i->click(['css'=>'input[type=submit]']);
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee("Test Homepage");
    }

    #@depends createHomepage
    public function createElement(AcceptanceTester $i): void
    {
        echo "Not done yet";
    }

    #@depends createHomepage
    #@depends createElement
    public function editElement(AcceptanceTester $i): void
    {
        echo "Not done yet";
    }

    #@depends createHomepage
    #@depends editElement
    public function viewElement(AcceptanceTester $i): void
    {
        echo "Not done yet";
    }

    #@depends createHomepage
    #@depends viewElement
    public function deleteElement(AcceptanceTester $i): void
    {
        echo "Not done yet";
    }

    #@depends createHomepage
    #@depends deleteElement
    public function deleteHomepage(AcceptanceTester $i): void
    {
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee("Test Homepage");
        echo "Not done yet";
    }
}