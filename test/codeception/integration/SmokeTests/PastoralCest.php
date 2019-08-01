<?php

use GuzzleHttp\Psr7\Response;

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class PastoralCest {

    /**
     * Smoke test pastoral post forms include following actions:
     *
     * Create pastoral record
     * modify a pastoral record
     * duplicate a pastoral record to a second user
     * comment on normal section of pastoral record
     * comment on confidential section of the pastoral record
     *
     * Each of these in the future itteration should be split into sepperate test functions.
     *
     * @param IntegrationTester $i
     */
    public function SmokePastoralCarePOST(IntegrationTester $i)
    {
        #GIVEN
        $i->loginAdmin(); #we logged in as alaress
        $teacher = $i->getUser('zsmith');
        $student_drhodes = $i->getUser('drhodes');
        $blocked_teacher = $i->getUser('jbenson');
        $teacher_role = $i->getRoleByTitle('Teachers');
        $public_viewers = [
            'role' => $teacher_role->getId()
        ];
        $confidencial_viewers = [
            'user' => $teacher->getId()
        ];
        $blocked_viewers = [
            'user' => $blocked_teacher->getId()
        ];

        #WHEN
        $heading='Automated Test Pastrol record create';
        $response = $i->createPastoralCareRecord($heading, $student_drhodes->getId(), $public_viewers, $confidencial_viewers, $blocked_viewers);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');

        #THEN
        $i->comment('Created pastoral record for student #'.$student_drhodes->getId());
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected after we create a pastoral record.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/pastoral/student/'.$student_drhodes->getId(), $redirect_history[0], 'I expect we get redirected to the student the record we created belongs to.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');


        #WHEN
        $heading='Automated Test Pastrol record modify';
        $i->createPastoralCareRecord($heading, $student_drhodes->getId(), $public_viewers, $confidencial_viewers, $blocked_viewers);
        $patoral_record=$i->getPastoralRecordByTitle($heading);
        $response = $i->modifyPastoralCareRecord($heading, $patoral_record->getId(), $student_drhodes->getId(), $public_viewers, $confidencial_viewers, $blocked_viewers);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');

        #THEN
        $i->comment('Modified pastoral record for student #'.$student_drhodes->getId());
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected after we modify the pastoral record.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/pastoral/student/'.$student_drhodes->getId(), $redirect_history[0], 'I expect we get redirected to the student the record we modified belongs to.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');


        #WHEN
        $heading='Automated Test Pastrol record duplicate';
        $student_duplicate_target = $i->getUser('rrhodes');
        $i->createPastoralCareRecord($heading, $student_drhodes->getId(), $public_viewers, $confidencial_viewers, $blocked_viewers);
        $patoral_record=$i->getPastoralRecordByTitle($heading);
        $response = $i->modifyPastoralCareRecord($heading, $patoral_record->getId(), $student_drhodes->getId(), $public_viewers, $confidencial_viewers, $blocked_viewers, $student_duplicate_target->getId());
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');

        #THEN
        $i->comment('Duplicate pastoral record for student #'.$student_drhodes->getId().' to student #'.$student_duplicate_target->getId());
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected after we duplicate a pastoral record.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/pastoral/student/'.$student_drhodes->getId(), $redirect_history[0], 'I expect we get redirected to the student the record we duplicated belongs to.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $heading='Automated Test Pastrol record Comments';
        $i->createPastoralCareRecord($heading, $student_drhodes->getId(), $public_viewers, $confidencial_viewers, $blocked_viewers);
        $patoral_record=$i->getPastoralRecordByTitle($heading);
        $comment = 'Automated Test Regular Comment';
        $response = $i->createPastoralCareRecordComment($patoral_record->getId(), $student_drhodes->getId(), $comment);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');

        #THEN
        $i->comment('Create pastoral record comment on record #'.$patoral_record->getId());
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected after we comment on pastoral record.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/pastoral/student/'.$student_drhodes->getId().'/record/'.$patoral_record->getId(), $redirect_history[0], 'I expect we get redirected to the record we commented on.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $comment = 'Automated Test Private Comment';
        $response = $i->createPastoralCareRecordComment($patoral_record->getId(), $student_drhodes->getId(), $comment, true);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');

        #THEN
        $i->comment('Create a confidential pastoral record comment on record #'.$patoral_record->getId());
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected after we comment on pastoral record.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/pastoral/student/'.$student_drhodes->getId().'/record/'.$patoral_record->getId(), $redirect_history[0], 'I expect we get redirected to the record we commented on.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

    }
}