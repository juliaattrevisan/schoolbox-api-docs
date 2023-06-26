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
    public function _before(AcceptanceTester $i): void
    {
        $i->logout();
        $i->loginAsAdmin();
        $badges = new Badges($i);
        $i->amOnPage($badges::URL);
    }

    /**
     * Deleting Bagdes when the test is about to start so it does not get impacted by other tests already executed
     *
     * @param AcceptanceTester $i
     */
    public function beforeSuite(AcceptanceTester $i): void
    {
        $i->amGoingTo("update delete_at for the news article so it does not impact the news tests");
        $i->updateInDatabase('badge', ['deleted_at' => "2022-01-15 11:22:34"], ['name like' => "Automation Badge%"]);
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
        $i->amGoingTo('click Create Badge button and create a badge');
        $badges->clickAddBadgeButton();
        $createBadge->typeName($badgeName);
        $createBadge->clickBrowseIconLibraryButton();
        $createBadge->waitModalDialogPresented();
        $createBadge->clickVerticalTabItem("food");
        $createBadge->clickBadgeImage("food/1");
        $createBadge->clickCategoryDropDown();
        $createBadge->clickCategoryItem($category);
        $createBadge->clickCreateBadgeButton();
        $i->amGoingTo('ensure the badge is presented');
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
        $i->amGoingTo('click the badge dropdown and click Modify');
        $badgeId = $i->grabFromDatabase('badge', 'id', ['deleted_at' => null,'name like' => 'Automation Badge']);
        $badges->clickBadgeDropdown($badgeId);
        $badges->clickDropdownModify($badgeId);
        $i->amGoingTo('get name and category updated and click Modify Badge button');
        $createBadge->typeName('Automation Badge Updated');
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
     * on /learning/badge click the Badge dropdown
     * Click Delete and confirm it
     * Ensure the Badge is no longer presented in the /learning/badge screen
     */
    public function deleteBadge(AcceptanceTester $i)
    {
        $badges = new Badges($i);
        $i->amGoingTo('click the badge dropdown and click Delete');
        $badgeId = $i->grabFromDatabase('badge', 'id', ['deleted_at' => null,'name like' => 'Automation Badge Updated']);
        $badges->clickBadgeDropdown($badgeId);
        $badges->clickDropdownDelete();
        $badges->clickConfirm();
        $i->amGoingTo('ensure the badge is no longer presented in the page');
        $i->cantSee('Automation Badge Updated');
        $i->amGoingTo('reload the page to ensue the badge is not presented');
        $i->amOnPage($badges::URL);
        $i->cantSee('Automation Badge Updated');
    }
}
