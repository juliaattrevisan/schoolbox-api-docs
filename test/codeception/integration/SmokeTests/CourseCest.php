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

class CourseCest {

    public function _before(IntegrationTester $i)
    {
        $i->loginAdmin(); #we logged in as alaress
    }

    /** Creates course using curl payloads
     *
     * Fires off creating a course folder.
     *
     * @param IntegrationTester $i
     * @param Payload\Course    $coursePayload
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function createCourse (IntegrationTester $i, Payload\Course $coursePayload){
        #GIVEN
        $timestamp = time();
        $folder_title='Test Folder For Create Course at '.$timestamp;
        $faculty_folder=$i->getFirstFolderByTitle('Faculties');

        $folderPayload = new Payload\Folder();
        $folderPayload->setTemplate(Payload\Folder::TEMPLATE_NONE);
        $folderPayload->setName($folder_title);
        $folderPayload->setParentFolder($faculty_folder->getId());
        $i->sendCurlRequest($folderPayload, false, 'Send request create a Folder in faculty folder');
        $course_folder=$i->getFirstFolderByTitle($folder_title);

        #WHEN
        $title='Course with empty Unit at '.$timestamp;

        $coursePayload->setName($title);
        $coursePayload->setParent($course_folder->getId());

        $response = $i->sendCurlRequest($coursePayload, true, 'Send request create a course spec');
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/course/list'=>'303'],
            [IntegrationTester::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $course_spec = $i->getFirstCourseByTitle($title);
        $unit_name=$title.' EmptyUnit';

        $unitSpecPayload = new Base();
        $unitSpecPayload->setPage('/course/'.$course_spec->getId().'/unitspecs/new');
        $unitSpecPayload->setReferer('/course/modify/'.$course_spec->getId());
        $unitSpecPayload->setFormParam('name', $unit_name);

        $response = $i->sendCurlRequest($unitSpecPayload, true, 'Send request create a unit spec');
        $expectedUrlsReturned= [[IntegrationTester::NO_REDIRECT=>'200']];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $unit = $i->getFirstUnitByTitle($unit_name);

        $coursePayload->setModifyCourseSpec($course_spec->getId());
        $coursePayload->addUnitSpec($unit->getId(), $unit_name);

        $response = $i->sendCurlRequest($coursePayload, true, 'Send save request for the newly created course');
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/course/list'=>'303'],
            [IntegrationTester::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Imports course into a class using curl payloads
     *
     * Fires off creating a course folder, class folder, adding users to group, creating a course, creating assessments for the course.
     *
     * @param IntegrationTester $i
     * @param Payload\Course    $coursePayload
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function importCourseToClass (IntegrationTester $i, Payload\Course $coursePayload){
        #GIVEN
        $timestamp = time();
        $folder_title='Test Folder For Create Course with assessments at '.$timestamp;
        $faculty_folder=$i->getFirstFolderByTitle('Faculties');

        $folderPayload = new Payload\Folder();
        $folderPayload->setTemplate(Payload\Folder::TEMPLATE_NONE);
        $folderPayload->setName($folder_title);
        $folderPayload->setParentFolder($faculty_folder->getId());
        $i->sendCurlRequest($folderPayload, false, 'Send request create a Folder in faculty folder');
        $course_folder=$i->getFirstFolderByTitle($folder_title);

        $title='Course with Unit with assessment at '.$timestamp;

        $coursePayload->setName($title);
        $coursePayload->setParent($course_folder->getId());

        $i->sendCurlRequest($coursePayload, false, 'Send request create a course spec');

        $course_spec = $i->getFirstCourseByTitle($title);
        $unit_name=$title.' EmptyUnit';

        $unitSpecPayload = new Base();
        $unitSpecPayload->setPage('/course/'.$course_spec->getId().'/unitspecs/new');
        $unitSpecPayload->setReferer('/course/modify/'.$course_spec->getId());
        $unitSpecPayload->setFormParam('name', $unit_name);

        $i->sendCurlRequest($unitSpecPayload, false, 'Send request create a unit spec');
        $unit = $i->getFirstUnitByTitle($unit_name);
        $unit_folder = $i->getFirstFolderByTitle($unit_name);

        $assessment_title='Assessment for Course tests On Unit spec ID '.$unit->getId().' at '.$timestamp;

        $newAssessmentRequest= new \Payload\Assessment();
        $newAssessmentRequest->setTitle($assessment_title);
        $newAssessmentRequest->setFolderId($unit_folder->getId());
        $newAssessmentRequest->setContextType('unitspecs');
        $newAssessmentRequest->setContextId($unit->getId());

        /** @var Response $response */
        $i->sendCurlRequest($newAssessmentRequest, false, 'Send request create asessment on Unit spec #'.$unit->getId());

        $project_name='Assessment for course class project at '.$timestamp;
        $project_duework_name='Assessment for project in class at '.$timestamp;

        $newAssessmentRequest= new \Payload\Assessment();
        $newAssessmentRequest->setTitle($project_name);
        $newAssessmentRequest->setFolderId($unit_folder->getId());
        $newAssessmentRequest->setContextType('unitspecs');
        $newAssessmentRequest->setContextId($unit->getId());
        $newAssessmentRequest->setAssessmentType('project');

        /** @var Response $response */
        $i->sendCurlRequest($newAssessmentRequest, false, 'Send request create project on Unit spec #'.$unit->getId());
        $project_assessment_id=$i->getFirstAssessmentIdByTitle($project_name);

        $newAssessmentRequest= new \Payload\Assessment();
        $newAssessmentRequest->setTitle($project_duework_name);
        $newAssessmentRequest->setFolderId($unit_folder->getId());
        $newAssessmentRequest->setContextType('projects');
        $newAssessmentRequest->setContextId($project_assessment_id);

        /** @var Response $response */
        $i->sendCurlRequest($newAssessmentRequest, false, 'Send request create due work on Project #'.$unit->getId());

        $coursePayload->setModifyCourseSpec($course_spec->getId());
        $coursePayload->addUnitSpec($unit->getId(), $unit_name);

        $i->sendCurlRequest($coursePayload, false, 'Send save request for the newly created course');

        $classpage_title='Testing Class Page for Course Sync and Assessment at '.$timestamp;
        $class_code='test'.$timestamp;
        $student = $i->getUser('drhodes');
        $teacher = $i->getUser('zsmith');
        $admin = $i->getUser('alaress');

        $folderPayload = new Payload\Folder();
        $folderPayload->setTemplate(Payload\Folder::TEMPLATE_HOMEPAGE_ID);
        $folderPayload->setName($classpage_title);
        $folderPayload->setFormParam('code', $class_code);
        $i->sendCurlRequest($folderPayload, false, 'Create new class for import test');
        $classpage=$i->getFirstFolderByTitle($classpage_title);

        $memberPayload= new \Payload\GroupMember();
        $memberPayload->setFolderGroup($classpage->getId());
        $memberPayload->setNewMember($student->getId());
        $memberPayload->setNewMember($admin->getId());
        $memberPayload->setNewMember($teacher->getId());
        $i->sendCurlRequest($memberPayload, false, 'Add group members to class page #'.$classpage->getId());

        #WHEN
        $importCourseSpecPayload = new \Payload\CourseImport();

        $importCourseSpecPayload->setCourseSpecId($course_spec->getId());
        $importCourseSpecPayload->setClassPageId($classpage->getId());

        $response = $i->sendCurlRequest($importCourseSpecPayload, true, 'Send import Step 1. course spec to class page #'.$classpage->getId());
        $expectedUrlsReturned= [[IntegrationTester::NO_REDIRECT=>'200']];
        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $importCourseSpecPayload->setImportUnits();
        $unitsForCourseSpec=$i->getUnitsByCourseSpecId($course_spec->getId());
        $importCourseSpecPayload = $i->courseImportAddUnits($importCourseSpecPayload, $unitsForCourseSpec);

        $response = $i->sendCurlRequest($importCourseSpecPayload, true, 'Send import Step 2. unit specs to class page course, class page #'.$classpage->getId());
        $expectedUrlsReturned= [[IntegrationTester::NO_REDIRECT=>'200']];
        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $importCourseSpecPayload->setImportAssessments();
        $unitsForCourseSpec=$i->getUnitsByCourseSpecId($course_spec->getId());
        $importCourseSpecPayload = $i->courseImportAddAssessments($importCourseSpecPayload, $unitsForCourseSpec);

        $response = $i->sendCurlRequest($importCourseSpecPayload, true, 'Send import Step 3. assessments to class page course unit spec, class page #'.$classpage->getId());
        $expectedUrlsReturned= [[IntegrationTester::NO_REDIRECT=>'200']];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

}
