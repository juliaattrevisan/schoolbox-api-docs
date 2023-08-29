<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/** @noinspection PhpDocMissingThrowsInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace Tests\Smoketests;

use Tests\Support\IntegrationTester;
use Tests\Support\Payload;
use Schoolbox\Pastoral;
use Schoolbox\User;

class PastoralCest
{
    /**
     * Push a login payload to Schoolbox each test to ensure session is active and logged in.
     *
     * @param IntegrationTester $i
     */
    public function _before(IntegrationTester $i): void
    {
        $i->loginAdmin();
        $i->stopFollowingRedirects();
        $i->updateInDatabase('config', array('value' => '1'), array('name' => 'pastoral_student_access'));
    }


    /** Creates a pastoral record for a student using a curl request.
     *
     * @param IntegrationTester $i
     */
    public function createPastoralRecord(IntegrationTester $i): void
    {
        # GIVEN
        $student_jreed = $i->grabUser('jreed');
        $this->createDefaultPastoralRecord($i, $student_jreed, 'Automated Test Pastoral record create at ' . time());

        #THEN
        $i->sendGET("/pastoral/student/{$student_jreed->getId()}");
        $i->seeResponseCodeIs(200);
    }


    /** Modifies a pastoral record for a student using a curl request.
     *
     *  Fires off create a pastoral record step.
     *
     * @param IntegrationTester $i
     */
    public function modifyPastoralRecord(IntegrationTester $i): void
    {
        #GIVEN
        $student_jreed = $i->grabUser('jreed');
        $pastoral_record = $this->createDefaultPastoralRecord($i, $student_jreed, 'Automated Test Pastoral record modify at ' . time());

        #WHEN
        $updatePastoral = new Payload\Pastoral\Update($pastoral_record);
        $updatePastoral->setFormParam('body_public', '<p>automated public statement - Edited</p>');
        $i->amGoingTo('Send request update pastoral record for student #' . $student_jreed->getId());
        $i->sendRequest($updatePastoral);

        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/pastoral/student/{$student_jreed->getId()}");

        $i->sendGET($i->grabHttpHeader('location'));
        $i->seeResponseCodeIs(200);
    }


    /** Duplicates a pastoral record for a student using a curl request.
     *
     *  Fires off create a pastoral record step.
     *
     * @param IntegrationTester $i
     */
    public function duplicatePastoralRecord(IntegrationTester $i): void
    {
        #GIVEN
        $student_jreed = $i->grabUser('jreed');
        $pastoral_record = $this->createDefaultPastoralRecord($i, $student_jreed, 'Automated Test Pastoral record duplicate at ' . time());

        #WHEN
        $student_duplicate_target = $i->grabUser('ereed');
        $updatePastoral           = new Payload\Pastoral\Update($pastoral_record);
        $updatePastoral->setDuplicateToUser($student_duplicate_target->getId());
        $i->amGoingTo("Send request duplicate pastoral record for student #{$student_jreed->getId()} to student #{$student_duplicate_target->getId()}");
        $i->sendRequest($updatePastoral);

        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/pastoral/student/{$student_jreed->getId()}");

        $i->sendGET($i->grabHttpHeader('location'));
        $i->seeResponseCodeIs(200);
    }


    /** Comments on a pastoral record for a student using a curl request.
     *  Ensure parents are not able to comment as the flag for parents comments is off
     *  Fires off create a pastoral record step.
     *
     * @param IntegrationTester $i
     */
    public function commentOnPastoralRecord(IntegrationTester $i): void
    {
        #GIVEN
        $student_jreed = $i->grabUser('jreed');
        $pastoral_record = $this->createDefaultPastoralRecord($i, $student_jreed, 'Automated Test Pastoral create comments at ' . time());

        #WHEN
        $pastoralCommentPayload = new Payload\Pastoral\Comment\PastoralComment($pastoral_record);
        $pastoralCommentPayload->setPublicComment('A very public comment');
        $i->amGoingTo("Send request add comment to student #{$student_jreed->getId()} record # {$pastoral_record->getId()}");
        $i->sendRequest($pastoralCommentPayload);

        #THEN
        $i->seeResponseCodeIs(200);
        #WHEN
        $pastoralCommentPayload = new Payload\Pastoral\Comment\PastoralComment($pastoral_record);
        $pastoralCommentPayload->setPrivateComment('A very private comment');
        $i->amGoingTo("Send request add private comment to student #{$student_jreed->getId()} record # {$pastoral_record->getId()}");
        $i->sendRequest($pastoralCommentPayload);

        #THEN
        $i->seeResponseCodeIs(200);

        #WHEN impersonating Jenni Reed
        $i->impersonateUser(27);

        #THEN I am unable to make a comment
        $pastoralCommentPayload = new Payload\Pastoral\Comment\PastoralComment($pastoral_record);
        $pastoralCommentPayload->setPublicComment('A very public comment from a parent');
        $i->amGoingTo("Send request add comment to student #{$student_jreed->getId()} record # {$pastoral_record->getId()}");
        $i->sendRequest($pastoralCommentPayload);

        $i->seeResponseCodeIs(400);

        $pastoralCommentPayload = new Payload\Pastoral\Comment\PastoralComment($pastoral_record);
        $pastoralCommentPayload->setPrivateComment('A very private comment from a parent');
        $i->amGoingTo("Send request add private comment to student #{$student_jreed->getId()} record # {$pastoral_record->getId()}");
        $i->sendRequest($pastoralCommentPayload);

        $i->seeResponseCodeIs(400);
    }

    /** Parents able to comment on a pastoral record for a student using a curl request.
     *
     *  Fires off create a pastoral record step.
     *
     * @param IntegrationTester $i
     */
    public function parentCommentOnPastoralRecord(IntegrationTester $i): void
    {
        #GIVEN
        $student_jreed = $i->grabUser('jreed');
        $parent         = $i->grabUser('jennireed01');

        $pastoral_record = $this->createUserCommentsToggledOnPastoralRecord($i, $student_jreed, $parent, 'Automated Test Pastoral Allow Parents to comment toggled on at ' . time());

        #WHEN impersonating Jenni Reed
        $i->impersonateUser(27);

        #THEN Jenni Reed(parent) is able to add comment to the pastoral record
        $pastoralCommentPayload = new Payload\Pastoral\Comment\PastoralComment($pastoral_record);
        $pastoralCommentPayload->setPublicComment('A very public comment from a parent');
        $i->amGoingTo("Send request add comment to student #{$student_jreed->getId()} record # {$pastoral_record->getId()}");
        $i->sendRequest($pastoralCommentPayload);

        $i->seeResponseCodeIs(200);

        $pastoralCommentPayload = new Payload\Pastoral\Comment\PastoralComment($pastoral_record);
        $pastoralCommentPayload->setPrivateComment('A very private comment from a parent');
        $i->amGoingTo("Send request add private comment to student #{$student_jreed->getId()} record # {$pastoral_record->getId()}");
        $i->sendRequest($pastoralCommentPayload);

        $i->seeResponseCodeIs(200);
    }

    /** Student is able to comment on a pastoral record for themselves using a curl request.
     *
     * @param IntegrationTester $i
     */
    public function studentCommentOnPastoralRecord(IntegrationTester $i): void
    {
        #GIVEN
        $student_jreed = $i->grabUser('jreed');
        $parent         = $i->grabUser('jennireed01');

        $pastoral_record = $this->createUserCommentsToggledOnPastoralRecord($i, $student_jreed, $student_jreed, 'Automated Test Pastoral Allow Student to comment toggled on at ' . time());

        #WHEN impersonating Jenni Reed
        $i->impersonateUser($student_jreed->getId());

        #THEN Jenni Reed(parent) is able to add comment to the pastoral record
        $pastoralCommentPayload = new Payload\Pastoral\Comment\PastoralComment($pastoral_record);
        $pastoralCommentPayload->setPublicComment('A very public comment from self');
        $i->amGoingTo("Send request add comment to self #{$student_jreed->getId()} record # {$pastoral_record->getId()}");
        $i->sendRequest($pastoralCommentPayload);

        $i->seeResponseCodeIs(200);

        $pastoralCommentPayload = new Payload\Pastoral\Comment\PastoralComment($pastoral_record);
        $pastoralCommentPayload->setPrivateComment('A very private comment from self');
        $i->amGoingTo("Send request add private comment to self #{$student_jreed->getId()} record # {$pastoral_record->getId()}");
        $i->sendRequest($pastoralCommentPayload);

        $i->seeResponseCodeIs(200);
    }

    /** Record visibility empty - only admin and pastoral moderator can view the record
     *
     *  Fires off create a pastoral record step.
     *
     * @param IntegrationTester $i
     */
    public function recordNoVisibilitySet(IntegrationTester $i): void
    {
        #GIVEN
        $student_jreed = $i->grabUser('jreed');
        $heading = 'Automated Test Pastoral Record visibility empty ' . time();

        $createPastoral = new Payload\Pastoral\Create($student_jreed->getId());
        $createPastoral->setHeading($heading);
        $i->amGoingTo('Send request create pastoral record visibility empty for student #' . $student_jreed->getId());
        $i->sendRequest($createPastoral);

        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/pastoral/student/{$student_jreed->getId()}");

        $record = $i->grabPastoralRecordByTitle($heading);
        $recordID = $i->grabFromDatabase('pastoral', 'id', ['heading' => $heading]);
        $pastoralCommentPayload = new Payload\Pastoral\Comment\PastoralComment($record);

        #WHEN admin navigates to the pastoral record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);

        #THEN admin can view that record
        $i->seeResponseCodeIs(200);
        $pastoralCommentPayload->setPublicComment('A comment from admin');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(200);

        #WHEN impersonating Jenni Reed
        $i->impersonateUser(27);

        #THEN Jenni Reed cannot view the record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);
        $i->seeResponseCodeIs(400);
        $pastoralCommentPayload->setPublicComment('A very public comment from a parent');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(400);

        #WHEN impersonating Alison Chan
        $i->impersonateUser(43);

        #THEN Alison Chan cannot view the record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);
        $i->seeResponseCodeIs(400);
        $pastoralCommentPayload->setPublicComment('A very public comment from a denied staff');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(400);

        #WHEN impersonating Jack Coles
        $i->impersonateUser(22);

        #THEN Jack Coles can view the record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);
        $i->seeResponseCodeIs(200);
        $pastoralCommentPayload->setPublicComment('A very public comment from moderator');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(200);
    }

    /** Record visibility - grant access to roles teacher (general) and parent (deny)
     * Ensure teacher can view the record and parent cannot view it
     *  Fires off create a pastoral record step.
     *
     * @param IntegrationTester $i
     */
    public function recordVisibilityRoleSet(IntegrationTester $i): void
    {
        #GIVEN
        $student_jreed = $i->grabUser('jreed');
        $heading = 'Automated Test Pastoral Record visibility Role Teacher General and Parent Deny ' . time();
        $teacher_role    = $i->grabRoleByTitle('Teacher');

        $createPastoral = new Payload\Pastoral\Create($student_jreed->getId());
        $createPastoral->setHeading($heading);
        $createPastoral->setRoleAccess('role-type-parent', Pastoral\Access\Mapper::ACCESS_DENY);
        $createPastoral->setRoleAccess($teacher_role->getId(), Pastoral\Access\Mapper::ACCESS_PUBLIC);
        $i->amGoingTo('Send request create pastoral record grant access by role for student #' . $student_jreed->getId());
        $i->sendRequest($createPastoral);

        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/pastoral/student/{$student_jreed->getId()}");

        $record = $i->grabPastoralRecordByTitle($heading);
        $recordID = $i->grabFromDatabase('pastoral', 'id', ['heading' => $heading]);
        $pastoralCommentPayload = new Payload\Pastoral\Comment\PastoralComment($record);

        #WHEN admin navigates to the pastoral record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);

        #THEN admin can view that record
        $i->seeResponseCodeIs(200);
        $pastoralCommentPayload->setPublicComment('A very public comment from admin');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(200);

        #WHEN impersonating Jenni Reed
        $i->impersonateUser(27);

        #THEN Jenni Reed cannot view the record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);
        $i->seeResponseCodeIs(400);
        $pastoralCommentPayload->setPublicComment('A very public comment from a parent');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(400);

        #WHEN impersonating Alison Chan
        $i->impersonateUser(43);

        #THEN Alison Chan can view the record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);
        $i->seeResponseCodeIs(200);
        $pastoralCommentPayload->setPublicComment('A very public comment from a staff');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(200);

        #WHEN impersonating Jack Coles
        $i->impersonateUser(22);

        #THEN Jack Coles can view the record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);
        $i->seeResponseCodeIs(200);
        $pastoralCommentPayload->setPublicComment('A very public comment from a moderator');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(200);
    }

    /** Record visibility - grant access by user: Jenni Reed(general&confidential), Alison Chan(general) and Staf 1(deny)
     * Ensure Jenni Reed can view the record, Alison can view the record and Staff 1 cannot view the record
     *  Fires off create a pastoral record step.
     *
     * @param IntegrationTester $i
     */
    public function recordVisibilityByUser(IntegrationTester $i): void
    {
        #GIVEN
        $student_jreed = $i->grabUser('jreed');
        $heading = 'Automated Test Pastoral Record visibility grant access by user ' . time();

        $createPastoral = new Payload\Pastoral\Create($student_jreed->getId());
        $createPastoral->setHeading($heading);
        $teacherUser         = $i->grabUser('staff1');
        $parentUser         = $i->grabUser('jennireed01');
        $teacherUser2         = $i->grabUser('achan');
        $createPastoral->setUserAccess($teacherUser->getId(), Pastoral\Access\Mapper::ACCESS_DENY);
        $createPastoral->setUserAccess($parentUser->getId(), Pastoral\Access\Mapper::ACCESS_PRIVATE);
        // $createPastoral->setParentCommentsAllowed($parentUser->getId());
        $createPastoral->setUserAccess($teacherUser2->getId(), Pastoral\Access\Mapper::ACCESS_PUBLIC);
        $i->amGoingTo('Send request create pastoral record grant access by user for student #' . $student_jreed->getId());
        $i->sendRequest($createPastoral);

        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/pastoral/student/{$student_jreed->getId()}");

        $record = $i->grabPastoralRecordByTitle($heading);
        $recordID = $i->grabFromDatabase('pastoral', 'id', ['heading' => $heading]);
        $pastoralCommentPayload = new Payload\Pastoral\Comment\PastoralComment($record);

        #WHEN admin navigates to the pastoral record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);

        #THEN admin can view that record
        $i->seeResponseCodeIs(200);
        $pastoralCommentPayload->setPublicComment('A very public comment from admin');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(200);

        #WHEN impersonating Jenni Reed
        $i->impersonateUser(27);

        #THEN Jenni Reed cannot view the record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);
        $i->seeResponseCodeIs(200);
        $pastoralCommentPayload->setPublicComment('A very public comment from a parent');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(401);

        #WHEN impersonating Alison Chan
        $i->impersonateUser(43);

        #THEN Alison Chan can view the record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);
        $i->seeResponseCodeIs(200);
        $pastoralCommentPayload->setPublicComment('A very public comment from a staff');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(200);

        #WHEN impersonating Jack Coles
        $i->impersonateUser(22);

        #THEN Jack Coles can view the record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);
        $i->seeResponseCodeIs(200);
        $pastoralCommentPayload->setPublicComment('A very public comment from a moderator');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(200);

        #WHEN impersonating Staff 1
        $i->impersonateUser(65);

        #THEN Staff 1 cannot view the record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);
        $i->seeResponseCodeIs(400);
        $pastoralCommentPayload->setPublicComment('A very public comment from a denied staff');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(400);
    }

    /** Record visibility - grant access by group
     * Ensure by granting access to Student Wellbeing Team Alison Chan is able to view the record
     * Ensure by denying access to Student Wellbeing Team Alison Chan is not able to view the record
     *  Fires off create a pastoral record step.
     *
     * @param IntegrationTester $i
     */
    public function recordVisibilityByGroup(IntegrationTester $i): void
    {
        #GIVEN
        $student_jreed = $i->grabUser('jreed');
        $heading = 'Automated Test Pastoral Record visibility grant access by group ' . time();

        $createPastoral = new Payload\Pastoral\Create($student_jreed->getId());
        $createPastoral->setHeading($heading);
        $createPastoral->setGroupAccess(2, Pastoral\Access\Mapper::ACCESS_PRIVATE);
        $i->amGoingTo('Send request create pastoral record grant access by group for student #' . $student_jreed->getId());
        $i->sendRequest($createPastoral);

        $pastoral = $i->grabPastoralRecordByTitle($heading);

        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/pastoral/student/{$student_jreed->getId()}");

        $record = $i->grabPastoralRecordByTitle($heading);
        $recordID = $i->grabFromDatabase('pastoral', 'id', ['heading' => $heading]);
        $pastoralCommentPayload = new Payload\Pastoral\Comment\PastoralComment($record);

        #WHEN admin navigates to the pastoral record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);

        #WHEN impersonating Jenni Reed
        $i->impersonateUser(27);

        #THEN Jenni Reed cannot view the record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);
        $i->seeResponseCodeIs(400);
        $pastoralCommentPayload->setPublicComment('A very public comment from a parent');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(400);

        #WHEN impersonating Alison Chan
        $i->impersonateUser(43);

        #THEN Alison Chan can view the record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);
        $i->seeResponseCodeIs(200);
        $pastoralCommentPayload->setPublicComment('A very public comment from a staff in a group');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(200);

        #WHEN impersonating Jack Coles
        $i->impersonateUser(22);

        #THEN Jack Coles can view the record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);
        $i->seeResponseCodeIs(200);
        $pastoralCommentPayload->setPublicComment('A very public comment from a moderator');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(200);

        #WHEN impersonating Staff 1
        $i->impersonateUser(65);

        #THEN Staff 1 cannot view the record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);
        $i->seeResponseCodeIs(400);
        $pastoralCommentPayload->setPublicComment('A very public comment from a denied staff');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(400);

        #GIVEN Jack Coles set access to the group as denied
        $i->impersonateUser(22);
        $updatePastoral = new Payload\Pastoral\Update($pastoral);
        $updatePastoral->setGroupAccess(2, Pastoral\Access\Mapper::ACCESS_DENY);
        $i->amGoingTo('Send request update pastoral record grant access by group for student #' . $student_jreed->getId());
        $i->sendRequest($updatePastoral);

        # WHEN impersonating Alison Chan
        $i->impersonateUser(43);

        #THEN Alison Chan can not view the record
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);
        $i->seeResponseCodeIs(400);
        $pastoralCommentPayload->setPublicComment('A very public comment from a denied staff');
        $i->sendRequest($pastoralCommentPayload);
        $i->seeResponseCodeIs(400);
    }

    /** Record visibility - grant access by campus
     * Ensure teachers can only see pastoral records for their campus when there record has campus toggled on
     * Ensure teachers can see pastoral records for any campus when there record has campus toggled off
     *  Fires off create a pastoral record step.
     *
     * @param IntegrationTester $i
     */
    public function recordVisibilityByCampus(IntegrationTester $i): void
    {
        #GIVEN
        $student_jreed = $i->grabUser('jreed');
        $heading = 'Automated Test Pastoral Record visibility grant access by campus to Jana Reed' . time();

        $createPastoral = new Payload\Pastoral\Create($student_jreed->getId());
        $createPastoral->setHeading($heading);
        $teacher_role    = $i->grabRoleByTitle('Teacher');
        $createPastoral->setRoleAccess($teacher_role->getId(), Pastoral\Access\Mapper::ACCESS_PUBLIC);
        $createPastoral->setRoleMatchCampus();
        $i->amGoingTo('Send request create pastoral record grant access according to the campus #' . $student_jreed->getId());
        $i->sendRequest($createPastoral);

        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/pastoral/student/{$student_jreed->getId()}");

        $record = $i->grabPastoralRecordByTitle($heading);
        $recordID = $i->grabFromDatabase('pastoral', 'id', ['heading' => $heading]);

        #WHEN admin navigates to the pastoral record
        $i->amGoingTo('as admin, navigate to the pastoral record and ensure they have access to it');
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);

        #THEN admin can view that record
        $i->seeResponseCodeIs(200);

        #WHEN Rebecca White navigates to the pastoral record
        $i->amGoingTo('as Rebecca White, navigate to the pastoral record and ensure they have access to it');
        $i->impersonateUser(24);
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);

        #THEN  Rebecca White can view that record
        $i->seeResponseCodeIs(200);

        #WHEN Alisson Chan navigates to the pastoral record
        $i->amGoingTo('as Alisson Chan primary teacher, navigate to the secondary student pastoral record and ensure they cannot access to it');
        $i->impersonateUser(43);
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);

        #THEN  Alisson Chan cannot view that record
        $i->seeResponseCodeIs(400);
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);

        #WHEN Staff1 navigates to the pastoral record
        $i->amGoingTo('as Staff1 secondary teacher, navigate to the secondary student pastoral record and ensure they have access to it');
        $i->impersonateUser(65);
        $i->sendGet('/pastoral/student/' . $student_jreed->getId() . '/record/' . $recordID);

        #THEN  Staff1 can view that record
        $i->seeResponseCodeIs(200);

        #GIVEN
        $student_ereed = $i->grabUser('ereed');
        $heading = 'Automated Test Pastoral Record visibility grant access by campus to Eden Reed' . time();

        $i->impersonateUser(1);
        $createPastoral = new Payload\Pastoral\Create($student_ereed->getId());
        $createPastoral->setHeading($heading);
        $teacher_role    = $i->grabRoleByTitle('Teacher');
        $createPastoral->setRoleAccess($teacher_role->getId(), Pastoral\Access\Mapper::ACCESS_PUBLIC);
        $createPastoral->setRoleMatchCampus();
        $i->amGoingTo('Send request create pastoral record grant access according to the campus #' . $student_ereed->getId());
        $i->sendRequest($createPastoral);

        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/pastoral/student/{$student_ereed->getId()}");

        $record = $i->grabPastoralRecordByTitle($heading);
        $recordID = $i->grabFromDatabase('pastoral', 'id', ['heading' => $heading]);

        #WHEN admin navigates to the pastoral record
        $i->amGoingTo('as admin, navigate to the pastoral record and ensure they have access to it');
        $i->sendGet('/pastoral/student/' . $student_ereed->getId() . '/record/' . $recordID);

        #THEN admin can view that record
        $i->seeResponseCodeIs(200);

        #WHEN Rebecca White navigates to the pastoral record
        $i->amGoingTo('as Rebecca White, navigate to the pastoral record and ensure they have access to it');
        $i->impersonateUser(24);
        $i->sendGet('/pastoral/student/' . $student_ereed->getId() . '/record/' . $recordID);

        #THEN  Rebecca White can view that record
        $i->seeResponseCodeIs(200);

        #WHEN Alisson Chan navigates to the pastoral record
        $i->amGoingTo('as Alisson Chan primary teacher, navigate to the primary student pastoral record and ensure they can access to it');
        $i->impersonateUser(43);
        $i->sendGet('/pastoral/student/' . $student_ereed->getId() . '/record/' . $recordID);

        #THEN  Alisson Chan can view that record
        $i->seeResponseCodeIs(200);
        $i->sendGet('/pastoral/student/' . $student_ereed->getId() . '/record/' . $recordID);

        #WHEN Staff1 navigates to the pastoral record
        $i->amGoingTo('as Staff1 secondary teacher, navigate to the primary student pastoral record and ensure they cannot access to it');
        $i->impersonateUser(65);
        $i->sendGet('/pastoral/student/' . $student_ereed->getId() . '/record/' . $recordID);

        #THEN  Staff1 can view that record
        $i->seeResponseCodeIs(400);
    }

    /** Test add pastoral type
     *  Fires off create a pastoral record step with the new pastoral type
     *
     * @param IntegrationTester $i
     */
    public function addPastoralType(IntegrationTester $i): void
    {
        #GIVEN new pastoral types are created
        $name = 'Automated Pastoral Type ' . time();
        $id = $i->grabNumRecords('lu_pastoral_type');
        $createPastoralType = new Payload\Admin\Lists\PastoralTypes($name, $id);
        $i->sendRequest($createPastoralType);
        $i->seeResponseCodeIs(200);
        $name = 'Automated Pastoral Type 2' . time();
        $id = $i->grabNumRecords('lu_pastoral_type');
        $createPastoralType = new Payload\Admin\Lists\PastoralTypes($name, $id);
        $i->sendRequest($createPastoralType);
        $i->seeResponseCodeIs(200);

        #WHEN sending a request to create a new pastoral record with the new pastoral type
        $student_jreed = $i->grabUser('jreed');
        $heading = 'Automated Test Pastoral Record Pastoral Type ' . time();
        $createPastoral = new Payload\Pastoral\Create($student_jreed->getId());
        $createPastoral->setHeading($heading);
        $createPastoral->setPastoralType($id);
        $createPastoral->setPastoralSubType(null);
        $i->amGoingTo('Send request create pastoral record with the new pastoral type for student #' . $student_jreed->getId());
        $i->sendRequest($createPastoral);

        #THEN the record is created with the new type id
        $i->seeResponseCodeIs(303);
        $i->grabPastoralRecordByTitle($heading);
        $typeID = $i->grabFromDatabase('pastoral', 'type_id', ['heading' => $heading]);
        $i->assertEquals($typeID, $id);
    }


    /** Test add pastoral subType for the type Bullying
     *  Fires off create a pastoral record step with the new pastoral subType
     *
     * @param IntegrationTester $i
     */
    public function addPastoralSubType(IntegrationTester $i): void
    {
        #GIVEN some new pastoral type are created
        $pastoralType = 4;
        $subTypeName = 'Automated Pastoral Bullying Sub Type ' . time();
        $id = $i->grabNumRecords('lu_pastoral_subtype');
        $createPastoralSubType = new Payload\Admin\Lists\PastoralSubType($pastoralType, $subTypeName, $id);
        $i->sendRequest($createPastoralSubType);
        $i->seeResponseCodeIs(200);
        $subTypeName = 'Automated Pastoral Bullying Sub Type 2' . time();
        $id = $i->grabNumRecords('lu_pastoral_subtype');
        $createPastoralSubType = new Payload\Admin\Lists\PastoralSubType($pastoralType, $subTypeName, $id);
        $i->sendRequest($createPastoralSubType);
        $i->seeResponseCodeIs(200);

        #WHEN sending a request to create a new pastoral record with the new pastoral type
        $student_jreed = $i->grabUser('jreed');
        $heading = 'Automated Test Pastoral Record Pastoral Sub Type 2' . time();
        $createPastoral = new Payload\Pastoral\Create($student_jreed->getId());
        $createPastoral->setHeading($heading);
        $createPastoral->setPastoralType($pastoralType);
        $createPastoral->setPastoralSubType($id);
        $i->amGoingTo('Send request create pastoral record with the new pastoral subtype for student #' . $student_jreed->getId());
        $i->sendRequest($createPastoral);

        #THEN the record is created with the new type id
        $i->seeResponseCodeIs(303);
        $i->grabPastoralRecordByTitle($heading);
        $subTypeID = $i->grabFromDatabase('pastoral', 'subtype_id', ['heading' => $heading]);
        $i->assertEquals($subTypeID, $id);
    }

    /** Test add pastoral action in the admin list
     *  Fires off create a pastoral record step with the pastoral action
     *
     * @param IntegrationTester $i
     */
    public function addPastoralActions(IntegrationTester $i): void
    {
        #GIVEN a news pastoral action is created
        $i->updateInDatabase('config', array('value' => '1'), array('name' => 'pastoral_action_enable'));
        $pastoralName = 'Automated Pastoral Action' . time();
        $id = $i->grabNumRecords('lu_pastoral_action');
        $createPastoraAction = new Payload\Admin\Lists\PastoralActions($pastoralName, $id);

        #WHEN the pastoral action request is sent
        $i->sendRequest($createPastoraAction);

        #THEN the pastoral action is added
        $i->seeResponseCodeIs(200);

        #GIVEN another pastoral action is created
        $pastoralName = 'Automated Pastoral Action' . time();
        $id = $i->grabNumRecords('lu_pastoral_action');
        $createPastoraAction = new Payload\Admin\Lists\PastoralActions($pastoralName, $id);

        #WHEN the pastoral action request is sent
        $i->sendRequest($createPastoraAction);

        #THEN the pastoral action is added
        $i->seeResponseCodeIs(200);

        #WHEN sending a request to create a new pastoral record with the pastoral action
        $i->haveInDatabase('pastoral_type_action', array('type_id' => 2, 'subtype_id' => null,'action_id' => $id));
        $student_jreed = $i->grabUser('jreed');
        $heading = 'Automated Test Pastoral Record with a Pastoral Action ' . time();
        $pastoralType = 2;
        $pastoralAction = $id;
        $createPastoral = new Payload\Pastoral\Create($student_jreed->getId());
        $createPastoral->setHeading($heading);
        $createPastoral->setPastoralType($pastoralType);
        $createPastoral->setPastoralSubType(null);
        $createPastoral->setPastoralActions($pastoralAction);
        $i->amGoingTo('Send request create pastoral record with the new pastoral action for student #' . $student_jreed->getId());
        $i->sendRequest($createPastoral);

        #THEN the record is created with the new pastoral action
        $i->seeResponseCodeIs(303);
        $pastoralRecord =  $i->grabFromDatabase('pastoral', 'id', ['heading' => $heading]);
        $actionId = $i->grabFromDatabase('pastoral_record_action', 'action_id', ['pastoral_id' => $pastoralRecord]);
        $i->assertEquals($pastoralAction, $actionId);
    }


    /**
     * Convenience method to create a default pastoral record via the API
     *
     * @param IntegrationTester $i
     * @param User              $student
     * @param string            $heading
     * @return Pastoral\Model
     */
    private function createDefaultPastoralRecord(IntegrationTester $i, User $student, string $heading): Pastoral\Model
    {
        $teacher         = $i->grabUser('jcoles');
        $blocked_teacher = $i->grabUser('staff1');
        $teacher_role    = $i->grabRoleByTitle('Teacher');

        $createPastoral = new Payload\Pastoral\Create($student->getId());
        $createPastoral->setHeading($heading);
        $createPastoral->setUserAccess($blocked_teacher->getId(), Pastoral\Access\Mapper::ACCESS_DENY);
        $createPastoral->setRoleAccess($teacher_role->getId(), Pastoral\Access\Mapper::ACCESS_PUBLIC);
        $createPastoral->setUserAccess($teacher->getId(), Pastoral\Access\Mapper::ACCESS_PRIVATE);
        $i->amGoingTo('Send request create pastoral record for student #' . $student->getId());
        $i->sendRequest($createPastoral);

        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/pastoral/student/{$student->getId()}");

        return $i->grabPastoralRecordByTitle($heading);
    }

    /**
     * Convenience method to create a pastoral record that parents can comment (general and confidential) it via the API
     *
     * @param IntegrationTester $i
     * @param User              $student
     * @param string            $heading
     * @return Pastoral\Model
     */
    private function createUserCommentsToggledOnPastoralRecord(IntegrationTester $i, User $student, User $viewer, string $heading): Pastoral\Model
    {
        $createPastoral = new Payload\Pastoral\Create($student->getId());
        $createPastoral->setHeading($heading);
        $createPastoral->setUserAccess($viewer->getId(), Pastoral\Access\Mapper::ACCESS_PRIVATE);
        $createPastoral->setUserCommentsAllowed($viewer->getId());
        $i->amGoingTo('Send request create pastoral record for student #' . $student->getId());
        $i->sendRequest($createPastoral);

        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/pastoral/student/{$student->getId()}");

        return $i->grabPastoralRecordByTitle($heading);
    }
}
