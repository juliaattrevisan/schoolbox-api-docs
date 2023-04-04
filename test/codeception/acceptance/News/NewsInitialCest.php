<?php

namespace NewsInitial;

use AcceptanceTester;
use Codeception\Util\Locator;
use Exception;

//TODO: add verification after the actions

class NewsInitialCest
{
    /**
     * @param AcceptanceTester $i
     * @return void
     */
    public function _before(AcceptanceTester $i)
    {
        $i->logout();
        $i->loginAsAdmin();
    }

    /**
     * @throws Exception
     * @beforeSuite
     */
    public function createNews(AcceptanceTester $i)
    {
        $i->amOnPage('/news');
        $i->waitForElementVisible(['css' => 'a.icon-add[title=Create\ news]'], 5);
        $i->clickWithLeftButton(['css' => 'a.icon-add[title=Create\ news]']);
        $i->fillField("title", "Test News Title");
        $i->fillCkEditorById('body', 'wow what interesting news');
        $i->scrollTo(['css' => 'button.button.icon-drag-drop']);
        $i->click(['css' => 'button.button.icon-drag-drop'], ['css' => 'div#audience-selectize-group']);
        $i->waitForText('Year Levels');
        $element = Locator::contains('button.button-block', 'Year Levels');
        $i->clickWithLeftButton($element);
        $i->executeJS("var scrollableDiv = document.getElementsByClassName('checklist checklist-container');
        scrollableDiv.scrollTop=scrollableDiv.scrollHeight - scrollableDiv.offsetHeight;");
        $i->waitForText('Year 9', 5);
        $Year_9_element = Locator::contains('div.checklist-wrapper', 'Year 9');
        $i->clickWithLeftButton($Year_9_element);
        $i->click('button.button.submit');
        sleep(5);
        $i->click("Publish news");
        sleep(5);
        $i->see('Test News Title');
    }

    /**
     * @throws Exception
     * @beforeSuite
     */
    public function unpublishNews(AcceptanceTester $i)
    {
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        $i->click(['css' => 'a.icon-more[title=Actions]']);
        $i->click('#unpublish-article');
        $i->waitForElementVisible(['css' => 'textarea#rejectedReason'], 5);
        $i->fillField(['css' => "textarea#rejectedReason"], "Testing unpublish");
        $i->click('#confirm-dialog-confirm-button');

        //TODO: Check that article is actually unpublished
    }

    /**
     * @throws Exception
     * @beforeSuite
     */
    public function publishNews(AcceptanceTester $i)
    {
        $i->amOnPage('/news/me?status=Rejected');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        $i->click(['css' => 'input#article-submit[value=Publish\ news]']);
    }

    /**
     * @throws Exception
     * @beforeSuite
     */
    public function audienceViewsNewsFeed(AcceptanceTester $i)
    {
        $i->impersonate("Jana Reed");
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->seeElement($element);
    }

    /**
     * @throws Exception
     * @beforeSuite
     */
    public function audienceViewsNewsItem(AcceptanceTester $i)
    {
        $i->impersonate("Jana Reed");
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        $i->see("Test News Title");
    }

    /**
     * @throws Exception
     * @beforeSuite
     */
    public function enableNewsComments(AcceptanceTester $i)
    {
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        sleep(1);
        $i->dontSeeElement(['css' => 'div#cke_comment']);
        $i->click(['css' => 'label[for=allow-comments]']);
    }

    /**
     * @throws Exception
     * @beforeSuite
     */
    public function addTwoNewsComments(AcceptanceTester $i)
    {
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        //$i->waitForElementVisible(['css'=>'div#cke_comment'], 5);
        $i->waitForElementVisible(['css' => 'button.show-ckeditor-button'], 5);
        $i->click(['css' => 'button.show-ckeditor-button']);
        $i->waitForElementVisible(['css' => '#comment-0 + div.ck'], 5);
        $i->fillCkEditorById('comment-0', "indeed this is interesting news");
        sleep(2);
        $i->click(['css' => 'button[class=button\ submit]']);
        sleep(3);
        $i->fillCkEditorById('comment-0', "its poop, like your face");
        sleep(2);
        $i->click(['css' => 'button[class=button\ submit]']);
        sleep(3);
    }

    /**
     * @throws Exception
     * @beforeSuite
     */
    public function deleteNewsComments(AcceptanceTester $i)
    {
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        $i->click(['css' => 'a.icon-delete.no-text']);
        $i->waitForElementVisible(['css' => 'ul.flex-list.buttons'], 8);
        $i->click('#article-threads-delete-button');
    }

    /**
     * @throws Exception
     * @beforeSuite
     */
    public function disableNewsComments(AcceptanceTester $i)
    {
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        $i->waitForElementVisible(['css' => 'button.show-ckeditor-button'], 1);
        $i->click(['css' => 'label[for=allow-comments]']);
    }

    /**
     * @throws Exception
     * @beforeSuite
     */
    public function deleteNews(AcceptanceTester $i)
    {
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);

        $i->waitForElementVisible(['css' => 'a.icon-more[title=Actions]'], 5);
        $i->click(['css' => 'a.icon-more[title=Actions]']);
        $i->click(['css' => 'a.icon-delete']);
        $i->waitForElementVisible('#confirm-dialog-confirm-button', 8);
        $i->click('#confirm-dialog-confirm-button');
    }
}
