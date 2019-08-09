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

class CourseCest {
    /**
     * Smoke test course post forms include following actions:
     * Create course
     * Create unit spec for course
     * Save newly created course
     * Create assignment in new course + unit spec
     * Create project in course unit spec
     * Create am assessment in project
     * Save course which contains the assessment and project
     * Create folder as a class
     * Import new course and unit with assignment into our class.
     *
     * Each of these in the future itteration should be split into sepperate test functions.
     * Also some of the folder stuff belong to testing the resource browser. So might need to be split out
     *
     * @param IntegrationTester $i
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function SmokeCoursesPOST(IntegrationTester $i){
        #GIVEN
        $i->loginAdmin(); #we logged in as alaress

        $folder_title='Test Folder For Courses';
        $faculty_folder=$i->getFirstFolderByTitle('Faculties');
        $i->createFolder($folder_title, -1, $faculty_folder);
        $course_folder=$i->getFirstFolderByTitle($folder_title);

        #WHEN
        $title='Course with empty Unit';
        $response = $i->createNewCourseSpec($title, $course_folder);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/course/list'=>'303'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];
        $i->comment('Created folder and created a course in it');

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $course_spec = $i->getFirstCourseByTitle($title);
        $unit_name=$title.' EmptyUnit';
        $response = $i->createNewUnitSpec($unit_name, $course_spec);
        $expectedUrlsReturned= [[GuzzleConstants::NO_REDIRECT=>'200']];
        $i->comment('Created unit spec for course');

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $unit = $i->getFirstUnitByTitle($unit_name);
        $response = $i->saveCourseSpec($title, $unit_name, $course_folder->getId(), $course_spec, $unit);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/course/list'=>'303'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];
        $i->comment('Save newly created course');

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $title='Course with Unit with assessment';
        $i->createNewCourseSpec($title, $course_folder);
        $course_spec = $i->getFirstCourseByTitle($title);
        $unit_name=$title.' UnitWithAssessments';
        $i->createNewUnitSpec($unit_name, $course_spec);
        $unit_folder = $i->getFirstFolderByTitle($unit_name);
        $unit = $i->getFirstUnitByTitle($unit_name);
        $response = $i->createAssessmentOnCourseUnitSpec('Assessment for course due work', $unit->getId(), $unit_folder->getId());
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/course/modify/'.$course_spec->getId()=>'303'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];
        $i->comment('create an assessment in a new course + unit spec');

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $project_name='Assessment for course project';
        $response = $i->createAssessmentProjectOnCourseUnitSpec($project_name, $unit->getId(), $unit_folder->getId());
        $project_assessment_id=$i->getFirstAssessmentIdByTitle($project_name);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/learning/assessments/'.$project_assessment_id.'/status'=>'303'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];
        $i->comment('create an project in a the course unit spec');

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $response = $i->createAssessmentOnProject('Assessment for project in course', $project_assessment_id, $unit_folder->getId());
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/learning/assessments/'.$project_assessment_id.'/status'=>'303'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];
        $i->comment('create an assessment in the new projects');

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $response = $i->saveCourseSpec($title, $unit_name, $course_folder->getId(), $course_spec, $unit);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/course/list'=>'303'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];
        $i->comment('Save newly created course that contains a unit spec with assessment');

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #GIVEN
        $homepage_title='Testing Class Page for Course Sync and Assessment';
        $class_code='test104';
        $i->createClass($homepage_title, $class_code);
        $classPageId=$i->getFirstHomepageIdByTitle($homepage_title);
        $student = $i->getUser('drhodes');
        $teacher = $i->getUser('zsmith');
        $admin = $i->getUser('alaress');
        $i->setHomepageGroupUserPermission($classPageId, [$student->getId()=>1, $teacher->getId()=>2, $admin->getId()=>3]);
        $i->comment('Created new folder as a class.');
        $title='Course with Units with assessments for Class';
        $i->createNewCourseSpec($title, $course_folder);
        $course_spec = $i->getFirstCourseByTitle($title);
        $unit_name=$title.' UnitWithAssessments';
        $i->createNewUnitSpec($unit_name, $course_spec);
        $unit_folder = $i->getFirstFolderByTitle($unit_name);
        $unit = $i->getFirstUnitByTitle($unit_name);
        $i->createAssessmentOnCourseUnitSpec('Assessment for course due work', $unit->getId(), $unit_folder->getId());
        $project_name='Assessment for course class project';
        $i->createAssessmentProjectOnCourseUnitSpec($project_name, $unit->getId(), $unit_folder->getId());
        $project_assessment_id=$i->getFirstAssessmentIdByTitle($project_name);
        $i->createAssessmentOnProject('Assessment for project in class', $project_assessment_id, $unit_folder->getId());
        #TODO change saveCourseSpec to allow for more than one unit to be present on course.
        $i->saveCourseSpec($title, $unit_name, $course_folder->getId(), $course_spec, $unit);
        $i->comment('Created new course spec with unit and assessment to import.');

        #WHEN
        $response = $i->importCourseSpecToClass($course_spec->getId(), $classPageId);
        $expectedUrlsReturned= [[GuzzleConstants::NO_REDIRECT=>'200']];
        $i->comment('Importing course spec to class step 1.');
        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
        #WHEN
        $response = $i->importUnitSpecToClassCourse($course_spec->getId(), $classPageId);
        $expectedUrlsReturned= [[GuzzleConstants::NO_REDIRECT=>'200']];
        $i->comment('Importing unit spec to class course step 2.');
        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
        #WHEN
        $response = $i->importDueWorkToClassCourseUnit($course_spec->getId(), $classPageId);
        $expectedUrlsReturned= [[GuzzleConstants::NO_REDIRECT=>'200']];
        $i->comment('Importing all the due work into units of the source in class step 3.');
        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
        $i->comment('Import course into class folder.');

    }
}