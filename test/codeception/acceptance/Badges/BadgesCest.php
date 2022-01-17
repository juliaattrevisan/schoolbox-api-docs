<?php

declare(strict_types=1);

namespace Badges;

use AcceptanceTester;
use Page\Badges\Badges;
use Page\Badges\CreateBadge;
use Exception;

class BadgesCest
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
        $badges = new Badges($i);
        $i->amOnPage($badges::URL);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on learning/badge page click Create Badge button
     * Type a Name
     * Select a badge image via modal
     * Select a category
     * Click Create Badge
     * Ensure the Badge is presented in the /learning/badge screen
     */
    public function createBadge(AcceptanceTester $i)
    {
        $badges = new Badges($i);
        $timestamp     = time();
        $badgeName = "Automation Badge " . $timestamp;
        $badges->clickAddBadgeButton();
        $createBadge = new CreateBadge($i);
        $createBadge->typeName($badgeName);
        $createBadge->clickBrowseIconLibraryButton();
        $createBadge->waitModalDialogPresented();
        $createBadge->clickVerticalTabItem("food");
        $createBadge->clickBadgeImage("food/1");
        $createBadge->clickCategoryDropDown();
        $createBadge->clickCategoryItem("Learning");
        $createBadge->clickCreateBadgeButton();
        $badges->verifyBadgeName($badgeName);
    }
}
