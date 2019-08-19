<?php

namespace SmokeTests;

use GuzzleHelper\GuzzleConstants;
use GuzzleHttp\Exception\GuzzleException;
use IntegrationTester;
use GuzzleHttp\Psr7\Response;
use Payload\Base;
use Payload\GroupMember;
use Schoolbox\External\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Payload\Folder as FolderPayload;
use Payload\Assessment as AssessmentPayload;
use Payload\AssessmentMark as AssessmentMarkPayload;
use Schoolbox\User;

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class AssessmentOnGroupCest {

    /**
     * Push a login payload to Schoolbox each test to ensure session is active and logged in.
     *
     * @param IntegrationTester $i
     */
    public function _before(IntegrationTester $i)
    {
        $i->loginAdmin2();
    }

    public function createAssessment(IntegrationTester $i, AssessmentPayload $newAssessmentRequest)
    {
        #GIVEN
        $timestamp = time();
        $group_title='Testing Create Assessments on Group Page at '.$timestamp;


        $folderPayload = new FolderPayload();
        $folderPayload->setTemplate(FolderPayload::TEMPLATE_HOMEPAGE_ID);
        $folderPayload->setName($group_title);
        $i->sendCurlRequest($folderPayload, false, 'Send request create Group Page');
        $groupPage=$i->getFirstFolderByTitle($group_title);
        $assessment_title='Testing Create Assessment On Group Page ID '.$groupPage->getId().' at '.$timestamp;

        #WHEN
        $newAssessmentRequest->setTitle($assessment_title);
        $newAssessmentRequest->setFolderId($groupPage->getId());
        $newAssessmentRequest->setContextId($groupPage->getId());

        /** @var Response $response */
        $response = $i->sendCurlRequest($newAssessmentRequest, true, 'Send request create asessment on Group Page #'.$groupPage->getId());
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/homepage/'.$groupPage->getId()=>'303'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /**        $student = $i->getUser('drhodes');
    $teacher = $i->getUser('zsmith');
    $admin = $i->getUser('alaress');

    $memberPayload = new GroupMember();
    $memberPayload->setFolderGroup($groupPage->getId());
    $memberPayload->setNewMember($student->getId());
    $memberPayload->setNewMember($admin->getId());
    $memberPayload->setNewMember($teacher->getId());
    $i->sendCurlRequest($memberPayload, false, 'Add group members to homepage #'.$homePage->getId());

    $memberPayload->resetMemberAdjustmentList();
    $memberPayload->setNewMemberPermission($student->getId(), 'read');
    $memberPayload->setNewMemberPermission($teacher->getId(), 'write');
    $memberPayload->setNewMemberPermission($admin->getId(), 'admin');
    $i->sendCurlRequest($memberPayload, false, 'Add group members permissions to homepage #'.$homePage->getId());
    */

    public function updateAssessment(IntegrationTester $i, AssessmentPayload $newAssessmentRequest)
    {
        #GIVEN
        $timestamp = time();
        $group_title='Testing Edit Assessments on Group Page at '.$timestamp;
        $student = $i->getUser('drhodes');

        $folderPayload = new FolderPayload();
        $folderPayload->setTemplate(FolderPayload::TEMPLATE_HOMEPAGE_ID);
        $folderPayload->setName($group_title);
        $i->sendCurlRequest($folderPayload, false, 'Send request create Group Page');
        $groupPage=$i->getFirstFolderByTitle($group_title);
        $assessment_title='Testing Edit Assessment On Group Page ID '.$groupPage->getId().' at '.$timestamp;

        $newAssessmentRequest->setTitle($assessment_title);
        $newAssessmentRequest->setFolderId($groupPage->getId());
        $newAssessmentRequest->setContextId($groupPage->getId());

        $i->sendCurlRequest($newAssessmentRequest, true, 'Send request create asessment on Group Page #'.$groupPage->getId());
        $assessment=$i->getFirstAssessmentByTitle($assessment_title);

        #WHEN
        $newAssessmentRequest->setPage('/rubric/determineMarkInvalidation');
        $newAssessmentRequest->setReferer( '/learning/assessment/'.$assessment->getId().'/modify?returnUrl=');
        $newAssessmentRequest->removeFormParam('part-2');
        $newAssessmentRequest->setFormParam('id', $assessment->getId());
        $newAssessmentRequest->setFormParam('assessmentType', ['dueWork', 'dueWork']);
        $newAssessmentRequest->removeFormParam('rb[0][level][0][id]');
        $newAssessmentRequest->removeFormParam('rb[0][level][0][name]');
        $newAssessmentRequest->removeFormParam('rb[0][level][1][id]');
        $newAssessmentRequest->removeFormParam('rb[0][level][1][name]');
        $newAssessmentRequest->setFormParam('rb['.$assessment->getId().'][level][0][id]','');
        $newAssessmentRequest->setFormParam('rb['.$assessment->getId().'][level][0][name]','');
        $newAssessmentRequest->setFormParam('rb['.$assessment->getId().'][level][1][id]','');
        $newAssessmentRequest->setFormParam('rb['.$assessment->getId().'][level][1][name]','');

        $determine_mark_response = $i->sendCurlRequest($newAssessmentRequest, true, 'Send request to edit assessment part 1 mark invalidation #'.$groupPage->getId());

        $newAssessmentRequest->setPage('/learning/assessments/'.$assessment->getId());
        $newAssessmentRequest->setReferer('/learning/assessments/'.$assessment->getId().'/modify?returnUrl=');
        $newAssessmentRequest->setFormParam('part-2', 1);

        $update_response = $i->sendCurlRequest($newAssessmentRequest, true, 'Send request to edit assessment part 2 mark edit assesssment #'.$groupPage->getId());
        $expectedUrlsReturned= [[GuzzleConstants::NO_REDIRECT=>'200']];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $determine_mark_response);
        $i->checkCurlReturn($expectedUrlsReturned, $update_response);
    }

    public function createAssessmentWithRubric(IntegrationTester $i, AssessmentPayload $newAssessmentRequest)
    {
        #GIVEN
        $timestamp = time();
        $group_title='Testing Create Assessments with rubric on Group Page at '.$timestamp;
        $student = $i->getUser('drhodes');

        $folderPayload = new FolderPayload();
        $folderPayload->setTemplate(FolderPayload::TEMPLATE_HOMEPAGE_ID);
        $folderPayload->setName($group_title);
        $i->sendCurlRequest($folderPayload, false, 'Send request create Group Page');
        $groupPage=$i->getFirstFolderByTitle($group_title);

        #WHEN
        $assessment_title='Testing Create Assessment with Rubric On Group Page ID '.$groupPage->getId().' at '.$timestamp;
        $newAssessmentRequest->setTitle($assessment_title);
        $newAssessmentRequest->setFolderId($groupPage->getId());
        $newAssessmentRequest->setContextId($groupPage->getId());
        $newAssessmentRequest->setFormParam('useRubric', 'on');
        $newAssessmentRequest=$i->setDefaultRubric($newAssessmentRequest);

        $response = $i->sendCurlRequest($newAssessmentRequest, true, 'Send request create asessment with rubric on Group Page #'.$groupPage->getId());
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/homepage/'.$groupPage->getId()=>'303'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    public function submitAssessment(IntegrationTester $i, AssessmentPayload $newAssessmentRequest)
    {
        #GIVEN
        $timestamp = time();
        $group_title='Testing Submit Assessment on Group Page at '.$timestamp;


        $folderPayload = new FolderPayload();
        $folderPayload->setTemplate(FolderPayload::TEMPLATE_HOMEPAGE_ID);
        $folderPayload->setName($group_title);
        $i->sendCurlRequest($folderPayload, false, 'Send request create Group Page');
        $groupPage=$i->getFirstFolderByTitle($group_title);

        $student = $i->getUser('drhodes');
        $teacher = $i->getUser('zsmith');
        $admin = $i->getUser('alaress');

        $memberPayload = new GroupMember();
        $memberPayload->setFolderGroup($groupPage->getId());
        $memberPayload->setNewMember($student->getId());
        $memberPayload->setNewMember($admin->getId());
        $memberPayload->setNewMember($teacher->getId());
        $i->sendCurlRequest($memberPayload, false, 'Add group members to Group Page #'.$groupPage->getId());

        $memberPayload->resetMemberAdjustmentList();
        $memberPayload->setNewMemberPermission($student->getId(), 'read');
        $memberPayload->setNewMemberPermission($teacher->getId(), 'write');
        $memberPayload->setNewMemberPermission($admin->getId(), 'admin');
        $i->sendCurlRequest($memberPayload, false, 'Add group members permissions to Group Page #'.$groupPage->getId());

        $assessment_title='Testing Submit Assessment On Homepage ID '.$groupPage->getId().' at '.$timestamp;

        $newAssessmentRequest->setTitle($assessment_title);
        $newAssessmentRequest->setFolderId($groupPage->getId());
        $newAssessmentRequest->setContextId($groupPage->getId());

        $i->sendCurlRequest($newAssessmentRequest, false, 'Send request create asessment on Group Page #'.$groupPage->getId());
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title);

        #WHEN
        $submitAssessmentPayload = new Base();
        $submitAssessmentPayload->setPage('/learning/assessments/'.$assessmentPageId .'/'.$student->getId().'/submit');
        $submitAssessmentPayload->setReferer('/learning/assessments/'.$assessmentPageId .'/'.$student->getId().'/submit');
        $submitAssessmentPayload->setFormParam('responseId','');
        $submitAssessmentPayload->setFormParam('submitted_in_person','on');

        $response = $i->sendCurlRequest($submitAssessmentPayload, true, 'Send submit assessment on students behalf. Student #'.$student->getId());

        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/learning/assessments/'.$assessmentPageId.'/'.$student->getId()=>'302'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

    }

    public function markAssessmentWithRubric(IntegrationTester $i, AssessmentPayload $newAssessmentRequest, AssessmentMarkPayload $newMarkRequest)
    {
        #GIVEN
        $timestamp = time();
        $group_title='Testing Mark Assessment with rubric on Group Page at '.$timestamp;

        $folderPayload = new FolderPayload();
        $folderPayload->setTemplate(FolderPayload::TEMPLATE_HOMEPAGE_ID);
        $folderPayload->setName($group_title);
        $i->sendCurlRequest($folderPayload, false, 'Send request create Group Page');
        $groupPage=$i->getFirstFolderByTitle($group_title);

        $student = $i->getUser('drhodes');
        $teacher = $i->getUser('zsmith');
        $admin = $i->getUser('alaress');

        $memberPayload = new GroupMember();
        $memberPayload->setFolderGroup($groupPage->getId());
        $memberPayload->setNewMember($student->getId());
        $memberPayload->setNewMember($admin->getId());
        $memberPayload->setNewMember($teacher->getId());
        $i->sendCurlRequest($memberPayload, false, 'Add group members to Group Page #'.$groupPage->getId());

        $memberPayload->resetMemberAdjustmentList();
        $memberPayload->setNewMemberPermission($student->getId(), 'read');
        $memberPayload->setNewMemberPermission($teacher->getId(), 'write');
        $memberPayload->setNewMemberPermission($admin->getId(), 'admin');
        $i->sendCurlRequest($memberPayload, false, 'Add group members permissions to Group Page #'.$groupPage->getId());

        $assessment_title='Testing Mark Assessment with rubric On Homepage ID '.$groupPage->getId().' at '.$timestamp;
        $newAssessmentRequest->setTitle($assessment_title);
        $newAssessmentRequest->setFolderId($groupPage->getId());
        $newAssessmentRequest->setContextId($groupPage->getId());
        $newAssessmentRequest->setFormParam('useRubric', 'on');
        $newAssessmentRequest=$i->setDefaultRubric($newAssessmentRequest);

        $i->sendCurlRequest($newAssessmentRequest, false, 'Send request create asessment on Group Page #'.$groupPage->getId());
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title);

        $submitAssessmentPayload = new Base();
        $submitAssessmentPayload->setPage('/learning/assessments/'.$assessmentPageId .'/'.$student->getId().'/submit');
        $submitAssessmentPayload->setReferer('/learning/assessments/'.$assessmentPageId .'/'.$student->getId().'/submit');
        $submitAssessmentPayload->setFormParam('responseId','');
        $submitAssessmentPayload->setFormParam('submitted_in_person','on');

        $i->sendCurlRequest($submitAssessmentPayload, false, 'Send submit assessment on students behalf. Student #'.$student->getId());
        $submissionID = $i->getFirstSubmissionIdByOwnerAndAssessmentID($assessmentPageId, $admin->getId(), $student->getId());

        #WHEN
        $newMarkRequest->setAssessmentId($assessmentPageId);
        $newMarkRequest->setSubmissionId($submissionID);
        $newMarkRequest->setSubmittingStudentId($student->getId());
        $i->setDefaultRubricMarks($newMarkRequest);

        $response = $i->sendCurlRequest($newMarkRequest, true, 'Send request mark rubric assessment # '.$assessmentPageId.' for student # '.$student->getId());
        $expectedUrlsReturned= [[GuzzleConstants::NO_REDIRECT=>'200']];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    public function markAssessment(IntegrationTester $i, AssessmentPayload $newAssessmentRequest, AssessmentMarkPayload $newMarkRequest)
    {
        #GIVEN
        $timestamp = time();
        $group_title='Testing Mark Assessment on Group Page at '.$timestamp;


        $folderPayload = new FolderPayload();
        $folderPayload->setTemplate(FolderPayload::TEMPLATE_HOMEPAGE_ID);
        $folderPayload->setName($group_title);
        $i->sendCurlRequest($folderPayload, false, 'Send request create Group Page');
        $groupPage=$i->getFirstFolderByTitle($group_title);

        $student = $i->getUser('drhodes');
        $teacher = $i->getUser('zsmith');
        $admin = $i->getUser('alaress');

        $memberPayload = new GroupMember();
        $memberPayload->setFolderGroup($groupPage->getId());
        $memberPayload->setNewMember($student->getId());
        $memberPayload->setNewMember($admin->getId());
        $memberPayload->setNewMember($teacher->getId());
        $i->sendCurlRequest($memberPayload, false, 'Add group members to Group Page #'.$groupPage->getId());

        $memberPayload->resetMemberAdjustmentList();
        $memberPayload->setNewMemberPermission($student->getId(), 'read');
        $memberPayload->setNewMemberPermission($teacher->getId(), 'write');
        $memberPayload->setNewMemberPermission($admin->getId(), 'admin');
        $i->sendCurlRequest($memberPayload, false, 'Add group members permissions to Group Page #'.$groupPage->getId());

        $assessment_title='Testing Mark Assessment On Homepage ID '.$groupPage->getId().' at '.$timestamp;

        $newAssessmentRequest->setTitle($assessment_title);
        $newAssessmentRequest->setFolderId($groupPage->getId());
        $newAssessmentRequest->setContextId($groupPage->getId());

        $i->sendCurlRequest($newAssessmentRequest, false, 'Send request create asessment on Group Page #'.$groupPage->getId());
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title);

        $submitAssessmentPayload = new Base();
        $submitAssessmentPayload->setPage('/learning/assessments/'.$assessmentPageId .'/'.$student->getId().'/submit');
        $submitAssessmentPayload->setReferer('/learning/assessments/'.$assessmentPageId .'/'.$student->getId().'/submit');
        $submitAssessmentPayload->setFormParam('responseId','');
        $submitAssessmentPayload->setFormParam('submitted_in_person','on');

        $i->sendCurlRequest($submitAssessmentPayload, false, 'Send submit assessment on students behalf. Student #'.$student->getId());
        $submissionID = $i->getFirstSubmissionIdByOwnerAndAssessmentID($assessmentPageId, $admin->getId(), $student->getId());

        #WHEN
        $newMarkRequest->setAssessmentId($assessmentPageId);
        $newMarkRequest->setSubmissionId($submissionID);
        $newMarkRequest->setSubmittingStudentId($student->getId());

        $response = $i->sendCurlRequest($newMarkRequest, true, 'Send request mark assessment # '.$assessmentPageId.' for student # '.$student->getId());
        $expectedUrlsReturned= [[GuzzleConstants::NO_REDIRECT=>'200']];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }



    /**
     * Smoke test assessment post forms include following actions:
     * Add assessment to created homepage
     * Update assesssment
     * Create assessment with rubric
     * Submit assessment on behalf of student
     * Mark assessment with rubric
     * Mark normal assessment
     *
     * Each of these in the future itteration should be split into sepperate test functions.
     *
     * @param IntegrationTester $i
     *
     * @throws GuzzleException
     * @throws \Schoolbox\Mapper\Exception
     * @throws Deleted
     * @throws Exception
     *
     *
     */
    public function SmokeAssessmentPOST(IntegrationTester $i):void
    {
        #GIVEN
        $i->loginAdmin(); #we logged in as alaress
        $homepage_title='Testing Home Page for Assessment';
        $i->createHomepage($homepage_title);
        $homePageId=$i->getFirstHomepageIdByTitle($homepage_title);
        $i->addDefaultUsersToHomepage($homePageId);
        $student = $i->getUser('drhodes');
        $admin = $i->getUser('alaress');
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId;

        #WHEN
        /** @var Response $response */
        $response = $i->createAssessmentOnHomepage($assessment_title,$homePageId);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/homepage/'.$homePageId=>'303'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->comment('Created homepage and added assessment to it');
        $i->checkCurlReturn($expectedUrlsReturned, $response);



        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId.' Edit it 3';
        $i->createAssessmentOnHomepage($assessment_title, $homePageId);
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title);
        $determine_mark_response = $i->determineMarkInvalidation($homePageId, $assessmentPageId, $assessment_title);
        $update_response = $i->updateAssessmentOnHomepage($homePageId, $assessmentPageId, $assessment_title);
        $expectedUrlsReturned= [[GuzzleConstants::NO_REDIRECT=>'200']];

        #THEN
        $i->comment('Updated Assessment');
        $i->checkCurlReturn($expectedUrlsReturned, $determine_mark_response);
        $i->checkCurlReturn($expectedUrlsReturned, $update_response);

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId.' With Rubric';
        $response = $i->createAssessementForHomepageWithRubric($assessment_title,$homePageId);

        #THEN
        $i->comment('Create Assessment with rubric for a homepage');
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId;
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title);
        $response = $i->submitAssessment($assessmentPageId, $student->getId());

        #THEN
        $i->comment('Submit assessment on behalf of student');
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId.' With Rubric';
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title);
        $i->submitAssessment($assessmentPageId, $student->getId());

        #THEN
        $response = $i->markAssessmentWithRubric($assessmentPageId, $admin->getId(), $student->getId());
        $i->comment('Mark assessment with rubric');

        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId;
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title);
        $response = $i->markAssessment($assessmentPageId, $admin->getId(), $student->getId());

        #THEN
        $i->comment('Mark normal assessment');
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        ##To Do - Submit reflection on assessment.

    }
}