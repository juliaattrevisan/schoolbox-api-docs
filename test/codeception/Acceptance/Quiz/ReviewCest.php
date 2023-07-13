<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Tests\Acceptance\Quiz;

/**
 * Steps:
 * 1. Provision legacy.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /usr/share/schoolbox && ./vendor/codeception/codeception/codecept --steps run acceptance Quiz/ReviewCest
 *
 * To run a specific test:
 *  ./vendor/codeception/codeception/codecept --steps run acceptance Quiz/ReviewCest:<test method name>
 */

use Tests\Support\AcceptanceTester;
use Tests\Support\Page\Homepage;
use Tests\Support\Page\Quiz\AssessmentForm;
use Tests\Support\Page\Quiz\QuestionForm;
use Tests\Support\Page\Quiz\StatusPage;
use Tests\Support\Page\Quiz\SectionForm;
use Tests\Support\Page\Quiz\AssessmentPage;

use Tests\Support\Page\Quiz\AttemptForm;
use Tests\Support\Page\Common;
use Exception;

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
    public function _before(AcceptanceTester $i): void
    {
        $i->amGoingTo('Removing Quiz component on class 83, so that we can re-add it in the test script');
        $componentInstanceMapper = \Component_InstanceMapper::getInstance();
        $componentInstanceList   = $componentInstanceMapper->findByContext('homepage', 83);
        foreach ($componentInstanceList as $componentInstance) {
            if ($componentInstance->getDefinition()->getClass() === \Schoolbox\Learning\Assessment\Quiz\Component\Homepage\Controller::class) {
                $componentInstanceMapper->delete($componentInstance);
            }
        }

        $i->logout();
        $i->loginAsAdmin();
    }

    /**
     * Test reviewing from review attempt page
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function testQuizBasic(AcceptanceTester $i): void
    {
        $className = Common::CLASS_9AENG;
        $classId = Common::CLASS_ID_9AENG;
        $student1 = Common::USER_LOUISASMITH;
        $student1Id = Common::USER_ID_LOUISASMITH;
        $student2 = Common::USER_JAMALALI;
        $teacher = Common::USER_JACKCOLES;

        $quizTitle = 'Cest One ' . Common::gentext(6);

        $i->amGoingTo('As Jack Coles, create a quiz');
        $i->impersonate($teacher);
        $this->createQuiz(
            $i,
            [$student1, $student2],
            $classId,
            $className,
            $teacher,
            $quizTitle,
            AssessmentForm::VISIBILITY_STAFF_AND_STUDENT
        );

        $i->amGoingTo('As Jack Coles, review attempt');
        $i->canSee('Attempted on time', StatusPage::getStatusSelector($student1Id, ''));

        $statusPage = new StatusPage($i);
        $reviewForm = $statusPage->clickReviewAttempt($student1Id, $quizTitle, $student1);

        // TODO: check whether the displayed answer is what has been entered.
        // TODO: enter something
        $reviewForm->finalise();

        $i->amGoingTo('wait 5 seconds and should receive upcoming notification');
        $i->wait(5);
        $i->amGoingTo('As Louisa Smith, check notification');
        $i->impersonateViaModifyUserPage($student1);
        $i->assertNotifExists($teacher . ' has marked your attempt of ' . $quizTitle . ' in ' . $className);

        $i->amGoingTo('As Louisa Smith, click into the quiz');
        $i->click($quizTitle, '#content');
        $i->canSee('ATTEMPTS');
    }

    /**
     * Test review horizontal
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function testQuizReviewHorizontal(AcceptanceTester $i): void
    {
        $className = Common::CLASS_9AENG;
        $classId = Common::CLASS_ID_9AENG;
        $student1 = Common::USER_LOUISASMITH;
        $student1Id = Common::USER_ID_LOUISASMITH;
        $student2 = Common::USER_JAMALALI;
        $teacher = Common::USER_JACKCOLES;

        $quizTitle = 'Cest Two ' . Common::gentext(6);

        $i->amGoingTo('As Jack coles, create a quiz');
        $i->impersonate($teacher);
        $this->createQuiz(
            $i,
            [$student1, $student2],
            $classId,
            $className,
            $teacher,
            $quizTitle,
            AssessmentForm::VISIBILITY_STAFF_AND_STUDENT
        );

        $i->amGoingTo('As Jack Coles, review attempt');
        $i->canSee('Attempted on time', StatusPage::getStatusSelector($student1Id, ''));

        $i->amGoingTo('Now go to review horizontal page');
        $statusPage = new StatusPage($i);
        $reviewHorizontalForm = $statusPage->clickReviewHorizontal($quizTitle);
        // TODO: check whether the displayed answer is what has been entered.
        // TODO: enter something
        $reviewHorizontalForm->finalise();

        $i->amGoingTo('Do the same horizontal review again. Should not change existing review result.');
        $reviewHorizontalForm = $statusPage->clickReviewHorizontal($quizTitle);
        $reviewHorizontalForm->finalise();

        $i->amGoingTo('wait 5 seconds and should receive upcoming notification');
        $i->wait(5);
        $i->amGoingTo('As Louisa Smith, check notification');
        $i->impersonateViaModifyUserPage($student1);
        $i->assertNotifExists($teacher . ' has marked your attempt of ' . $quizTitle . ' in ' . $className);

        $i->amGoingTo('As Louisa Smith, click into the due work');
        $i->click($quizTitle, '#content');
        $i->canSee('ATTEMPTS');

        // #attempts > tbody > tr:nth-child(1) > td:nth-child(4)
        // TODO: check only latest one is reviewed
        // TODO: USE ACTUAL REVIEW TIME!!!
        $year = date("Y");
        $i->canSee($year, '#attempts > tbody > tr:nth-child(1) > td:nth-child(4)');
        $i->canSee('10/10', '#attempts > tbody > tr:nth-child(1) > td:nth-child(5)');
        $i->cantSee($year, '#attempts > tbody > tr:nth-child(2) > td:nth-child(4)');
        $i->canSee('—', '#attempts > tbody > tr:nth-child(2) > td:nth-child(4)');
        $i->cantSee('10/10', '#attempts > tbody > tr:nth-child(2) > td:nth-child(5)');
    }

    /**
     * Test bulk mark as reviewed
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function testQuizBulkReview(AcceptanceTester $i): void
    {
        $className = Common::CLASS_9AENG;
        $classId = Common::CLASS_ID_9AENG;
        $student1 = Common::USER_LOUISASMITH;
        $student1Id = Common::USER_ID_LOUISASMITH;
        $student2 = Common::USER_JAMALALI;
        $teacher = Common::USER_JACKCOLES;
        $quizTitle = 'Cest Three ' . Common::gentext(6);

        $i->amGoingTo('As Jack Coles, create a quiz');
        $i->impersonate($teacher);
        $this->createQuiz(
            $i,
            [$student1, $student2],
            $classId,
            $className,
            $teacher,
            $quizTitle,
            AssessmentForm::VISIBILITY_STAFF_AND_STUDENT
        );

        $i->amGoingTo('As Jack Coles, review attempt');
        $i->canSee('Attempted on time', StatusPage::getStatusSelector($student1Id, ''));

        $i->amGoingTo('Bulk Review');
        $statusPage = new StatusPage($i);
        $statusPage->bulkReview($quizTitle);

        $i->amGoingTo('wait 5 seconds and should receive upcoming notification');
        $i->wait(5);
        $i->amGoingTo('As Louisa Smith, check notification');
        $i->impersonateViaModifyUserPage($student1);
        $i->assertNotifExists($teacher . ' has marked your attempt of ' . $quizTitle . ' in ' . $className);

        $i->amGoingTo('As Louisa Smith, click into the due work');
        $i->click($quizTitle, '#content');
        $i->canSee('ATTEMPTS');
    }

    /**
     * Test changing quiz grade visibility
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function testQuizGradeVisibility(AcceptanceTester $i): void
    {
        $className = Common::CLASS_9AENG;
        $classId = Common::CLASS_ID_9AENG;
        $student1 = Common::USER_LOUISASMITH;
        $student1Id = Common::USER_ID_LOUISASMITH;
        $student2 = Common::USER_JAMALALI;
        $teacher = Common::USER_JACKCOLES;
        $quizTitle = 'Cest Four ' . Common::gentext(6);

        $i->amGoingTo('adding a quiz component into the homepage');
        $i->amOnPage('/homepage/83');
        $addComponent = new Homepage($i);
        $addComponent->addComponent($addComponent::ELEMENT_ADD_COMPONENT_SECTION_LEARNING, 'Quizzes');

        $i->amGoingTo('As Jack Coles, create a quiz');
        $i->impersonate($teacher);
        $this->createQuiz(
            $i,
            [$student1, $student2],
            $classId,
            $className,
            $teacher,
            $quizTitle,
            AssessmentForm::VISIBILITY_STAFF_ONLY
        );

        $i->amGoingTo('As Jack Coles, review attempt');
        $i->canSee('Attempted on time', StatusPage::getStatusSelector($student1Id, ''));

        $statusPage = new StatusPage($i);
        $reviewForm = $statusPage->clickReviewAttempt($student1Id, $quizTitle, $student1);
        // TODO: check whether the displayed answer is what has been entered.
        // TODO: enter something
        $reviewForm->finalise();

        $i->amGoingTo('wait 5 seconds and should receive upcoming notification');
        $i->wait(5);
        $i->amGoingTo('As Louisa Smith, check notification');
        $i->impersonateViaModifyUserPage($student1);
        $i->assertNotifNotExists($teacher . ' has marked your attempt of ' . $quizTitle . ' in ' . $className);

        $i->amGoingTo('As Louisa Smith, click into the assessment');
        $i->click($quizTitle, '#content');
        $i->canSee('ATTEMPTS');
        $i->amGoingTo('Louisa Smith shouldnt see any grades');
        $year = date("Y");
        $i->cantSee($year, '#attempts > tbody > tr:nth-child(1) > td:nth-child(4)');
        $i->cantSee('10/10', '#attempts > tbody > tr:nth-child(1) > td:nth-child(5)');

        $i->amGoingTo('As Jack Coles, change visibility');
        $i->impersonate($teacher);
        $i->amOnPage('/homepage/' . $classId);
        $i->click($quizTitle, '#content');
        $i->waitForText('Begin Quiz');

        $quizPage = new AssessmentPage($i);
        $quizForm = $quizPage->clickModify();

        $i->amGoingTo('change visibility');
        $quizForm->setVisibility(AssessmentForm::VISIBILITY_STAFF_AND_STUDENT)
            ->update();

        $i->amGoingTo('wait 5 seconds and should receive upcoming notification');
        $i->wait(5);
        $i->amGoingTo('As Louisa Smith, check notification');
        $i->impersonateViaModifyUserPage($student1);
        sleep(5);
        $i->amOnPage('/messages');
        $i->assertNotifExists($teacher . ' has marked your attempt of ' . $quizTitle . ' in ' . $className);

        $i->amGoingTo('As Louisa Smith, click into the due work');
        $i->click($quizTitle, '#content');
        $i->canSee('ATTEMPTS');
        $i->amGoingTo('should see grade');
        $year = date("Y");
        $i->canSee($year, '#attempts > tbody > tr:nth-child(1) > td:nth-child(4)');
        $i->canSee('10/10', '#attempts > tbody > tr:nth-child(1) > td:nth-child(5)');
    }


    /**
     * Internal steps to create a quiz
     * @param AcceptanceTester $i
     * @param array            $students
     * @param int              $classId
     * @param string           $className
     * @param String           $teacher
     * @param String           $quizTitle
     * @param int              $visibility
     * @throws Exception
     */
    private function createQuiz(
        AcceptanceTester $i,
        array $students,
        int $classId,
        string $className,
        string $teacher,
        string $quizTitle,
        int $visibility
    ): void {
        $quizForm = new AssessmentForm($i);
        $i->amOnPage($quizForm->generateRoute($classId));

        $i->amGoingTo('create a quiz');
        $quizForm->setWorkType($quizForm::WORKTYPE_ASSESSMENT_TASK)
            ->setVisibility($visibility)
            ->setMaxAttempts(0)
            ->setTitle($quizTitle)
            ->setPublishDate(\Alaress_Date_Format::now()->setTime(0, 0, 0))
            ->setOpenDate(\Alaress_Date_Format::now()->setTime(0, 0, 0))
            ->create();

        $i->amGoingTo('add a section');
        $sectionForm = new SectionForm($i);
        $sectionTitle1 = 'Cest Section ' . Common::gentext(6);
        $sectionForm->setTitle($sectionTitle1)
            ->create();

        $i->amGoingTo('add a question');
        $questionForm = new QuestionForm($i);
        $question1 = 'What kind of food do you like? ' . time();
        $correctAnswer = Common::gentext(5);
        $wrongAnswer = Common::gentext(6);
        $questionForm->setType($questionForm::TYPE_EXACT_ANSWER)
            ->setAnswer($correctAnswer)
            ->setMark(10)
            ->setQuestion($question1)
            ->create();

        $i->amGoingTo('finalize the question');
        $sectionForm->finalize();

        $i->amGoingTo('wait 5 seconds and should receive upcoming notification');
        $i->wait(5);
        $i->amGoingTo('As Louisa Smith, check notification');
        $attemptForm = new AttemptForm($i);
        foreach ($students as $student) {
            $i->impersonateViaModifyUserPage($student);
            $i->assertNotifExists('Assessment Task ' . $quizTitle . ' opened in ' . $className);

            $i->amGoingTo('create attempt');
            $attemptForm->createAttempt($quizTitle, $wrongAnswer);

            $i->amGoingTo('another attempt (some how this is easier way to come back to the quiz page)');
            $i->amOnPage('/messages');
            $attemptForm->createAttempt($quizTitle, $correctAnswer);
        }

        $i->amGoingTo('wait 5 seconds and should receive upcoming notification');
        $i->wait(5);
        $i->amGoingTo('As Jack Coles, check notification');
        $i->impersonate($teacher);
        // TODO:: the following works only for two students
        // @see schoolbox/library/Schoolbox/Realtime/Notification/Decorator.php
        sort($students);
        $i->assertNotifExists(implode(' and ', $students) . ' attempted ' . $quizTitle . ' in ' . $className);

        $i->amGoingTo('As Jack Coles, click into the quiz -> brings to status page');
        $i->click($quizTitle, '#content');
        $i->waitForText('Status of ' . $quizTitle);
    }
}
