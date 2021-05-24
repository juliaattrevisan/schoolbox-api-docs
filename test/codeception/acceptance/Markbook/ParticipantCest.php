<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/**
 * Steps:
 * 1. Provision legacy.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /vagrant && ./vendor/codeception/codeception/codecept --steps run acceptance Markbook/ParticipantCest
 *
 * To run a specific test:
 *  ./vendor/codeception/codeception/codecept --steps run acceptance Markbook/ParticipantCest:<test method name>
 */

use Page\Markbook\MarkbookPage;
use Page\Common;
use Page\Duework\AssessmentForm;
use Page\Duework\SubmitForm;
use Page\Duework\StatusPage;

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
        $i->loginAsAdmin();
        // TODO: Delete something already in markbook
    }

    /**
     * Clean up after every test
     *
     * @param AcceptanceTester $i
     */
    public function _after(AcceptanceTester $i)
    {
        $i->logout();
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function testMarkbookBasic(AcceptanceTester $i)
    {

        $className = Common::CLASS_CHEMISTRY_1_3;
        $classId = Common::CLASS_ID_CHEMISTRY_1_3;
        $student = Common::USER_RACHEL;
        $studentId = Common::USER_ID_RACHEL;
        $teacher = Common::USER_ZANE;
        $teacherId = Common::USER_ID_ZANE;

        // As Zane, create a due work
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

        // As Zane, create submission
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
        $i->click(StatusPage::LINK_JELLY_TOT);
        $i->click(StatusPage::LINK_MODIFY);

        // remove Zane from participants
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
        $i->click(StatusPage::LINK_JELLY_TOT);
        $i->click(StatusPage::LINK_MODIFY);

        // remove Rachel from participants
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
