<?php

use Codeception\Util\Locator;

class DueWorkInitialCest
{
    public $home_page_url;

    public function create2Assessments(AcceptanceTester $i)
    {
        $i->login('alaress', 'alaress');
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resource/folder/create/0");
        $i->selectOption("template", "Blank");
        $i->fillField(['name'=>'name'], 'Test Class Homepage');
        $i->click(['css'=>'label[class=radio-label][for=groupWrite1]']);
        $i->click(['css'=>'label[class=radio-label][for=groupWrite2]']);
        $i->click(['css'=>'label[class=radio-label][for=groupRead3]']);
        $i->click(['css'=>'label[class=radio-label][for=groupRead4]']);
        $i->click(['css'=>'label[class=radio-label][for=groupRead5]']);
        $i->click(['css'=>'input[type=submit]']);
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee("Test Class Homepage");
        $i->click("Test Class Homepage");
        $i->waitForText("Homepage", 5);
        $i->click("Homepage");
        $this->home_page_url=$i->getCurrentUrl();
        $i->click('a#all-actions.icon-more');
        $i->click('ul > li a.icon-group');
        $i->waitForText("Manage Group", 5);
        $i->click('Add Members');
        $i->clickWithLeftButton('div.selectize-input.items.not-full');
        $i->pressKey('div.selectize-input.items.not-full input', 'David Rhodes');
        sleep(1);
        $i->pressKey('div.selectize-input.items.not-full input', WebDriverKeys::ENTER);
        $i->clickWithLeftButton('div.selectize-input.items.not-full');
        $i->pressKey('div.selectize-input.items.not-full input', 'Zane Smith');
        sleep(1);
        $i->pressKey('div.selectize-input.items.not-full input', WebDriverKeys::ENTER);
        $i->click('input[type=submit][value=Add\ Users]');
        $i->click('label.input-toggle[for=accessLevel-151-3]');
        sleep(5);
        $i->amOnUrl($this->home_page_url);
        $i->click(['css'=>'label[for=component-lock-toggle]']);
        $i->canSeeElement(['css'=>'span[class=icon-unlocked]']);
        $i->click(['css'=>'a#add-component']);
        sleep(1);
        $i->canSeeElement(['css'=>'div#tabs-1']);
        $i->click(['css'=>'a[href=\#tabs-sub-3]']);
        $i->canSeeElement(['css'=>'ul#tabs-sub-3']);
        $i->dragAndDrop("//*[@id='tabs-sub-3']/li[8]","//div[@data-position='top']");
        sleep(5);
        $i->canSeeElement(['css'=>'div[class=component-container\ Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller]']);
        $i->impersonate('Zane Smith');
        $i->amOnUrl($this->home_page_url);
        $i->waitForElementVisible(['css'=>'div[class=component-container\ Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller]'], 5);
        $i->click('Create due work');
        $i->waitForText("Create Due Work", 5);
        $i->fillField("title", "Test Due Work");
        $i->fillCkEditorById('assessment-description', 'We need more fish.');
        $i->selectOption('acceptOnline', '1');
        $i->fillField('weight', '5');
        $i->click('input[type=submit][value=Create]');
        $i->waitForElementVisible(['css'=>'div[class=component-container\ Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller]'], 5);
        $i->canSee('Test Due Work');
        $i->waitForElementVisible(['css'=>'div[class=component-container\ Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller]'], 5);
        $createButton = Locator::contains('section a.button', 'Create Due Work');
        $i->click($createButton);
        $i->waitForText("Create Due Work", 5);
        $i->fillField("title", "Test Due Work2");
        $i->fillCkEditorById('assessment-description', 'We need more fish.');
        $i->selectOption('acceptOnline', '1');
        $i->fillField('weight', '5');
        $i->click('input[type=submit][value=Create]');
        $i->waitForElementVisible(['css'=>'div[class=component-container\ Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller]'], 5);
        $i->canSee('Test Due Work2');
    }

    public function deleteAssessment(AcceptanceTester $i)
    {
        $i->login('alaress', 'alaress');
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee("Test Class Homepage");
        $i->click("Test Class Homepage");
        $i->waitForText("Homepage", 5);
        $i->click("Homepage");
        $this->home_page_url=$i->getCurrentUrl();
        $i->impersonate('Zane Smith');
        $i->amOnUrl($this->home_page_url);
        $i->click('Test Due Work2');
        $currentURL=$i->getCurrentUrl();
        $assessmentID=substr(strrchr(substr($currentURL, 0, strrpos($currentURL, '/')), '/'), 1);
        $i->amOnUrl($this->home_page_url);
        $i->click('#component-assessment-'.$assessmentID.' > nav:nth-child(2) > a:nth-child(1)');
        $i->click('#component-assessment-'.$assessmentID.' a.icon-delete');
        $i->click('div.reveal-modal.open a.button.submit');
    }


    public function viewClassWork(AcceptanceTester $i)
    {
        $i->login('alaress', 'alaress');
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee("Test Class Homepage");
        $i->click("Test Class Homepage");
        $i->waitForText("Homepage", 5);
        $i->click("Homepage");
        $this->home_page_url=$i->getCurrentUrl();
        $i->impersonate('David Rhodes');
        $i->amOnUrl($this->home_page_url);
        $i->canSee('Test Due Work');
        $i->click('Test Due Work');
        $i->canSee('Test Due Work');
    }

    public function submitWork(AcceptanceTester $i)
    {
        $i->login('alaress', 'alaress');
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee("Test Class Homepage");
        $i->click("Test Class Homepage");
        $i->waitForText("Homepage", 5);
        $i->click("Homepage");
        $this->home_page_url=$i->getCurrentUrl();
        $i->impersonate('David Rhodes');
        $i->amOnUrl($this->home_page_url);
        $i->waitForText('Test Due Work', 5);
        $i->click('Test Due Work');
        $i->waitForText('Test Due Work', 5);
        $i->click('a.submit.button.expand');
        $i->click('label.checkbox-label[for=editor-submittedInPerson]');
        sleep(2);
        $i->click('input[type=submit][value=Submit\ for\ assessment]');
        $i->canSee('Submission by David Rhodes');
    }

    public function viewSubmission(AcceptanceTester $i)
    {
        $i->login('alaress', 'alaress');
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee("Test Class Homepage");
        $i->click("Test Class Homepage");
        $i->waitForText("Homepage", 5);
        $i->click("Homepage");
        $this->home_page_url=$i->getCurrentUrl();
        $i->impersonate('Zane Smith');
        $i->amOnUrl($this->home_page_url);
        $i->click('Test Due Work');
        $currentURL=$i->getCurrentUrl();
        $assessmentID=substr(strrchr(substr($currentURL, 0, strrpos($currentURL, '/')), '/'), 1);
        $i->amOnUrl($this->home_page_url);
        $i->click('#component-assessment-'.$assessmentID.' > nav:nth-child(2) > a:nth-child(1)');
        $i->click('#component-assessment-'.$assessmentID.' a.icon-progress');
        $i->click('div.row.actions-small-2 a.icon-more');
        $i->click('div.row.actions-small-2 a.icon-task');
        $i->canSee('Grading Test Due Work');
    }

    public function markSubmission(AcceptanceTester $i)
    {
        $i->login('alaress', 'alaress');
        $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
        $i->canSee("Test Class Homepage");
        $i->click("Test Class Homepage");
        $i->waitForText("Homepage", 5);
        $i->click("Homepage");
        $this->home_page_url=$i->getCurrentUrl();
        $i->impersonate('Zane Smith');
        $i->amOnUrl($this->home_page_url);
        $i->click('Test Due Work');
        $currentURL=$i->getCurrentUrl();
        $assessmentID=substr(strrchr(substr($currentURL, 0, strrpos($currentURL, '/')), '/'), 1);
        $i->amOnUrl($this->home_page_url);
        $i->click('#component-assessment-'.$assessmentID.' > nav:nth-child(2) > a:nth-child(1)');
        $i->click('#component-assessment-'.$assessmentID.' a.icon-progress');
        $i->click('div.row.actions-small-2 a.icon-more');
        $i->click('div.row.actions-small-2 a.icon-task');
        $i->fillField('textarea[name=comment]', 'Not bad, but you should work on your spelling.');
        $i->fillField('input[name=mark]', '65');
        sleep(1);
        $studentWeAreMarkingUrl=$i->getCurrentUrl();
        $i->click('a.button[data-draft=publish]');
        $i->amOnUrl($studentWeAreMarkingUrl);
        $i->canSee('Reviewed');
    }

    public function viewMarkedSubmission(AcceptanceTester $i)
    {
        $i->login('alaress', 'alaress');
        $i->impersonate('David Rhodes');
        $i->amOnPage('learning/grades/230');
        $link_marks_class_test = Locator::contains('a.flex-grade.link-row', 'Test Class Homepage');
        $i->click($link_marks_class_test);
        $i->waitForText('Test Due Work', 5);
        $link_test_due_work = Locator::contains('a', 'Test Due Work');
        $i->scrollTo($link_test_due_work);
        sleep(2);
        $i->canSee('Test Due Work');
        $i->canSee('65 %');
        $i->click('Test Due Work');
        $i->canSee('Feedback by Mr Zane Smith');
        $i->canSee('Submission by David Rhodes');
        $i->canSee('65 %');
    }

}