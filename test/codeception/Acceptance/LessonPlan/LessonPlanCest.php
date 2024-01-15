<?php

namespace Tests\Acceptance\LessonPlan;

use Tests\Support\AcceptanceTester;
use Exception;
use Tests\Support\Page\Admin\LMS\LessonPlanTemplates\CreateLessonPlanTemplate;
use Tests\Support\Page\Admin\LMS\LessonPlanTemplates\LessonPlanTemplateList;
use Tests\Support\Page\Common;
use Tests\Support\Page\CourseModify;
use Tests\Support\Page\Courses\CourseList;
use Tests\Support\Page\Courses\ImportCourseIntoClass;
use Tests\Support\Page\Folder\CreateFolder;
use Tests\Support\Page\Group\ManageGroup;
use Tests\Support\Page\LessonPlan\CreateLessonPlan;
use Tests\Support\Page\LessonPlan\TemplateList;

class LessonPlanCest
{
    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * get logged in
     * navigate to  /adminv2/lesson-plan/template/create
     */
    public function _before(AcceptanceTester $i): void
    {
        $i->logout();
        $i->loginAsAdmin();
        $templateList = new LessonPlanTemplateList($i);
        $i->amOnPage($templateList::URL);
    }

    /**
     * @param AcceptanceTester $i
     */

    /**
     * Soft deleting data to not impact the tests
     *
     * @param AcceptanceTester $i
     */
    public function beforeSuite(AcceptanceTester $i): void
    {
        $i->amGoingTo("update delete_at for the folder and lesson plan table so it does not impact the tests");
        $i->updateInDatabase('lesson_plan_template_field', ['deleted_at' => "2022-01-15 11:22:34"], ['title like' => "Automation Field%"]);
        $i->updateInDatabase('lesson_plan_template', ['deleted_at' => "2022-01-15 11:22:34"], ['title like' => "Automation Template%"]);
        $i->updateInDatabase('lesson_plan_field', ['deleted_at' => "2022-01-15 11:22:34"], ['title like' => "Automation Field%"]);
        $i->updateInDatabase('lesson_plan', ['deleted_at' => "2022-01-15 11:22:34"], ['created_by' => '1']);
        $i->updateInDatabase('assessment', ['deleted_at' => "2022-01-15 11:22:34"], ['title like' => "Lesson Plan Automation Test%"]);
        $i->updateInDatabase('folder', ['deleted_at' => "2022-01-15 11:22:34"], ['name like' => "Test Class Homepage Lesson Plan%"]);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * create a lesson plan template with a field visible to student and a field that is not visible to students
     * create  lesson plan using the lesson plan template above
     */
    public function createLessonPlan(AcceptanceTester $i): void
    {

        $courseModify = new CourseModify($i);
        $templateList = new TemplateList($i);
        $common = new Common($i);
        $createLessonPlan = new CreateLessonPlan($i);
        $templateName = "Automation Template to create a lesson plan";
        $lessonPlanName = 'Lesson Plan Automation Test 1';
        $folderName = 'Test Class Homepage Lesson Plan';

        $teacher = 'Jack Coles';
        $student = 'Eden Reed';

        $i->amGoingTo('create a homepage and a lesson plan template');
        $this->createLessonPlanTemplate($i, $templateName, $folderName, $teacher, $student);

        $i->amGoingTo('navigate to the  Year 9 English course modify page');
        $i->amOnPage($courseModify->getURL(6));
        $i->amGoingTo('click the arrow down for Romeo and Juliet unit');
        $courseModify->clickUnitArrow('15');
        $i->amGoingTo('click New Lesson PLan button to create a lesson plan');
        $courseModify->clickNewLessonPlanButton();
        $i->waitForText("Create Lesson Plan", 5);
        $lessonPlanTemplateId = $i->grabFromDatabase('lesson_plan_template', 'id', ['deleted_at' => null, 'title like' => $templateName]);
        $templateList->clickTemplateLink($lessonPlanTemplateId);
        $i->wait(2);
        $common->clickSubmitButton();
        $createLessonPlan->typeTitle($lessonPlanName);
        $createLessonPlan->clickCreateButton();
        //this if-clause is due to issue SA-16429
        while ($i->tryToSeeElement('input[value="Working..."]')) {
            $i->wait(3);
        }
        $i->amGoingTo('check if the lesson plan is presented');
        $i->canSee('link existing activities');
        $i->canSee($lessonPlanName);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     * @depends createLessonPlan
     *
     * importing the course with a lesson plan  into a homepage
     * check if the teacher can view all the lesson plan fields
     * check if the student is able to view the visible to student fields
     * check if the students cannot see the not visible to student fields
     */
    public function lessonPlanFiledsVisibility(AcceptanceTester $i): void
    {

        $courseList = new CourseList($i);
        $importCourseIntoClass = new ImportCourseIntoClass($i);
        $lessonPlanName = 'Lesson Plan Automation Test 1';
        $folderName = 'Test Class Homepage Lesson Plan';

        $teacher = 'Mr Jack Coles';
        $student = 'Ms Eden Reed';

        $i->amGoingTo('navigate to course list and click the 3-dot menu for Year 9 English course to import into a class page' . $folderName);
        $i->amOnPage($courseList::URL);
        $i->canSee('Course List');
        $courseList->clickCourseDotMenu('6');
        $i->amGoingTo('click the option Import into classes from the dropdown menu');
        $courseList->clickImportIntoClass('6');
        $i->amGoingTo('type a class name, select it from the results and click the Next button');
        $importCourseIntoClass->typeClassName($folderName);
        $importCourseIntoClass->clickClassName();
        $importCourseIntoClass->clickSubmit('Next');
        $i->amGoingTo('tick Romeo and Juliet unit checkbox, fill open and close dates and hit Next');
        $importCourseIntoClass->tickUnitCheckbox('1');
        $importCourseIntoClass->fillOpenDate('1', \Alaress_Date_Format::now()->addDays(-2)->setTime(0, 0, 0));
        $importCourseIntoClass->insertDueDate('1', \Alaress_Date_Format::now()->addDays(30)->setTime(0, 0, 0));
        $importCourseIntoClass->clickSubmit('Next');
        $i->amGoingTo('untick Romeo and Juliet Key Quote Activity in the step 3 Coursework');
        $importCourseIntoClass->tickAssessmentCheckbox('52');
        $i->amGoingTo('untick Present a scene of Romeo and Juliet in the step 3 Coursework');
        $importCourseIntoClass->tickAssessmentCheckbox('146');
        $i->amGoingTo('untick Post-Unit Quiz in the step 3 Coursework');
        $importCourseIntoClass->tickAssessmentCheckbox('151');
        $i->amGoingTo('click button Import, navigate to the homepage and check that Romeo and Juliet unit is presented in the page');
        $importCourseIntoClass->clickSubmit('Import');

        //this if-clause is due to issue SA-16429
        while ($i->tryToSeeElement('input[value="Working..."]')) {
            $i->wait(5);
        }
        $i->amGoingTo('check if the lesson plan is presented in the homepage');
        $folder_id = $i->grabFromDatabase('folder', 'id', ['name' => $folderName, 'deleted_at' => null]);
        $i->amOnPage('/homepage/' . $folder_id);
        while (!$i->tryToSee('Romeo and Juliet')) {
            $i->wait(2);
        }
        $i->canSee($lessonPlanName);
        $i->amGoingTo('as a student view the lesson plan');
        $i->impersonateViaModifyUserPage($student);
        $i->amOnPage('/homepage/' . $folder_id);
        $i->click($lessonPlanName);
        $i->amGoingTo('check if student can view the visible field and cannot view the not visible fields to students');
        $i->canSee('Automation Field 1');
        $i->cantSee('Automation Field 2 not visible to students');

        $i->amGoingTo('as a teacher view the lesson plan');
        $i->impersonateViaModifyUserPage($teacher);
        $i->amOnPage('/homepage/' . $folder_id);
        $i->click($lessonPlanName);
        $i->amGoingTo('check if teacher can view all the lesson plan fields');
        $i->canSee('Automation Field 1');
        $i->canSee('Automation Field 2 not visible to students');
    }

    /**
     *
     * covenience function to create a homepage and a lesson plan template
     * @throws Exception
     */
    private function createLessonPlanTemplate(AcceptanceTester $i, string $templateName, string $folderName, string $teacher, string $student): void
    {
        $templates = new CreateLessonPlanTemplate($i);
        $templateList = new LessonPlanTemplateList($i);
        $createFolder = new createFolder($i);
        $manageGroup = new ManageGroup($i);

        $i->amGoingTo('Click add a lesson plan template button in the lesson plan template lists page');
        $templateList->clickAddLessonPlanTemplateButton();
        $i->amGoingTo("Type a template name,add a field name and body and save it");
        $templates->typeName($templateName);
        $templates->typeFieldTile(1, 'Automation Field 1');
        $templates->fillBody(1, 'Automation Body 1');
        $i->amGoingTo('Add a new field, type a filed title and body and save it');
        $templates->clickAddNewFieldButton();
        $templates->typeFieldTile(2, 'Automation Field 2 not visible to students');
        $templates->fillBody(2, 'Automation Body 2 not visible to students');
        $templates->clickCheckboxVisibleStudentsCreatingTemplate(2);
        $templates->clickSaveButton();
        $i->amGoingTo("Check the lesson pan template is presented in the template list");
        $i->canSee($templateName);

        $i->amGoingTo('Create a homepage');
        $i->amOnPage($createFolder::URL);
        $createFolder->waitCreateFolderTitleToBePresented();
        $homepageTemplateOption = "Blank";
        $i->amGoingTo("type a folder name, select a template option and set access permissions");
        $createFolder->typeName($folderName);
        $createFolder->setHomepageTemplate($homepageTemplateOption);
        $createFolder->setAccessPermission('groupWrite1');
        $createFolder->setAccessPermission('groupWrite2');
        $createFolder->setAccessPermission('groupRead3');
        $createFolder->setAccessPermission('groupRead4');
        $createFolder->setAccessPermission('groupRead5');
        $createFolder->setAccessPermission('groupRead6');
        $createFolder->clickCreateFolderButton();
        $i->amGoingTo("navigate to the resources page and check the new homepage folder is presented");
        $i->amOnPage($createFolder::URL_RESOURCES);
        $i->canSee($folderName);

        $i->amGoingTo('Navigate to the homepage');
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee($folderName);
        $i->click($folderName);
        $i->waitForText("Homepage", 5);
        $i->click("Homepage");
        $i->amGoingTo('Add Jack Coles into the group via manage group page');
        $i->clickElementInWebComponent('[data-test=sbx-pop-out-tab-control]', '[data-test=sbx-pop-out-tab-control-button]');
        $i->clickWithLeftButton('#tab-control > div > div > ul > li a.icon-group');
        $manageGroup->search($teacher);
        $manageGroup->selectUser(22);
        $manageGroup->clickFromRadioButton('role');
        $i->click('Add Members');
        $i->amGoingTo('Making Jack Coles the group admin via database');
        $folderId = $i->grabFromDatabase('folder', 'id', ['deleted_at' => null, 'name like' => $folderName]);
        $i->updateInDatabase('userAccess', ['type' => '3'], ['sid' => $folderId]);
        $i->amGoingTo('Add Eden Reed into the group');
        $manageGroup->search($student);
        $manageGroup->selectUser(6);
        $manageGroup->clickFromRadioButton('role');
        $i->click('Add Members');
    }
}
