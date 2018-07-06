<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

/**
 * Steps:
 * 1. Provision legacy.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /vagrant && ./vendor/codeception/codeception/codecept --steps run acceptance DueworkCest
 *
 * To run a specific test:
 *  ./vendor/codeception/codeception/codecept --steps run acceptance DueworkCest:<test method name>
 */

use Page\Duework;
use Page\Common;
use Codeception\Util\Locator;

class DueworkCest
{
    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function _before(AcceptanceTester $i)
    {
        exec('cd /vagrant/tools/notif; sudo php /vagrant/test/socket/destroy-rabbit-data.php;');
        $i->logout();
        $i->loginAsAdmin();
    }

    /**
     * Clean up after every test
     *
     * @param AcceptanceTester $i
     */
    public function _after(AcceptanceTester $i)
    {
        // TODO: clean up?
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function testDueworkBasic(AcceptanceTester $i)
    {
        $dueworkPage = new Duework($i);

        $dueworkPage->className = Common::CLASS_CHEMISTRY_1_3;
        $dueworkPage->classId = Common::CLASS_ID_CHEMISTRY_1_3;
        $student = Common::USER_RACHEL;
        $teacher = Common::USER_ZANE;

        // As Zane, create a due work
        $i->impersonate($teacher);
        $dueworkTitle1 = 'Cest One ' . Common::gentext(6);
        $dueworkPage->createDuework(
            [
                Duework::SELECTOR_WORKTYPE => Duework::WORKTYPE_ASSESSMENT_TASK,
                Duework::SELECTOR_ACCEPT_SUBMISSION => Duework::ACCEPT_SUBMISSION_MULTIPLE
            ],
            [
                Duework::SELECTOR_TITLE => $dueworkTitle1,
                Duework::SELECTOR_PUBLISH_DATE => Common::toDatepickerFormat(new \DateTime())
            ]
        );

        // As Rachel, check notification
        $i->impersonate($student);
        $dueworkPage->assertNotifExists('Assessment Task ' . $dueworkTitle1 . ' opened in ' . $dueworkPage->className);

        // create submission
        $dueworkPage->createSubmission($dueworkTitle1, Common::gentext(200));

        // As Zane, check notification
        $i->impersonate($teacher);
        $dueworkPage->assertNotifExists($student . ' submitted work to ' . $dueworkTitle1 . ' in ' . $dueworkPage->className);

        // As Zane, click into the due work
        $i->click($dueworkTitle1, '#content');
        $i->waitForText('SUBMISSION HISTORY');

        // As Zane, click to the student's submission page
        $i->click('Submission by ' . $student, '#content section[class="submission-history"]');
        $i->waitForText('Grading ' . $dueworkTitle1);
        $i->fillField('input[name="mark"]', rand(50, 100));
        $i->fillField('textarea[name="comment"]', Common::gentext(200));

        // wait until the change is saved
        $i->waitForText('SAVED AS DRAFT');
        $i->wait(2); // this is to wait for auto saving to finish
        $i->click('Publish And Continue');
        $i->waitForText('PUBLISH AND CONTINUE'); // for next student

        // As Rachel, check notification
        $i->impersonate($student);
        $dueworkPage->assertNotifExists($teacher . ' has marked your work for ' . $dueworkTitle1 . ' in ' . $dueworkPage->className);

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
        $dueworkPage = new Duework($i);

        $dueworkPage->className = Common::CLASS_CHEMISTRY_1_3;
        $dueworkPage->classId = Common::CLASS_ID_CHEMISTRY_1_3;
        $student = Common::USER_RACHEL;
        $studentId = 229;
        $teacher = Common::USER_ZANE;

        // As Zane, create a due work
        $i->impersonate($teacher);
        $dueworkTitle2 = 'Cest Two ' . Common::gentext(6);
        $dueworkPage->createDuework(
            [
                Duework::SELECTOR_WORKTYPE => Duework::WORKTYPE_ASSESSMENT_TASK,
                Duework::SELECTOR_ACCEPT_SUBMISSION => Duework::ACCEPT_SUBMISSION_MULTIPLE
            ],
            [
                Duework::SELECTOR_TITLE => $dueworkTitle2,
                Duework::SELECTOR_PUBLISH_DATE => Common::toDatepickerFormat(new \DateTime())
            ]
        );

        // As Rachel, check notification
        $i->impersonate($student);
        $dueworkPage->assertNotifExists('Assessment Task ' . $dueworkTitle2 . ' opened in ' . $dueworkPage->className);

        // create submission
        $dueworkPage->createSubmission($dueworkTitle2, Common::gentext(200));

        // As Zane, check notification
        $i->impersonate($teacher);
        $dueworkPage->assertNotifExists($student . ' submitted work to ' . $dueworkTitle2 . ' in ' . $dueworkPage->className);

        // As Zane, click into the due work
        $i->click($dueworkTitle2, '#content');
        $i->waitForText('SUBMISSION HISTORY');

        // Now go to duework status page
        // Jelly tot
        $i->click(Locator::elementAt('#content a.icon-more', 1));
        // View Status
        $i->click(Locator::elementAt('#header-options a.icon-progress', 1));

        // find Rachel's submission and enter feed back
        $i->fillField(Duework::getStatusSelector($studentId, Duework::SELECTOR_MARK), rand(50, 100));
        $i->fillField(Duework::getStatusSelector($studentId, Duework::SELECTOR_COMMENT), Common::gentext(200));
        $i->waitForElementVisible(Duework::getStatusSelector($studentId, Duework::SELECTOR_BUTTON_PUBLISH));
        $i->wait(2); // this is to wait for auto saving to finish
        $i->click(Duework::getStatusSelector($studentId, Duework::SELECTOR_BUTTON_PUBLISH));
        $i->waitForElementNotVisible(Duework::getStatusSelector($studentId, Duework::SELECTOR_BUTTON_PUBLISH));
        $i->canSee('Reviewed', Duework::getStatusSelector($studentId, ''));

        // As Rachel, check notification
        $i->impersonate($student);
        $dueworkPage->assertNotifExists($teacher . ' has marked your work for ' . $dueworkTitle2 . ' in ' . $dueworkPage->className);

        // As Rachel, click into the due work
        $i->click($dueworkTitle2, '#content');
        $i->canSee('GRADING');

    }

}
