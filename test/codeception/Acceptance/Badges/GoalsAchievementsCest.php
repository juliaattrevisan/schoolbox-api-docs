<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Acceptance\Badges;

use Tests\Support\AcceptanceTester;
use Codeception\Util\Locator;
use Tests\Support\Page\Badges\Badges;
use Exception;
use Tests\Support\Page\Badges\CreateBadge;
use Tests\Support\Page\ProfilePage\ProfilePage;

/**
 * This test sets a goal, award an achievement, remove the goal, withdraw the achievement, adds an evidence
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /usr/share/schoolbox && ./vendor/bin/codecept run --steps Acceptance Badges/GoalsAchievementsCest
 */
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
        $i->amGoingTo("update delete_at for the badge so it does not impact the news tests");
        $i->updateInDatabase('badge', ['deleted_at' => "2022-01-15 11:22:34"], ['name like' => "Automation Badge%"]);
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
        $profilePage = new ProfilePage($i);
        $badgeName = 'Automation Badge created to set goal from Badges page';
        $student = 'Jana Reed';
        $studentId = 25;
        $badgeId = $i->grabFromDatabase('badge', 'id', ['deleted_at' => null,'name like' => $badgeName]);
        if (!$badgeId) {
            $i->amGoingTo('Create a Badge');
            $createBadge->createBadge($i, $badgeName);
            $badgeId = $i->grabFromDatabase('badge', 'id', ['deleted_at' => null,'name like' => $badgeName]);
        }
        $i->amGoingTo('Set a Goal to Jana Reed');
        $i->amGoingTo('click the 3-dot menu and click Set Goal');
        $badges->clickBadgeDropdown($badgeId);
        $badges->clickDropdownOptionFirstElement($badgeId, 'goal');
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
        $badgeId = $i->grabFromDatabase('badge', 'id', ['deleted_at' => null,'name like' => $badgeName]);
        if (!$badgeId) {
            $i->amGoingTo('Create a Badge');
            $createBadge->createBadge($i, $badgeName);
            $badgeId = $i->grabFromDatabase('badge', 'id', ['deleted_at' => null,'name like' => $badgeName]);
        }
        $i->amGoingTo('Award achievement to Jana Reed');
        $i->amGoingTo('click the 3-dot menu and click Award Achievement');
        $badges->clickBadgeDropdown($badgeId);
        $badges->clickDropdownOptionFirstElement($badgeId, 'achievement');
        $i->amGoingTo('search and select Jana Reed on the modal');
        $badges->selectStudentAwardAchievement($student);
        $i->amGoingTo('click Award achievement button on the modal');
        $badges->clickSetGoalorAwardAchievement();
        $i->amGoingTo('check the achievement is presented on Jana Reed activity feed');
        $i->amOnPage($studentProfilePage);
        $i->canSee($badgeName);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     * @depends setGoals
     * @depends awardAchievement
     *
     *
     * remove the goal and withdraw the achievement from Jana Reed
     * ensure the goals and achievements are  no longer presented on Jana Reed's activity feed, goals and achievement tabs on profile page
     */
    public function removeGoalWithdrawAchievement(AcceptanceTester $i): void
    {
        $badges = new Badges($i);
        $profilePage = new ProfilePage($i);
        $badgeNameGoal = 'Automation Badge created to set goal from Badges page';
        $badgeNameAchievement = 'Automation Badge created for award achievement from Badges page';
        $studentProfilePage = '/search/user/25';
        $i->amGoingTo('Navigate to Jana Reed profile page, click Goals tab and award the goal as achievement');
        $i->amOnPage($studentProfilePage);
        $profilePage->clickTab('goals');
        $i->canSee($badgeNameGoal);

        $profilePage->clickBadgeOrDropdownOption($badgeNameGoal);
        $badges->clickSetGoalorAwardAchievement();
        $i->amGoingTo('Click the achievements tab, ensure the achievements are presented and withdraw them');
        $profilePage->clickTab('achievements');
        $i->canSee($badgeNameGoal);
        $badgeIdAchievement = $i->grabFromDatabase('badge', 'id', ['deleted_at' => null,'name like' => $badgeNameAchievement]);
        $profilePage->clickBadgeAchievementDropdown($badgeIdAchievement);
        $profilePage->clickBadgeOrDropdownOption('Withdraw achievement');
        $badges->clickConfirm();
        $badgeIdAchievement = $i->grabFromDatabase('badge', 'id', ['deleted_at' => null,'name like' => $badgeNameGoal]);
        $profilePage->clickBadgeAchievementDropdown($badgeIdAchievement);
        $profilePage->clickBadgeOrDropdownOption('Withdraw achievement');
        $badges->clickConfirm();
        $i->amGoingTo('Click the goals tab, ensure the goal is presented and remove it');
        $profilePage->clickTab('goals');
        $i->canSee($badgeNameGoal);
        $i->cantSee($badgeNameAchievement);
        $badgeIdGoal = $i->grabFromDatabase('badge', 'id', ['deleted_at' => null,'name like' => $badgeNameGoal]);
        $profilePage->clickBadgeGoalDropdown($badgeIdGoal);
        $badges->clickDropdownOptionLastElement('close');
        $badges->clickConfirm();
        $i->cantSee($badgeNameGoal);
        $profilePage->clickTab('activity');
        $i->cantSee($badgeNameAchievement);
        $i->cantSee($badgeNameGoal);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     * @depends setGoals
     * set a goal, add an evidence, view the evidence, edit it, check it is updated, award it and check the evidence is presented
     * withdraw the achievement, remove the evidence, award it again with evidence, check the evidence, update the evidence, check the evidence is presented properly
     *
     */
    public function viewEvidence(AcceptanceTester $i): void
    {
        $badges = new Badges($i);
        $profilePage = new ProfilePage($i);
        $badgeNameGoal = 'Automation Badge created to set goal from Badges page';
        $student = 'Jana Reed';
        $studentId = 25;
        $i->amGoingTo('Set a Goal to Jana Reed');
        $badgeId = $i->grabFromDatabase('badge', 'id', ['deleted_at' => null,'name like' => $badgeNameGoal]);
        $i->amGoingTo('click the 3-dot menu and click Set Goal');
        $badges->clickBadgeDropdown($badgeId);
        $badges->clickDropdownOptionFirstElement($badgeId, 'goal');
        $i->amGoingTo('search and select Jana Reed on the modal');
        $badges->selectStudentSetGoal($student);
        $i->amGoingTo('click Set Goal button on the modal');
        $badges->clickSetGoalorAwardAchievement();
        $i->amGoingTo('Navigate to Jana Reed profile page goals tab and add an evidence to the goal');
        $i->amOnPage($profilePage->generateRoute($studentId));
        $profilePage->clickTab('goals');
        $i->canSee($badgeNameGoal);
        $profilePage->clickBadgeOrDropdownOption($badgeNameGoal);
        $profilePage->clickBadgeOrDropdownOption('Add evidence');
        $i->fillCkEditorById('reason', 'Great job, Jana!');
        $badges->clickEvidenceSubmitButton();
        $i->amGoingTo('Check the evidence is presented by navigating to view evidence');
        $i->amOnPage($profilePage->generateRoute($studentId));
        $profilePage->clickTab('goals');
        $profilePage->clickBadgeOrDropdownOption($badgeNameGoal);
        $profilePage->clickBadgeOrDropdownOption('View evidence');
        $i->canSee('Great job, Jana!');
        $i->amGoingTo('Get the evidence updated');
        $i->click(Locator::lastElement("a.icon-modify"));
        $i->fillCkEditorById('reason', 'Well done, Jana!');
        $badges->clickEvidenceSubmitButton();
        $i->amGoingTo('Ensure the evidence text is updated');
        $i->canSee('Well done, Jana!');
        $i->amGoingTo('Award the goal as achievement and check if it is presented on the achievements tab on the profile page');
        $badges->clickConfirm();
        $i->amOnPage($profilePage->generateRoute($studentId));
        $profilePage->clickTab('achievements');
        $i->canSee($badgeNameGoal);
        $i->amGoingTo("Check the evidence is still presented");
        $profilePage->clickBadgeOrDropdownOption($badgeNameGoal);
        $profilePage->clickBadgeOrDropdownOption('View evidence');
        $i->canSee('Well done, Jana!');
        $i->amGoingTo('Navigate to the profile page and withdraw the achievement');
        $i->amOnPage($profilePage->generateRoute($studentId));
        $profilePage->clickTab('achievements');
        $profilePage->clickBadgeAchievementDropdown($badgeId);
        $profilePage->clickBadgeOrDropdownOption('Withdraw achievement');
        $badges->clickConfirm();
        $i->amGoingTo('click the tab Goals and ensure the evidence is still presented to the goal');
        $profilePage->clickTab('goals');
        $profilePage->clickBadgeOrDropdownOption($badgeNameGoal);
        $profilePage->clickBadgeOrDropdownOption('View evidence');
        $i->canSee('Well done, Jana!');
        $i->amGoingTo('Remove the evidence and ensure it is no longer presented');
        $badges->clickDropdownDelete();
        $i->click("a#confirm-dialog-confirm-button.button.submit");
        $i->cantSee('Well done, Jana!');
        $i->amGoingTo('Award the goal as achievement with evidence and check the evidence is presented');
        $i->amOnPage($profilePage->generateRoute($studentId));
        $profilePage->clickTab('goals');
        $profilePage->clickBadgeOrDropdownOption($badgeNameGoal);
        $profilePage->clickBadgeOrDropdownOption('Award with evidence');
        $i->fillCkEditorById('reason', 'Awarding this as achievement with evidence.');
        $badges->clickEvidenceSubmitButton();
        $i->amOnPage($profilePage->generateRoute($studentId));
        $profilePage->clickTab('achievements');
        $profilePage->clickBadgeOrDropdownOption($badgeNameGoal);
        $profilePage->clickBadgeOrDropdownOption('View evidence');
        $i->canSee('Awarding this as achievement with evidence.');
        $i->amGoingTo('Get the evidence updated and ensure it is properly updated');
        $i->click(Locator::lastElement("a.icon-modify"));
        $i->fillCkEditorById('reason', 'This is updated');
        $badges->clickEvidenceSubmitButton();
        $i->amGoingTo('Ensure the evidence text is updated');
        $i->canSee('This is updated');
    }


    /**
     * Soft deleting data to not impact the tests
     *
     * @param AcceptanceTester $i
     */
    public function tearDown(AcceptanceTester $i): void
    {
        $i->amGoingTo("update delete_at for the data created in this test so it does impact next suite of tests");
        $i->updateInDatabase('badge', ['deleted_at' => "2022-01-15 11:22:34"], ['name like' => "Automation Badge%"]);
    }
}
