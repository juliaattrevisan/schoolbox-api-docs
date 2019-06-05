<?php

use Codeception\Util\Locator;

class DueWorkInitialCest
{

    public function createAssessment(AcceptanceTester $i)
    {
        $i->login('alaress', 'alaress');
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resource/folder/create/0");
        $i->selectOption("template", "Blank");
        $i->fillField(['name'=>'name'], 'Test Class Homepage');
        $i->click(['css'=>'label[class=radio-label][for=groupWrite1]']);
        $i->click(['css'=>'label[class=radio-label][for=groupWrite2]']);
        $i->click(['css'=>'label[class=radio-label][for=groupRead3]']);
        $i->click(['css'=>'label[class=radio-label][for=groupRead4]']);
        $i->click(['css'=>'label[class=radio-label][for=groupRead5]']);
        $i->click(['css'=>'input[type=submit]']);
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee("Test Class Homepage");
        $i->click("Test Class Homepage");
        $i->waitForText("Homepage", 5);
        $i->click("Homepage");
        $home_page_url=$i->getCurrentUrl();
        $i->click('a#all-actions.icon-more');
        $i->click('ul > li a.icon-group');
        $i->waitForText("Manage Group", 5);
        $i->click('Add Members');
        $i->clickWithLeftButton('div.selectize-input.items.not-full');
        $i->pressKey('div.selectize-input.items.not-full input', 'David Rhodes');
        sleep(1);
        $i->pressKey('div.selectize-input.items.not-full input', WebDriverKeys::ENTER);
        $i->clickWithLeftButton('div.selectize-input.items.not-full');
        $i->pressKey('div.selectize-input.items.not-full input', 'Zane Smith');
        sleep(1);
        $i->pressKey('div.selectize-input.items.not-full input', WebDriverKeys::ENTER);
        $i->click('input[type=submit][value=Add\ Users]');
        $i->click('label.input-toggle[for=accessLevel-151-3]');
        sleep(5);
        $i->amOnUrl($home_page_url);
        $i->click(['css'=>'label[for=component-lock-toggle]']);
        $i->canSeeElement(['css'=>'span[class=icon-unlocked]']);
        $i->click(['css'=>'a#add-component']);
        sleep(1);
        $i->canSeeElement(['css'=>'div#tabs-1']);
        $i->click(['css'=>'a[href=\#tabs-sub-3]']);
        $i->canSeeElement(['css'=>'ul#tabs-sub-3']);
        $i->dragAndDrop("//*[@id='tabs-sub-3']/li[6]","//div[@data-position='top']");
        sleep(2);
        $i->canSeeElement(['css'=>'div[class=component-container\ Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller]']);
        $i->click('Create due work');
        $i->waitForText("Create Due Work", 5);
        $i->fillField("title", "Test Due Work");
        $i->selectOption('workType', '7');
        $i->fillCkEditorById('assessment-description', 'We need more fish.');
        $i->selectOption('acceptOnline', '1');
        $i->fillField('weight', '5');
        $i->click('input[type=submit][value=Create]');
        $i->waitForElementVisible(['css'=>'div[class=component-container\ Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller]'], 5);
        $i->canSee('Test Due Work');
    }
}