<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/**
 * Steps:
 * 1. Provision legacy.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /vagrant && ./vendor/codeception/codeception/codecept --steps run acceptance Duework/FeedbackCest
 *
 * To run a specific test:
 *  ./vendor/codeception/codeception/codecept --steps run acceptance Duework/FeedbackCest:<test method name>
 */

use Page\Common;
use Page\Duework\AssessmentForm;
use Page\Duework\AssessmentPage;
use Page\Duework\SubmitForm;
use Page\Duework\StatusPage;
use Codeception\Util\Locator;

class FeedbackCest
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
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function testDueworkBasic(AcceptanceTester $i)
    {
        $className = Common::CLASS_CHEMISTRY_1_3;
        $classId = Common::CLASS_ID_CHEMISTRY_1_3;
        $student = Common::USER_RACHEL;
        $teacher = Common::USER_ZANE;
        $dueworkTitle1 = 'Cest One ' . Common::gentext(6);
        $filename = __DIR__ . '/../News/upload/test.jpg';


        // As Zane, create a due work
        $i->impersonate($teacher);
        $dueworkForm = new AssessmentForm($i);
        $dueworkForm->openCreate($classId);

        $dueworkForm->setWorkType(AssessmentForm::WORKTYPE_ASSESSMENT_TASK)
            ->setAcceptSubmission(AssessmentForm::ACCEPT_SUBMISSION_MULTIPLE)
            ->setTitle($dueworkTitle1)
            ->setPublishDate(\Alaress_Date_Format::now()->setTime(0, 0, 0))
            ->setOpenDate(\Alaress_Date_Format::now()->setTime(0, 0, 0))
            ->setMarkType(AssessmentForm::MARK_TYPE_PERCENTAGE)
            ->create();

        // As Rachel, check notification
        $i->impersonate($student);
        $i->assertNotifExists('Assessment Task ' . $dueworkTitle1 . ' opened in ' . $className);

        // Goto submit page
        // TODO: need something nicer
        $i->click($dueworkTitle1, '#content');
        $i->waitForText('Create submission');
        $i->click('Create submission', '#content');
        $i->waitForText($dueworkTitle1);

        // create submission
        $submitForm = new SubmitForm($i);
        $submitForm->setContents(Common::gentext(200))
            ->setAttachment($filename)
            ->submit();

        // As Zane, check notification .. this requires SA-7050 fix to success.
        $i->impersonate($teacher);
        $i->assertNotifExists($student . ' submitted work to ' . $dueworkTitle1 . ' in ' . $className);

        // As Zane, click into the due work
        $i->click($dueworkTitle1, '#content');
        $i->waitForText('SUBMISSION HISTORY');

        // As Zane, click to the student's submission page
        $dueworkPage = new AssessmentPage($i);
        $feedbackForm = $dueworkPage->openInlineGrader($student, $dueworkTitle1);

        $feedbackForm->setGrade(rand(50, 100))
            ->setComment(Common::gentext(200))
            ->publish();

        // As Rachel, check notification
        $i->impersonate($student);
        $i->assertNotifExists($teacher . ' has marked your work for ' . $dueworkTitle1 . ' in ' . $className);

        // As Rachel, click into the due work
        $i->click($dueworkTitle1, '#content');
        $i->canSee('GRADING');

    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function testDueworkStatusPage(AcceptanceTester $i)
    {
        $className = Common::CLASS_CHEMISTRY_1_3;
        $classId = Common::CLASS_ID_CHEMISTRY_1_3;
        $student = Common::USER_RACHEL;
        $studentId = Common::USER_ID_RACHEL;
        $teacher = Common::USER_ZANE;
        $dueworkTitle2 = 'Cest Two ' . Common::gentext(6);

        // As Zane, create a due work
        $i->impersonate($teacher);

        $dueworkForm = new AssessmentForm($i);
        $dueworkForm->openCreate($classId);

        $dueworkForm->setWorkType(AssessmentForm::WORKTYPE_ASSESSMENT_TASK)
            ->setAcceptSubmission(AssessmentForm::ACCEPT_SUBMISSION_MULTIPLE)
            ->setTitle($dueworkTitle2)
            ->setPublishDate(\Alaress_Date_Format::now()->setTime(0, 0, 0))
            ->setOpenDate(\Alaress_Date_Format::now()->setTime(0, 0, 0))
            ->setMarkType(AssessmentForm::MARK_TYPE_PERCENTAGE)
            ->create();

        // As Rachel, check notification
        $i->impersonate($student);
        $i->assertNotifExists('Assessment Task ' . $dueworkTitle2 . ' opened in ' . $className);

        // Goto submit page
        // TODO: need something nicer
        $i->click($dueworkTitle2, '#content');
        $i->waitForText('Create submission');
        $i->click('Create submission', '#content');
        $i->waitForText($dueworkTitle2);

        // create submission
        $submitForm = new SubmitForm($i);
        $submitForm->setContents(Common::gentext(200))
            ->submit();

        // As Zane, check notification
        $i->impersonate($teacher);
        $i->assertNotifExists($student . ' submitted work to ' . $dueworkTitle2 . ' in ' . $className);

        // As Zane, click into the due work
        $i->click($dueworkTitle2, '#content');
        $i->waitForText('SUBMISSION HISTORY');

        // Now go to duework status page
        $dueworkPage = new AssessmentPage($i);
        $statusPage = $dueworkPage->openStatusPage();
        $statusPage->gradeSubmission($studentId, rand(50, 100), Common::gentext(200));

        // find Rachel's submission and enter feed back
        $i->canSee('Reviewed', StatusPage::getStatusSelector($studentId, ''));

        // As Rachel, check notification
        $i->impersonate($student);
        $i->assertNotifExists($teacher . ' has marked your work for ' . $dueworkTitle2 . ' in ' . $className);

        // As Rachel, click into the due work
        $i->click($dueworkTitle2, '#content');
        $i->canSee('GRADING');

    }

}
