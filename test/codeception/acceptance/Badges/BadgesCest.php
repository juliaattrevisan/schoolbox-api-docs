<?php

declare(strict_types=1);

namespace Badges;

use AcceptanceTester;
use Codeception\Util\Locator;
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
        $createBadge = new CreateBadge($i);
        $badgeName = "Automation Badge";
        $category = "Learning";
        $badges->clickAddBadgeButton();
        $createBadge->typeName($badgeName);
        $createBadge->clickBrowseIconLibraryButton();
        $createBadge->waitModalDialogPresented();
        $createBadge->clickVerticalTabItem("food");
        $createBadge->clickBadgeImage("food/1");
        $createBadge->clickCategoryDropDown();
        $createBadge->clickCategoryItem($category);
        $createBadge->clickCreateBadgeButton();
        $badges->waitAddBadgeButtonToBePresented();
        $badges->verifyTextIsPresented($badgeName);
        $badges->verifyTextIsPresented($category);
    }

    /**
     * @depends createBadge
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on /learning/badge select Modify badge
     * update the title
     * update the category
     * Click Modify Badge
     * Ensure the Badge title and category are properly presented in the /learning/badge screen
     */
    public function modifyBadge(AcceptanceTester $i)
    {
        $badges = new Badges($i);
        $createBadge = new CreateBadge($i);
        $category = "Learning";
        $badgeId = $i->grabNumRecords('badge');
        $badges->clickBadgeDropdown($badgeId);
        $badges->clickDropdownModify($badgeId);
        $createBadge->typeName(' Updated');
        $createBadge->clickCategoryDropDown();
        $createBadge->clickCategoryItem($category);
        $createBadge->clickCreateBadgeButton();
        $badges->waitAddBadgeButtonToBePresented();
        $badges->verifyTextIsPresented('Automation Badge Updated');
        $badges->verifyTextIsPresented($category);
    }

    /**

     * @param AcceptanceTester $i
     * @throws Exception
     * @depends modifyBadge
     * on /learning/badge select Modify badge
     * update the title
     * update the category
     * Click Modify Badge
     * Ensure the Badge title and category are properly presented in the /learning/badge screen
     */
    public function deleteBadge(AcceptanceTester $i)
    {
        $badges = new Badges($i);
        $badgeId = $i->grabNumRecords('badge');
        $badges->clickBadgeDropdown($badgeId);
        $badges->clickDropdownDelete();
        $badges->clickConfirmDelete();
        $badges->waitAddBadgeButtonToBePresented();
        $i->cantSee('Automation Badge Updated');
    }
}
