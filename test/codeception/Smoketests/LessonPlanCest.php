<?php

namespace Tests\Smoketests;

use Exception;
use Schoolbox\Learning\Assessment\AssessmentContextType;
use Schoolbox\Resource\HomepageTemplate;
use Tests\Support\IntegrationTester;
use Tests\Support\Page\Duework\AssessmentForm;
use Tests\Support\Payload\Admin\LMS\LessonPlanTemplate\CreateLessonPlanTemplate;
use Tests\Support\Payload;
use Tests\Support\Payload\LessonPlan\Create;

class LessonPlanCest
{
    /**
     * Push a login payload to Schoolbox each test to ensure session is active and logged in.
     *
     * @param IntegrationTester $i
     * @throws Exception
     */
    public function _before(IntegrationTester $i): void
    {
        $i->loginAdmin();
        $i->stopFollowingRedirects();
    }

    public function linkedActivitiesOnlyExposeParticipatingLearners(IntegrationTester $i): void
    {
        $student = $i->grabUser('jreed');

        $i->impersonateUser($student->getId());
        $i->sendGet('/learning/assessments/433/activities');
        $i->seeResponseCodeIs(200);
        $i->dontSeeResponseContainsJson(['id' => 3]);
        $i->dontSeeResponseContainsJson(['id' => 4]);
        $i->dontSeeResponseContainsJson(['id' => 900]);
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

        $folder = $this->importCourseToClass($i);
        $unitSpec = $i->grabFromDatabase('course_unit_implementation', 'id', ['course_unit_specification_id' => $folder]);

        $i->amGoingTo('Send request to create a lesson plan template');
        $lessons = array(
            array(1, $lessonPlanTemplateFieldName1, 'on', 'Body ' . $timestamp),
            array(2, $lessonPlanTemplateFieldName2, 'on', 'Body 2 ' . $timestamp),
            array(3, $lessonPlanTemplateFieldName3, 'off', 'Body 3 ' . $timestamp)
        );
        $this->createLessonPlanTemplate($i, $lessonPlanTemplateTitle, $lessons);
        $lessonPlanTemplateName = $i->grabLessonPlanTemplateByTitle($lessonPlanTemplateTitle);
        $lessonPlanTemplateFieldName = $i->grabLessonPlanTemplateFieldByTitle($lessonPlanTemplateFieldName1);

        $i->seeResponseCodeIs(303);

        #WHEN
        $i->amGoingTo('Send a request to create a lesson plan with Visible to students OFF');
        $contextType = AssessmentContextType::TYPE_UNIT_IMPLEMENTATION;
        $contextId = $unitSpec;
        $folderId = $unitSpec;
        $lessonPlanTemplateId = $lessonPlanTemplateName->getId();
        $lessonPlanTemplateFieldId1 = $lessonPlanTemplateFieldName->getId();
        $title = 'Automation Lesson Plan' . $timestamp;
        $visibility = ['staff'];


        $lessonPlanCreate = new Create(
            $contextType,
            $contextId,
            $folderId,
            $lessonPlanTemplateId,
            $title,
            $lessons,
            (array)$visibility
        );

        for ($j = 0; $j < count($lessons); $j++) {
            $lessonPlanCreate->setFormParam('field[new' . ($j + 1) . '][template]', '' . ($lessonPlanTemplateFieldId1 + $j));
            $lessonPlanCreate->setFormParam('field[new' . ($j + 1) . '][body]', 'Body field text' . ($j + 1));
        }

        $lessonPlanCreate->setFormParam('workType', AssessmentForm::WORKTYPE_NONE); // Required form parameter.

        #THEN
        $i->amGoingTo('Check the lesson plan is created');
        $i->sendRequest($lessonPlanCreate);
        $lessonPlanName = $i->grabFirstAssessmentByTitle($title);
        $i->seeResponseCodeIs(303);

        #WHEN
        $i->amGoingTo('Check the student cannot view the lesson plan as Visible to students is OFF');
        $lessonPlanAssessmentId = $lessonPlanName->getId();
        $i->impersonateUser($student->getId());
        $i->sendGet('/learning/assessments/' . $lessonPlanAssessmentId . '/' . $student->getId());

        #THEN
        $i->seeResponseCodeIs(401);

        #WHEN
        $i->amGoingTo('Check the teacher can view the lesson plan as Visible to students is OFF');
        $i->impersonateUser($teacher->getId());
        $i->sendGet('/learning/assessments/' . $lessonPlanAssessmentId . '/' . $student->getId());

        #THEN
        $i->seeResponseCodeIs(200);
    }

    /**
     * @throws Exception
     * Update a lesson plan using a curl payload turning Visible to students ON
     * Check the student can see the lesson plan
     * Check the teacher is able to see the lesson plan
     */
    public function updateLessonPlan(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();
        $student = $i->grabUser('jreed');
        $teacher = $i->grabUser('jcoles');

        $i->amGoingTo('Send request to create a homepage');
        $folder = $this->importCourseToClass($i);
        $unitSpec = $i->grabFromDatabase('course_unit_implementation', 'id', ['course_unit_specification_id' => $folder]);

        $i->amGoingTo('Send request to create a lesson plan activity');
        $title = 'Automation Lesson Plan' . $timestamp;
        $this->createLessonPlanActivity($i, $title, $unitSpec, $unitSpec);
        $lessonPlanName = $i->grabFirstAssessmentByTitle($title);
        $lessonPlanAssessmentId = $lessonPlanName->getId();
        $lessonPlanTemplateId = $i->grabFromDatabase('lesson_plan', 'lesson_plan_template_id', ['assessment_id' => $lessonPlanAssessmentId]);
        $lessonPlanId = $i->grabFromDatabase('lesson_plan', 'id', ['assessment_id' => $lessonPlanAssessmentId]);
        $fields = $i->grabNumRecords('lesson_plan_field', ['lesson_plan_id' => $lessonPlanId]);


        #WHEN
        $i->amGoingTo('Get the lesson plan modified setting visible to students ON');
        $title = 'Automation Lesson Plan Updated' . $timestamp;
        $visibility = ['on'];

        $lessonPlanUpdate = new Payload\LessonPlan\Update(
            $lessonPlanAssessmentId,
            $unitSpec,
            $lessonPlanTemplateId,
            $title,
            (array)$visibility
        );

        $lessonPlanFieldId = $i->grabFromDatabase('lesson_plan_field', 'id', ['lesson_plan_id' => $lessonPlanId]);
        $lessonPlanTemplateFieldId = $i->grabFromDatabase('lesson_plan_template_field', 'id', ['lesson_plan_template_id' => $lessonPlanTemplateId]);

        for ($j = 0; $j < $fields; $j++) {
            $lessonPlanUpdate->setFormParam('field[' . ($lessonPlanFieldId + $j) . '][template]', '' . ($lessonPlanTemplateFieldId + $j));
            $lessonPlanUpdate->setFormParam('field[' . ($lessonPlanFieldId + $j) . '][body]', 'Body field text updated' . ($lessonPlanFieldId  + $j));
        }

        $lessonPlanUpdate->setFormParam('workType', AssessmentForm::WORKTYPE_ASSESSMENT_TASK); // Required form parameter.

        $i->amGoingTo('Check the lesson plan is updated');
        $i->sendRequest($lessonPlanUpdate);
        $i->seeResponseCodeIs(303);

        #THEN
        $i->amGoingTo('Check the student cannot view the lesson plan as Visible to students is OFF');
        $lessonPlanAssessmentId = $lessonPlanName->getId();
        $i->impersonateUser($student->getId());
        $i->sendGet('/learning/assessments/' . $lessonPlanAssessmentId . '/' . $student->getId());
        $i->seeResponseCodeIs(200);

        #THEN
        $i->amGoingTo('Check the teacher can view the lesson plan as Visible to students is OFF');
        $i->impersonateUser($teacher->getId());
        $i->sendGet('/learning/assessments/' . $lessonPlanAssessmentId . '/' . $student->getId());
        $i->seeResponseCodeIs(200);
    }

    /**
     * @throws Exception
     * Attempt to create a lesson plan without passing a mandatory field: title
     */
    public function lessonPlanMandatoryFields(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();

        $i->amGoingTo('Send request to create a homepage');
        $folder = $this->importCourseToClass($i);
        $unitSpec = $i->grabFromDatabase('course_unit_implementation', 'id', ['course_unit_specification_id' => $folder]);

        $i->amGoingTo('Send request to create a lesson plan activity');
        $title = 'Automation Lesson Plan' . $timestamp;
        $this->createLessonPlanActivity($i, $title, $unitSpec, $unitSpec);
        $lessonPlanName = $i->grabFirstAssessmentByTitle($title);
        $lessonPlanAssessmentId = $lessonPlanName->getId();
        $lessonPlanTemplateId = $i->grabFromDatabase('lesson_plan', 'lesson_plan_template_id', ['assessment_id' => $lessonPlanAssessmentId]);
        $lessonPlanId = $i->grabFromDatabase('lesson_plan', 'id', ['assessment_id' => $lessonPlanAssessmentId]);
        $fields = $i->grabNumRecords('lesson_plan_field', ['lesson_plan_id' => $lessonPlanId]);


        #WHEN
        $i->amGoingTo('Attempt to create a lesson plan without passing the mandatory filed title');
        $title = '';
        $visibility = ['on'];

        $lessonPlanUpdate = new Payload\LessonPlan\Update(
            $lessonPlanAssessmentId,
            $unitSpec,
            $lessonPlanTemplateId,
            $title,
            (array)$visibility
        );

        $lessonPlanFieldId = $i->grabFromDatabase('lesson_plan_field', 'id', ['lesson_plan_id' => $lessonPlanId]);
        $lessonPlanTemplateFieldId = $i->grabFromDatabase('lesson_plan_template_field', 'id', ['lesson_plan_template_id' => $lessonPlanTemplateId]);

        for ($j = 0; $j < $fields; $j++) {
            $lessonPlanUpdate->setFormParam('field[' . ($lessonPlanFieldId + $j) . '][template]', '' . ($lessonPlanTemplateFieldId + $j));
            $lessonPlanUpdate->setFormParam('field[' . ($lessonPlanFieldId + $j) . '][body]', 'Body field text updated' . ($lessonPlanFieldId  + $j));
        }

        #THEN
        $i->amGoingTo('Check the lesson plan is not created: response code 400');
        $i->sendRequest($lessonPlanUpdate);
        $i->seeResponseCodeIs(400);
    }

    /**
     * @throws Exception
     * Create a lesson plan
     * Delete a lesson plan
     * @param IntegrationTester $i
     */
    public function deleteLessonPlan(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();

        $i->amGoingTo('Send request to create a homepage');
        $folder = $this->importCourseToClass($i);
        $unitSpec = $i->grabFromDatabase('course_unit_implementation', 'id', ['course_unit_specification_id' => $folder]);

        $i->amGoingTo('Send request to create a lesson plan activity');
        $title = 'Automation Lesson Plan' . $timestamp;
        $this->createLessonPlanActivity($i, $title, $unitSpec, $unitSpec);
        $lessonPlanName = $i->grabFirstAssessmentByTitle($title);

        #WHEN
        $lessonPlanDelete = new Payload\LessonPlan\Delete($lessonPlanName->getId());
        $i->amGoingTo('Send a request to delete a lesson plan');
        $i->sendRequest($lessonPlanDelete);

        #THEN
        $i->amGoingTo('Check the lesson plan is deleted');
        $i->seeResponseCodeIs(303);
    }

    /**
     * Convenience method to create a lesson plan template
     *
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

    /**
     * Convenience method to create a lesson plan
     *
     * @throws Exception
     */
    private function createLessonPlanActivity(IntegrationTester $i, string $title, int $contextId, int $folderId): void
    {
        #GIVEN
        $timestamp  = time();
        $lessonPlanTemplateTitle = 'Lesson Plan Template' . $timestamp;
        $lessonPlanTemplateFieldName1 = 'Field 1 ' . $timestamp;
        $lessonPlanTemplateFieldName2 = 'Field 2 ' . $timestamp;
        $lessonPlanTemplateFieldName3 = 'Field 3 ' . $timestamp;

        $i->amGoingTo('Send request to create a lesson plan template');
        $lessons = array(
            array(1, $lessonPlanTemplateFieldName1, 'on', 'Body ' . $timestamp),
            array(2, $lessonPlanTemplateFieldName2, 'on', 'Body 2 ' . $timestamp),
            array(3, $lessonPlanTemplateFieldName3, 'off', 'Body 3 ' . $timestamp)
        );
        $this->createLessonPlanTemplate($i, $lessonPlanTemplateTitle, $lessons);

        $lessonPlanTemplateName = $i->grabLessonPlanTemplateByTitle($lessonPlanTemplateTitle);
        $lessonPlanTemplateFieldName = $i->grabLessonPlanTemplateFieldByTitle($lessonPlanTemplateFieldName1);
        $i->seeResponseCodeIs(303);

        #WHEN
        $i->amGoingTo('Send a request to create a lesson plan with Visible to students OFF');
        $contextType = AssessmentContextType::TYPE_UNIT_IMPLEMENTATION;
        $lessonPlanTemplateId = $lessonPlanTemplateName->getId();
        $lessonPlanTemplateFieldId1 = $lessonPlanTemplateFieldName->getId();
        $visibility = ['staff'];


        $lessonPlanCreate = new Create(
            $contextType,
            $contextId,
            $folderId,
            $lessonPlanTemplateId,
            $title,
            $lessons,
            (array)$visibility
        );

        for ($j = 0; $j < count($lessons); $j++) {
            $lessonPlanCreate->setFormParam('field[new' . ($j + 1) . '][template]', '' . ($lessonPlanTemplateFieldId1 + $j));
            $lessonPlanCreate->setFormParam('field[new' . ($j + 1) . '][body]', 'Body field text' . ($j + 1));
        }

        $lessonPlanCreate->setFormParam('workType', AssessmentForm::WORKTYPE_NONE); // Required form parameter.

        #THEN
        $i->amGoingTo('Check the lesson plan is created');
        $i->sendRequest($lessonPlanCreate);
        $i->seeResponseCodeIs(303);
    }


    /**
     *
     * Convenience method to imports course into a group
     *
     * @param IntegrationTester $i
     * @throws Exception
     *
     */
    private function importCourseToClass(IntegrationTester $i): int
    {
        #GIVEN
        $timestamp      = time();
        $student        = $i->grabUser('jreed');
        $teacher        = $i->grabUser('jcoles');
        $admin          = $i->grabUser('alaress');
        $folder_title   = 'Test Folder to create lesson plans ' . $timestamp;
        $course_base_folder = $i->grabFirstFolderByTitle('Courses');

        $folderPayload = new Payload\Resource\Folder\Create($course_base_folder->getId());
        $folderPayload->setTemplate(HomepageTemplate::HOMEPAGE_NONE);
        $folderPayload->setName($folder_title);
        $i->amGoingTo('Send request create a Folder in faculty folder');
        $i->sendRequest($folderPayload);
        $course_folder = $i->grabFirstFolderByTitle($folder_title);

        $title = 'Course with Unit with assessment at ' . $timestamp;

        $coursePayload = new  Payload\Course\Specification\Create();
        $coursePayload->setName($title);
        $coursePayload->setParent($course_folder->getId());
        $i->amGoingTo('Send request create a course spec');
        $i->sendRequest($coursePayload);

        $course_spec = $i->grabFirstCourseSpecByTitle($title);
        $unit_name   = $title . ' lesson plan';

        $this->createUnit($i, $course_spec->getId(), $unit_name);
        $unitSpec        = $i->grabFirstUnitSpecByTitle($unit_name);

        $modifyCourse = new Payload\Course\Specification\Update($course_spec);
        $modifyCourse->addUnitSpec($unitSpec->getId(), $unit_name);
        $i->amGoingTo('Send save request for the newly created course');
        $i->sendRequest($modifyCourse);

        $classpage_title = 'Testing Class Page for Lesson Plans at ' . $timestamp;
        $class_code      = 'test' . $timestamp;

        $folderPayload = new Payload\Resource\Folder\Create();
        $folderPayload->setTemplate(Payload\Resource\Folder\Create::TEMPLATE_BLANK);
        $folderPayload->setName($classpage_title);
        $folderPayload->setFormParam('code', $class_code);
        $folderPayload->setCreateGroup(true);
        $i->amGoingTo('Create new class for import test');
        $i->sendRequest($folderPayload);
        $groupFolder = $i->grabFirstFolderByTitle($classpage_title);
        $memberPayload = new Payload\Group\AddMembers($groupFolder->getId());
        $memberPayload->setNewMembers([$student->getId(), $admin->getId(), $teacher->getId()]);
        $i->amGoingTo('Add group members to homepage #' . $groupFolder->getId());
        $i->sendRequest($memberPayload);
        $i->seeResponseCodeIs(200);

        #WHEN
        $importCourseSpecPayload = new Payload\Course\Import\Part1($course_spec->getId(), $groupFolder->getId(), 0, '');
        $i->amGoingTo('Send import Step 1. course spec to class page #' . $groupFolder->getId());
        $i->sendRequest($importCourseSpecPayload);
        #THEN
        $i->seeResponseCodeIs(200);

        #WHEN
        $part2 = new Payload\Course\Import\Part2($course_spec->getId(), $groupFolder->getId(), 0, '', $course_spec->getId(), (string)$groupFolder->getId(), null);
        $unitOpen = strtotime('- 5 day');
        $unitClose = strtotime('+ 35 day');
        $part2->setUnitImport(1, $unitSpec->getId(), null, true, $unitOpen, $unitClose);
        $i->amGoingTo('Send import Step 2. unit specs to group page course, group page #' . $groupFolder->getId());
        $i->sendRequest($part2);

        #THEN
        $i->seeResponseCodeIs(200);

        #WHEN
        $selectedUnits = [
            [
                'spec_id' => $unitSpec->getId(),
                'impl_id' => '',
                'open'    => date('d/m/Y', strtotime('- 5 day')),
                'close'   => date('d/m/Y', strtotime('+ 35 day')),
            ],
        ];
        $part3 = new Payload\Course\Import\Part3($course_spec->getId(), $groupFolder->getId(), 0, '', $course_spec->getId(), (string)$groupFolder->getId(), null, $selectedUnits);
        $i->amGoingTo('Send import Step 3. assessments to class page course unit spec, class page #' . $groupFolder->getId());
        $i->sendRequest($part3);

        #THEN
        $i->seeResponseCodeIs(200);

        return $unitSpec->getId();
    }

    /**
     * Convenience helper to create a unit
     * @param IntegrationTester $i
     * @throws \JsonException
     */
    private function createUnit(IntegrationTester $i, int $courseSpecId, string $unit_name): void
    {
        $unitSpecPayload = new Payload\Course\Unit\Specification\Create($courseSpecId);
        $unitSpecPayload->setName($unit_name);
        $i->amGoingTo('Send request create a unit spec');
        $i->sendRequest($unitSpecPayload);
    }
}
