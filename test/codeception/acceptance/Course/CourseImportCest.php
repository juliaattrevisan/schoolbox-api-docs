<?php

declare(strict_types=1);

namespace Course;

use AcceptanceTester;
use Exception;
use Page\CourseModify;
use Page\Courses\CourseList;
use Page\Courses\ImportCourseIntoClass;
use Page\Folder\CreateFolder;

class CourseImportCest
{

    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * get logged in
     */
    public function _before(AcceptanceTester $i)
    {
        $i->logout();
        $i->loginAsAdmin();
    }


    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * navigate to /resource/folder/0/create
     * create a folder
     * navigate to /course/list
     * import  year 9 English into the homepage created above in the before function
     * export only romeo and Juliet
     * untick a quiz, due work and task before importing
     * ensure the course is presented in the homepage
     */
    public function importCourseIntoClass(AcceptanceTester $i)
    {

        $createFolder = new CreateFolder($i);
        $courseList = new CourseList($i);
        $importCourseIntoClass = new ImportCourseIntoClass($i);

        $i->amOnPage($createFolder::URL);

        $folderName = "Automation Course Import Folder1";
        $homepageTemplateOption = "Course";
        $schoolCode = "test1";

        $i->amGoingTo('create a folder by typing a name and selecting a homepage template');
        $createFolder->typeName($folderName);
        $createFolder->setHomepageTemplate($homepageTemplateOption);
        $i->amGoingTo('set write access permission for Administration and Teacher');
        $createFolder->setAccessPermission('groupWrite1');
        $createFolder->setAccessPermission('groupWrite2');
        $i->amGoingTo('set read access permission for senior student');
        $createFolder->setAccessPermission('groupRead3');
        $i->amGoingTo('set read access permission for parent');
        $createFolder->setAccessPermission('groupRead4');
        $i->amGoingTo('set read access permission for junior student');
        $createFolder->setAccessPermission('groupRead5');
        $i->amGoingTo('set read access permission for middle student');
        $createFolder->setAccessPermission('groupRead6');
        $i->amGoingTo('type a school code and click Create button');
        $createFolder->typeSchoolCode($schoolCode);
        $createFolder->clickCreateFolderButton();

        $i->amOnPage($courseList::URL);
        $i->amGoingTo('click the 3-dot menu for Year 9 English course in the course list page');
        $courseList->clickCourseDotMenu('6');
        $i->amGoingTo('click the option Import into classes from the dropdown menu');
        $courseList->clickImportIntoClass('6');
        $i->amGoingTo('type a class name, select it from the results and click the Next button');
        $importCourseIntoClass->typeClassName('Automation Course Import Folder');
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
        $folder_id = $i->grabFromDatabase('folder', 'id', ['name' => 'Automation Course Import Folder1']);
        $i->amOnPage('/homepage/' . $folder_id);
        $i->canSee('Romeo and Juliet');
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     *
     * navigate to /course/modify/6
     * copy activity into a class  for the unticked quiz, due work and quiz above
     * fill the dates fields and click export to selected class
     * ensure the assessment is presented in that class
     */
    public function copyActivityIntoClass(AcceptanceTester $i)
    {
        $courseModify = new CourseModify($i);
        $i->amGoingTo('navigate to Year 9 English Modify course page');
        $i->amOnPage($courseModify->getURL(6));
        $i->amGoingTo('click the arrow to expand Romeo and Juliet unit');
        $courseModify->clickUnitArrow('15');
        $i->amGoingTo('click the export icon for Romeo and Juliet Key Quote Activity');
        $courseModify->clickExportIcon(52);
        $i->amGoingTo('tick the checkbox');
        $i->executeJS("var clickCheckBox = document.getElementById('import-94');
        clickCheckBox.click();");
        $i->amGoingTo('click export the selected classes button');
        $i->clickWithLeftButton(['css' => 'input[type=submit]']);

        $i->amGoingTo('click the export icon for Present a scene of Romeo and Juliet');
        $courseModify->clickExportIcon(146);
        $i->amGoingTo('tick the checkbox');
        $i->executeJS("var clickCheckBox = document.getElementById('import-94');
        clickCheckBox.click();");
        $i->amGoingTo('click export the selected classes button');
        $i->clickWithLeftButton(['css' => 'input[type=submit]']);

        $i->amGoingTo('click the export icon for Post-Unit Quiz');
        $courseModify->clickExportIcon(151);
        $i->amGoingTo('tick the checkbox');
        $i->executeJS("var clickCheckBox = document.getElementById('import-94');
        clickCheckBox.click();");
        $i->amGoingTo('click export the selected classes button');
        $i->clickWithLeftButton(['css' => 'input[type=submit]']);

        $i->amGoingTo('navigate to the class homepage to check if the assessments are presented in the course outline');
        $folder_id = $i->grabFromDatabase('folder', 'id', ['name' => 'Automation Course Import Folder1']);
        $i->amOnPage('/homepage/' . $folder_id);
        $i->canSee('Romeo and Juliet Key Quote Activity');
        $i->canSee('Present a scene of Romeo and Juliet');
        $i->canSee('Post-Unit Quiz');
    }
}
