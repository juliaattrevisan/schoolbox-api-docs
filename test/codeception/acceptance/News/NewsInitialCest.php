<?php

use Codeception\Util\Locator;

class NewsInitialCest
{

    public function createNews(AcceptanceTester $i)
    {
        $i->amOnPage('/login/');
        $i->login("alaress", "alaress");
        $i->amOnPage('/news');
        $i->waitForElementVisible(['css' => 'a.icon-add[title=Create\ news]'], 5);
        $i->clickWithLeftButton(['css' => 'a.icon-add[title=Create\ news]']);
        $i->fillField("title", "Test News Title");
        $i->fillCkEditorById('body', 'wow what interesting news');
        $i->click(['css' => 'button.button.icon-drag-drop'], ['css' => 'div#audience-selectize-group']);
        $i->waitForText('Year Levels');
        $element = Locator::contains('button.button-block', 'Year Levels');
        $i->clickWithLeftButton($element);
        $i->executeJS("var scrollableDiv = document.getElementsByClassName('checklist checklist-container');
        scrollableDiv.scrollTop=scrollableDiv.scrollHeight - scrollableDiv.offsetHeight;");
        $i->waitForText('Year 12', 5);
        $Year_12_element = Locator::contains('div.checklist-wrapper', 'Year 12');
        $i->clickWithLeftButton($Year_12_element);
        $i->click('button.button.submit');
        sleep(5);
        $i->click("Publish news");
        sleep(5);
        $i->see('Test News Title');
    }

    public function unpublishNews(AcceptanceTester $i)
    {
        $i->amOnPage('/login/');
        $i->login("alaress", "alaress");
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        $i->click(['css' => 'a.icon-more[title=Actions]']);
        $i->click(['css' => 'a.icon-undo']);
        $i->fillField(['css' => "textarea#rejectedReason"], "Testing unpublish");
        $i->waitForElementVisible(['css' => 'a[class=button\ submit]'], 5);
        $i->click(['css' => 'a[class=button\ submit]']);
    }

    public function publishNews(AcceptanceTester $i)
    {
        $i->amOnPage('/login/');
        $i->login("alaress", "alaress");
        $i->amOnPage('/news/me?status=Rejected');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        $i->click(['css' => 'input#article-submit[value=Publish\ news]']);
    }

    public function audienceViewsNewsFeed(AcceptanceTester $i)
    {
        $i->amOnPage('/login/');
        $i->login("alaress", "alaress");
        $i->impersonate("Rachel Rhodes");
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->seeElement($element);
    }

    public function audienceViewsNewsItem(AcceptanceTester $i)
    {
        $i->amOnPage('/login/');
        $i->login("alaress", "alaress");
        $i->impersonate("Rachel Rhodes");
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        $i->see("Test News Title");
    }

    public function enableNewsComments(AcceptanceTester $i)
    {
        $i->amOnPage('/login/');
        $i->login("alaress", "alaress");
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        sleep(1);
        $i->dontSeeElement(['css'=>'div#cke_comment']);
        $i->click(['css'=> 'label[for=allow-comments]']);
    }

    public function addTwoNewsComments(AcceptanceTester $i)
    {
        $i->amOnPage('/login/');
        $i->login("alaress", "alaress");
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        $i->waitForElementVisible(['css'=>'div#cke_comment'], 5);
        $i->executeJS('CKEDITOR.instances[\'comment\'].setData("indeed this is intresting news");');
        sleep(2);
        $i->click(['css' => 'a[class=button\ submit]']);
        sleep(3);
        $i->executeJS('CKEDITOR.instances[\'comment\'].setData("its poop, like your face");');
        sleep(2);
        $i->click(['css' => 'a[class=button\ submit]']);
        sleep(3);
    }

    public function deleteNewsComments(AcceptanceTester $i)
    {
        $i->amOnPage('/login/');
        $i->login("alaress", "alaress");
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        $i->click(['css'=>'a.icon-delete.no-text']);
        $i->waitForElementVisible('a.button.icon-approve.submit', 5);
        $i->click(['css'=>'a.button.icon-approve.submit']);
    }

    public function disableNewsComments(AcceptanceTester $i)
    {
        $i->amOnPage('/login/');
        $i->login("alaress", "alaress");
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        $i->waitForElementVisible(['css'=>'div#cke_comment'], 1);
        $i->click(['css'=> 'label[for=allow-comments]']);
    }

    public function deleteNews(AcceptanceTester $i)
    {
        $i->amOnPage('/login/');
        $i->login("alaress", "alaress");
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        $i->click(['css'=>'a.icon-more[title=Actions]']);
        $i->click(['css'=>'a.icon-delete']);
        $i->waitForElementVisible(['css'=>'a[class=button\ submit]'], 5);
        $i->click(['css'=>'a[class=button\ submit]']);
    }

}

//descendant-or-self::button[@class and contains(concat(' ', normalize-space(@class), ' '), ' button-block ')][contains(., 'Year Levels')]