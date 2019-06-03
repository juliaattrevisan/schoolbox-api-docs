<?php

use Codeception\Util\Locator;

class LoginCest
{

    public function createNews(AcceptanceTester $i)
    {
        $i->login("alaress", "alaress");
        $i->clickWithLeftButton(['css'=>'a.icon-news[title=Current\ school\ news]']);
        $i->clickWithLeftButton(['css'=>'a.icon-add[title=Create\ news]']);
        $i->fillField("title","Test News Title");
        $i->fillCkEditorById('body', 'wow what interesting news');
        $i->click(['css' => 'button.button.icon-drag-drop'], ['css' => 'div#audience-selectize-group']);
        $i->clickWithLeftButton('Year Levels');
        $i->executeJS("var scrollableDiv = document.getElementsByClassName('checklist checklist-container');
        scrollableDiv.scrollTop=scrollableDiv.scrollHeight - scrollableDiv.offsetHeight;");
        $i->waitForText('Year 12', 5);
        $Year_12_element=Locator::contains(['css'=>'div.checklist-wrapper'], 'Year 12');
        $i->clickWithLeftButton($Year_12_element);
        sleep(5);
        $i->click("Publish news");
        sleep(5);
    }
}
