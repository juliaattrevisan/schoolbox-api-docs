<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

/**
 * Steps:
 * 1. Provision legacy.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /vagrant && ./vendor/codeception/codeception/codecept --steps run acceptance QuizCest
 *
 * To run a specific test:
 *  ./vendor/codeception/codeception/codecept --steps run acceptance QuizCest:<test method name>
 */

use Page\Quiz;
use Page\Common;
use Codeception\Util\Locator;

class QuizCest
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
    public function testQuizBasic(AcceptanceTester $i)
    {
        $quizPage = new Quiz($i);
        $quizPage->className = Common::CLASS_CHEMISTRY_1_3;
        $quizPage->classId = Common::CLASS_ID_CHEMISTRY_1_3;
        $student1 = Common::USER_RACHEL;
        $student1Id = Common::USER_ID_RACHEL;
        $student2 = Common::USER_DAVID;
        $student2Id = Common::USER_ID_DAVID;
        $teacher = Common::USER_ZANE;

        $quizTitle = 'Cest One ' . Common::gentext(6);
        $this->testCreateQuiz($i, $quizPage, [$student1, $student2], $teacher, $quizTitle);

        // As Zane, review attempt
        $i->canSee('Attempted on time', Quiz::getStatusSelector($student1Id, ''));
        $i->click('Review Attempt', Quiz::getStatusSelector($student1Id, ''));
        $i->waitForText('Marking ' . $quizTitle . ' quiz results for ' . $student1);

        // TODO: check whether the displayed answer is what has been entered.
        // TODO: enter something

        $i->click('Finalise Review');
        $i->click('Yes');
        $i->waitForText('Status of ' . $quizTitle);

        // As Rachel, check notification
        $i->impersonate($student1);
        $quizPage->assertNotifExists($teacher . ' has marked your attempt of ' . $quizTitle . ' in ' . $quizPage->className);

        // As Rachel, click into the due work
        $i->click($quizTitle, '#content');
        $i->canSee('ATTEMPTS');

    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function testQuizReviewHorizontal(AcceptanceTester $i)
    {
        $quizPage = new Quiz($i);
        $quizPage->className = Common::CLASS_CHEMISTRY_1_3;
        $quizPage->classId = Common::CLASS_ID_CHEMISTRY_1_3;
        $student1 = Common::USER_RACHEL;
        $student1Id = Common::USER_ID_RACHEL;
        $student2 = Common::USER_DAVID;
        $student2Id = Common::USER_ID_DAVID;
        $teacher = Common::USER_ZANE;

        $quizTitle = 'Cest Two ' . Common::gentext(6);
        $this->testCreateQuiz($i, $quizPage, [$student1, $student2], $teacher, $quizTitle);

        // As Zane, review attempt
        $i->canSee('Attempted on time', Quiz::getStatusSelector($student1Id, ''));

        // Now go to review horizontal page
        // Jelly tot
        $i->click(Locator::elementAt('#content a.icon-more', 1));
        // Review horizontally
        $i->click(Locator::elementAt('#header-options a.icon-class-list', 1));
        $i->waitForText('Horizontal Marking for ' . $quizTitle);

        $i->click('Q1)');
        $i->waitForText('Horizontal Marking for ' . $quizTitle);

        // TODO: check whether the displayed answer is what has been entered.
        // TODO: enter something

        $i->click('Finalise review'); // this Finalise review has lower case "r"
        $i->waitForText('Status of ' . $quizTitle);

        // Do the same horizontal review again. Should not change existing review result.
        // Jelly tot
        $i->click(Locator::elementAt('#content a.icon-more', 1));
        // Review horizontally
        $i->click(Locator::elementAt('#header-options a.icon-class-list', 1));
        $i->waitForText('Horizontal Marking for ' . $quizTitle);

        $i->click('Q1)');
        $i->waitForText('Horizontal Marking for ' . $quizTitle);

        // TODO: check whether the displayed answer is what has been entered.
        // TODO: enter something

        $i->click('Finalise review'); // this Finalise review has lower case "r"
        $i->waitForText('Status of ' . $quizTitle);

        // As Rachel, check notification
        $i->impersonate($student1);
        $quizPage->assertNotifExists($teacher . ' has marked your attempt of ' . $quizTitle . ' in ' . $quizPage->className);

        // As Rachel, click into the due work
        $i->click($quizTitle, '#content');
        $i->canSee('ATTEMPTS');

        // #attempts > tbody > tr:nth-child(1) > td:nth-child(4)
        // TODO: check only latest one is reviewed
        // TODO: USE ACTUAL REVIEW TIME!!!
        $i->canSee('2018', '#attempts > tbody > tr:nth-child(1) > td:nth-child(4)');
        $i->canSee('10/10', '#attempts > tbody > tr:nth-child(1) > td:nth-child(5)');
        $i->cantSee('2018', '#attempts > tbody > tr:nth-child(2) > td:nth-child(4)');
        $i->cantSee('10/10', '#attempts > tbody > tr:nth-child(2) > td:nth-child(5)');

    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function testQuizBulkReview(AcceptanceTester $i)
    {
        $quizPage = new Quiz($i);
        $quizPage->className = Common::CLASS_CHEMISTRY_1_3;
        $quizPage->classId = Common::CLASS_ID_CHEMISTRY_1_3;
        $student1 = Common::USER_RACHEL;
        $student1Id = Common::USER_ID_RACHEL;
        $student2 = Common::USER_DAVID;
        $student2Id = Common::USER_ID_DAVID;
        $teacher = Common::USER_ZANE;

        $quizTitle = 'Cest Three ' . Common::gentext(6);
        $this->testCreateQuiz($i, $quizPage, [$student1, $student2], $teacher, $quizTitle);

        // As Zane, review attempt
        $i->canSee('Attempted on time', Quiz::getStatusSelector($student1Id, ''));

        // Now go to review horizontal page
        // Jelly tot
        $i->click(Locator::elementAt('#content a.icon-more', 1));
        // Review horizontally
        $i->click(Locator::elementAt('#header-options a.icon-approve', 1));
        $i->waitForText('Mark Attempts as Reviewed');

        $i->click('Mark All as Reviewed');
        $i->waitForText('Status of ' . $quizTitle);

        // As Rachel, check notification
        $i->impersonate($student1);
        $quizPage->assertNotifExists($teacher . ' has marked your attempt of ' . $quizTitle . ' in ' . $quizPage->className);

        // As Rachel, click into the due work
        $i->click($quizTitle, '#content');
        $i->canSee('ATTEMPTS');

    }

    /**
     * Internal steps to create a quiz
     * @param AcceptanceTester $i
     * @param Quiz $quizPage
     * @param array $students
     * @param String $teacher
     * @param String $quizTitle
     * @throws Exception
     */
    private function testCreateQuiz(AcceptanceTester $i, Quiz $quizPage, array $students,
                                    String $teacher, String $quizTitle)
    {

        // As Zane, create a quiz
        $i->impersonate($teacher);
        $assessId = $quizPage->createQuiz(
            [
                Quiz::SELECTOR_WORKTYPE => Quiz::WORKTYPE_ASSESSMENT_TASK,
                Quiz::SELECTOR_MAX_ATTEMPTS => 0 // unlimited
            ],
            [
                Quiz::SELECTOR_TITLE => $quizTitle,
                Quiz::SELECTOR_PUBLISH_DATE => Common::toDatepickerFormat(new \DateTime()),
                Quiz::SELECTOR_OPEN_DATE => Common::toDatepickerFormat(new \DateTime())
            ]
        );

        // add a section
        $sectionTitle1 = 'Cest Section ' . Common::gentext(6);
        $sectionId = $quizPage->createQuizSection($sectionTitle1);

        // add a question
        $question1 = 'What kind of food do you like? ' . time();
        $correctAnswer = Common::gentext(5);
        $wrongAnswer = Common::gentext(6);
        $question1Id = $quizPage->createQuestion(Quiz::TYPE_EXACT_ANSWER, $question1, 10, $correctAnswer);

        // finalize the question
        $i->click($sectionTitle1);
        $i->waitForText('Finish Modifying');
        $i->click('Finish Modifying');

        // As Rachel, check notification
        foreach ($students as $student) {
            $i->impersonate($student);
            $quizPage->assertNotifExists('Assessment Task ' . $quizTitle . ' opened in ' . $quizPage->className);

            // create attempt
            $attempt1Id = $quizPage->createAttempt($quizTitle, $wrongAnswer);

            // another attempt (some how this is easier way to come back to the quiz page)
            $i->amOnPage('/messages');
            $attempt2Id = $quizPage->createAttempt($quizTitle, $correctAnswer);
        }

        // As Zane, check notification
        $i->impersonate($teacher);
        // TODO:: the following works only for two students
        // @see schoolbox/library/Schoolbox/Realtime/Notification/Decorator.php
        sort($students);
        $quizPage->assertNotifExists(implode(' and ', $students) . ' attempted ' . $quizTitle . ' in ' . $quizPage->className);

        // As Zane, click into the quiz -> brings to status page
        $i->click($quizTitle, '#content');
        $i->waitForText('Status of ' . $quizTitle);

    }

}
