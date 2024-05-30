<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Tests\Acceptance\Markbook;

/**
 *
 * creates a due work, remove participants and check the excluded user for that activity is no longer presented in the class markbook
 *
 * Steps:
 * 1. Provision legacy.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /usr/share/schoolbox && ./vendor/codeception/codeception/codecept --steps run Acceptance Markbook/ParticipantCest
 *
 */

use Codeception\Util\Locator;
use Tests\Support\AcceptanceTester;
use Tests\Support\Page\Homepage;
use Tests\Support\Page\Markbook\MarkbookPage;
use Tests\Support\Page\Common;
use Tests\Support\Page\Duework\AssessmentForm;
use Tests\Support\Page\Duework\SubmitForm;
use Tests\Support\Page\Duework\StatusPage;
use Exception;

class ParticipantCest
{
    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function _before(AcceptanceTester $i): void
    {
        $i->logout();
        $i->loginAsAdmin();
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function testMarkbookBasic(AcceptanceTester $i): void
    {
        $classId = Common::CLASS_ID_DEMO2;
        $student = Common::USER_LOUISASMITH;
        $studentId = Common::USER_ID_LOUISASMITH;
        $teacher = Common::USER_JACKCOLES;
        $teacherId = Common::USER_ID_JACKCOLES;

        $i->amGoingTo('add a due work component into the homepage');
        $i->amOnPage('/homepage/106');
        $addComponent = new Homepage($i);
        $addComponent->addComponent($addComponent::ELEMENT_ADD_COMPONENT_SECTION_LEARNING, 'Due Work');
        $i->amGoingTo('As Jack Coles, create a due work');
        $i->impersonate($teacher);
        $dueworkTitle1 = 'Cest One ' . Common::gentext(6);
        $filename = __DIR__ . '/../News/upload/test.jpg';

        $dueworkForm = new AssessmentForm($i);
        $dueworkForm->openCreate($classId);

        $dueworkForm->setWorkType(AssessmentForm::WORKTYPE_ASSESSMENT_TASK)
            ->setAcceptSubmission(AssessmentForm::ACCEPT_SUBMISSION_MULTIPLE)
            ->checkSubmissionAllowed(AssessmentForm::ALLOW_OFFLINE)
            ->checkSubmissionAllowed(AssessmentForm::TEXT_EDITOR)
            ->checkSubmissionAllowed(AssessmentForm::TYPE_FILES)
            ->setTitle($dueworkTitle1)
            ->setPublishDate(\Alaress_Date_Format::now()->setTime(0, 0, 0))
            ->setOpenDate(\Alaress_Date_Format::now()->setTime(0, 0, 0))
            ->create();

        $i->amGoingTo('As Jack Coles, create submission');
        $i->click($dueworkTitle1, '#content');
        $i->click('Create submission', '#content');

        $submitForm = new SubmitForm($i);
        $submitForm->setContents(Common::gentext(200))
            ->setAttachment($filename)
            ->submit();

        $i->amGoingTo('open the markbook and check if I can see my submission');
        $markbookPage = new MarkbookPage($i);
        $markbookPage->open($classId);
        $markbookPage->canSeeParticipant($teacher);
        $markbookPage->canSeeAssessment($dueworkTitle1);

        $i->amGoingTo('exclude Jack Coles from the due work participants list');
        $i->click($dueworkTitle1);
        $i->waitForText('Status of ' . $dueworkTitle1);
        $i->clickElementInWebComponent('[data-test=sbx-pop-out-header-options]', '[data-test=sbx-pop-out-header-options-button]');
        $i->click(StatusPage::LINK_MODIFY);
        $dueworkForm->modifyParticipation($teacherId, true, true)
            ->update();

        $i->amGoingTo('open course mark book and check that Jack Coles is not presented and the student is presented');
        $markbookPage->open($classId);
        $markbookPage->canSeeAssessment($dueworkTitle1);
        $markbookPage->cantSeeParticipant($teacher);
        $markbookPage->canSeeParticipant($student);

        $i->amGoingTo('exclude a student (Louisa Smith) from the due work participants list');
        $i->click($dueworkTitle1);
        $i->waitForText('Status of ' . $dueworkTitle1);
        $i->clickElementInWebComponent('[data-test=sbx-pop-out-header-options]', '[data-test=sbx-pop-out-header-options-button]');
        $i->click(StatusPage::LINK_MODIFY);
        $dueworkForm->modifyParticipation($studentId, false, false)
            ->update();

        $i->amGoingTo('open course mark book and check that Jack Coles and he student(Louisa Smith) are not presented');
        $markbookPage->open($classId);
        $markbookPage->canSeeAssessment($dueworkTitle1);
        $markbookPage->cantSeeParticipant($teacher);
        $markbookPage->canSeeParticipant($student);
    }

    /**
     * Soft deleting data to not impact the tests
     *
     * @param AcceptanceTester $i
     */
    public function tearDown(AcceptanceTester $i): void
    {
        $i->amGoingTo("update delete_at for the data created in this test so it does impact next suite of tests");
        $addComponent = new Homepage($i);
        $i->amGoingTo('remove the due work component from the homepage if this is presented');
        $i->amOnPage('/homepage/106');
        if ($i->tryToSeeElement(['css' => 'div[class=component-container\ Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller'])) {
            $i->amGoingTo('remove the component of the homepage');
            $addComponent->clickLockToggle();
            $addComponent->clickComponentRemoveIcon();
            $addComponent->clickRemoveConfirmationMessage();
        }
        $i->updateInDatabase('assessment', ['deleted_at' => "2022-01-15 11:22:34"], ['title like' => "Cest One%"]);
    }
}
