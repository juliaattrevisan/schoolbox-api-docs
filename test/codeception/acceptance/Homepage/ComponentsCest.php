<?php

namespace Homepage;

use AcceptanceTester;
use Exception;
use Page\Badges\Badges;
use Page\Homepage;

class ComponentsCest
{
    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * get logged in
     * navigate to https://vagrant.dev.schoolbox.cloud/learning/badge
     */
    public function _before(AcceptanceTester $i)
    {
        $i->logout();
        $i->loginAsAdmin();

        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/homepage/83');
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * navigate to a homepage
     * check components are presented under the proper tabs
     */
    public function checkComponentsTabs(AcceptanceTester $i): void
    {
        $homepage = new Homepage($i);
        $i->amGoingTo('Click the add component icon');
//        $i->click(['css' => 'a#add-component']);
        $homepage->clickAddComponentsButton();
//        sleep(1);
//        $i->canSeeElement(['css' => 'div#tabs-1']);
        $i->amGoingTo('Check the tabs presents the proper numbers of components');
        $i->canSee('Information (7)');
        $i->canSee('Collaboration (10)');
        $i->canSee('Learning (11)');
        $i->canSee('Multimedia (9)');
        $i->canSee('Resources (4)');
        $i->canSee('Feeds (6)');
        $i->amGoingTo('Check the tabs presents the proper components');
        $i->click(['css' => 'a[href=\#tabs-sub-3]']);
        $i->canSeeElement(['css' => 'ul#tabs-sub-3']);
        $i->canSeeElement(['css' => 'span[class=size-32\ icon-masonry\ icon-masonry-Component_Homepage_ClassListController]']);
        $i->canSee('Class List');
    }
}
