<?php

namespace Tests\Acceptance\LessonPlan;

use Codeception\Util\Locator;
use Tests\Support\AcceptanceTester;
use Exception;
use Tests\Support\Page\Admin\LMS\LessonPlanTemplates\CreateLessonPlanTemplate;
use Tests\Support\Page\Admin\LMS\LessonPlanTemplates\LessonPlanTemplateList;
use Tests\Support\Page\Admin\LMS\LessonPlanTemplates\UpdateLessonPlanTemplate;
use Tests\Support\Page\Common;
use Tests\Support\Page\CourseModify;
use Tests\Support\Page\Courses\CourseList;
use Tests\Support\Page\Courses\ImportCourseIntoClass;
use Tests\Support\Page\Folder\CreateFolder;
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
     * @throws Exception
     *
     * create a lesson plan template with a field visible to student and a field that is not visible to students
     * create  lesson plan using the lesson plan template above
     */
    public function createLessonPlan(AcceptanceTester $i): void
    {

        $courseList = new CourseList($i);
        $courseModify = new CourseModify($i);
        $importCourseIntoClass = new ImportCourseIntoClass($i);
        $templateList = new TemplateList($i);
        $common = new Common($i);
        $createLessonPlan = new CreateLessonPlan($i);
        $templateName = "Automation Template to create a lesson plan";
        $lessonPlanName = 'Lesson Plan Automation Test 1';
        $folderName = 'Test Class Homepage ' . $i->getUuid();

        $teacher = 'Jack Coles';
        $student = 'Eden Reed';

        $i->amGoingTo('create a homepage and a lesson plan template');
        $this->createLessonPlanTemplate($i, $templateName, $folderName, $teacher, $student);

        $i->amGoingTo('navigate to the  Year 9 English course modify page');
        $i->amOnPage($courseModify->getURL(6));
        $i->amGoingTo('click the arrow down for Romeo and Juliet unit');
        $courseModify->clickUnitArrow('15');
        $i->amGoingTo('click New Lesson PLan button');
        $courseModify->clickNewLessonPlanButton();
        $i->waitForText("Create Lesson Plan", 5);
        $lessonPlanTemplateId = $i->grabFromDatabase('lesson_plan_template','id',['deleted_at' => null,'title like'=>$templateName]);
        $templateList->clickTemplateLink($lessonPlanTemplateId);
        $i->wait(2);
        $common->clickSubmitButton();
        $createLessonPlan->typeTitle($lessonPlanName);
        $createLessonPlan->clickCreateButton();
        $i->canSee('Modify Course');
        $i->canSee($lessonPlanName);



        
        $i->amGoingTo('navigate to course list and click the 3-dot menu for Year 9 English course in the' .$folderName);
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
        $folder_id = $i->grabFromDatabase('folder', 'id', ['name' => $folderName,'deleted_at' => null]);
        $i->amOnPage('/homepage/' . $folder_id);
        $i->canSee('Romeo and Juliet');
        $i->canSee($lessonPlanName);

    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * check if the teacher can view all the lesson plan fields
     * check if the student is able to view the visible to student fields
     * check if the students cannot see the not visible to student fields
     */
//    public function lessonPlanFiledsVisibility(AcceptanceTester $i): void
//    {
//
//    }

    /**
     *
     * covenience function to creaate a homepage and a lesson plan template
     */

    private function createLessonPlanTemplate (AcceptanceTester $i, string $templateName, string $folderName, string $student, string $teacher): void
    {
        $templates = new CreateLessonPlanTemplate($i);
        $templateList = new LessonPlanTemplateList($i);


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
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resource/folder/0/create");
        $i->selectOption("template", "Blank");
        $i->fillField(['name' => 'name'], $folderName);
        $i->click(['css' => 'label[class=radio-label][for=groupWrite1]']);
        $i->click(['css' => 'label[class=radio-label][for=groupWrite2]']);
        $i->click(['css' => 'label[class=radio-label][for=groupRead3]']);
        $i->click(['css' => 'label[class=radio-label][for=groupRead4]']);
        $i->click(['css' => 'label[class=radio-label][for=groupRead5]']);
        $i->click(['css' => 'input[type=submit]']);

        $i->amGoingTo('Navigate to the new folder, store its location for later tests');
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee($folderName);
        $i->click($folderName);
        $i->waitForText("Homepage", 5);
        $i->click("Homepage");

        $i->amGoingTo('Add few members into the homepage');
        $i->click('a#all-actions.icon-more');
        $i->clickWithLeftButton('#tab-control > div > div > ul > li a.icon-group');
        $i->waitForText("Manage Group", 5);
        $i->clickWithLeftButton('#search-text');
        $i->fillField('input#search-text', $student);
        $i->checkOption('button.search-result input');
        $i->click('Add Members');
        sleep(1);
        $i->clickWithLeftButton('#search-text');
        $i->fillField('input#search-text', $teacher);
        $i->checkOption('button.search-result input');
        $i->click('Add Members');
        sleep(1);
        $i->selectOption('tr.VueTables__row:first-child select', 'admin');
        sleep(5);


    }
}
