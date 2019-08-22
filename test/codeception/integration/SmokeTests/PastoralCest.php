<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace SmokeTests;

use IntegrationTester;
use Payload\PastoralRecord;
use Payload\PastoralComment;

class PastoralCest
{

    /**
     * Push a login payload to Schoolbox each test to ensure session is active and logged in.
     *
     * @param IntegrationTester $i
     */
    public function _before(IntegrationTester $i)
    {
        $i->loginAdmin();
    }


    /** Creates a pastoral record for a student using a curl request.
     *
     * @param IntegrationTester $i
     * @param PastoralRecord    $pastoralRecPayload
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function createPastoralRecord(IntegrationTester $i, PastoralRecord $pastoralRecPayload)
    {
        #GIVEN
        $timestamp = time();
        $teacher = $i->getUser('zsmith');
        $student_drhodes = $i->getUser('drhodes');
        $blocked_teacher = $i->getUser('jbenson');
        $teacher_role = $i->getRoleByTitle('Teachers');
        $heading = 'Automated Test Pastrol record create at ' . $timestamp;

        #WHEN
        $pastoralRecPayload->setStudentId($student_drhodes->getId());
        $pastoralRecPayload->setViewer('user', $blocked_teacher->getId(), 'blocked');
        $pastoralRecPayload->setViewer('role', $teacher_role->getId(), 'public');
        $pastoralRecPayload->setViewer('user', $teacher->getId(), 'confidential');
        $pastoralRecPayload->setHeading($heading);
        $response = $i->sendCurlRequest($pastoralRecPayload, true, 'Send request create pastoral record for student #' . $student_drhodes->getId());

        $expectedUrlsReturned = [
            ['https://vagrant.dev.schoolbox.cloud/pastoral/student/' . $student_drhodes->getId() => '303'],
            [IntegrationTester::NO_REDIRECT => '200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Modifies a pastoral record for a student using a curl request.
     *
     *  Fires off create a pastoral record step.
     *
     * @param IntegrationTester $i
     * @param PastoralRecord    $pastoralRecPayload
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function modifyPastoralRecord(IntegrationTester $i, PastoralRecord $pastoralRecPayload)
    {
        #GIVEN
        $timestamp = time();
        $teacher = $i->getUser('zsmith');
        $student_drhodes = $i->getUser('drhodes');
        $blocked_teacher = $i->getUser('jbenson');
        $teacher_role = $i->getRoleByTitle('Teachers');
        $heading = 'Automated Test Pastrol record modify at ' . $timestamp;

        $pastoralRecPayload->setStudentId($student_drhodes->getId());
        $pastoralRecPayload->setViewer('user', $blocked_teacher->getId(), 'blocked');
        $pastoralRecPayload->setViewer('role', $teacher_role->getId(), 'public');
        $pastoralRecPayload->setViewer('user', $teacher->getId(), 'confidential');
        $pastoralRecPayload->setHeading($heading);
        $i->sendCurlRequest($pastoralRecPayload, false, 'Send request create pastoral record for student #' . $student_drhodes->getId());
        $pastoral_record = $i->getPastoralRecordByTitle($heading);

        #WHEN
        $pastoralRecPayload->setUpdateRecord($pastoral_record->getId());
        $pastoralRecPayload->setFormParam('body_public', '<p>automated public statement - Edited</p>');
        $response = $i->sendCurlRequest($pastoralRecPayload, true, 'Send request modify pastoral record for student #' . $student_drhodes->getId());

        $expectedUrlsReturned = [
            ['https://vagrant.dev.schoolbox.cloud/pastoral/student/' . $student_drhodes->getId() => '303'],
            [IntegrationTester::NO_REDIRECT => '200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Duplicates a pastoral record for a student using a curl request.
     *
     *  Fires off create a pastoral record step.
     *
     * @param IntegrationTester $i
     * @param PastoralRecord    $pastoralRecPayload
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function duplicatePastoralRecord(IntegrationTester $i, PastoralRecord $pastoralRecPayload)
    {
        #GIVEN
        $timestamp = time();
        $teacher = $i->getUser('zsmith');
        $student_drhodes = $i->getUser('drhodes');
        $blocked_teacher = $i->getUser('jbenson');
        $teacher_role = $i->getRoleByTitle('Teachers');
        $student_duplicate_target = $i->getUser('rrhodes');
        $heading = 'Automated Test Pastrol record duplicate at ' . $timestamp;

        $pastoralRecPayload->setStudentId($student_drhodes->getId());
        $pastoralRecPayload->setViewer('user', $blocked_teacher->getId(), 'blocked');
        $pastoralRecPayload->setViewer('role', $teacher_role->getId(), 'public');
        $pastoralRecPayload->setViewer('user', $teacher->getId(), 'confidential');
        $pastoralRecPayload->setHeading($heading);
        $i->sendCurlRequest($pastoralRecPayload, false, 'Send request create pastoral record for student #' . $student_drhodes->getId());
        $pastoral_record = $i->getPastoralRecordByTitle($heading);

        #WHEN
        $pastoralRecPayload->setUpdateRecord($pastoral_record->getId());
        $pastoralRecPayload->setDupicateToUser($student_duplicate_target->getId());
        $response = $i->sendCurlRequest($pastoralRecPayload, true, 'Send request duplicate pastoral record for student #' . $student_drhodes->getId() . ' to student #' . $student_duplicate_target->getId());

        $expectedUrlsReturned = [
            ['https://vagrant.dev.schoolbox.cloud/pastoral/student/' . $student_drhodes->getId() => '303'],
            [IntegrationTester::NO_REDIRECT => '200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Comments on a pastoral record for a student using a curl request.
     *
     *  Fires off create a pastoral record step.
     *
     * @param IntegrationTester $i
     * @param PastoralRecord    $pastoralRecPayload
     * @param PastoralComment   $pastoralCommentPayload
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function commentOnPastoralRecord(IntegrationTester $i, PastoralRecord $pastoralRecPayload, PastoralComment $pastoralCommentPayload)
    {
        #GIVEN
        $timestamp = time();
        $teacher = $i->getUser('zsmith');
        $student_drhodes = $i->getUser('drhodes');
        $blocked_teacher = $i->getUser('jbenson');
        $teacher_role = $i->getRoleByTitle('Teachers');
        $heading = 'Automated Test Pastrol create comments at ' . $timestamp;

        $pastoralRecPayload->setStudentId($student_drhodes->getId());
        $pastoralRecPayload->setViewer('user', $blocked_teacher->getId(), 'blocked');
        $pastoralRecPayload->setViewer('role', $teacher_role->getId(), 'public');
        $pastoralRecPayload->setViewer('user', $teacher->getId(), 'confidential');

        $pastoralRecPayload->setHeading($heading);
        $i->sendCurlRequest($pastoralRecPayload, false, 'Send request create pastoral record for student #' . $student_drhodes->getId());
        $pastoral_record = $i->getPastoralRecordByTitle($heading);

        #WHEN
        $pastoralCommentPayload->setStudentAndRecordIds($student_drhodes->getId(), $pastoral_record->getId());
        $pastoralCommentPayload->setPublicComment('A very public comment');
        $response = $i->sendCurlRequest($pastoralCommentPayload, true, 'Send request add comment to student #' . $student_drhodes->getId() . ' record # ' . $pastoral_record->getId());

        $expectedUrlsReturned = [
            ['https://vagrant.dev.schoolbox.cloud/pastoral/student/' . $student_drhodes->getId() . '/record/' . $pastoral_record->getId() => '303'],
            [IntegrationTester::NO_REDIRECT => '200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $pastoralCommentPayload->setPrivateComment('A very private comment');
        $response = $i->sendCurlRequest($pastoralCommentPayload, true, 'Send request add private comment to student #' . $student_drhodes->getId() . ' record # ' . $pastoral_record->getId());

        $expectedUrlsReturned = [
            ['https://vagrant.dev.schoolbox.cloud/pastoral/student/' . $student_drhodes->getId() . '/record/' . $pastoral_record->getId() => '303'],
            [IntegrationTester::NO_REDIRECT => '200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

}
