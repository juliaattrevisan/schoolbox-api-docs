<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/**
 * Steps:
 * 1. Provision legacy.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /vagrant && ./vendor/codeception/codeception/codecept --steps run acceptance Quiz/ReviewCest
 *
 * To run a specific test:
 *  ./vendor/codeception/codeception/codecept --steps run acceptance Quiz/ReviewCest:<test method name>
 */

use Page\Quiz\AssessmentForm;
use Page\Quiz\StatusPage;
use Page\Quiz\SectionForm;
use Page\Quiz\AssessmentPage;

use Page\Quiz\AttemptForm;
use Page\Quiz\QuestionForm;
use Page\Common;
use Codeception\Util\Locator;

/**
 * Test create, attempt and review of quiz
 */
class ReviewCest
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
     * Test reviewing from review attempt page
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function testQuizBasic(AcceptanceTester $i)
    {
        $className = Common::CLASS_CHEMISTRY_1_3;
        $classId = Common::CLASS_ID_CHEMISTRY_1_3;
        $student1 = Common::USER_RACHEL;
        $student1Id = Common::USER_ID_RACHEL;
        $student2 = Common::USER_DAVID;
        $student2Id = Common::USER_ID_DAVID;
        $teacher = Common::USER_ZANE;
        $quizTitle = 'Cest One ' . Common::gentext(6);

        // As Zane, create a quiz
        $i->impersonate($teacher);
        $this->createQuiz($i, [$student1, $student2], $classId, $className, $teacher, $quizTitle,
            AssessmentForm::VISIBILITY_STAFF_AND_STUDENT);

        // As Zane, review attempt
        $i->canSee('Attempted on time', StatusPage::getStatusSelector($student1Id, ''));

        $statusPage = new StatusPage($i);
        $reviewForm = $statusPage->clickReviewAttempt($student1Id, $quizTitle, $student1);

        // TODO: check whether the displayed answer is what has been entered.
        // TODO: enter something
        $reviewForm->finalise();

        // As Rachel, check notification
        $i->impersonate($student1);
        $i->assertNotifExists($teacher . ' has marked your attempt of ' . $quizTitle . ' in ' . $className);

        // As Rachel, click into the quiz
        $i->click($quizTitle, '#content');
        $i->canSee('ATTEMPTS');
    }

    /**
     * Test review horizontal
     * @param AcceptanceTester $i
     * @throws Exception
     */
     public function testQuizReviewHorizontal(AcceptanceTester $i)
    {
        $className = Common::CLASS_CHEMISTRY_1_3;
        $classId = Common::CLASS_ID_CHEMISTRY_1_3;
        $student1 = Common::USER_RACHEL;
        $student1Id = Common::USER_ID_RACHEL;
        $student2 = Common::USER_DAVID;
        $student2Id = Common::USER_ID_DAVID;
        $teacher = Common::USER_ZANE;
        $quizTitle = 'Cest Two ' . Common::gentext(6);

        // As Zane, create a quiz
        $i->impersonate($teacher);
        $this->createQuiz($i, [$student1, $student2], $classId, $className, $teacher, $quizTitle,
            AssessmentForm::VISIBILITY_STAFF_AND_STUDENT);

        // As Zane, review attempt
        $i->canSee('Attempted on time', StatusPage::getStatusSelector($student1Id, ''));

        // Now go to review horizontal page
        $statusPage = new StatusPage($i);
        $reviewHorizontalForm = $statusPage->clickReviewHorizontal($quizTitle);
        // TODO: check whether the displayed answer is what has been entered.
        // TODO: enter something
        $reviewHorizontalForm->finalise();

        // Do the same horizontal review again. Should not change existing review result.
        $reviewHorizontalForm = $statusPage->clickReviewHorizontal($quizTitle);
        $reviewHorizontalForm->finalise();

        // As Rachel, check notification
        $i->impersonate($student1);
        $i->assertNotifExists($teacher . ' has marked your attempt of ' . $quizTitle . ' in ' . $className);

        // As Rachel, click into the due work
        $i->click($quizTitle, '#content');
        $i->canSee('ATTEMPTS');

        // #attempts > tbody > tr:nth-child(1) > td:nth-child(4)
        // TODO: check only latest one is reviewed
        // TODO: USE ACTUAL REVIEW TIME!!!
        $i->canSee('2021', '#attempts > tbody > tr:nth-child(1) > td:nth-child(4)');
        $i->canSee('10/10', '#attempts > tbody > tr:nth-child(1) > td:nth-child(5)');
        $i->cantSee('2021', '#attempts > tbody > tr:nth-child(2) > td:nth-child(4)');
        $i->cantSee('10/10', '#attempts > tbody > tr:nth-child(2) > td:nth-child(5)');

    }

    /**
     * Test bulk mark as reviewed
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function testQuizBulkReview(AcceptanceTester $i)
    {
        $className = Common::CLASS_CHEMISTRY_1_3;
        $classId = Common::CLASS_ID_CHEMISTRY_1_3;
        $student1 = Common::USER_RACHEL;
        $student1Id = Common::USER_ID_RACHEL;
        $student2 = Common::USER_DAVID;
        $student2Id = Common::USER_ID_DAVID;
        $teacher = Common::USER_ZANE;
        $quizTitle = 'Cest Three ' . Common::gentext(6);

        // As Zane, create a quiz
        $i->impersonate($teacher);
        $this->createQuiz($i, [$student1, $student2], $classId, $className, $teacher, $quizTitle,
            AssessmentForm::VISIBILITY_STAFF_AND_STUDENT);

        // As Zane, review attempt
        $i->canSee('Attempted on time', StatusPage::getStatusSelector($student1Id, ''));

        // Bulk Review
        $statusPage = new StatusPage($i);
        $statusPage->bulkReview($quizTitle);

        // As Rachel, check notification
        $i->impersonate($student1);
        $i->assertNotifExists($teacher . ' has marked your attempt of ' . $quizTitle . ' in ' . $className);

        // As Rachel, click into the due work
        $i->click($quizTitle, '#content');
        $i->canSee('ATTEMPTS');

    }

    /**
     * Test changing quiz grade visibility
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function testQuizGradeVisibility(AcceptanceTester $i)
    {
        $className = Common::CLASS_CHEMISTRY_1_3;
        $classId = Common::CLASS_ID_CHEMISTRY_1_3;
        $student1 = Common::USER_RACHEL;
        $student1Id = Common::USER_ID_RACHEL;
        $student2 = Common::USER_DAVID;
        $student2Id = Common::USER_ID_DAVID;
        $teacher = Common::USER_ZANE;
        $quizTitle = 'Cest Four ' . Common::gentext(6);

        // As Zane, create a quiz
        $i->impersonate($teacher);
        $this->createQuiz($i, [$student1, $student2], $classId, $className, $teacher, $quizTitle,
            AssessmentForm::VISIBILITY_STAFF_ONLY);

        // As Zane, review attempt
        $i->canSee('Attempted on time', StatusPage::getStatusSelector($student1Id, ''));

        $statusPage = new StatusPage($i);
        $reviewForm = $statusPage->clickReviewAttempt($student1Id, $quizTitle, $student1);
        // TODO: check whether the displayed answer is what has been entered.
        // TODO: enter something
        $reviewForm->finalise();

        // As Rachel, check notification
        $i->impersonate($student1);
        $i->assertNotifNotExists($teacher . ' has marked your attempt of ' . $quizTitle . ' in ' . $className);

        // As Rachel, click into the due work
        $i->click($quizTitle, '#content');
        $i->canSee('ATTEMPTS');
        // shouldn't see any grades
        $i->cantSee('2021', '#attempts > tbody > tr:nth-child(1) > td:nth-child(4)');
        $i->cantSee('10/10', '#attempts > tbody > tr:nth-child(1) > td:nth-child(5)');

        // Az Zane, change visibility
        $i->impersonate($teacher);
        $i->amOnPage('/homepage/' . $classId);
        $i->click($quizTitle,'#content');
        $i->waitForText('Begin Quiz');

        $quizPage = new AssessmentPage($i);
        $quizForm = $quizPage->clickModify();

        // change visibility
        $quizForm->setVisibility(AssessmentForm::VISIBILITY_STAFF_AND_STUDENT)
            ->update();

        // As Rachel, check notification
        $i->impersonate($student1);
        sleep(5);
        $i->amOnPage('/messages');
        $i->assertNotifExists($teacher . ' has marked your attempt of ' . $quizTitle . ' in ' . $className);

        // As Rachel, click into the due work
        $i->click($quizTitle, '#content');
        $i->canSee('ATTEMPTS');
        // shouldn't see any grades
        $i->canSee('2021', '#attempts > tbody > tr:nth-child(1) > td:nth-child(4)');
        $i->canSee('10/10', '#attempts > tbody > tr:nth-child(1) > td:nth-child(5)');

    }


    /**
     * Internal steps to create a quiz
     * @param AcceptanceTester $i
     * @param AssessmentForm $quizForm
     * @param array $students
     * @param String $teacher
     * @param String $quizTitle
     * @param int $visibility
     * @throws Exception
     */
    private function createQuiz(AcceptanceTester $i, array $students, int $classId, string $className,
                                string $teacher, string $quizTitle, int $visibility)
    {
        $quizForm = new AssessmentForm($i);
        $i->amOnPage($quizForm->generateRoute($classId));

        // create a quiz
        $quizForm->setWorkType($quizForm::WORKTYPE_ASSESSMENT_TASK)
            ->setVisibility($visibility)
            ->setMaxAttempts(0)
            ->setTitle($quizTitle)
            ->setPublishDate(\Alaress_Date_Format::now()->setTime(0, 0, 0))
            ->setOpenDate(\Alaress_Date_Format::now()->setTime(0, 0, 0))
            ->create();

        // add a section
        $sectionForm = new SectionForm($i);
        $sectionTitle1 = 'Cest Section ' . Common::gentext(6);
        $sectionForm->setTitle($sectionTitle1)
            ->create();

        // add a question
        $questionForm = new \Page\Quiz\QuestionForm($i);
        $question1 = 'What kind of food do you like? ' . time();
        $correctAnswer = Common::gentext(5);
        $wrongAnswer = Common::gentext(6);
        $questionForm->setType($questionForm::TYPE_EXACT_ANSWER)
            ->setAnswer($correctAnswer)
            ->setMark(10)
            ->setQuestion($question1)
            ->create();

        // finalize the question
        $sectionForm->finalize();

        // As Rachel, check notification
        $attemptForm = new AttemptForm($i);
        foreach ($students as $student) {
            $i->impersonate($student);
            $i->assertNotifExists('Assessment Task ' . $quizTitle . ' opened in ' . $className);

            // create attempt
            $attemptForm->createAttempt($quizTitle, $wrongAnswer);

            // another attempt (some how this is easier way to come back to the quiz page)
            $i->amOnPage('/messages');
            $attemptForm->createAttempt($quizTitle, $correctAnswer);
        }

        // As Zane, check notification
        $i->impersonate($teacher);
        // TODO:: the following works only for two students
        // @see schoolbox/library/Schoolbox/Realtime/Notification/Decorator.php
        sort($students);
        $i->assertNotifExists(implode(' and ', $students) . ' attempted ' . $quizTitle . ' in ' . $className);

        // As Zane, click into the quiz -> brings to status page
        $i->click($quizTitle, '#content');
        $i->waitForText('Status of ' . $quizTitle);

    }

}
