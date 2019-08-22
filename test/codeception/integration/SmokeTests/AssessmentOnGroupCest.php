<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace SmokeTests;

use IntegrationTester;
use GuzzleHttp\Psr7\Response;
use Payload;
use Payload\Base;
use Payload\GroupMember;
use Schoolbox\External\Exception;
use Schoolbox\Mapper\Exception\Deleted;

class AssessmentOnGroupCest
{

    /**
     * Push a login payload to Schoolbox each test to ensure session is active and logged in.
     *
     * @param IntegrationTester $i
     */
    public function _before(IntegrationTester $i): void
    {
        $i->loginAdmin();
    }

    /** Creates an assessment using a curl payload
     *
     * Fires off creation of a group homepage folder.
     *
     * @param IntegrationTester  $i
     * @param Payload\Assessment $newAssessmentRequest
     * @throws \Schoolbox\Mapper\Exception
     */
    public function createAssessment(IntegrationTester $i, Payload\Assessment $newAssessmentRequest): void
    {
        #GIVEN
        $timestamp  = time();
        $groupTitle = 'Testing Create Assessments on Group Page at ' . $timestamp;

        $folderPayload = new Payload\Folder();
        $folderPayload->setTemplate(Payload\Folder::TEMPLATE_HOMEPAGE_ID);
        $folderPayload->setName($groupTitle);
        $i->sendCurlRequest($folderPayload, false, 'Send request create Group Page');
        $groupPage        = $i->getFirstFolderByTitle($groupTitle);
        $assessment_title = 'Testing Create Assessment On Group Page ID ' . $groupPage->getId() . ' at ' . $timestamp;

        #WHEN
        $newAssessmentRequest->setTitle($assessment_title);
        $newAssessmentRequest->setFolderId($groupPage->getId());
        $newAssessmentRequest->setContextId($groupPage->getId());

        /** @var Response $response */
        $response             = $i->sendCurlRequest($newAssessmentRequest, true, 'Send request create asessment on Group Page #' . $groupPage->getId());
        $expectedUrlsReturned = [
            ['https://vagrant.dev.schoolbox.cloud/homepage/' . $groupPage->getId() => '303'],
            [IntegrationTester::NO_REDIRECT => '200'],
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Updates an assessment using a curl payuload.
     *
     * Fires off creation of a group homepage folder and create assessment.
     *
     * @param IntegrationTester  $i
     * @param Payload\Assessment $newAssessmentRequest
     * @throws Deleted
     * @throws \Schoolbox\Mapper\Exception
     */
    public function updateAssessment(IntegrationTester $i, Payload\Assessment $newAssessmentRequest): void
    {
        #GIVEN
        $timestamp   = time();
        $group_title = 'Testing Edit Assessments on Group Page at ' . $timestamp;

        $folderPayload = new Payload\Folder();
        $folderPayload->setTemplate(Payload\Folder::TEMPLATE_HOMEPAGE_ID);
        $folderPayload->setName($group_title);
        $i->sendCurlRequest($folderPayload, false, 'Send request create Group Page');
        $groupPage        = $i->getFirstFolderByTitle($group_title);
        $assessment_title = 'Testing Edit Assessment On Group Page ID ' . $groupPage->getId() . ' at ' . $timestamp;

        $newAssessmentRequest->setTitle($assessment_title);
        $newAssessmentRequest->setFolderId($groupPage->getId());
        $newAssessmentRequest->setContextId($groupPage->getId());

        $i->sendCurlRequest($newAssessmentRequest, true, 'Send request create asessment on Group Page #' . $groupPage->getId());
        $assessment = $i->getFirstAssessmentByTitle($assessment_title);

        #WHEN
        $newAssessmentRequest->setPage('/rubric/determineMarkInvalidation');
        $newAssessmentRequest->setReferer('/learning/assessment/' . $assessment->getId() . '/modify?returnUrl=');
        $newAssessmentRequest->removeFormParam('part-2');
        $newAssessmentRequest->setFormParam('id', $assessment->getId());
        $newAssessmentRequest->setFormParam('assessmentType', ['dueWork', 'dueWork']);
        $newAssessmentRequest->removeFormParam('rb[0][level][0][id]');
        $newAssessmentRequest->removeFormParam('rb[0][level][0][name]');
        $newAssessmentRequest->removeFormParam('rb[0][level][1][id]');
        $newAssessmentRequest->removeFormParam('rb[0][level][1][name]');
        $newAssessmentRequest->setFormParam('rb[' . $assessment->getId() . '][level][0][id]', '');
        $newAssessmentRequest->setFormParam('rb[' . $assessment->getId() . '][level][0][name]', '');
        $newAssessmentRequest->setFormParam('rb[' . $assessment->getId() . '][level][1][id]', '');
        $newAssessmentRequest->setFormParam('rb[' . $assessment->getId() . '][level][1][name]', '');

        $determine_mark_response = $i->sendCurlRequest($newAssessmentRequest, true, 'Send request to edit assessment part 1 mark invalidation #' . $groupPage->getId());

        $newAssessmentRequest->setPage('/learning/assessments/' . $assessment->getId());
        $newAssessmentRequest->setReferer('/learning/assessments/' . $assessment->getId() . '/modify?returnUrl=');
        $newAssessmentRequest->setFormParam('part-2', 1);

        $update_response      = $i->sendCurlRequest($newAssessmentRequest, true, 'Send request to edit assessment part 2 mark edit assesssment #' . $groupPage->getId());
        $expectedUrlsReturned = [[IntegrationTester::NO_REDIRECT => '200']];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $determine_mark_response);
        $i->checkCurlReturn($expectedUrlsReturned, $update_response);
    }

    /** Creates an assessment that has a rubric grid set for its mark.
     *
     * Fires off creation of a group homepage folder.
     *
     * @param IntegrationTester  $i
     * @param Payload\Assessment $newAssessmentRequest
     * @throws Deleted
     * @throws \Schoolbox\Mapper\Exception
     */
    public function createAssessmentWithRubric(IntegrationTester $i, Payload\Assessment $newAssessmentRequest): void
    {
        #GIVEN
        $timestamp   = time();
        $group_title = 'Testing Create Assessments with rubric on Group Page at ' . $timestamp;

        $folderPayload = new Payload\Folder();
        $folderPayload->setTemplate(Payload\Folder::TEMPLATE_HOMEPAGE_ID);
        $folderPayload->setName($group_title);
        $i->sendCurlRequest($folderPayload, false, 'Send request create Group Page');
        $groupPage = $i->getFirstFolderByTitle($group_title);

        #WHEN
        $assessment_title = 'Testing Create Assessment with Rubric On Group Page ID ' . $groupPage->getId() . ' at ' . $timestamp;
        $newAssessmentRequest->setTitle($assessment_title);
        $newAssessmentRequest->setFolderId($groupPage->getId());
        $newAssessmentRequest->setContextId($groupPage->getId());
        $newAssessmentRequest->setFormParam('useRubric', 'on');
        $newAssessmentRequest = $i->setDefaultRubric($newAssessmentRequest);

        $response             = $i->sendCurlRequest($newAssessmentRequest, true, 'Send request create asessment with rubric on Group Page #' . $groupPage->getId());
        $expectedUrlsReturned = [
            ['https://vagrant.dev.schoolbox.cloud/homepage/' . $groupPage->getId() => '303'],
            [IntegrationTester::NO_REDIRECT => '200'],
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /**Submits an assessment on behalf of the student using a curl payload.
     *
     * Fires off creation of a group homepage folder and create assessment.
     *
     * @param IntegrationTester  $i
     * @param Payload\Assessment $newAssessmentRequest
     * @throws Deleted
     * @throws \Schoolbox\Mapper\Exception
     */
    public function submitAssessment(IntegrationTester $i, Payload\Assessment $newAssessmentRequest): void
    {
        #GIVEN
        $timestamp   = time();
        $group_title = 'Testing Submit Assessment on Group Page at ' . $timestamp;


        $folderPayload = new Payload\Folder();
        $folderPayload->setTemplate(Payload\Folder::TEMPLATE_HOMEPAGE_ID);
        $folderPayload->setName($group_title);
        $i->sendCurlRequest($folderPayload, false, 'Send request create Group Page');
        $groupPage = $i->getFirstFolderByTitle($group_title);

        $student = $i->getUser('drhodes');
        $teacher = $i->getUser('zsmith');
        $admin   = $i->getUser('alaress');

        $memberPayload = new GroupMember();
        $memberPayload->setFolderGroup($groupPage->getId());
        $memberPayload->setNewMember($student->getId());
        $memberPayload->setNewMember($admin->getId());
        $memberPayload->setNewMember($teacher->getId());
        $i->sendCurlRequest($memberPayload, false, 'Add group members to Group Page #' . $groupPage->getId());

        $memberPayload->resetMemberAdjustmentList();
        $memberPayload->setNewMemberPermission($student->getId(), 'read');
        $memberPayload->setNewMemberPermission($teacher->getId(), 'write');
        $memberPayload->setNewMemberPermission($admin->getId(), 'admin');
        $i->sendCurlRequest($memberPayload, false, 'Add group members permissions to Group Page #' . $groupPage->getId());

        $assessment_title = 'Testing Submit Assessment On Homepage ID ' . $groupPage->getId() . ' at ' . $timestamp;

        $newAssessmentRequest->setTitle($assessment_title);
        $newAssessmentRequest->setFolderId($groupPage->getId());
        $newAssessmentRequest->setContextId($groupPage->getId());

        $i->sendCurlRequest($newAssessmentRequest, false, 'Send request create asessment on Group Page #' . $groupPage->getId());
        $assessmentPageId = $i->getFirstAssessmentIdByTitle($assessment_title);

        #WHEN
        $submitAssessmentPayload = new Base();
        $submitAssessmentPayload->setPage('/learning/assessments/' . $assessmentPageId . '/' . $student->getId() . '/submit');
        $submitAssessmentPayload->setReferer('/learning/assessments/' . $assessmentPageId . '/' . $student->getId() . '/submit');
        $submitAssessmentPayload->setFormParam('responseId', '');
        $submitAssessmentPayload->setFormParam('submitted_in_person', 'on');

        $response = $i->sendCurlRequest($submitAssessmentPayload, true, 'Send submit assessment on students behalf. Student #' . $student->getId());

        $expectedUrlsReturned = [
            ['https://vagrant.dev.schoolbox.cloud/learning/assessments/' . $assessmentPageId . '/' . $student->getId() => '302'],
            [IntegrationTester::NO_REDIRECT => '200'],
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

    }

    /**Marks an assessment with a rubric using a curl payload.
     *
     * Fires off creation of a group homepage folder and create assessment with rubric grid for mark and submission on behalf of student.
     *
     * @param IntegrationTester      $i
     * @param Payload\Assessment     $newAssessmentRequest
     * @param Payload\AssessmentMark $newMarkRequest
     * @throws Deleted
     * @throws Exception
     * @throws \Schoolbox\Mapper\Exception
     */
    public function markAssessmentWithRubric(
        IntegrationTester $i,
        Payload\Assessment $newAssessmentRequest,
        Payload\AssessmentMark $newMarkRequest
    ): void {
        #GIVEN
        $timestamp   = time();
        $group_title = 'Testing Mark Assessment with rubric on Group Page at ' . $timestamp;

        $folderPayload = new Payload\Folder();
        $folderPayload->setTemplate(Payload\Folder::TEMPLATE_HOMEPAGE_ID);
        $folderPayload->setName($group_title);
        $i->sendCurlRequest($folderPayload, false, 'Send request create Group Page');
        $groupPage = $i->getFirstFolderByTitle($group_title);

        $student = $i->getUser('drhodes');
        $teacher = $i->getUser('zsmith');
        $admin   = $i->getUser('alaress');

        $memberPayload = new GroupMember();
        $memberPayload->setFolderGroup($groupPage->getId());
        $memberPayload->setNewMember($student->getId());
        $memberPayload->setNewMember($admin->getId());
        $memberPayload->setNewMember($teacher->getId());
        $i->sendCurlRequest($memberPayload, false, 'Add group members to Group Page #' . $groupPage->getId());

        $memberPayload->resetMemberAdjustmentList();
        $memberPayload->setNewMemberPermission($student->getId(), 'read');
        $memberPayload->setNewMemberPermission($teacher->getId(), 'write');
        $memberPayload->setNewMemberPermission($admin->getId(), 'admin');
        $i->sendCurlRequest($memberPayload, false, 'Add group members permissions to Group Page #' . $groupPage->getId());

        $assessment_title = 'Testing Mark Assessment with rubric On Homepage ID ' . $groupPage->getId() . ' at ' . $timestamp;
        $newAssessmentRequest->setTitle($assessment_title);
        $newAssessmentRequest->setFolderId($groupPage->getId());
        $newAssessmentRequest->setContextId($groupPage->getId());
        $newAssessmentRequest->setFormParam('useRubric', 'on');
        $newAssessmentRequest = $i->setDefaultRubric($newAssessmentRequest);

        $i->sendCurlRequest($newAssessmentRequest, false, 'Send request create asessment on Group Page #' . $groupPage->getId());
        $assessmentPageId = $i->getFirstAssessmentIdByTitle($assessment_title);

        $submitAssessmentPayload = new Base();
        $submitAssessmentPayload->setPage('/learning/assessments/' . $assessmentPageId . '/' . $student->getId() . '/submit');
        $submitAssessmentPayload->setReferer('/learning/assessments/' . $assessmentPageId . '/' . $student->getId() . '/submit');
        $submitAssessmentPayload->setFormParam('responseId', '');
        $submitAssessmentPayload->setFormParam('submitted_in_person', 'on');

        $i->sendCurlRequest($submitAssessmentPayload, false, 'Send submit assessment on students behalf. Student #' . $student->getId());
        $submissionID = $i->getFirstSubmissionIdByOwnerAndAssessmentID($assessmentPageId, $admin->getId(), $student->getId());

        #WHEN
        $newMarkRequest->setAssessmentId($assessmentPageId);
        $newMarkRequest->setSubmissionId($submissionID);
        $newMarkRequest->setSubmittingStudentId($student->getId());
        $i->setDefaultRubricMarks($newMarkRequest);

        $response             = $i->sendCurlRequest($newMarkRequest, true, 'Send request mark rubric assessment # ' . $assessmentPageId . ' for student # ' . $student->getId());
        $expectedUrlsReturned = [[IntegrationTester::NO_REDIRECT => '200']];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /**Marks an assessment using a curl payload.
     *
     * Fires off creation of a group homepage folder and create assessment for mark and submission on behalf of student.
     *
     * @param IntegrationTester      $i
     * @param Payload\Assessment     $newAssessmentRequest
     * @param Payload\AssessmentMark $newMarkRequest
     * @throws Deleted
     * @throws \Schoolbox\Mapper\Exception
     */
    public function markAssessment(
        IntegrationTester $i,
        Payload\Assessment $newAssessmentRequest,
        Payload\AssessmentMark $newMarkRequest
    ): void {
        #GIVEN
        $timestamp   = time();
        $group_title = 'Testing Mark Assessment on Group Page at ' . $timestamp;


        $folderPayload = new Payload\Folder();
        $folderPayload->setTemplate(Payload\Folder::TEMPLATE_HOMEPAGE_ID);
        $folderPayload->setName($group_title);
        $i->sendCurlRequest($folderPayload, false, 'Send request create Group Page');
        $groupPage = $i->getFirstFolderByTitle($group_title);

        $student = $i->getUser('drhodes');
        $teacher = $i->getUser('zsmith');
        $admin   = $i->getUser('alaress');

        $memberPayload = new GroupMember();
        $memberPayload->setFolderGroup($groupPage->getId());
        $memberPayload->setNewMember($student->getId());
        $memberPayload->setNewMember($admin->getId());
        $memberPayload->setNewMember($teacher->getId());
        $i->sendCurlRequest($memberPayload, false, 'Add group members to Group Page #' . $groupPage->getId());

        $memberPayload->resetMemberAdjustmentList();
        $memberPayload->setNewMemberPermission($student->getId(), 'read');
        $memberPayload->setNewMemberPermission($teacher->getId(), 'write');
        $memberPayload->setNewMemberPermission($admin->getId(), 'admin');
        $i->sendCurlRequest($memberPayload, false, 'Add group members permissions to Group Page #' . $groupPage->getId());

        $assessment_title = 'Testing Mark Assessment On Homepage ID ' . $groupPage->getId() . ' at ' . $timestamp;

        $newAssessmentRequest->setTitle($assessment_title);
        $newAssessmentRequest->setFolderId($groupPage->getId());
        $newAssessmentRequest->setContextId($groupPage->getId());

        $i->sendCurlRequest($newAssessmentRequest, false, 'Send request create asessment on Group Page #' . $groupPage->getId());
        $assessmentPageId = $i->getFirstAssessmentIdByTitle($assessment_title);

        $submitAssessmentPayload = new Base();
        $submitAssessmentPayload->setPage('/learning/assessments/' . $assessmentPageId . '/' . $student->getId() . '/submit');
        $submitAssessmentPayload->setReferer('/learning/assessments/' . $assessmentPageId . '/' . $student->getId() . '/submit');
        $submitAssessmentPayload->setFormParam('responseId', '');
        $submitAssessmentPayload->setFormParam('submitted_in_person', 'on');

        $i->sendCurlRequest($submitAssessmentPayload, false, 'Send submit assessment on students behalf. Student #' . $student->getId());
        $submissionID = $i->getFirstSubmissionIdByOwnerAndAssessmentID($assessmentPageId, $admin->getId(), $student->getId());

        #WHEN
        $newMarkRequest->setAssessmentId($assessmentPageId);
        $newMarkRequest->setSubmissionId($submissionID);
        $newMarkRequest->setSubmittingStudentId($student->getId());

        $response             = $i->sendCurlRequest($newMarkRequest, true, 'Send request mark assessment # ' . $assessmentPageId . ' for student # ' . $student->getId());
        $expectedUrlsReturned = [[IntegrationTester::NO_REDIRECT => '200']];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }
}
