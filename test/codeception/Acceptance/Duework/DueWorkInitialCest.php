<?php

namespace Tests\Acceptance\Duework;

use Tests\Support\AcceptanceTester;
use Codeception\Util\Locator;

class DueWorkInitialCest
{
    private string $homepageUrl;
    private string $folderName;

    /**
     * @param AcceptanceTester $i
     * @return void
     */
    public function _before(AcceptanceTester $i)
    {
        $i->logout();
        $i->loginAsAdmin();
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws \Exception
     */
    public function create2Assessments(AcceptanceTester $i)
    {
        $teacher = 'Jack Coles';
        $student = 'Eden Reed';
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resource/folder/0/create");
        $i->selectOption("template", "Blank");
        $this->folderName = 'Test Class Homepage ' . $i->getUuid();
        $i->fillField(['name' => 'name'], $this->folderName);
        $i->click(['css' => 'label[class=radio-label][for=groupWrite1]']);
        $i->click(['css' => 'label[class=radio-label][for=groupWrite2]']);
        $i->click(['css' => 'label[class=radio-label][for=groupRead3]']);
        $i->click(['css' => 'label[class=radio-label][for=groupRead4]']);
        $i->click(['css' => 'label[class=radio-label][for=groupRead5]']);
        $i->click(['css' => 'input[type=submit]']);

        // Navigate to the new folder, store its location for later tests
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee($this->folderName);
        $i->click($this->folderName);
        $i->waitForText("Homepage", 5);
        $i->click("Homepage");
        $this->homepageUrl = $i->getCurrentUrl();

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
        $i->amOnUrl($this->homepageUrl);
        $i->click(['css' => 'label[for=component-lock-toggle]']);
        $i->canSeeElement(['css' => 'span[class=icon-unlocked]']);
        $i->click(['css' => 'a#add-component']);
        sleep(1);
        $i->canSeeElement(['css' => 'div#tabs-1']);
        $i->click(['css' => 'a[href=\#tabs-sub-3]']);
        $i->canSeeElement(['css' => 'ul#tabs-sub-3']);
        //add due work component into the homepage
        $i->click("//*[@id='tabs-sub-3']/li[7]");
        sleep(5);
        $i->canSeeElement(['css' => 'div[class=component-container\ Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller]']);
        $i->impersonate($teacher);
        $i->amOnUrl($this->homepageUrl);
        $i->waitForElementVisible(['css' => 'div[class=component-container\ Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller]'], 5);
        $i->click('Create due work');
        $i->waitForText("Create Due Work", 5);
        $i->fillField("title", "Test Due Work");
        $i->fillCkEditorById('assessment-description', 'We need more fish.');
        $i->selectOption('acceptOnline', '1');
        $i->fillField('weight', '5');
        $i->click('select#markType');
        $i->selectOption('select[name=markType]', 'Percentage');
        $i->click('input[type=submit][value=Create]');
        $i->waitForElementVisible(['css' => 'div[class=component-container\ Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller]'], 5);
        $i->canSee('Test Due Work');
        $i->waitForElementVisible(['css' => 'div[class=component-container\ Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller]'], 5);
        $createButton = Locator::contains('section a.button', 'Create Due Work');
        $i->click($createButton);
        $i->waitForText("Create Due Work", 5);
        $i->fillField("title", "Test Due Work2");
        $i->fillCkEditorById('assessment-description', 'We need more fish.');
        $i->selectOption('acceptOnline', '1');
        $i->fillField('weight', '5');
        $i->click('input[type=submit][value=Create]');
        $i->waitForElementVisible(['css' => 'div[class=component-container\ Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller]'], 5);
        $i->canSee('Test Due Work2');
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws \Exception
     */
    public function deleteAssessment(AcceptanceTester $i)
    {
        $teacher = 'Jack Coles';
        $i->impersonate($teacher);
        $i->amOnUrl($this->homepageUrl);
        $i->click('Test Due Work2');
        $currentURL = $i->getCurrentUrl();
        $assessmentID = substr(strrchr(substr($currentURL, 0, strrpos($currentURL, '/')), '/'), 1);
        $i->amOnUrl($this->homepageUrl);
        $i->click('#component-assessment-' . $assessmentID . ' > nav:nth-child(2) > a:nth-child(1)');
        $i->click('#component-assessment-' . $assessmentID . ' a.icon-delete');
        $i->click('div.reveal-modal.open a.button.submit');
    }


    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws \Exception
     */
    public function viewClassWork(AcceptanceTester $i)
    {
        $student = 'Eden Reed';
        $i->impersonate($student);
        $i->amOnUrl($this->homepageUrl);
        $i->canSee('Test Due Work');
        $i->click('Test Due Work');
        $i->canSee('Test Due Work');
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws \Exception
     */
    public function submitWorkInPersonByStudent(AcceptanceTester $i)
    {
        $student = 'Eden Reed';
        $i->impersonate($student);
        $i->amOnUrl($this->homepageUrl);
        $i->waitForText('Test Due Work', 5);
        $i->click('Test Due Work');
        $i->waitForText('Test Due Work', 5);
        $i->click('a.submit.button');
        $i->click('label.checkbox-label[for=editor-submittedInPerson]');
        sleep(2);
        $i->click('input[type=submit][value=Submit\ for\ assessment]');
        $i->canSee('Submitted in person by Ms Eden Reed');
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws \Exception
     */
    public function viewSubmission(AcceptanceTester $i)
    {
        $teacher = 'Jack Coles';
        $i->impersonate($teacher);
        $i->amOnUrl($this->homepageUrl);
        $i->click('Test Due Work');
        $currentURL = $i->getCurrentUrl();
        $assessmentID = substr(strrchr(substr($currentURL, 0, strrpos($currentURL, '/')), '/'), 1);
        $i->amOnUrl($this->homepageUrl);
        $i->click('#component-assessment-' . $assessmentID . ' > nav:nth-child(2) > a:nth-child(1)');
        $i->click('#component-assessment-' . $assessmentID . ' a.icon-progress');
        sleep(1);
        $i->click('ul.information-list li div.small-12.column div.row.actions-small-2 nav a.icon-more');
        $i->click('a.icon-task');
        $i->canSee('Grading Test Due Work');
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws \Exception
     */
    public function markSubmission(AcceptanceTester $i)
    {
        $teacher = 'Jack Coles';
        $i->impersonate($teacher);
        $i->amOnUrl($this->homepageUrl);
        $i->click('Test Due Work');
        $currentURL = $i->getCurrentUrl();
        $assessmentID = substr(strrchr(substr($currentURL, 0, strrpos($currentURL, '/')), '/'), 1);
        $i->amOnUrl($this->homepageUrl);
        $i->click('#component-assessment-' . $assessmentID . ' > nav:nth-child(2) > a:nth-child(1)');
        $i->click('#component-assessment-' . $assessmentID . ' a.icon-progress');
        $i->click('ul.information-list li div.small-12.column div.row.actions-small-2 nav a.icon-more');
        $i->click('a.icon-task');
        $i->fillField('textarea[name=comment]', 'Not bad, but you should work on your spelling.');
        $i->fillField('input[name=mark]', '65');
        $i->wait(3);
        $studentWeAreMarkingUrl = $i->getCurrentUrl();
        $i->click('a.button[data-draft=publish]');
        $i->waitForElementNotVisible('Eden');
        $i->amOnUrl($studentWeAreMarkingUrl);
        $i->canSee('Reviewed');
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws \Exception
     */
    public function viewMarkedSubmission(AcceptanceTester $i)
    {
        $student = 'Eden Reed';
        $i->impersonate($student);
        $i->amOnPage('learning/grades/6');
        $linkMarksClassTest = Locator::contains('a.flex-grade.link-row', $this->folderName);
        $i->click($linkMarksClassTest);
        $i->waitForText('Test Due Work', 5);
        $linkTestDueWork = Locator::contains('a', 'Test Due Work');
        $i->scrollTo($linkTestDueWork);
        sleep(2);
        $i->canSee('Test Due Work');
        $i->canSee('65 %');
        $i->click($linkTestDueWork);
        $i->canSee('Feedback by Mr Jack Coles');
        $i->canSee('Submitted in person by Ms Eden Reed');
        $i->canSee('65 %');
    }


    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws \Exception
     */
    public function submitWorkTypingSomethingByTeacher(AcceptanceTester $i)
    {
        $teacher = 'Jack Coles';
        $i->impersonate($teacher);
        $i->amOnUrl($this->homepageUrl);
        $i->waitForText('Test Due Work', 5);
        $i->click('Test Due Work');
        $i->waitForText('Test Due Work', 5);
        $i->click('a.submit.button');
        $i->fillCkEditorById('editor-body', 'This is a due work submission by typing something!');
        sleep(2);
        $i->click('input[type=submit][value=Submit\ for\ assessment]');
        $i->canSee('Submission by Mr Jack Coles');
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws \Exception
     */
    public function submitWorkTypingSomethingByAdmin(AcceptanceTester $i)
    {
        $i->amOnUrl($this->homepageUrl);
        $i->waitForText('Test Due Work', 5);
        $i->click('Test Due Work');
        $i->waitForText('Test Due Work', 5);
        $i->click('a.submit.button');
        $i->fillCkEditorById('editor-body', 'This is a due work submission by typing something!');
        sleep(2);
        $i->click('input[type=submit][value=Submit\ for\ assessment]');
        $i->canSee('Submission by Schoolbox Admin');
    }
}
