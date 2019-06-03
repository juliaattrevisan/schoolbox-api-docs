<?php

use Codeception\Util\Locator;

class NewsInitialCest
{

    public function createNews(AcceptanceTester $i)
    {
        $i->amOnPage('/login/');
        $i->login("alaress", "alaress");
        $i->amOnPage('/news');
        $i->waitForElementVisible(['css'=>'a.icon-add[title=Create\ news]'], 5);
        $i->clickWithLeftButton(['css'=>'a.icon-add[title=Create\ news]']);
        $i->fillField("title","Test News Title");
        $i->fillCkEditorById('body', 'wow what interesting news');
        $i->click(['css' => 'button.button.icon-drag-drop'], ['css' => 'div#audience-selectize-group']);
        $i->waitForText('Year Levels');
        //Issue as all buttons have the same ID. need to ID where the text is and click on the spot where the text is.
        $i->clickWithLeftButton('Year Levels');
        $i->executeJS("var scrollableDiv = document.getElementsByClassName('checklist checklist-container');
        scrollableDiv.scrollTop=scrollableDiv.scrollHeight - scrollableDiv.offsetHeight;");
        $i->waitForText('Year 12', 5);
        $Year_12_element=Locator::contains(['css'=>'div.checklist-wrapper'], 'Year 12');
        $i->clickWithLeftButton($Year_12_element);
        sleep(5);
        $i->click("Publish news");
        sleep(5);
        echo "not finished";
    }
}
#top-menu > li:nth-child(5)