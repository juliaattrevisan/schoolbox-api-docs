<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/**
 * Steps:
 * 1. Provision legacy.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /usr/share/schoolbox && ./vendor/codeception/codeception/codecept --steps run acceptance Duework/FeedbackCest
 *
 * To run a specific test:
 *  ./vendor/codeception/codeception/codecept --steps run acceptance Duework/FeedbackCest:<test method name>
 */

namespace Tests\Acceptance\Duework;

use Tests\Support\AcceptanceTester;
use Tests\Support\Page\Common;
use Tests\Support\Page\Duework\AssessmentForm;
use Tests\Support\Page\Duework\AssessmentPage;
use Tests\Support\Page\Duework\SubmitForm;
use Tests\Support\Page\Duework\StatusPage;
use Codeception\Util\Locator;
use Exception;

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
        $className = Common::CLASS_9AENG;
        $classId = Common::CLASS_ID_9AENG;
        $student = Common::USER_LOUISASMITH;
        $teacher = Common::USER_JACKCOLES;
        $dueworkTitle1 = 'Cest One ' . Common::gentext(6);
        $filename = __DIR__ . '/../News/upload/test.jpg';


        $i->amGoingTo('As Jack Coles, create a due work');
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

        $i->amGoingTo('As Louisa Smith, check notification');
        $i->impersonateViaModifyUserPage($student);
        $i->assertNotifExists('Assessment Task ' . $dueworkTitle1 . ' opened in ' . $className);

        $i->amGoingTo('Go to submit page');
        // TODO: need something nicer
        $i->click($dueworkTitle1, '#content');
        $i->waitForText('Create submission');
        $i->click('Create submission', '#content');
        $i->waitForText($dueworkTitle1);

        $i->amGoingTo('create submission');
        $submitForm = new SubmitForm($i);
        $submitForm->setContents(Common::gentext(200))
            ->setAttachment($filename)
            ->submit();

        $i->amGoingTo('As Jack Coles, check the notification');
        $i->impersonate($teacher);
        $i->assertNotifExists($student . ' submitted work to ' . $dueworkTitle1 . ' in ' . $className);

        $i->amGoingTo('As Jack Coles, click into the due work');
        $i->click($dueworkTitle1, '#content');
        $i->waitForText('SUBMISSION HISTORY');

        $i->amGoingTo('As Jack Coles, click to the students submission page');
        $dueworkPage = new AssessmentPage($i);
        $feedbackForm = $dueworkPage->openInlineGrader($student, $dueworkTitle1);

        $feedbackForm->setGrade(rand(50, 100))
            ->setComment(Common::gentext(200))
            ->publish();

        $i->amGoingTo('As Louisa Smith, check notification');
        $i->impersonateViaModifyUserPage($student);
        $i->assertNotifExists($teacher . ' has marked your work for ' . $dueworkTitle1 . ' in ' . $className);

        $i->amGoingTo('As Louisa Smith, click into the due work');
        $i->click($dueworkTitle1, '#content');
        $i->canSee('GRADING');
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function testDueworkStatusPage(AcceptanceTester $i)
    {
        $className = Common::CLASS_9AENG;
        $classId = Common::CLASS_ID_9AENG;
        $student = Common::USER_LOUISASMITH;
        $studentId = Common::USER_ID_LOUISASMITH;
        $teacher = Common::USER_JACKCOLES;
        $dueworkTitle2 = 'Cest Two ' . Common::gentext(6);

        $i->amGoingTo('As Jack Coles, create a due work');
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

        $i->amGoingTo('As Louisa Smith, check notification');
        $i->impersonateViaModifyUserPage($student);
        $i->assertNotifExists('Assessment Task ' . $dueworkTitle2 . ' opened in ' . $className);

        $i->amGoingTo('go to submit page');
        // TODO: need something nicer
        $i->click($dueworkTitle2, '#content');
        $i->waitForText('Create submission');
        $i->click('Create submission', '#content');
        $i->waitForText($dueworkTitle2);

        $i->amGoingTo('create submission');
        $submitForm = new SubmitForm($i);
        $submitForm->setContents(Common::gentext(200))
            ->submit();

        $i->amGoingTo('As Jack Coles, check notification');
        $i->impersonate($teacher);
        $i->assertNotifExists($student . ' submitted work to ' . $dueworkTitle2 . ' in ' . $className);

        $i->amGoingTo('As Jack Coles, click into the due work');
        $i->click($dueworkTitle2, '#content');
        $i->waitForText('SUBMISSION HISTORY');

        $i->amGoingTo('Now go to duework status page');
        $dueworkPage = new AssessmentPage($i);
        $statusPage = $dueworkPage->openStatusPage();
        $statusPage->gradeSubmission($studentId, rand(50, 100), Common::gentext(200));

        $i->amGoingTo('find Louisas submission and enter feed back');
        $i->canSee('Reviewed', StatusPage::getStatusSelector($studentId, ''));

        $i->amGoingTo('As Louisa, check notification');
        $i->impersonateViaModifyUserPage($student);
        $i->assertNotifExists($teacher . ' has marked your work for ' . $dueworkTitle2 . ' in ' . $className);

        $i->amGoingTo('As Louisa, click into the due work');
        $i->click($dueworkTitle2, '#content');
        $i->canSee('GRADING');
    }
}
