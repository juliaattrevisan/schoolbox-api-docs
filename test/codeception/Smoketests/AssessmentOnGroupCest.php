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
use Tests\Support\Page\Duework\AssessmentForm;
use Tests\Support\Payload;
use Tests\Support\Payload\Group\AddMembers;
use Schoolbox\Learning\Assessment\AssessmentContextType;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;

class AssessmentOnGroupCest
{
    /**
     * Push a login payload to Schoolbox each test to ensure session is active and logged in.
     *
     * @param IntegrationTester $i
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    public function _before(IntegrationTester $i): void
    {
        $i->loginAdmin();
        $i->stopFollowingRedirects();
    }

    /**
     * Creates an assessment using a curl payload
     *
     * Fires off creation of a group homepage folder.
     *
     * @param IntegrationTester $i
     */
    public function createAssessment(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();
        $groupPage = $this->createHomepage($i, 'Testing Create Assessments on Group Page at ' . $timestamp);
        $assessment_title = 'Testing Create Assessment On Group Page ID ' . $groupPage->getId() . ' at ' . $timestamp;

        #WHEN
        $this->createDefaultAssessable(
            $i,
            AssessmentContextType::TYPE_FOLDER,
            $groupPage->getId(),
            $groupPage->getId(),
            AssessmentType::TYPE_DUE_WORK,
            $assessment_title
        );

        #THEN
        $i->seeHttpHeader('location', "/homepage/{$groupPage->getId()}");
    }


    /**
     * Updates an assessment using a curl payload.
     *
     * Fires off creation of a group homepage folder and create assessment.
     *
     * @param IntegrationTester $i
     */
    public function updateAssessment(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();
        $groupPage = $this->createHomepage($i, 'Testing Edit Assessments on Group Page at ' . $timestamp);
        $assessment_title = 'Testing Edit Assessment On Group Page ID ' . $groupPage->getId() . ' at ' . $timestamp;

        $newAssessmentRequest = new Payload\Assessable\Create(AssessmentContextType::TYPE_FOLDER, $groupPage->getId(), $groupPage->getId(), AssessmentType::TYPE_DUE_WORK);
        $newAssessmentRequest->setTitle($assessment_title);
        $newAssessmentRequest->setFormParam('workType', AssessmentForm::WORKTYPE_ASSESSMENT_TASK); // Required form parameter.
        $i->amGoingTo('Send request create assessment on Group Page #' . $groupPage->getId());
        $i->sendRequest($newAssessmentRequest);
        $i->seeResponseCodeIs(303);

        $assessable = $i->grabFirstAssessmentByTitle($assessment_title);

        #WHEN
        $checkRubric = new Payload\Rubric\CheckMarkInvalidation($assessable, '');
        $i->amGoingTo('Send request to edit assessment part 1 mark invalidation #' . $groupPage->getId());
        $i->sendRequest($checkRubric);

        $i->seeResponseCodeIs(200);
        $checkRubricResponse = json_decode($i->grabResponse(), true);
        $i->assertTrue($checkRubricResponse['compatible'] ?? null);
        $i->assertSame([], $checkRubricResponse['affectedUsers'] ?? null);

        $updateAssessment = new Payload\Assessable\Update($assessable, '');
        $i->amGoingTo('Send request to edit assessment part 2 mark edit assesssment #' . $groupPage->getId());
        $i->sendRequest($updateAssessment);
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/learning/assessments/{$assessable->getId()}/modify");
    }


    /**
     * Submits an assessment on behalf of the student using a curl payload.
     *
     * Fires off creation of a group homepage folder and create assessment.
     *
     * @param IntegrationTester $i
     */
    public function submitAssessment(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();
        $groupPage = $this->createHomepage($i, 'Testing Submit Assessment on Group Page at ' . $timestamp);

        $student = $i->grabUser('jreed');
        $teacher = $i->grabUser('jcoles');
        $admin   = $i->grabUser('alaress');

        $addMembers = new AddMembers($groupPage->getId());
        $addMembers->setNewMembers([$student->getId(), $admin->getId(), $teacher->getId()]);
        $i->amGoingTo('Add group members to Group Page #' . $groupPage->getId());
        $i->sendRequest($addMembers);

        $setAccessLevels = new Payload\Group\ModifyAccessLevel($groupPage->getId());
        $setAccessLevels->setMemberAccessLevel($student->getId(), UserAccess::ACCESS_READ);
        $setAccessLevels->setMemberAccessLevel($teacher->getId(), UserAccess::ACCESS_WRITE);
        $setAccessLevels->setMemberAccessLevel($admin->getId(), UserAccess::ACCESS_FULL);
        $i->amGoingTo("Add group members permissions to Group Page #{$groupPage->getId()}");
        $i->sendRequest($setAccessLevels);

        $newAssessment    = new Payload\Assessable\Create(AssessmentContextType::TYPE_FOLDER, $groupPage->getId(), $groupPage->getId(), AssessmentType::TYPE_DUE_WORK);
        $assessment_title = 'Testing Submit Assessment On Homepage ID ' . $groupPage->getId() . ' at ' . $timestamp;
        $newAssessment->setTitle($assessment_title);
        $newAssessment->setFormParam('workType', AssessmentForm::WORKTYPE_ASSESSMENT_TASK); // Required form parameter.
        $i->amGoingTo("Send request create assessment on Group Page #{$groupPage->getId()}");
        $i->sendRequest($newAssessment);
        $assessmentPageId = $i->grabFirstAssessmentIdByTitle($assessment_title);

        //submitting the assessment on behalf of a student
        #WHEN
        $referrer                = "/learning/assessments/$assessmentPageId/{$student->getId()}/submit";
        $url                     = "/learning/assessments/$assessmentPageId/{$student->getId()}/submit";
        $submitAssessmentPayload = new Payload\Base('POST', $referrer, $url);
        $submitAssessmentPayload->setFormParam('responseId', '');
        $submitAssessmentPayload->setFormParam('submitted_in_person', 'on');
        $i->amGoingTo('Send submit assessment on students behalf. Student #' . $student->getId());
        $i->sendRequest($submitAssessmentPayload);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', "/learning/assessments/$assessmentPageId/{$student->getId()}");
    }


    /**Marks an assessment using a curl payload.
     *
     * Fires off creation of a group homepage folder and create assessment for mark and submission on behalf of student.
     *
     * @param IntegrationTester $i
     */
    public function markAssessment(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $groupPage = $this->createHomepage($i, 'Testing Mark Assessment on Group Page at ' . $timestamp);

        $student = $i->grabUser('jreed');
        $teacher = $i->grabUser('jcoles');
        $admin   = $i->grabUser('alaress');

        $memberPayload = new AddMembers($groupPage->getId());
        $memberPayload->setNewMembers([$student->getId(), $admin->getId(), $teacher->getId()]);
        $i->amGoingTo('Add group members to Group Page #' . $groupPage->getId());
        $i->sendRequest($memberPayload);

        $accessLevels = new Payload\Group\ModifyAccessLevel($groupPage->getId());
        $accessLevels->setMemberAccessLevel($student->getId(), UserAccess::ACCESS_READ);
        $accessLevels->setMemberAccessLevel($teacher->getId(), UserAccess::ACCESS_WRITE);
        $accessLevels->setMemberAccessLevel($admin->getId(), UserAccess::ACCESS_FULL);
        $i->amGoingTo('Add group members permissions to Group Page #' . $groupPage->getId());
        $i->sendRequest($accessLevels);

        $assessment_title = 'Testing Mark Assessment On Homepage ID ' . $groupPage->getId() . ' at ' . $timestamp;

        $newAssessmentRequest = new Payload\Assessable\Create(AssessmentContextType::TYPE_FOLDER, $groupPage->getId(), $groupPage->getId(), AssessmentType::TYPE_DUE_WORK);
        $newAssessmentRequest->setTitle($assessment_title);
        $newAssessmentRequest->setFormParam('workType', AssessmentForm::WORKTYPE_ASSESSMENT_TASK); // Required form parameter.

        $i->amGoingTo('Send request create assessment on Group Page #' . $groupPage->getId());
        $i->sendRequest($newAssessmentRequest);
        $assessmentId = $i->grabFirstAssessmentIdByTitle($assessment_title);

        $referrer                = '/learning/assessments/' . $assessmentId . '/' . $student->getId() . '/submit';
        $url                     = '/learning/assessments/' . $assessmentId . '/' . $student->getId() . '/submit';
        $submitAssessmentPayload = new Payload\Base('POST', $referrer, $url);
        $submitAssessmentPayload->setFormParam('responseId', '');
        $submitAssessmentPayload->setFormParam('submitted_in_person', 'on');
        $i->amGoingTo('Send submit assessment on students behalf. Student #' . $student->getId());
        $i->sendRequest($submitAssessmentPayload);
        $submissionID = $i->grabFirstSubmissionIdByOwnerAndAssessmentID($assessmentId, $admin->getId(), $student->getId());

        #WHEN
        $newMarkRequest = new Payload\Assessable\Grading\AssessmentMark($assessmentId, $student->getId(), $submissionID);
        $i->amGoingTo('Send request mark assessment # ' . $assessmentId . ' for student # ' . $student->getId());
        $i->sendRequest($newMarkRequest);

        #THEN
        $i->seeResponseCodeIs(200);
    }

    /**
     * Submits an assessment by attaching a file on behalf of the student
     * @param IntegrationTester $i
     */
    public function submitAssessmentByAttachingFile(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();
        $groupPage = $this->createHomepage($i, 'Testing Submit Assessment on Group Page at ' . $timestamp);

        $student = $i->grabUser('jreed');
        $teacher = $i->grabUser('jcoles');
        $admin   = $i->grabUser('alaress');

        $addMembers = new AddMembers($groupPage->getId());
        $addMembers->setNewMembers([$student->getId(), $admin->getId(), $teacher->getId()]);
        $i->amGoingTo('Add group members to Group Page #' . $groupPage->getId());
        $i->sendRequest($addMembers);

        $setAccessLevels = new Payload\Group\ModifyAccessLevel($groupPage->getId());
        $setAccessLevels->setMemberAccessLevel($student->getId(), UserAccess::ACCESS_READ);
        $setAccessLevels->setMemberAccessLevel($teacher->getId(), UserAccess::ACCESS_WRITE);
        $setAccessLevels->setMemberAccessLevel($admin->getId(), UserAccess::ACCESS_FULL);
        $i->amGoingTo("Add group members permissions to Group Page #{$groupPage->getId()}");
        $i->sendRequest($setAccessLevels);

        $newAssessment    = new Payload\Assessable\Create(AssessmentContextType::TYPE_FOLDER, $groupPage->getId(), $groupPage->getId(), AssessmentType::TYPE_DUE_WORK);
        $assessment_title = 'Testing Submit Assessment by attaching a file On Homepage ID ' . $groupPage->getId() . ' at ' . $timestamp;
        $newAssessment->setTitle($assessment_title);
        $newAssessment->setFormParam('workType', AssessmentForm::WORKTYPE_ASSESSMENT_TASK); // Required form parameter.
        $i->amGoingTo("Send request create assessment on Group Page #{$groupPage->getId()}");
        $i->sendRequest($newAssessment);
        $assessmentPageId = $i->grabFirstAssessmentIdByTitle($assessment_title);


        #WHEN
        $referrer                = "/learning/assessments/$assessmentPageId/{$student->getId()}/submit";
        $url                     = "/learning/assessments/$assessmentPageId/{$student->getId()}/submit";
        $submitAssessmentPayload = new Payload\Base('POST', $referrer, $url);
        $submitAssessmentPayload->setFormParam('responseId', '');
        $submitAssessmentPayload->setFormParam('body', '');
        //$submitAssessmentPayload->setFormParam('submitted_in_person', 'on');
        $image_name          = "homepageUploadTestDragDrop{$timestamp}.png";
        $image_location      = '/UploadTestFiles/fish2.png';
        $storageUpload = new Payload\Resource\File\StorageUpload('upload', $image_name, $image_location);
        $i->amGoingTo('Upload image to the Badge');
        $i->sendRequest($storageUpload);
        $i->seeResponseCodeIs(200);
        $json_reply = json_decode($i->grabResponse(), true);
        $file = ($json_reply['hash'] . ":fish2.png");
        $submitAssessmentPayload->setFormParam('attachment[]', $file);
        $i->amGoingTo('Send submit assessment by attaching a file on students behalf. Student #' . $student->getId());
        $i->sendRequest($submitAssessmentPayload);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', "/learning/assessments/$assessmentPageId/{$student->getId()}");
    }

    /**
     * Submits an assessment by typing something in the ckeditor on behalf of the student
     * @param IntegrationTester $i
     */
    public function submitAssessmentByTypingSomething(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();
        $groupPage = $this->createHomepage($i, 'Testing Submit Assessment on Group Page at ' . $timestamp);

        $student = $i->grabUser('jreed');
        $teacher = $i->grabUser('jcoles');
        $admin   = $i->grabUser('alaress');

        $addMembers = new AddMembers($groupPage->getId());
        $addMembers->setNewMembers([$student->getId(), $admin->getId(), $teacher->getId()]);
        $i->amGoingTo('Add group members to Group Page #' . $groupPage->getId());
        $i->sendRequest($addMembers);

        $setAccessLevels = new Payload\Group\ModifyAccessLevel($groupPage->getId());
        $setAccessLevels->setMemberAccessLevel($student->getId(), UserAccess::ACCESS_READ);
        $setAccessLevels->setMemberAccessLevel($teacher->getId(), UserAccess::ACCESS_WRITE);
        $setAccessLevels->setMemberAccessLevel($admin->getId(), UserAccess::ACCESS_FULL);
        $i->amGoingTo("Add group members permissions to Group Page #{$groupPage->getId()}");
        $i->sendRequest($setAccessLevels);

        $newAssessment    = new Payload\Assessable\Create(AssessmentContextType::TYPE_FOLDER, $groupPage->getId(), $groupPage->getId(), AssessmentType::TYPE_DUE_WORK);
        $assessment_title = 'Testing Submit Assessment by typing something in the ckeditor On Homepage ID ' . $groupPage->getId() . ' at ' . $timestamp;
        $newAssessment->setTitle($assessment_title);
        $newAssessment->setFormParam('workType', AssessmentForm::WORKTYPE_ASSESSMENT_TASK); // Required form parameter.
        $i->amGoingTo("Send request create assessment on Group Page #{$groupPage->getId()}");
        $i->sendRequest($newAssessment);
        $assessmentPageId = $i->grabFirstAssessmentIdByTitle($assessment_title);


        #WHEN
        $referrer                = "/learning/assessments/$assessmentPageId/{$student->getId()}/submit";
        $url                     = "/learning/assessments/$assessmentPageId/{$student->getId()}/submit";
        $submitAssessmentPayload = new Payload\Base('POST', $referrer, $url);
        $submitAssessmentPayload->setFormParam('responseId', '');
        $submitAssessmentPayload->setFormParam('body', 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industrys standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.');
        $i->amGoingTo('Send submit assessment by typing something in the ckeditor on students behalf. Student #' . $student->getId());
        $i->sendRequest($submitAssessmentPayload);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', "/learning/assessments/$assessmentPageId/{$student->getId()}");
    }

    /**
     * Submits an assessment by impersonating a teacher (write user)
     * @param IntegrationTester $i
     */
    public function teacherSubmitsAssessment(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();
        $groupPage = $this->createHomepage($i, 'Testing Submit Assessment on Group Page at ' . $timestamp);

        $student = $i->grabUser('jreed');
        $teacher = $i->grabUser('jcoles');
        $admin   = $i->grabUser('alaress');

        $addMembers = new AddMembers($groupPage->getId());
        $addMembers->setNewMembers([$student->getId(), $admin->getId(), $teacher->getId()]);
        $i->amGoingTo('Add group members to Group Page #' . $groupPage->getId());
        $i->sendRequest($addMembers);

        $setAccessLevels = new Payload\Group\ModifyAccessLevel($groupPage->getId());
        $setAccessLevels->setMemberAccessLevel($student->getId(), UserAccess::ACCESS_READ);
        $setAccessLevels->setMemberAccessLevel($teacher->getId(), UserAccess::ACCESS_WRITE);
        $setAccessLevels->setMemberAccessLevel($admin->getId(), UserAccess::ACCESS_FULL);
        $i->amGoingTo("Add group members permissions to Group Page #{$groupPage->getId()}");
        $i->sendRequest($setAccessLevels);

        $newAssessment    = new Payload\Assessable\Create(AssessmentContextType::TYPE_FOLDER, $groupPage->getId(), $groupPage->getId(), AssessmentType::TYPE_DUE_WORK);
        $assessment_title = 'Testing  Write User Able To Submit Assessment' . $groupPage->getId() . ' at ' . $timestamp;
        $newAssessment->setTitle($assessment_title);
        $newAssessment->setFormParam('workType', AssessmentForm::WORKTYPE_ASSESSMENT_TASK); // Required form parameter.
        $i->amGoingTo("Send request create assessment on Group Page #{$groupPage->getId()}");
        $i->sendRequest($newAssessment);
        $assessmentPageId = $i->grabFirstAssessmentIdByTitle($assessment_title);


        #WHEN
        $i->impersonateUser($teacher->getId());

        $referrer                = "/learning/assessments/$assessmentPageId/{$teacher->getId()}/submit";
        $url                     = "/learning/assessments/$assessmentPageId/{$teacher->getId()}/submit";
        $submitAssessmentPayload = new Payload\Base('POST', $referrer, $url);
        $submitAssessmentPayload->setFormParam('responseId', '');
        $submitAssessmentPayload->setFormParam('body', 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industrys standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.');
        $i->amGoingTo('Send submit assessment by typing something in the ckeditor on students behalf. Student #' . $student->getId());
        $i->sendRequest($submitAssessmentPayload);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', "/learning/assessments/$assessmentPageId/{$teacher->getId()}");
    }


    /**
     * Submits an assessment as an admin (full access user)
     * @param IntegrationTester $i
     */
    public function adminSubmitsAssessment(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();
        $groupPage = $this->createHomepage($i, 'Testing Full Access User Submits Assessment on Group Page at ' . $timestamp);

        $student = $i->grabUser('jreed');
        $teacher = $i->grabUser('jcoles');
        $admin   = $i->grabUser('alaress');

        $addMembers = new AddMembers($groupPage->getId());
        $addMembers->setNewMembers([$student->getId(), $admin->getId(), $teacher->getId()]);
        $i->amGoingTo('Add group members to Group Page #' . $groupPage->getId());
        $i->sendRequest($addMembers);

        $setAccessLevels = new Payload\Group\ModifyAccessLevel($groupPage->getId());
        $setAccessLevels->setMemberAccessLevel($student->getId(), UserAccess::ACCESS_READ);
        $setAccessLevels->setMemberAccessLevel($teacher->getId(), UserAccess::ACCESS_WRITE);
        $setAccessLevels->setMemberAccessLevel($admin->getId(), UserAccess::ACCESS_FULL);
        $i->amGoingTo("Add group members permissions to Group Page #{$groupPage->getId()}");
        $i->sendRequest($setAccessLevels);

        $newAssessment    = new Payload\Assessable\Create(AssessmentContextType::TYPE_FOLDER, $groupPage->getId(), $groupPage->getId(), AssessmentType::TYPE_DUE_WORK);
        $assessment_title = 'Testing  Full Access User Able To Submit Assessment' . $groupPage->getId() . ' at ' . $timestamp;
        $newAssessment->setTitle($assessment_title);
        $newAssessment->setFormParam('workType', AssessmentForm::WORKTYPE_ASSESSMENT_TASK); // Required form parameter.
        $i->amGoingTo("Send request create assessment on Group Page #{$groupPage->getId()}");
        $i->sendRequest($newAssessment);
        $assessmentPageId = $i->grabFirstAssessmentIdByTitle($assessment_title);


        #WHEN
        $referrer                = "/learning/assessments/$assessmentPageId/{$admin->getId()}/submit";
        $url                     = "/learning/assessments/$assessmentPageId/{$admin->getId()}/submit";
        $submitAssessmentPayload = new Payload\Base('POST', $referrer, $url);
        $submitAssessmentPayload->setFormParam('responseId', '');
        $submitAssessmentPayload->setFormParam('body', 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industrys standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.');
        $i->amGoingTo('Send submit assessment by typing something in the ckeditor on students behalf. Student #' . $student->getId());
        $i->sendRequest($submitAssessmentPayload);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', "/learning/assessments/$assessmentPageId/{$admin->getId()}");
    }

    /**
     * Submits an assessment as a student (read user)
     * @param IntegrationTester $i
     */
    public function studentSubmitsAssessment(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();
        $groupPage = $this->createHomepage($i, 'Testing Read Only Submits Assessment on Group Page at ' . $timestamp);

        $student = $i->grabUser('jreed');
        $teacher = $i->grabUser('jcoles');
        $admin   = $i->grabUser('alaress');

        $addMembers = new AddMembers($groupPage->getId());
        $addMembers->setNewMembers([$student->getId(), $admin->getId(), $teacher->getId()]);
        $i->amGoingTo('Add group members to Group Page #' . $groupPage->getId());
        $i->sendRequest($addMembers);

        $setAccessLevels = new Payload\Group\ModifyAccessLevel($groupPage->getId());
        $setAccessLevels->setMemberAccessLevel($student->getId(), UserAccess::ACCESS_READ);
        $setAccessLevels->setMemberAccessLevel($teacher->getId(), UserAccess::ACCESS_WRITE);
        $setAccessLevels->setMemberAccessLevel($admin->getId(), UserAccess::ACCESS_FULL);
        $i->amGoingTo("Add group members permissions to Group Page #{$groupPage->getId()}");
        $i->sendRequest($setAccessLevels);

        $newAssessment    = new Payload\Assessable\Create(AssessmentContextType::TYPE_FOLDER, $groupPage->getId(), $groupPage->getId(), AssessmentType::TYPE_DUE_WORK);
        $assessment_title = 'Testing  Read Only User Able To Submit Assessment' . $groupPage->getId() . ' at ' . $timestamp;
        $newAssessment->setTitle($assessment_title);
        $newAssessment->setFormParam('acceptOnline', 1);
        $newAssessment->setFormParam('workType', AssessmentForm::WORKTYPE_ASSESSMENT_TASK); // Required form parameter.
        $i->amGoingTo("Send request create assessment on Group Page #{$groupPage->getId()}");
        $i->sendRequest($newAssessment);
        $assessmentPageId = $i->grabFirstAssessmentIdByTitle($assessment_title);


        #WHEN
        $i->impersonateUser($student->getId());

        $referrer                = "/learning/assessments/$assessmentPageId/{$student->getId()}/submit";
        $url                     = "/learning/assessments/$assessmentPageId/{$student->getId()}/submit";
        $submitAssessmentPayload = new Payload\Base('POST', $referrer, $url);
        $submitAssessmentPayload->setFormParam('responseId', '');
        $submitAssessmentPayload->setFormParam('body', 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industrys standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.');
        $i->amGoingTo('Send submit assessment by typing something in the ckeditor on students behalf. Student #' . $student->getId());
        $i->sendRequest($submitAssessmentPayload);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', "/learning/assessments/$assessmentPageId/{$student->getId()}");
    }

    /**
     * Convenience method to create and return a new folder with homepage via the HTTP API
     *
     * @param IntegrationTester $i
     * @param string            $groupTitle
     * @return Folder\Model
     */
    private function createHomepage(IntegrationTester $i, string $groupTitle): Folder\Model
    {
        $folderPayload = new Payload\Resource\Folder\Create();
        $folderPayload->setTemplate(Payload\Resource\Folder\Create::TEMPLATE_BLANK);
        $folderPayload->setName($groupTitle);
        $i->amGoingTo('Send request create Group Page');
        $i->sendRequest($folderPayload);
        $i->seeResponseCodeIs(303);

        return $i->grabFirstFolderByTitle($groupTitle);
    }


    /**
     * Convenience method to create and return an IAssessable vis the HTTP API
     *
     * @param IntegrationTester $i
     * @param string            $contextType
     * @param int               $contextId
     * @param int               $folderId
     * @param string            $assessmentType
     * @param string            $title
     * @return IAssessable
     */
    private function createDefaultAssessable(
        IntegrationTester $i,
        string $contextType,
        int $contextId,
        int $folderId,
        string $assessmentType,
        string $title
    ): IAssessable {
        $newAssessmentRequest = new Payload\Assessable\Create($contextType, $contextId, $folderId, $assessmentType);
        $newAssessmentRequest->setTitle($title);
        $newAssessmentRequest->setFormParam('workType', AssessmentForm::WORKTYPE_ASSESSMENT_TASK); // Required form parameter.
        $i->amGoingTo("Send request create $assessmentType on Folder #$folderId for context $contextType ($contextId)");
        $i->sendRequest($newAssessmentRequest);
        $i->seeResponseCodeIs(303);

        return $i->grabFirstAssessmentByTitle($title);
    }
}
