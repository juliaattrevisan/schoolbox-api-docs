<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
/** @noinspection PhpDocMissingThrowsInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace SmokeTests;

use IntegrationTester;
use Payload;
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
     *
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
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/pastoral/student/{$student_jreed->getId()}/record/{$pastoral_record->getId()}");

        #WHEN
        $pastoralCommentPayload = new Payload\Pastoral\Comment\PastoralComment($pastoral_record);
        $pastoralCommentPayload->setPrivateComment('A very private comment');
        $i->amGoingTo("Send request add private comment to student #{$student_jreed->getId()} record # {$pastoral_record->getId()}");
        $i->sendRequest($pastoralCommentPayload);

        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/pastoral/student/{$student_jreed->getId()}/record/{$pastoral_record->getId()}");

        #WHEN
        $i->sendGET($i->grabHttpHeader('location'));
        $i->seeResponseCodeIs(200);
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
}
