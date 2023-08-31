<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Tests\Acceptance\Markbook;

/**
 * Steps:
 * 1. Provision legacy.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /usr/share/schoolbox && ./vendor/codeception/codeception/codecept --steps run acceptance Markbook/ParticipantCest
 *
 * To run a specific test:
 *  ./vendor/codeception/codeception/codecept --steps run acceptance Markbook/ParticipantCest:<test method name>
 */

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
    public function _before(AcceptanceTester $i)
    {
        $i->logout();
        $i->loginAsAdmin();
        //adding a due work component into the homepage
        $i->amOnPage('/homepage/106');
        $addComponent = new Homepage($i);
        $addComponent->addComponent($addComponent::ELEMENT_ADD_COMPONENT_SECTION_LEARNING, 'Due Work');
        // TODO: Delete something already in markbook
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function testMarkbookBasic(AcceptanceTester $i)
    {
        $classId = Common::CLASS_ID_DEMO2;
        $student = Common::USER_LOUISASMITH;
        $studentId = Common::USER_ID_LOUISASMITH;
        $teacher = Common::USER_JACKCOLES;
        $teacherId = Common::USER_ID_JACKCOLES;

        // As Jack Coles, create a due work
        $i->impersonate($teacher);
        $dueworkTitle1 = 'Cest One ' . Common::gentext(6);
        $filename = __DIR__ . '/../News/upload/test.jpg';

        $dueworkForm = new AssessmentForm($i);
        $dueworkForm->openCreate($classId);

        $dueworkForm->setWorkType(AssessmentForm::WORKTYPE_ASSESSMENT_TASK)
            ->setAcceptSubmission(AssessmentForm::ACCEPT_SUBMISSION_MULTIPLE)
            ->setTitle($dueworkTitle1)
            ->setPublishDate(\Alaress_Date_Format::now()->setTime(0, 0, 0))
            ->setOpenDate(\Alaress_Date_Format::now()->setTime(0, 0, 0))
            ->create();

        // As Jack Coles, create submission
        // TODO: need something nicer
        $i->click($dueworkTitle1, '#content');
        $i->click('Create submission', '#content');

        $submitForm = new SubmitForm($i);
        $submitForm->setContents(Common::gentext(200))
            ->setAttachment($filename)
            ->submit();

        // open markbook
        $markbookPage = new MarkbookPage($i);
        $markbookPage->open($classId);

        // check the existence
        $markbookPage->canSeeParticipant($teacher);
        $markbookPage->canSeeAssessment($dueworkTitle1);

        // exclude the user from the duework
        $i->click($dueworkTitle1);
        $i->waitForText('Status of ' . $dueworkTitle1);

        // exclude the user from the task
        $i->clickElementInWebComponent(StatusPage::LINK_JELLY_TOT, StatusPage::LINK_JELLY_TOT_BUTTON);
        $i->click(StatusPage::LINK_MODIFY);

        // remove Jack Coles from participants
        $dueworkForm->modifyParticipation($teacherId, true, true)
            ->update();

        // open course mark book
        $markbookPage->open($classId);

        // check the existence
        $markbookPage->canSeeAssessment($dueworkTitle1);
        // teacher should disappear
        $markbookPage->cantSeeParticipant($teacher);
        // student should remain
        $markbookPage->canSeeParticipant($student);

        // exclude the user from the duework
        $i->click($dueworkTitle1);
        $i->waitForText('Status of ' . $dueworkTitle1);

        // exclude the user from the task
        $i->clickElementInWebComponent(StatusPage::LINK_JELLY_TOT, StatusPage::LINK_JELLY_TOT_BUTTON);
        $i->click(StatusPage::LINK_MODIFY);

        // remove Louisa Smith from participants
        $dueworkForm->modifyParticipation($studentId, false, false)
            ->update();

        // open course mark book
        $markbookPage->open($classId);

        // check the existence
        $markbookPage->canSeeAssessment($dueworkTitle1);
        // teacher should disappear
        $markbookPage->cantSeeParticipant($teacher);
        // student should remain
        $markbookPage->canSeeParticipant($student);
    }
}
