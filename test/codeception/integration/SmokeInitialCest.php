<?php

use GuzzleHttp\Psr7\Response;

/**
 * @author    Alexamder Tsuru <alexander.simonov@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class SmokeInitialCest {

    public function LoginLocalUser(IntegrationTester $i){

        #GIVEN
        $user='alaress';
        $password='alaress';
        #WHEN
        /** @var GuzzleHttp\Psr7\Response $response */
        $response = $i->loginUser($user, $password);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected from the login page.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/', $redirect_history[0], 'I expect that we get redirected to the main index page.');
        $i->assertArrayNotHasKey(1, $redirect_history_status, 'I expect there is only one redirect in the history of this request');
    }


    public function LoginForgotPassword(IntegrationTester $i){

        #GIVEN
        $user_email='alaress@schoolbox.com.au';
        $user_username='alaress';
        #WHEN
        /** @var GuzzleHttp\Psr7\Response $response */
        $response = $i->loginForgotPasswordUsername($user_username);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected from the login page.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/passwordReset', $redirect_history[0], 'I expect that we get redirected to the main index page.');
        $i->assertArrayNotHasKey(1, $redirect_history_status, 'I expect there is only one redirect in the history of this request');

        #WHEN
        /** @var GuzzleHttp\Psr7\Response $response */
        $response = $i->loginForgotPasswordEmail($user_email);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected from the login page.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/passwordReset', $redirect_history[0], 'I expect that we get redirected to the main index page.');
        $i->assertArrayNotHasKey(1, $redirect_history_status, 'I expect there is only one redirect in the history of this request');
    }

    /**
     *
     */
    public function SmokeNewsPOST(IntegrationTester $i){

        #GIVEN
        $i->loginAdmin();
        #The regex matches: https://vagrant,dev.echoolbox.cloud/news/<atricle_id>?returnUrl=Lw%253D%253D
        $return_page_url_regex_pattern='/https:\/\/vagrant\.dev\.schoolbox\.cloud\/news\/\d+\?returnUrl=Lw%253D%253D/';
        $capture_new_news_id='/news\/(\d+)\?returnUrl/';
        $title = 'Smoke Test News Title Draft';
        #WHEN
        /** @var GuzzleHttp\Psr7\Response $response */
        $response = $i->draftNews($title);
        $i->comment('Created a draft');
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected from the create news page when draft is auto submitted.');
        $i->assertRegExp($return_page_url_regex_pattern, $redirect_history[0], 'I expect that we get redirected to the main index page.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        $title = 'Smoke Test News Title Moderate';
        $response = $i->draftNews($title);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        #WHEN
        preg_match($capture_new_news_id, $redirect_history[0],$news_id_matches);
        $response = $i->createNewsToModerate($title, $news_id_matches[1]);
        $i->comment('Pushed draft to moderation');
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after I publish the draft to be moderated.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/news/'.$news_id_matches[1], $redirect_history[0], 'I expect that we get redirected to the news article we just pushed to moderation.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        $title = 'Smoke Test News Title Publish Form Path';
        $response = $i->draftNews($title);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        #WHEN
        preg_match($capture_new_news_id, $redirect_history[0],$news_id_matches);
        $response = $i->publishNewsForm($title, $news_id_matches[1]);
        $i->comment('Publish Draft through update');
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after I publish the draft directly.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/news/'.$news_id_matches[1], $redirect_history[0], 'I expect that we get redirected to the news article we just published the draft directly.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        $title = 'Smoke Test News Title Publish Moderate Path';
        $response = $i->draftNews($title);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        #WHEN
        preg_match($capture_new_news_id, $redirect_history[0],$news_id_matches);
        $response = $i->publishModeratedNewsByID($news_id_matches[1]);
        $i->comment('Publish Draft with the ID');
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after I publish via moderate.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/news/'.$news_id_matches[1], $redirect_history[0], 'I expect that we get redirected to the news article we just published via moderate.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        $title = 'Smoke Test News Title Reject By ID';
        $response = $i->draftNews($title);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        #WHEN
        preg_match($capture_new_news_id, $redirect_history[0],$news_id_matches);

        $i->createNewsToModerate($title, $news_id_matches[1]);
        $response = $i->rejectNewsByID($news_id_matches[1]);
        $i->comment('Rejected a Moderation waiting News Article');
        #THEN
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get 200 after rejection of article.');

        $title = 'Smoke Test News Title Save for later';
        $response = $i->draftNews($title);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        #WHEN
        preg_match($capture_new_news_id, $redirect_history[0],$news_id_matches);
        $i->publishModeratedNewsByID($news_id_matches[1]);
        $response = $i->saveNewsByIDforLater($news_id_matches[1]);
        $i->comment('Saved news article for later');
        #THEN
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after save news for later.');

        $title = 'Smoke Test News Title Delete, I should not exist';
        $response = $i->draftNews($title);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        #WHEN
        preg_match($capture_new_news_id, $redirect_history[0],$news_id_matches);
        $i->publishModeratedNewsByID($news_id_matches[1]);
        $response = $i->deleteNewsByID($news_id_matches[1]);
        $i->comment('Deleted a News Article');
        #THEN
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after delete news executed on "'.$title.'".');
    }

    /**
     *
     */
    public function SmokeHomepagePOST(IntegrationTester $i){
        #GIVEN
        $i->loginAdmin(); #we logged in as alaress
        $homepage_title='Testing Home Page for Homepage Smoke';
        $student = $i->getUser('drhodes');
        $teacher = $i->getUser('zsmith');
        $admin = $i->getUser('alaress');

        #WHEN
        $response = $i->createHomepage($homepage_title);
        $homePageId=$i->getFirstHomepageIdByTitle($homepage_title);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        $i->comment('Create homepage :'.$homePageId);

        #THEN
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected after I create a homepage.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/', $redirect_history[0], 'I expect that we get redirected to index after homepage is created.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $response = $i->addUsersToHomepageGroup($homePageId, [$student->getId(), $admin->getId(), $teacher->getId()]);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        $i->comment('Add users to homepage ID:'.$homePageId);

        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after we add users.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/group/modify/'.$homePageId, $redirect_history[0], 'I expect that we get redirected to the homepage modify group section after we add users.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $response = $i->setHomepageGroupUserPermission($homePageId, [$student->getId()=>1, $teacher->getId()=>2, $admin->getId()=>3]);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        $i->comment('Change permission for group users on homepage ID:'.$homePageId);

        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after edit permissions of users.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/group/modify/'.$homePageId, $redirect_history[0], 'I expect that we get redirected to the homepage modify group section after edit permissions of users.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $text_component_class=$i->getComponentByClass('Component_Homepage_TextBoxController');
        $text='<p>This is test Text</p>';
        $response = $i->addComponentToHomepage($text_component_class->getId(), $homePageId, IntegrationTester::HOMEPAGE_COMPONENT_POSITION['top']);
        $i->comment('Added the Text component to Homepage.');

        #THEN
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after text component creates.');

        #WHEN
        $homepage_text_component = $i->getComponentOnHomepage($homePageId, $text_component_class, 'top');
        $heading='Heading Component Test - Delete me';
        $i->addTextBoxToTextComponent($homePageId, $homepage_text_component, $text, $heading);
        $response = $i->deleteTextBoxToTextComponent($homePageId, $heading);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        $i->comment('Delete a text input we freshly created.');

        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after I delete text input.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/homepage/'.$homePageId, $redirect_history[0], 'I expect that we get redirected to the homepage after deleting text input in text component.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');


        #WHEN
        $heading='Heading Component Test';
        $response = $i->addTextBoxToTextComponent($homePageId, $homepage_text_component, $text, $heading);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        $i->comment('create a fresh text input into the text component');

        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after I create text input.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/homepage/'.$homePageId, $redirect_history[0], 'I expect that we get redirected to the homepage after creating text input in component.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

    }

    /**
     *
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

    /**
     *
     */
    public function SmokeAssessmentPOST(IntegrationTester $i){
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
        $response = $i->createAssessemntOnHomepage($assessment_title,$homePageId);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');

        #THEN
        $i->comment('Created homepage and added assessment to it');
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected after I create an assessment.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/homepage/'.$homePageId, $redirect_history[0], 'T expect we get redirected to the homepage our assessment is on.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId.' Edit it';
        $i->createAssessemntOnHomepage($assessment_title, $homePageId);
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title, $homePageId);
        $determine_mark_response = $i->determineMarkInvalidation($homePageId, $assessmentPageId, $assessment_title);
        $update_response = $i->updateAssessemntOnHomepage($homePageId, $assessmentPageId, $assessment_title);

        #THEN
        $i->comment('Updated Assessment');
        $i->assertEquals(200, $determine_mark_response->getStatusCode(), 'I expect that we get a 200 after mark validation on assessment update.');
        $i->assertEquals(200, $update_response->getStatusCode(), 'I expect that we get a 200 after updating assessment.');

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId.' With Rubric';
        $response = $i->createAssessementForHomepageWithRubric($assessment_title,$homePageId);

        #THEN
        $i->comment('Create Assessment with rubric for a homepage');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after creating an assessment with rubric.');

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId;
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title);
        $response = $i->submitAssessment($assessmentPageId, $student->getId());

        #THEN
        $i->comment('Submit assessment on behalf of student');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after submitting assessment on behalf of student.');

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId.' With Rubric';
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title);
        $i->submitAssessment($assessmentPageId, $student->getId());

        #THEN
        $response = $i->markAssessmentWithRubric($assessmentPageId, $admin->getId(), $student->getId());
        $i->comment('Mark assessment with rubric');

        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after marking assessement with rubric');

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId;
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title);
        $response = $i->markAssessment($assessmentPageId, $admin->getId(), $student->getId());

        #THEN
        $i->comment('Mark normal assessment');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after marking assesssment.');

        ##To Do - Submit reflection on assessment.


    }

    /**
     * @incomplete
     */
    public function SmokeCalendarPOST(IntegrationTester $i)
    {
        ;
    }

    /**
     * @incomplete
     */
    public function SmokePastoralCarePOST(IntegrationTester $i)
    {
        ;
    }

}

//PHPSESSID=dua5o1pn0jmmhp4g9iq9l09mnk; expires=Tue, 25-Jun-2019 00:14:41 GMT; Max-Age=3600; path=/; HttpOnly