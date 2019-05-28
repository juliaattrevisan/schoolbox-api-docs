<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Homepage;

use AcceptanceTester;
use Codeception\Util\Locator;
use Nette\Utils\Random;
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
        $i->login("alaress", "alaress");
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee("Test Homepage");
        $i->click("Test Homepage");
        $i->waitForText("Homepage", 5);
        $i->click("Homepage");
        $i->click(['css'=>'label[for=component-lock-toggle]']);
        $i->canSeeElement(['css'=>'span[class=icon-unlocked]']);
        $i->click(['css'=>'a#add-component']);
        sleep(1);
        $i->canSeeElement(['css'=>'div#tabs-1']);
        #Blog Test
        $i->click(['css'=>'a[href=\#tabs-sub-1]']);
        $i->canSeeElement(['css'=>'ul#tabs-sub-1']);
        $i->dragAndDrop("//*[@id='tabs-sub-1']/li[1]","//div[@data-position='top']");
        sleep(1);
        $i->canSeeElement(['css'=>'div[class=component-container\ Schoolbox_Blog_Homepage_Component_Controller]']);
        #News Test
        $i->click(['css'=>'a[href=\#tabs-sub-1]']);
        $i->canSeeElement(['css'=>'ul#tabs-sub-1']);
        $i->dragAndDrop("//*[@id='tabs-sub-1']/li[4]","//div[@data-position='top']");
        sleep(1);
        $i->canSeeElement(['css'=>'div[class=component-container\ Schoolbox_Comms_News_Component_Homepage_Controller]']);
        #Text box Test
        $i->click(['css'=>'a[href=\#tabs-sub-1]']);
        $i->canSeeElement(['css'=>'ul#tabs-sub-1']);
        $i->dragAndDrop("//*[@id='tabs-sub-1']/li[6]","//div[@data-position='top']");
        sleep(1);
        $i->canSeeElement(['css'=>'div[class=component-container\ Component_Homepage_TextBoxController]']);
    }

    #@depends createHomepage
    #@depends createElement
    public function useElement(AcceptanceTester $i): void
    {
        $i->login("alaress", "alaress");
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee("Test Homepage");
        $i->click("Test Homepage");
        $i->waitForText("Homepage", 5);
        $i->click("Homepage");
        $home_page_url=$i->getCurrentUrl();
        $i->canSeeElement(['css'=>'div[class=component-container\ Schoolbox_Blog_Homepage_Component_Controller]']);
        $i->click("Add a blog post");
        $i->canSee("Post Blog");
        $i->fillField("heading","Test Blog Title");
        $i->fillCkEditorById('body', 'Interesting blog text');
        $i->click(['css'=>'input[type=submit]']);
        $i->amOnUrl($home_page_url);
        $i->canSeeElement(['css'=>'div[class=component-container\ Schoolbox_Comms_News_Component_Homepage_Controller]']);
        $i->click("Post a news article");
        $i->fillField("title","Test News Title");
        $i->fillCkEditorById('body', 'wow what interesting news');
        sleep(5);
        $i->click("Publish news");
        sleep(5);
        $i->amOnUrl($home_page_url);
        $i->canSeeElement(['css'=>'div[class=component-container\ Component_Homepage_TextBoxController]']);
        $i->click("Add text and multimedia, or embed content");
        sleep(5);
        $i->fillField("textBoxHeading","Test Text Box Title");
        $i->fillCkEditorById('textBoxBody', 'wow what interesting Text box text');
        $i->scrollTo(['css'=>'div[class=component-container\ Component_Homepage_TextBoxController]']);
        sleep(1);
        $i->click(['css'=>'input[type=submit][value=Save\ Content]']);
    }

    #@depends createHomepage
    #@depends editElement
    public function viewElement(AcceptanceTester $i): void
    {
        $i->login("alaress", "alaress");
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee("Test Homepage");
        $i->click("Test Homepage");
        $i->waitForText("Homepage", 5);
        $i->click("Homepage");
        echo "Not done yet";
    }

    #@depends createHomepage
    #@depends viewElement
    public function deleteElement(AcceptanceTester $i): void
    {
        $i->login("alaress", "alaress");
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee("Test Homepage");
        $i->click("Test Homepage");
        $i->waitForText("Homepage", 5);
        $i->click("Homepage");
        echo "Not done yet";
    }

    #@depends createHomepage
    #@depends deleteElement
    public function deleteHomepageFail(AcceptanceTester $i): void
    {
        $i->login("alaress", "alaress");
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee("Test Homepage");
        $i->click("Test Homepage");
        $i->waitForText("Delete", 5);
        $i->click("Delete");
        $i->waitForText("Please Confirm", 5);
        $i->click("No");
        sleep(1);
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee("Test Homepage");
    }

    #@depends createHomepage
    #@depends deleteElement
    public function deleteHomepage(AcceptanceTester $i): void
    {
        $i->login("alaress", "alaress");
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee("Test Homepage");
        $i->click("Test Homepage");
        $i->waitForText("Delete", 5);
        $i->click("Delete");
        $i->waitForText("Please Confirm", 5);
        $i->click("Yes");
        sleep(1);
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->cantSee("Test Homepage");
    }
}