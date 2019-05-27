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
    public function createHomepage(AcceptanceTester $i): void
    {
        $i->login("alaress", "alaress");
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resource/folder/create/51");
        $i->click(['xpath' => '//select[@class="valid"]']);
        echo "Not done yet";
    }

    #@depends createHomepage
    public function createElement(): void
    {
        echo "Not done yet";
    }

    #@depends createHomepage
    #@depends createElement
    public function editElement(): void
    {
        echo "Not done yet";
    }

    #@depends createHomepage
    #@depends editElement
    public function viewElement(): void
    {
        echo "Not done yet";
    }

    #@depends createHomepage
    #@depends viewElement
    public function deleteElement(): void
    {
        echo "Not done yet";
    }

    #@depends createHomepage
    #@depends deleteElement
    public function deleteHomepage(): void
    {
        echo "Not done yet";
    }
}