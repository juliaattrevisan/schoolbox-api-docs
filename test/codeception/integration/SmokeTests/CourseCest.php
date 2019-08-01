<?php

use GuzzleHttp\Psr7\Response;

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
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        $i->comment('Created folder and created a course in it');

        #THEN
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected after I create course.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/course/list', $redirect_history[0], 'I expect we get redirected to the course list after creating the course.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $course_spec = $i->getFirstCourseByTitle($title);
        $unit_name=$title.' EmptyUnit';
        $response = $i->createNewUnitSpec($unit_name, $course_spec);
        $i->comment('Created unit spec for course');

        #THEN
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 okay after creation.');

        #WHEN
        $unit = $i->getFirstUnitByTitle($unit_name);
        $response = $i->saveCourseSpec($title, $unit_name, $course_folder->getId(), $course_spec, $unit);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        $i->comment('Save newly created course');

        #THEN
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected after I save course.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/course/list', $redirect_history[0], 'I expect we get redirected to the course list after saving the course.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $title='Course with Unit with assessment';
        $i->createNewCourseSpec($title, $course_folder);
        $course_spec = $i->getFirstCourseByTitle($title);
        $unit_name=$title.' UnitWithAssessments';
        $i->createNewUnitSpec($unit_name, $course_spec);
        $unit_folder = $i->getFirstFolderByTitle($unit_name);
        $unit = $i->getFirstUnitByTitle($unit_name);
        $response = $i->createAssessemntOnCourseUnitSpec('Assessment for course due work', $unit->getId(), $unit_folder->getId());
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        $i->comment('create an assessment in a new course + unit spec');

        #THEN
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected after I create an assessment in a unit spec.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/course/modify/'.$course_spec->getId(), $redirect_history[0], 'I expect we get redirected to the course list after creating the assessment.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $project_name='Assessment for course project';
        $response = $i->createAssessemntProjectOnCourseUnitSpec($project_name, $unit->getId(), $unit_folder->getId());
        $project_assessment_id=$i->getFirstAssessmentIdByTitle($project_name);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        $i->comment('create an project in a the course unit spec');

        #THEN
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected after I create a project.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/learning/assessments/'.$project_assessment_id.'/status', $redirect_history[0], 'I expect we get redirected to the project we just created.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $response = $i->createAssessemntOnProject('Assessment for project in course', $project_assessment_id, $unit_folder->getId());
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        $i->comment('create an assessment in the new projects');

        #THEN
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected after I create a assessment in project.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/learning/assessments/'.$project_assessment_id.'/status', $redirect_history[0], 'I expect we get redirected to the poject we added an assignment to.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $response = $i->saveCourseSpec($title, $unit_name, $course_folder->getId(), $course_spec, $unit);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        $i->comment('Save newly created course that contains a unit spec with assessment');

        #THEN
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected after I save course.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/course/list', $redirect_history[0], 'I expect we get redirected to the course list after saving the course.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

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
        $i->createAssessemntOnCourseUnitSpec('Assessment for course due work', $unit->getId(), $unit_folder->getId());
        $project_name='Assessment for course class project';
        $i->createAssessemntProjectOnCourseUnitSpec($project_name, $unit->getId(), $unit_folder->getId());
        $project_assessment_id=$i->getFirstAssessmentIdByTitle($project_name);
        $i->createAssessemntOnProject('Assessment for project in class', $project_assessment_id, $unit_folder->getId());
        #TODO change saveCourseSpec to allow for more than one unit to be present on course.
        $i->saveCourseSpec($title, $unit_name, $course_folder->getId(), $course_spec, $unit);
        $i->comment('Created new course spec with unit and assessment to import.');

        #WHEN
        $response = $i->importCourseSpecToClass($course_spec->getId(), $classPageId);
        #THEN
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after importing course spec to class step 1.');
        #WHEN
        $response = $i->importUnitSpecToClassCourse($course_spec->getId(), $classPageId);
        #THEN
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after importing unit spec to class course step 2.');
        #WHEN
        $response = $i->importDueWorkToClassCourseUnit($course_spec->getId(), $classPageId);
        #THEN
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after importing all the due work into units of the source in class step 3.');
        $i->comment('Import course into class folder.');

        # Submit work from the course unit as student.
        #Not possible, its dependant on externals for class lists.

        # Mark work for course unit as staff.
        #Not possible, its dependant on externals for class lists.

    }
}