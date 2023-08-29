<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Tests\Acceptance\Task;

use Tests\Support\AcceptanceTester;
use Exception;
use Tests\Support\Page\Duework\AssessmentForm;
use Tests\Support\Page\Duework\AssessmentPage;
use Tests\Support\Page\Folder\CreateFolder;
use Tests\Support\Page\Group\ManageGroup;
use Tests\Support\Page\Homepage;
use Tests\Support\Page\Resources\ResourcesMyFilesPicker;

class ParticipationPlanCest
{
    /**
     *
     * creates a task with participation and learning plan
     * check as admin that the learning plan is presented in the assessment modify page
     * impersonates a student in the learning plan and check the proper due date is presented
     * impersonates a student not in the learning plan and check the proper due date is presented
     *
     * @param AcceptanceTester $i
     * @return void
     * @throws Exception
     */
    public function _before(AcceptanceTester $i)
    {
        $i->logout();
        $i->loginAsAdmin();
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws Exception
     */
    public function createTask(AcceptanceTester $i): void
    {
        $teacher = 'Jack Coles';
        $student1 = 'Eden Reed';
        $student2 = 'Jana Reed';

        $createFolder = new CreateFolder($i);
        $resourcesFile = new ResourcesMyFilesPicker($i);
        $manageGroup = new ManageGroup($i);
        $homepage = new Homepage($i);
        $dueworkForm = new AssessmentForm($i);
        $assessmentPage = new AssessmentPage($i);
        $folderName = 'Participation and Learning Plan Homepage';
        $homepageTemplateOption = "Blank";

        $i->amGoingTo('create a folder by typing a name and selecting a homepage template');
        $i->amOnPage($createFolder::URL);
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
        $createFolder->clickCreateFolderButton();

        $i->amGoingTo("Navigate to the new folder store its location for later tests");
        $i->amOnPage($resourcesFile::URL);
        $i->canSee($folderName);
        $i->click($folderName);
        $i->waitForText("Homepage", 5);
        $i->click("Homepage");
        $homepageUrl = $i->getCurrentUrl();

        $i->amGoingTo('Add Jack Coles, Eden Reed and Jana Reed into the group via manage group page');
        $i->click('a#all-actions.icon-more');
        $i->clickWithLeftButton('#tab-control > div > div > ul > li a.icon-group');
        $manageGroup->search($teacher);
        $manageGroup->selectUser(22);
        $manageGroup->clickFromRadioButton('role');
        $i->click('Add Members');
        $i->amGoingTo('Making Jack Coles the group admin via database');
        $folderId = $i->grabFromDatabase('folder', 'id', ['deleted_at' => null,'name like' => $folderName]);
        $i->updateInDatabase('userAccess', ['type' => '3'], ['sid' => $folderId]);
        $i->amGoingTo('Add Eden Reed into the group');
        $manageGroup->search($student1);
        $manageGroup->selectUser(6);
        $manageGroup->clickFromRadioButton('role');
        $i->click('Add Members');
        $i->amGoingTo('Add Jana Reed into the group');
        $manageGroup->clickFromRadioButton('name');
        $manageGroup->search($student2);
        $manageGroup->selectUser(25);
        $manageGroup->clickFromRadioButton('role');
        $i->click('Add Members');

        $i->amGoingTo("Add the task component in the homepage");
        $i->amOnUrl($homepageUrl);
        $homepage->addComponent(Homepage::ELEMENT_ADD_COMPONENT_SECTION_LEARNING, 'Tasks');

        sleep(5);
        $i->canSeeElement(['css' => 'div[class=component-container\ Schoolbox_Learning_Assessment_Task_Component_Homepage_Controller]']);

        $i->amGoingTo("Create a task with a learning plan");
        $i->click('Create a task');
        $i->waitForText("Create Task", 5);
        $dateLearningPlan = \Alaress_Date_Format::now()->setDate(2030, 10, 03)->setTime(10, 0, 0);
        $dueDate = \Alaress_Date_Format::now()->setDate(2030, 8, 03)->setTime(10, 0, 0);
        $dueworkForm->setWorkType(AssessmentForm::WORKTYPE_ASSESSMENT_TASK)
                    ->setTitle('Task Participation and Learning Plan')
                    ->setPublishDate(\Alaress_Date_Format::now()->setTime(0, 0, 0))
                    ->setDueDate($dueDate)
                    ->createLearningPlan(6, 'Test learning plan', $dateLearningPlan)
                    ->create();
        $i->waitForText('Tasks');

        $i->amGoingTo("As admin check if the learning plan is presented under Participation and Learning Plans");
        $i->click('Task Participation and Learning Plan');
        $assessmentPage->clickModify();
        $i->canSee('Test learning plan');
        $i->canSee('(no customisations)');

        $i->amGoingTo("Get logged in as Eden Reed and ensure the due date is the same as per the learning plan");
        $i->impersonateViaModifyUserPage('Ms Eden Reed');
        $i->amOnUrl($homepageUrl);
        $i->waitForText('Tasks');
        $i->canSee('Oct 3, 2030');

        $i->amGoingTo("Get logged in as Jana Reed and ensure the due date is not from the learning plan");
        $i->impersonateViaModifyUserPage('Ms Jana Reed');
        $i->amOnUrl($homepageUrl);
        $i->waitForText('Tasks');
        $i->canSee('Aug 3, 2030');
    }
}
