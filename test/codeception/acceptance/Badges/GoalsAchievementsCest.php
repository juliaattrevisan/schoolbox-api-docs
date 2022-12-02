<?php

declare(strict_types=1);

namespace Badges;

use AcceptanceTester;
use Codeception\Util\Locator;
use Page\Badges\Badges;
use Exception;
use Page\Badges\CreateBadge;
use Page\ProfilePage\ProfilePage;

class GoalsAchievementsCest
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
     * @beforeSuite
     *
     * on learning/badge page create a Badge
     * set it as a goal to Jana Reed
     * ensure the goal is presented on Jana Reed's activity feed
     */
    public function setGoals(AcceptanceTester $i): void
    {

        $createBadge = new CreateBadge($i);
        $badges = new Badges($i);
        $profilePage = new ProfilePage($i);
        $badgeName = 'Automation Badge created to set goal from Badges page';
        $student = 'Jana Reed';
        $studentId = 25;
        $i->amGoingTo('Create a Badge');
        $createBadge->createBadge($i, $badgeName);
        $i->amGoingTo('Set a Goal to Jana Reed');
        $badgeId = $i->grabNumRecords('badge');
        $i->amGoingTo('click the 3-dot menu and click Set Goal');
        $badges->clickBadgeDropdown($badgeId);
        $badges->clickDropdownOption('goal');
        $i->amGoingTo('search and select Jana Reed on the modal');
        $badges->selectStudentSetGoal($student);
        $i->amGoingTo('click Set Goal button on the modal');
        $badges->clickSetGoalorAwardAchievement();
        $i->amGoingTo('check the goal is presented on Jana Reed profile page activity feed');
        $i->amOnPage($profilePage->generateRoute($studentId));
        $i->canSee($badgeName);
        $i->amGoingTo('check the goal is presented on Jana Reed profile page tab Goals');
        $profilePage->clickTab('goals');
        $i->canSee($badgeName);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * award an achievement to Jana Reed from the Badges page
     * ensure the achievement is presented on Jana Reed's activity feed
     */
    public function awardAchievement(AcceptanceTester $i): void
    {

        $createBadge = new CreateBadge($i);
        $badges = new Badges($i);
        $badgeName = 'Automation Badge created for award achievement from Badges page';
        $student = 'Jana Reed';
        $studentProfilePage = '/search/user/25';
        $i->amGoingTo('Create a Badge');
        $createBadge->createBadge($i, $badgeName);
        $i->amGoingTo('Award achievement to Jana Reed');
        $badgeId = $i->grabNumRecords('badge');
        $i->amGoingTo('click the 3-dot menu and click Award Achievement');
        $badges->clickBadgeDropdown($badgeId);
        $badges->clickDropdownOption('achievement');
        $i->amGoingTo('search and select Jana Reed on the modal');
        $badges->selectStudentAwardAchievement($student);
        $i->amGoingTo('click Award achievement button on the modal');
        $badges->clickSetGoalorAwardAchievement();
        $i->amGoingTo('check the achievement is presented on Jana Reed activity feed');
        $i->amOnPage($studentProfilePage);
        $i->canSee($badgeName);
    }
}
