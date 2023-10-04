<?php

namespace Tests\Acceptance\News;

use Tests\Support\AcceptanceTester;
use Codeception\Util\Locator;
use Exception;

//TODO: add verification after the actions

class NewsInitialCest
{
    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws Exception
     */
    public function _before(AcceptanceTester $i): void
    {
        $i->logout();
        $i->loginAsAdmin();
    }

    /**
     * @throws Exception
     */
    public function createNews(AcceptanceTester $i): void
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
        $i->waitForText('News Feed');
        $i->canSee('Test News Title', '//a');
    }

    /**
     * @throws Exception
     * @depends createNews
     */
    public function unpublishNews(AcceptanceTester $i): void
    {
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        $i->clickElementInWebComponent('[data-test=sbx-actions-dropdown-header-options]', '[data-test=sbx-actions-dropdown-header-options-button]');
        $i->click('#unpublish-article');
        $i->waitForElementVisible(['css' => 'textarea#rejectedReason'], 5);
        $i->fillField(['css' => "textarea#rejectedReason"], "Testing unpublish");
        $i->click('#confirm-dialog-confirm-button');

        //TODO: Check that article is actually unpublished
    }

    /**
     * @throws Exception
     * @depends unpublishNews
     */
    public function publishNews(AcceptanceTester $i): void
    {
        $i->amOnPage('/news/me?status=Rejected');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        $i->click(['css' => 'input#article-submit[value=Publish\ news]']);
    }

    /**
     * @throws Exception
     * @depends publishNews
     */
    public function audienceViewsNewsFeed(AcceptanceTester $i): void
    {
        $i->impersonate("Jana Reed");
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->seeElement($element);
    }

    /**
     * @throws Exception
     * @depends publishNews
     */
    public function audienceViewsNewsItem(AcceptanceTester $i): void
    {
        $i->impersonate("Jana Reed");
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);
        $i->wait(2);
        $i->canSee("Test News Title");
    }

    /**
     * @throws Exception
     * @depends publishNews
     */
    public function enableNewsComments(AcceptanceTester $i): void
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
     * @depends enableNewsComments
     */
    public function addTwoNewsComments(AcceptanceTester $i): void
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
     * @depends addTwoNewsComments
     */
    public function deleteNewsComments(AcceptanceTester $i): void
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
     * @depends enableNewsComments
     */
    public function disableNewsComments(AcceptanceTester $i): void
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
     * @depends createNews
     */
    public function deleteNews(AcceptanceTester $i): void
    {
        $i->amOnPage('/news');
        $element = Locator::contains('div.small-12.card h3 a', 'Test News Title');
        $i->waitForElementVisible($element, 5);
        $i->click($element);

        $i->waitForElementVisible('[data-test=sbx-actions-dropdown-header-options]', 5);
        $i->clickElementInWebComponent('[data-test=sbx-actions-dropdown-header-options]', '[data-test=sbx-actions-dropdown-header-options-button]');
        $i->click(['css' => 'a.icon-delete']);
        $i->waitForElementVisible('#confirm-dialog-confirm-button', 8);
        $i->click('#confirm-dialog-confirm-button');
    }
}
