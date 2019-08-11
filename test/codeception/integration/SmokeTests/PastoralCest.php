<?php

namespace SmokeTests;

use GuzzleHelper\GuzzleConstants;
use IntegrationTester;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\GuzzleException;

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
     *
     * @throws GuzzleException
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
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
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/pastoral/student/'.$student_drhodes->getId()=>'303'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->comment('Created pastoral record for student #'.$student_drhodes->getId());
        $i->checkCurlReturn($expectedUrlsReturned, $response);


        #WHEN
        $heading='Automated Test Pastrol record modify';
        $i->createPastoralCareRecord($heading, $student_drhodes->getId(), $public_viewers, $confidencial_viewers, $blocked_viewers);
        $patoral_record=$i->getPastoralRecordByTitle($heading);
        $response = $i->modifyPastoralCareRecord($heading, $patoral_record->getId(), $student_drhodes->getId(), $public_viewers, $confidencial_viewers, $blocked_viewers);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/pastoral/student/'.$student_drhodes->getId()=>'303'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->comment('Modified pastoral record for student #'.$student_drhodes->getId());
        $i->checkCurlReturn($expectedUrlsReturned, $response);


        #WHEN
        $heading='Automated Test Pastrol record duplicate';
        $student_duplicate_target = $i->getUser('rrhodes');
        $i->createPastoralCareRecord($heading, $student_drhodes->getId(), $public_viewers, $confidencial_viewers, $blocked_viewers);
        $patoral_record=$i->getPastoralRecordByTitle($heading);
        $response = $i->modifyPastoralCareRecord($heading, $patoral_record->getId(), $student_drhodes->getId(), $public_viewers, $confidencial_viewers, $blocked_viewers, $student_duplicate_target->getId());
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/pastoral/student/'.$student_drhodes->getId()=>'303'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->comment('Duplicate pastoral record for student #'.$student_drhodes->getId().' to student #'.$student_duplicate_target->getId());
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $heading='Automated Test Pastrol record Comments';
        $i->createPastoralCareRecord($heading, $student_drhodes->getId(), $public_viewers, $confidencial_viewers, $blocked_viewers);
        $patoral_record=$i->getPastoralRecordByTitle($heading);
        $comment = 'Automated Test Regular Comment';
        $response = $i->createPastoralCareRecordComment($patoral_record->getId(), $student_drhodes->getId(), $comment);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/pastoral/student/'.$student_drhodes->getId().'/record/'.$patoral_record->getId()=>'303'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->comment('Create pastoral record comment on record #'.$patoral_record->getId());
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $comment = 'Automated Test Private Comment';
        $response = $i->createPastoralCareRecordComment($patoral_record->getId(), $student_drhodes->getId(), $comment, true);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/pastoral/student/'.$student_drhodes->getId().'/record/'.$patoral_record->getId()=>'303'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->comment('Create a confidential pastoral record comment on record #'.$patoral_record->getId());
        $i->checkCurlReturn($expectedUrlsReturned, $response);

    }
}