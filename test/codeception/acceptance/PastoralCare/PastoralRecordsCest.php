<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace PastoralCare;

use AcceptanceTester;
use Exception;
use Page\Common;
use Tests\Page\PastoralCare\CreatePastoralRecord;
use Tests\Page\PastoralCare\PastoralProfile;
use Page\ProfilePage\ProfilePage;

class PastoralRecordsCest
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
    }

    /**
     * Deleting data when the test is about to start so it does not get impacted by other tests already executed
     *
     * @param AcceptanceTester $i
     */
//    public function beforeSuite(AcceptanceTester $i): void
//    {
//        $i->amGoingTo("update delete_at for the pastoral records so it does not impact the tests");
//        $i->updateInDatabase('badge', ['deleted_at' => "2022-01-15 11:22:34"], ['name like' => "Automation Test Pastoral Record%"]);
//        $i->updateInDatabase('badge', ['deleted_at' => "2022-01-15 11:22:34"], ['name like' => "This is an automation pastoral record updated%"]);
//    }


    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * navigate to the student pastoral care page
     * create a pastoral record for the student
     * ensure the record is created with the proper details
     */
    public function createPastoralRecord(AcceptanceTester $i): void
    {
        $profilePage = new ProfilePage($i);
        $pastoralProfile = new PastoralProfile($i);
        $createPastoralRecord = new CreatePastoralRecord($i);
        $common = new Common($i);
        $studentId = 25;
        $pastoralTitle = "Automation Test Pastoral Record";
        $pastoralDescription = 'This is an automation pastoral record';
        $i->amGoingTo('navigate to the student profile page');
        $i->amOnPage($profilePage->generateRoute($studentId));
        $i->amGoingTo('click the pastoral care button');
        $profilePage->clickButton('pastoral');
        $i->waitForText('PASTORAL PROFILE', 10);
        $i->amGoingTo('click create pastoral record and create a pastoral record');
        $pastoralProfile->clickCreatePastoralRecord();
        $createPastoralRecord->typeTitle($pastoralTitle);
        $createPastoralRecord->clickTypeDropdown();
        $createPastoralRecord->selectTypeSubType(1);
        $i->fillRichTextField('body_public', $pastoralDescription);
        $createPastoralRecord->clickVisibilityLink('Role');
        $createPastoralRecord->clickPastoralRole('Administration');
        $common->closeModal();
        $createPastoralRecord->clickSave();
        $i->amGoingTo('ensure the record is presented under pastoral records');
        $i->amOnPage('/pastoral/student/25');
        $i->waitForText('PASTORAL PROFILE', 10);
        $i->canSee($pastoralTitle, '//div/a/h3');
    }


    /**
     * @param AcceptanceTester $i
     * @throws Exception
     * @depends createPastoralRecord
     *
     * navigate to the student pastoral care page
     * comment a pastoral record for the student
     * ensure the comment is created with the proper details
     * delete the comment
     * ensure the comment is deleted
     */
    public function commentPastoralRecord(AcceptanceTester $i): void
    {
        $pastoralProfile = new PastoralProfile($i);
        $common = new Common($i);
        $studentId = 25;
        $pastoralComment = 'This is an automation pastoral comment';
        $i->amGoingTo('navigate to the student pastoral profile page');
        $i->amOnPage($pastoralProfile->generateRoute($studentId));
        $i->waitForText('PASTORAL PROFILE', 10);
        $i->amGoingTo('click the pastoral record');
        $recordId = $i->grabFromDatabase('pastoral', 'id', ['deleted_at' => null, 'heading like' => "Automation Test Pastoral%"]);
        $pastoralProfile->clickPastoralRecord($studentId, $recordId);
        $i->waitForElementVisible('a[id="panel-general"]', 10);
        $common->clickAddComment();
        $i->waitForElementVisible(['css' => '#comment-public + div.ck'], 5);
        $i->fillRichTextField('comment-public', $pastoralComment);
        $common->clickSubmitComment();
        $i->amGoingTo('check the comment is presented');
        $i->waitForText('Posted a few seconds ago', 2);
        $i->canSee('This is an automation pastoral comment', '//article/p');
        $i->amGoingTo('delete the pastoral comment');
        $pastoralProfile->clickDeleteCommentIcon();
        $common->clickModalDeleteButton();
        $i->cantSee($pastoralComment);
    }
    /**
     * @param AcceptanceTester $i
     * @throws Exception
     * @depends createPastoralRecord
     * navigate to the student pastoral care page
     * modify a pastoral record for the student
     * ensure the record is updated with the proper details
     */
    public function modifyPastoralRecord(AcceptanceTester $i): void
    {
        $pastoralProfile = new PastoralProfile($i);
        $common = new Common($i);
        $createPastoralRecord = new CreatePastoralRecord($i);
        $studentId = 25;
        $pastoralTitle = 'This is an automation pastoral record updated';
        $i->amGoingTo('navigate to the pastoral record and click the modify button');
        $i->amOnPage($pastoralProfile->generateRoute($studentId));
        $recordId = $i->grabFromDatabase('pastoral', 'id', ['deleted_at' => null, 'heading like' => "Automation Test Pastoral%"]);
        $pastoralProfile->clickPastoralRecord($studentId, $recordId);
        $i->waitForElementVisible('a[id="panel-general"]', 10);
        $common->clickIconModifyPage();
        $i->amGoingTo('get the pastoral record updated');
        $createPastoralRecord->typeTitle($pastoralTitle);
        $createPastoralRecord->clickTypeDropdown();
        $createPastoralRecord->selectTypeSubType(2);
        $createPastoralRecord->clickSave();
        $i->amGoingTo('ensure the record are properly updated');
        $i->amOnPage('/pastoral/student/25');
        $i->waitForText('PASTORAL PROFILE', 10);
        $i->canSee($pastoralTitle, '//div/a/h3');
        $i->amGoingTo('click the pastoral record');
        $recordId = $i->grabFromDatabase('pastoral', 'id', ['deleted_at' => null, 'heading like' => "This is an automation pastoral record updated%"]);
        $pastoralProfile->clickPastoralRecord($studentId, $recordId);
        $i->waitForElementVisible('a[id="panel-general"]', 10);
        $i->canSee('Attendance', '//div/dl/dd');
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     * @depends modifyPastoralRecord
     * navigate to the student pastoral care page
     * delete a pastoral record for the student
     * ensure the record is no longer presented
     */
    public function deletePastoralRecord(AcceptanceTester $i): void
    {
        $pastoralProfile = new PastoralProfile($i);
        $common = new Common($i);
        $studentId = 25;
        $i->amGoingTo('navigate to the pastoral profile page and delete a pastoral record');
        $i->amOnPage($pastoralProfile->generateRoute($studentId));
        $pastoralTitle = 'This is an automation pastoral record updated';
        $recordId = $i->grabFromDatabase('pastoral', 'id', ['deleted_at' => null, 'heading like' => $pastoralTitle]);
        $pastoralProfile->clickDeleteIcon($recordId);
        $common->clickConfirmButton();
        $i->amGoingTo('ensure the record is no longer presented');
        $i->cantSee($pastoralTitle);
    }

//    /**
//     * @param AcceptanceTester $i
//     * @throws Exception
//     * @beforeSuite
//     *
//     * navigate to the student pastoral care page
//     * create a pastoral record for the student
//     * ensure the record details is roperly presented in the pastoral record page
//     */
//    public function viewPastoralRecord(AcceptanceTester $i): void
//    {
//        $profilePage = new ProfilePage($i);
//        $studentId = 25;
//        $i->amOnPage($profilePage->generateRoute($studentId));
//        //click the pastoral care button
//        //click create pastoral record visible to parents and students
//        //enter the details
//        //save it
//        //navigate to the pastoral care record page
//        //ensure te details is properly presented
//        //as a parent check the pastoral care records details
//        //as a student check the pastoral care records details
//    }
}
