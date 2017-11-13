<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test\DataModel\Translator;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Quiz\Model as QuizModel;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Test\DataModel\TestTrait;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * Tests for the assessment data model translator (quizzes only).
 */
class QuizTest extends TestCase
{
    use TestTrait;

    /**
     * Test translation to quiz
     */
    public function testTranslateToDataModel()
    {
        $quiz = (new Quiz())
            // Generic fields
            ->setContext((new Folder())->setId(1))
            ->setId(61)
            ->setCreator($this->teacher2)
            ->setPublishAt(new Alaress_Date_Format('2016-10-14 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new Alaress_Date_Format('2016-10-16 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new Alaress_Date_Format('2016-10-18 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new Alaress_Date_Format('2016-10-23 13:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setTitle('Test Task κάτι')
            ->setDescription('Blah blah blah τίποτα')
            ->setWeight(65)
            // Specific fields
            ->setMaxAttempts(7)
            ->setDuration(1500)
            ->setCompletionMessage('You done the quiz, Συγχαρητήρια!')
        ;

        /** @var QuizModel $quizModel */
        $quizModel = $this->translator->translateToDataModel($quiz);

        // Test type
        $this->assertInstanceOf(QuizModel::class, $quizModel);

        // Test quiz-specific fields
        $this->assertEquals(151, $quizModel->getUser()->getId());
        $this->assertEquals(7, $quizModel->getMaxAttempts());
        $this->assertEquals(1500, $quizModel->getDuration());
        $this->assertEquals('You done the quiz, Συγχαρητήρια!', $quizModel->getCompletionMessage());
    }

    /**
     * Test translating quiz-specific fields to domain model
     */
    public function testTranslateToDomainModel()
    {
        $quizModel = (new QuizModel())
            ->setId(13)
            ->setAssessmentModel(
                (new AssessmentModel())
                    ->setId(105)
                    ->setAssessmentContext((new Folder())->setId(1))
            )
            ->setCompletionMessage('you done a quiz')
            ->setDuration(Quiz::DURATION_UNLIMITED)
            ->setMaxAttempts(Quiz::ATTEMPTS_UNLIMITED)
            ->setUser($this->teacher1)
        ;

        /** @var Quiz $quiz */
        $quiz = $this->translator->translateToDomainModel($quizModel);

        $this->assertInstanceOf(Quiz::class, $quiz);
        $this->assertEquals(105, $quiz->getId());
        $this->assertEquals('you done a quiz', $quiz->getCompletionMessage());
        $this->assertEquals(Quiz::DURATION_UNLIMITED, $quiz->getDuration());
        $this->assertEquals(Quiz::ATTEMPTS_UNLIMITED, $quiz->getMaxAttempts());
        $this->assertEquals(3, $quiz->getCreator()->getId());
    }
}
