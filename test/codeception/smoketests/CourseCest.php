<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/** @noinspection PhpDocMissingThrowsInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace smoketests;

use IntegrationTester;
use Payload;
use Schoolbox\Learning\Assessment\AssessmentContextType;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Resource\HomepageTemplate;

class CourseCest
{

    public function _before(IntegrationTester $i): void
    {
        $i->loginAdmin(); #we logged in as alaress
        $i->stopFollowingRedirects();
    }


    /** Creates course using curl payloads
     *
     * Fires off creating a course folder.
     *
     * @param IntegrationTester $i
     */
    public function createCourse(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp      = time();
        $folder_title   = 'Test Folder For Create Course at ' . $timestamp;
        $course_base_folder = $i->grabFirstFolderByTitle('Courses');

        $folderPayload = new Payload\Resource\Folder\Create($course_base_folder->getId());
        $folderPayload->setTemplate(HomepageTemplate::HOMEPAGE_NONE);
        $folderPayload->setName($folder_title);
        $i->amGoingTo('Send request create a Folder in faculty folder');
        $i->sendRequest($folderPayload);
        $course_folder = $i->grabFirstFolderByTitle($folder_title);

        #WHEN
        $title = 'Course with empty Unit at ' . $timestamp;

        $coursePayload = new Payload\Course\Specification\Create();
        $coursePayload->setName($title);
        $coursePayload->setParent($course_folder->getId());
        $i->amGoingTo('Send request create a course spec');
        $i->sendRequest($coursePayload);

        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', '/course/list');

        #WHEN
        $course_spec = $i->grabFirstCourseSpecByTitle($title);
        $unit_name   = $title . ' EmptyUnit';

        $unitSpecPayload = new Payload\Course\Unit\Specification\Create($course_spec->getId());
        $unitSpecPayload->setFormParam('name', $unit_name);
        $i->amGoingTo('Send request create a unit spec');
        $i->sendRequest($unitSpecPayload);

        #THEN
        $i->seeResponseCodeIs(200);

        #WHEN
        $unit         = $i->grabFirstUnitSpecByTitle($unit_name);
        $modifyCourse = new Payload\Course\Specification\Update($course_spec);
        $modifyCourse->addUnitSpec($unit->getId(), $unit_name);

        $i->amGoingTo('Send save request for the newly created course');
        $i->sendRequest($modifyCourse);

        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', '/course/list');
    }


    /**
     * Imports course into a group using curl payloads
     *
     * * creates a folder
     * * creates a course spec in that folder
     * * creates a unit spec in course spec
     * * creates a project in unit spec
     * * creates a due-work in project
     * * creates a group folder
     * * add group members to group
     * * imports course spec to group folder
     *
     * @param IntegrationTester $i
     */
    public function importCourseToClass(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp      = time();
        $folder_title   = 'Test Folder For Create Course and import assessments at ' . $timestamp;
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
        $unit_name   = $title . ' With 1 project';

        $unitSpecPayload = new Payload\Course\Unit\Specification\Create($course_spec->getId());
        $unitSpecPayload->setFormParam('name', $unit_name);
        $i->amGoingTo('Send request create a unit spec');
        $i->sendRequest($unitSpecPayload);
        $unitSpec        = $i->grabFirstUnitSpecByTitle($unit_name);
        $unit_folder = $i->grabFirstFolderByTitle($unit_name);

        $assessment_title = 'Assessment for Course tests On Unit spec ID ' . $unitSpec->getId() . ' at ' . $timestamp;

        $newAssessmentRequest = new Payload\Assessable\Create(AssessmentContextType::TYPE_UNIT_SPECIFICATION, $unitSpec->getId(), $unit_folder->getId(), AssessmentType::TYPE_DUE_WORK);
        $newAssessmentRequest->setTitle($assessment_title);

        $i->amGoingTo('Send request create assessment on Unit spec #' . $unitSpec->getId());
        $i->sendRequest($newAssessmentRequest);

        $project_name         = 'Assessment for course class project at ' . $timestamp;
        $project_duework_name = 'Assessment for project in class at ' . $timestamp;

        $newAssessmentRequest = new Payload\Assessable\Create(AssessmentContextType::TYPE_UNIT_SPECIFICATION, $unitSpec->getId(), $unit_folder->getId(), AssessmentType::TYPE_PROJECT);
        $newAssessmentRequest->setTitle($project_name);

        $i->amGoingTo('Send request create project on Unit spec #' . $unitSpec->getId());
        $i->sendRequest($newAssessmentRequest);
        $projectSpec = $i->grabFirstAssessmentByTitle($project_name);

        $newAssessmentRequest = new Payload\Assessable\Create(AssessmentContextType::TYPE_PROJECT, $projectSpec->getId(), $unit_folder->getId(), AssessmentType::TYPE_DUE_WORK);
        $newAssessmentRequest->setTitle($project_duework_name);

        $i->amGoingTo('Send request create due work on Project #' . $unitSpec->getId());
        $i->sendRequest($newAssessmentRequest);
        $dueWorkSpec = $i->grabFirstAssessmentByTitle($project_duework_name);

        $modifyCourse = new Payload\Course\Specification\Update($course_spec);
        $modifyCourse->addUnitSpec($unitSpec->getId(), $unit_name);
        $i->amGoingTo('Send save request for the newly created course');
        $i->sendRequest($modifyCourse);

        $classpage_title = 'Testing Class Page for Course Sync and Assessment at ' . $timestamp;
        $class_code      = 'test' . $timestamp;
        $student         = $i->grabUser('jreed');
        $teacher         = $i->grabUser('jcoles');
        $admin           = $i->grabUser('alaress');

        $folderPayload = new Payload\Resource\Folder\Create();
        $folderPayload->setTemplate(Payload\Resource\Folder\Create::TEMPLATE_BLANK);
        $folderPayload->setName($classpage_title);
        $folderPayload->setFormParam('code', $class_code);
        $i->amGoingTo('Create new class for import test');
        $i->sendRequest($folderPayload);
        $groupFolder = $i->grabFirstFolderByTitle($classpage_title);

        $memberPayload = new Payload\Group\AddMembers($groupFolder->getId());
        $memberPayload->setNewMembers([$student->getId(), $admin->getId(), $teacher->getId()]);
        $i->amGoingTo('Add group members to class page #' . $groupFolder->getId());
        $i->sendRequest($memberPayload);

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
        $part3->setAssessmentImport($unitSpec->getId(), $projectSpec->getId(), null, true, strtotime('-10 day'), null, strtotime('+10 day'), null);
        $part3->setSubAssessmentImport($unitSpec->getId(), $projectSpec->getId(), $dueWorkSpec->getId(), null, true, strtotime('-10 day'), null, strtotime('+10 day'), null);
        $i->amGoingTo('Send import Step 3. assessments to class page course unit spec, class page #' . $groupFolder->getId());
        $i->sendRequest($part3);

        #THEN
        $i->seeResponseCodeIs(200);
    }

    /**
     * Imports course into a group using curl payloads
     *
     * * creates a folder
     * * creates a course spec in that folder
     * * creates a unit spec in course spec
     * * creates a project in unit spec
     * * creates a due-work in project
     * * creates a task in the unit
     * * creates a group folder
     * * add group members to group
     * * imports course spec to group folder
     * * creates more units and activities in the course via course list
     * * exclude already imported activities from the course via course list
     * * exclude not yet imported activities from the course via course list
     *
     * @param IntegrationTester $i
     */
    public function modifyingCourseAlreadyOnAClassPage(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp      = time();
        $folder_title   = 'Test Folder For Create Course and import and modify assessments at ' . $timestamp;
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
        $unit_name   = $title . ' With 1 project';

        $unitSpecPayload = new Payload\Course\Unit\Specification\Create($course_spec->getId());
        $unitSpecPayload->setFormParam('name', $unit_name);
        $i->amGoingTo('Send request create a unit spec');
        $i->sendRequest($unitSpecPayload);
        $unitSpec        = $i->grabFirstUnitSpecByTitle($unit_name);
        $unit_folder = $i->grabFirstFolderByTitle($unit_name);

        $assessment_title = 'Assessment Due Work for Course tests On Unit spec ID ' . $unitSpec->getId() . ' at ' . $timestamp;

        $newAssessmentRequest = new Payload\Assessable\Create(AssessmentContextType::TYPE_UNIT_SPECIFICATION, $unitSpec->getId(), $unit_folder->getId(), AssessmentType::TYPE_DUE_WORK);
        $newAssessmentRequest->setTitle($assessment_title);

        $i->amGoingTo('Send request create assessment on Unit spec #' . $unitSpec->getId());
        $i->sendRequest($newAssessmentRequest);
        $dueWorkUnitSpec = $i->grabFirstAssessmentByTitle($assessment_title);

        //here adding a task
        $assessment_title = 'Assessment Task for Course tests On Unit spec ID ' . $unitSpec->getId() . ' at ' . $timestamp;

        $newAssessmentRequest = new Payload\Assessable\Create(AssessmentContextType::TYPE_UNIT_SPECIFICATION, $unitSpec->getId(), $unit_folder->getId(), AssessmentType::TYPE_TASK);
        $newAssessmentRequest->setTitle($assessment_title);


        $i->amGoingTo('Send request create assessment on Unit spec #' . $unitSpec->getId());
        $i->sendRequest($newAssessmentRequest);
        $taskSpec = $i->grabFirstAssessmentByTitle($assessment_title);
        //end

        $project_name         = 'Assessment for course class project at ' . $timestamp;
        $project_duework_name = 'Assessment for project in class at ' . $timestamp;

        $newAssessmentRequest = new Payload\Assessable\Create(AssessmentContextType::TYPE_UNIT_SPECIFICATION, $unitSpec->getId(), $unit_folder->getId(), AssessmentType::TYPE_PROJECT);
        $newAssessmentRequest->setTitle($project_name);

        $i->amGoingTo('Send request create project on Unit spec #' . $unitSpec->getId());
        $i->sendRequest($newAssessmentRequest);
        $projectSpec = $i->grabFirstAssessmentByTitle($project_name);

        $newAssessmentRequest = new Payload\Assessable\Create(AssessmentContextType::TYPE_PROJECT, $projectSpec->getId(), $unit_folder->getId(), AssessmentType::TYPE_DUE_WORK);
        $newAssessmentRequest->setTitle($project_duework_name);

        $i->amGoingTo('Send request create due work on Project #' . $unitSpec->getId());
        $i->sendRequest($newAssessmentRequest);
        $dueWorkSpec = $i->grabFirstAssessmentByTitle($project_duework_name);

        $modifyCourse = new Payload\Course\Specification\Update($course_spec);
        $modifyCourse->addUnitSpec($unitSpec->getId(), $unit_name);
        $i->amGoingTo('Send save request for the newly created course');
        $i->sendRequest($modifyCourse);

        $classpage_title = 'Testing Class Page for Course Sync and Assessment at ' . $timestamp;
        $class_code      = 'test' . $timestamp;
        $student         = $i->grabUser('jreed');
        $teacher         = $i->grabUser('jcoles');
        $admin           = $i->grabUser('alaress');

        $folderPayload = new Payload\Resource\Folder\Create();
        $folderPayload->setTemplate(Payload\Resource\Folder\Create::TEMPLATE_BLANK);
        $folderPayload->setName($classpage_title);
        $folderPayload->setFormParam('code', $class_code);
        $i->amGoingTo('Create new class for import test');
        $i->sendRequest($folderPayload);
        $groupFolder = $i->grabFirstFolderByTitle($classpage_title);

        $memberPayload = new Payload\Group\AddMembers($groupFolder->getId());
        $memberPayload->setNewMembers([$student->getId(), $admin->getId(), $teacher->getId()]);
        $i->amGoingTo('Add group members to class page #' . $groupFolder->getId());
        $i->sendRequest($memberPayload);

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
        $part3->setAssessmentImport($unitSpec->getId(), $projectSpec->getId(), null, true, strtotime('-10 day'), null, strtotime('+10 day'), null);
        $part3->setSubAssessmentImport($unitSpec->getId(), $projectSpec->getId(), $dueWorkSpec->getId(), null, true, strtotime('-10 day'), null, strtotime('+10 day'), null);
        //
        $part3->setAssessmentImport($unitSpec->getId(), $dueWorkUnitSpec->getId(), null, true, strtotime('-10 day'), null, strtotime('+10 day'), null);
        $part3->setAssessmentImport($unitSpec->getId(), $taskSpec->getId(), null, true, strtotime('-10 day'), null, strtotime('+10 day'), null);

        $i->amGoingTo('Send import Step 3. assessments to class page course unit spec, class page #' . $groupFolder->getId());
        $i->sendRequest($part3);

        #THEN
        $i->seeResponseCodeIs(200);

        /**
         * adds another 2 units to the same course above with activities
         * removes one of the units just added (not yet imported to the class)
         * export 2 due works and 2 tasks from the last unit created to the class
         * removes 1 task and 1 due work already exported to the class
         * check the task and due work removed from the course are still presented in the class page
         * remove 1 task and 1 due work not yet exported to the class
         * export a due work and a task to the class not yet exported
         */

        $unit_name   = $title . 'second unit added';
        $unitSpecPayload = new Payload\Course\Unit\Specification\Create($course_spec->getId());
        $unitSpecPayload->setFormParam('name', $unit_name);
        $i->amGoingTo('Send request create a second unit spec');
        $i->sendRequest($unitSpecPayload);
        $unitSpec        = $i->grabFirstUnitSpecByTitle($unit_name);
        $unit_folder = $i->grabFirstFolderByTitle($unit_name);
        $unitSpecId = $unitSpec->getId();
        $unit_folderId = $unit_folder->getId();
        $this->createAssessment($i,$unitSpecId,$unit_folderId);

       // $assessment_title = 'Assessment Due Work for Course tests On Unit 2 spec ID ' . $unitSpec->getId() . ' at ' . $timestamp;

//        $newAssessmentRequest = new Payload\Assessable\Create(AssessmentContextType::TYPE_UNIT_SPECIFICATION, $unitSpec->getId(), $unit_folder->getId(), AssessmentType::TYPE_DUE_WORK);
//        $newAssessmentRequest->setTitle($assessment_title);

//        $i->amGoingTo('Send request create assessment on Unit spec #' . $unitSpec->getId());
//        $i->sendRequest($newAssessmentRequest);
//        $dueWorkUnitSpec = $i->grabFirstAssessmentByTitle($assessment_title);


        $modifyCourse = new Payload\Course\Specification\Update($course_spec);
        $modifyCourse->addUnitSpec($unitSpec->getId(), $unit_name);
        $i->amGoingTo('Send save request for the newly created course');
        $i->sendRequest($modifyCourse);


        #WHEN
//        $unit_name   = $title . 'second unit added';
//        $unitSpecPayload = new Payload\Course\Unit\Specification\Create($course_spec->getId());
//        $unitSpecPayload->setFormParam('name', $unit_name);
//        $i->amGoingTo('Send request create a second unit spec');
//        $i->sendRequest($unitSpecPayload);
//        $unitSpec        = $i->grabFirstUnitSpecByTitle($unit_name);
//        $unit_folder = $i->grabFirstFolderByTitle($unit_name);
//
//        $assessment_title = 'Assessment Due Work for Course tests On Unit 2 spec ID ' . $unitSpec->getId() . ' at ' . $timestamp;
//
//        $newAssessmentRequest = new Payload\Assessable\Create(AssessmentContextType::TYPE_UNIT_SPECIFICATION, $unitSpec->getId(), $unit_folder->getId(), AssessmentType::TYPE_DUE_WORK);
//        $newAssessmentRequest->setTitle($assessment_title);
//
//        $i->amGoingTo('Send request create assessment on Unit spec #' . $unitSpec->getId());
//        $i->sendRequest($newAssessmentRequest);
//        $dueWorkUnitSpec = $i->grabFirstAssessmentByTitle($assessment_title);
//
//        $modifyCourse = new Payload\Course\Specification\Update($course_spec);
//        $modifyCourse->addUnitSpec($unitSpec->getId(), $unit_name);
//        $i->amGoingTo('Send save request for the newly created course');
//        $i->sendRequest($modifyCourse);

//        #WHEN
//        $unit_name   = $title . 'third unit added';
//        $unitSpecPayload = new Payload\Course\Unit\Specification\Create($course_spec->getId());
//        $unitSpecPayload->setFormParam('name', $unit_name);
//        $i->amGoingTo('Send request create a second unit spec');
//        $i->sendRequest($unitSpecPayload);
//        $unitSpec        = $i->grabFirstUnitSpecByTitle($unit_name);
//        $unit_folder = $i->grabFirstFolderByTitle($unit_name);
//
//        $assessment_title = 'Assessment Due Work 1 for Course tests On Unit 3 spec ID ' . $unitSpec->getId() . ' at ' . $timestamp;
//
//        $newAssessmentRequest = new Payload\Assessable\Create(AssessmentContextType::TYPE_UNIT_SPECIFICATION, $unitSpec->getId(), $unit_folder->getId(), AssessmentType::TYPE_DUE_WORK);
//        $newAssessmentRequest->setTitle($assessment_title);
//
//        $i->amGoingTo('Send request create assessment on Unit spec #' . $unitSpec->getId());
//        $i->sendRequest($newAssessmentRequest);
//        $dueWorkUnitSpec1 = $i->grabFirstAssessmentByTitle($assessment_title);
//
//        $assessment_title = 'Assessment Due Work 2 for Course tests On Unit 3 spec ID ' . $unitSpec->getId() . ' at ' . $timestamp;
//
//        $newAssessmentRequest = new Payload\Assessable\Create(AssessmentContextType::TYPE_UNIT_SPECIFICATION, $unitSpec->getId(), $unit_folder->getId(), AssessmentType::TYPE_DUE_WORK);
//        $newAssessmentRequest->setTitle($assessment_title);
//
//        $i->amGoingTo('Send request create assessment on Unit spec #' . $unitSpec->getId());
//        $i->sendRequest($newAssessmentRequest);
//        $dueWorkUnitSpec2 = $i->grabFirstAssessmentByTitle($assessment_title);
//
//        $assessment_title = 'Assessment Due Work 3 for Course tests On Unit 3 spec ID ' . $unitSpec->getId() . ' at ' . $timestamp;
//
//        $newAssessmentRequest = new Payload\Assessable\Create(AssessmentContextType::TYPE_UNIT_SPECIFICATION, $unitSpec->getId(), $unit_folder->getId(), AssessmentType::TYPE_DUE_WORK);
//        $newAssessmentRequest->setTitle($assessment_title);
//
//        $i->amGoingTo('Send request create assessment on Unit spec #' . $unitSpec->getId());
//        $i->sendRequest($newAssessmentRequest);
//        $dueWorkUnitSpec3 = $i->grabFirstAssessmentByTitle($assessment_title);
//
//        $assessment_title = 'Assessment Due Work 4 for Course tests On Unit 3 spec ID ' . $unitSpec->getId() . ' at ' . $timestamp;
//
//        $newAssessmentRequest = new Payload\Assessable\Create(AssessmentContextType::TYPE_UNIT_SPECIFICATION, $unitSpec->getId(), $unit_folder->getId(), AssessmentType::TYPE_DUE_WORK);
//        $newAssessmentRequest->setTitle($assessment_title);
//
//        $i->amGoingTo('Send request create assessment on Unit spec #' . $unitSpec->getId());
//        $i->sendRequest($newAssessmentRequest);
//        $dueWorkUnitSpec4 = $i->grabFirstAssessmentByTitle($assessment_title);
//
//        $modifyCourse = new Payload\Course\Specification\Update($course_spec);
//        $modifyCourse->addUnitSpec($unitSpec->getId(), $unit_name);
//        $i->amGoingTo('Send save request for the newly created course');
//        $i->sendRequest($modifyCourse);
    }

    /** Convenience helper to create assessments
     * @param IntegrationTester $i
     */
    public function createAssessment(IntegrationTester $i, int $unitSpecId, int $unitFolderId) {

        $timestamp      = time();
        $assessment_title = 'Assessment Due Work for Course tests On Unit spec ID ' . $unitSpecId . ' at ' . $timestamp;

        $newAssessmentRequest = new Payload\Assessable\Create(AssessmentContextType::TYPE_UNIT_SPECIFICATION, $unitSpecId, $unitFolderId, AssessmentType::TYPE_DUE_WORK);
        $newAssessmentRequest->setTitle($assessment_title);

        $i->amGoingTo('Send request create assessment on Unit spec #' . $unitSpecId);
        $i->sendRequest($newAssessmentRequest);
        $dueWorkUnitSpec = $i->grabFirstAssessmentByTitle($assessment_title);
    }
}
