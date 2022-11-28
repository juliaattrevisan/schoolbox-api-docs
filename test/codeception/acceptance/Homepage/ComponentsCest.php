<?php

namespace Homepage;

use AcceptanceTester;
use Exception;
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
     * navigate to https://vagrant.dev.schoolbox.cloud/homepage/83
     */
    public function _before(AcceptanceTester $i)
    {
        $i->logout();
        $i->loginAsAdmin();
        $homepage = new Homepage($i);
        $i->amOnPage($homepage->generateRoute(83));
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
        $homepage->clickAddComponentsButton();
        $i->amGoingTo('Check the tabs present the proper numbers of components');
        $i->canSee('Information (7)');
        $i->canSee('Collaboration (10)');
        $i->canSee('Learning (11)');
        $i->canSee('Multimedia (9)');
        $i->canSee('Resources (4)');
        $i->canSee('Feeds (6)');
        $i->amGoingTo('Check the tabs present the proper components');
        $i->click(['css' => 'a[href=\#tabs-sub-3]']);
        $i->canSeeElement(['css' => 'ul#tabs-sub-3']);
        $i->canSeeElement(['css' => 'span[class=size-32\ icon-masonry\ icon-masonry-Component_Homepage_ClassListController]']);
        $i->canSee('Class List');
    }
}
