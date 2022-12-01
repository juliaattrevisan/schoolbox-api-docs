<?php

declare(strict_types=1);

namespace Badges;

use AcceptanceTester;
use Codeception\Util\Locator;
use Page\Badges\Badges;
use Exception;
use Page\Badges\CreateBadge;

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
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on learning/badge page create a Badge
     * set it as a goal to Jana Reed
     * ensure the goal is presented on Jana Reed's activity feed
     */
    public function setGoals(AcceptanceTester $i): void
    {

        $createBadge = new CreateBadge($i);
        $badges = new Badges($i);
        $student = 'Jana Reed';
        $studentProfilePage = '/search/user/25';
        $i->amGoingTo('Create a Badge');
        $createBadge->createBadge($i);
        $i->amGoingTo('Set a Goal to Jana Reed');
        $badgeId = $i->grabNumRecords('badge');
        $i->amGoingTo('click the 3-dot menu and click Set Goal');
        $badges->clickBadgeDropdown($badgeId);
        $badges->clickDropdownSetGoal();
        $i->amGoingTo('search and select Jana Reed on the modal');
        $badges->selectStudent($student);
        $i->amGoingTo('click Set Goal button on the modal');
        $badges->clickSetGoal();
        $i->amGoingTo('check the goal is set on Jana Reed activity feed');
        $i->amOnPage($studentProfilePage);
        $i->canSee('Automation Badge created for convenience');
    }
}
