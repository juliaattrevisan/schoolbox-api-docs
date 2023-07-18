<?php

namespace Tests\Smoketests;


use Exception;
use Schoolbox\Learning\Assessment\AssessmentContextType;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Permission\Access\UserAccess;
use Tests\Support\IntegrationTester;
use Tests\Support\Payload\Admin\LMS\LessonPlanTemplate\CreateLessonPlanTemplate;
use Tests\Support\Payload\Admin\LMS\LessonPlanTemplate\EnableDisableLessonPlanTemplate;
use Schoolbox\Resource\Folder;
use Tests\Support\Payload;
use Tests\Support\Payload\Group\AddMembers;
use Tests\Support\Payload\LessonPlan\Create;

class LessonPlanCest
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

    /**
     * Creates a lesson plan using a curl payload with Visible to students OFF
     * Check the student cannot see the lesson plan
     * Check the teacher is able to see the lesson plan
     *
     * @param IntegrationTester $i
     * @throws Exception
     */
    public function createLessonPlan(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();
        $student = $i->grabUser('jreed');
        $teacher = $i->grabUser('jcoles');
        $lessonPlanTemplateTitle = 'Lesson Plan Template' . $timestamp;
        $lessonPlanTemplateFieldName1 = 'Field 1 ' . $timestamp;
        $lessonPlanTemplateFieldName2 = 'Field 2 ' . $timestamp;
        $lessonPlanTemplateFieldName3 = 'Field 3 ' . $timestamp;

        $i->amGoingTo('Send request to create a homepage');
        $groupPage = $this->createHomepage($i, 'Testing Create Assessments on Group Page at ' . $timestamp);

        $i->amGoingTo('Send request to create a lesson plan template');
        $lessons = array(
            array(1, $lessonPlanTemplateFieldName1, 'on', 'Body ' . $timestamp),
            array(2, $lessonPlanTemplateFieldName2, 'on', 'Body 2 ' . $timestamp),
            array(3, $lessonPlanTemplateFieldName3, 'off', 'Body 3 ' . $timestamp)
        );
        $this->createLessonPlanTemplate($i,$lessonPlanTemplateTitle,$lessons);

        $lessonPlanTemplateName = $i->grabLessonPlanTemplateByTitle($lessonPlanTemplateTitle);
        $lessonPlanTemplateFieldName = $i->grabLessonPlanTemplateFieldByTitle($lessonPlanTemplateFieldName1);

        $i->seeResponseCodeIs(303);

        #WHEN
        $i->amGoingTo('Send a request to create a lesson plan with Visible to students OFF');
        $contextType = AssessmentContextType::TYPE_FOLDER;
        $contextId = $groupPage->getId();
        $folderId = $groupPage->getId();
        $lessonPlanTemplateId = $lessonPlanTemplateName->getId();
        $lessonPlanTemplateFieldId1 = $lessonPlanTemplateFieldName->getId();
        $title = 'Automation Lesson Plan' . $timestamp;

        $lessonPlanCreate = new Create(
            $contextType,
            $contextId,
            $folderId,
            $lessonPlanTemplateId,
            $title,
            $lessons
        );

        for ($j = 0;$j < count($lessons); $j++) {
            $lessonPlanCreate->setFormParam('field[new' . $j + 1 . '][template]', ''.$lessonPlanTemplateFieldId1 + $j);
            $lessonPlanCreate->setFormParam('field[new' . $j + 1 . '][body]', 'Body field text'. $j + 1 );
        }

        #THEN
        $i->amGoingTo('Check the lesson plan is created');
        $i->sendRequest($lessonPlanCreate);
        $lessonPlanName = $i->grabFirstAssessmentByTitle($title);
        $i->seeResponseCodeIs(303);

        #WHEN
        $i->amGoingTo('Check the student cannot view the lesson plan as Visible to students is OFF');
        $lessonPlanId = $lessonPlanName->getId();
        $i->impersonateUser($student->getId());
        $i->sendGet('/learning/assessments/' .$lessonPlanId .'/'. $student->getId());

        #THEN
        $i->seeResponseCodeIs(401);

        #WHEN
        $i->amGoingTo('Check the teacher can view the lesson plan as Visible to students is OFF');
        $lessonPlanId = $lessonPlanName->getId();
        $i->impersonateUser($teacher->getId());
        $i->sendGet('/learning/assessments/' .$lessonPlanId .'/'. $student->getId());

        #THEN
        $i->seeResponseCodeIs(200);

    }

    /**
     * Convenience method to create and return a new folder with homepage via the HTTP API
     *
     * @param IntegrationTester $i
     * @param string            $groupTitle
     * @return Folder\Model
     * @throws Exception
     */
    private function createHomepage(IntegrationTester $i, string $groupTitle): Folder\Model
    {
        $folderPayload = new Payload\Resource\Folder\Create();
        $folderPayload->setTemplate(Payload\Resource\Folder\Create::TEMPLATE_BLANK);
        $folderPayload->setName($groupTitle);
        $i->amGoingTo('Send request create Group Page');
        $i->sendRequest($folderPayload);
        $i->seeResponseCodeIs(303);

        $groupName = $i->grabFirstFolderByTitle($groupTitle);
        $student = $i->grabUser('jreed');
        $teacher = $i->grabUser('jcoles');
        $admin   = $i->grabUser('alaress');


        $addMembers = new AddMembers($groupName->getId());
        $addMembers->setNewMembers([$student->getId(), $admin->getId(), $teacher->getId()]);
        $i->amGoingTo('Add group members to Group Page #' . $groupName->getId());
        $i->sendRequest($addMembers);

        $setAccessLevels = new Payload\Group\ModifyAccessLevel($groupName->getId());
        $setAccessLevels->setMemberAccessLevel($student->getId(), UserAccess::ACCESS_READ);
        $setAccessLevels->setMemberAccessLevel($teacher->getId(), UserAccess::ACCESS_WRITE);
        $setAccessLevels->setMemberAccessLevel($admin->getId(), UserAccess::ACCESS_FULL);
        $i->amGoingTo("Add group members permissions to Group Page #{$groupName->getId()}");
        $i->sendRequest($setAccessLevels);

        return $groupName;


    }

    /**
     * Convenience method to create a lesson plan template
     *
     * @param IntegrationTester $i
     * @param string            $lessonPlanTemplateTitle
     * @return void
     * @throws Exception
     */
    private function createLessonPlanTemplate(IntegrationTester $i, string $lessonPlanTemplateTitle, array $lessons): void
    {
        $lessonPlanTemplateCreate = new CreateLessonPlanTemplate(
            $lessonPlanTemplateTitle,
            $lessons
        );

        $i->sendRequest($lessonPlanTemplateCreate);
    }


}
