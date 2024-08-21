<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\ISummator;
use Schoolbox\Learning\Assessment\Summator\Maximum;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
use Schoolbox\Learning\Assessment\Test\ParticipantLinker;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\Completion;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Model as GradeLevel;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizFeedback;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Project;
use InvalidArgumentException;
use DomainException;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\User;

class FactoryTest extends TestCase
{
    /**
     * @param Task $task
     *
     * @throws InvalidArgumentException If assessment class not registered
     * @throws DomainException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTaskMarkTypeInvalid')]
    public function testTaskMarkTypeInvalid(Task $task)
    {
        $this->expectException(DomainException::class);
        $factory = new MarkSchemeFactory();
        $factory->spawnMarkSchemeFor($task);
    }


    /**
     * @param Task   $task
     * @param string $expectedMarkScheme
     * @throws \PHPUnit\Framework\Exception
     * @throws DomainException If MarkType is invalid for the assessment
     * @throws InvalidArgumentException If assessment class not registered
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTaskMarkTypeValid')]
    public function testTaskMarkTypeValid(Task $task, $expectedMarkScheme)
    {
        $factory    = new MarkSchemeFactory();
        $markScheme = $factory->spawnMarkSchemeFor($task);
        $this->assertInstanceOf($expectedMarkScheme, $markScheme);
    }


    /**
     * @param Quiz $quiz
     *
     * @throws DomainException If MarkType is invalid for the assessment
     * @throws InvalidArgumentException If assessment class not registered
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerQuizMarkTypeInvalid')]
    public function testQuizMarkTypeInvalid(Quiz $quiz)
    {
        $this->expectException(DomainException::class);
        $factory = new MarkSchemeFactory();
        $factory->spawnMarkSchemeFor($quiz);
    }


    /**
     * @param Quiz   $quiz
     * @param string $expectedMarkScheme
     * @throws DomainException If MarkType is invalid for the assessment
     * @throws InvalidArgumentException If assessment class not registered
     * @throws \PHPUnit\Framework\Exception
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerQuizMarkTypeValid')]
    public function testQuizMarkTypeValid(Quiz $quiz, $expectedMarkScheme)
    {
        $factory    = new MarkSchemeFactory();
        $markScheme = $factory->spawnMarkSchemeFor($quiz);
        $this->assertInstanceOf($expectedMarkScheme, $markScheme);
    }


    /**
     * @param DueWork $dueWork
     *
     * @throws DomainException If MarkType is invalid for the assessment
     * @throws InvalidArgumentException If assessment class not registered
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDueWOrkMarkTypeInvalid')]
    public function testDueWorkMarkTypeInvalid(DueWork $dueWork)
    {
        $this->expectException(DomainException::class);
        $factory = new MarkSchemeFactory();
        $factory->spawnMarkSchemeFor($dueWork);
    }


    /**
     * @param DueWork $dueWork
     * @param string  $expectedMarkScheme
     * @throws DomainException If MarkType is invalid for the assessment
     * @throws InvalidArgumentException If assessment class not registered
     * @throws \PHPUnit\Framework\Exception
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDueWorkMarkTypeValid')]
    public function testDueWorkMarkTypeValid(DueWork $dueWork, $expectedMarkScheme)
    {
        $factory    = new MarkSchemeFactory();
        $markScheme = $factory->spawnMarkSchemeFor($dueWork);
        $this->assertInstanceOf($expectedMarkScheme, $markScheme);
    }


    /**
     * @param Project $project
     *
     * @throws DomainException If MarkType is invalid for the assessment
     * @throws InvalidArgumentException If assessment class not registered
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerProjectMarkTypeInvalid')]
    public function testProjectMarkTypeInvalid($project)
    {
        $this->expectException(DomainException::class);
        $factory = new MarkSchemeFactory();
        $factory->spawnMarkSchemeFor($project);
    }


    /**
     * @param Project $project
     * @param string  $expectedMarkScheme
     * @throws DomainException If MarkType is invalid for the assessment
     * @throws InvalidArgumentException If assessment class not registered
     * @throws \PHPUnit\Framework\Exception
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerProjectMarkTypeValid')]
    public function testProjectMarkTypeValid($project, $expectedMarkScheme)
    {
        $factory    = new MarkSchemeFactory();
        $markScheme = $factory->spawnMarkSchemeFor($project);
        $this->assertInstanceOf($expectedMarkScheme, $markScheme);
    }

    // ==================== Data Providers ====================


    /**
     * @return Task[][]
     * @throws InvalidArgumentException If $slug is not recognised
     */
    public function providerTaskMarkTypeInvalid(): array
    {
        return [
            [(new Task())->setMarkType($this->getDummyMarkType(MarkType::TYPE_PERCENT))],
            [(new Task())->setMarkType($this->getDummyMarkType(MarkType::TYPE_LETTER))],
            [(new Task())->setMarkType($this->getDummyMarkType(MarkType::TYPE_TOTAL))],
            [(new Task())->setMarkType($this->getDummyMarkType(MarkType::TYPE_FREE))],
            [(new Task())->setMarkType($this->getDummyMarkType(MarkType::TYPE_RUBRIC))],
            [(new Task())->setMarkType($this->getDummyMarkType(MarkType::TYPE_NONE))],
        ];
    }


    /**
     * @return array<array{0: Task, 1: class-string}>
     * @throws InvalidArgumentException If $slug is not recognised
     */
    public function providerTaskMarkTypeValid(): array
    {
        return [
            [(new Task())->setMarkType($this->getDummyMarkType(MarkType::TYPE_COMPLETION)), Completion::class],
        ];
    }


    /**
     * @return Quiz[][]
     * @throws InvalidArgumentException If $slug is not recognised
     */
    public function providerQuizMarkTypeInvalid(): array
    {
        return [
            [$this->getDummyQuiz()->setMarkType($this->getDummyMarkType(MarkType::TYPE_FREE))],
            [$this->getDummyQuiz()->setMarkType($this->getDummyMarkType(MarkType::TYPE_RUBRIC))],
            [$this->getDummyQuiz()->setMarkType($this->getDummyMarkType(MarkType::TYPE_NONE))],
            [$this->getDummyQuiz()->setMarkType($this->getDummyMarkType(MarkType::TYPE_COMPLETION))],
        ];
    }


    /**
     * @return array<array{0: Quiz, 1: class-string}>
     * @throws InvalidArgumentException If $slug is not recognised
     */
    public function providerQuizMarkTypeValid(): array
    {
        return [
            [$this->getDummyQuiz()->setMarkType($this->getDummyMarkType(MarkType::TYPE_TOTAL)), TotalMarkFractional::class],
        ];
    }


    /**
     * @return DueWork[][]
     * @throws InvalidArgumentException If $slug is not recognised
     */
    public function providerDueWorkMarkTypeInvalid(): array
    {
        return [
            [(new DueWork())->setMarkType($this->getDummyMarkType(MarkType::TYPE_FREE))],
            [(new DueWork())->setMarkType($this->getDummyMarkType(MarkType::TYPE_RUBRIC))],
            [(new DueWork())->setMarkType($this->getDummyMarkType(MarkType::TYPE_COMPLETION))],
        ];
    }


    /**
     * @return array<array{0: DueWork, 1: class-string}>
     * @throws InvalidArgumentException If $slug is not recognised
     */
    public function providerDueWorkMarkTypeValid(): array
    {
        return [
            [(new DueWork())->setMarkType($this->getDummyMarkType(MarkType::TYPE_PERCENT)), Percentage::class],
            [
                (new DueWork())->setMarkType($this->getDummyMarkType(MarkType::TYPE_LETTER))->setGradeGroup($this->getDummyLetterGradeGroup()),
                LetterGrade::class,
            ],
            [(new DueWork())->setMarkType($this->getDummyMarkType(MarkType::TYPE_TOTAL))->setMaxMark(50), TotalMarkFractional::class],
            [(new DueWork())->setMarkType($this->getDummyMarkType(MarkType::TYPE_TOTAL))->setMaxMark(49.99), TotalMarkFractional::class],
            [(new DueWork())->setMarkType($this->getDummyMarkType(MarkType::TYPE_NONE)), None::class],
        ];
    }


    /**
     * @return Project[][]
     * @throws InvalidArgumentException If $slug is not recognised
     */
    public function providerProjectMarkTypeInvalid(): array
    {
        $projects = [];
        foreach ($this->getSummators() as $summator) {
            $projects[] = [(new Project($summator))->setMarkType($this->getDummyMarkType(MarkType::TYPE_TOTAL))];
            $projects[] = [(new Project($summator))->setMarkType($this->getDummyMarkType(MarkType::TYPE_FREE))];
            $projects[] = [(new Project($summator))->setMarkType($this->getDummyMarkType(MarkType::TYPE_RUBRIC))];
            $projects[] = [(new Project($summator))->setMarkType($this->getDummyMarkType(MarkType::TYPE_COMPLETION))];
        }
        return $projects;
    }


    /**
     * @return array<array{0: Project, 1: class-string}>
     * @var Project     $projects [][0]
     * @var IMarkScheme $projects [][1]
     * @throws InvalidArgumentException If $slug is not recognised
     */
    public function providerProjectMarkTypeValid(): array
    {
        $projects = [];
        foreach ($this->getSummators() as $summator) {
            // Percentage
            $project = new Project($summator);
            $project->setMarkType($this->getDummyMarkType(MarkType::TYPE_PERCENT));
            $projects[] = [$project, Percentage::class];

            // Letter Grade
            $project = new Project($summator);
            $project->setMarkType($this->getDummyMarkType(MarkType::TYPE_LETTER));
            $project->setGradeGroup($this->getDummyLetterGradeGroup());
            $projects[] = [$project, LetterGrade::class];

            // Nonce
            $project = new Project($summator);
            $project->setMarkType($this->getDummyMarkType(MarkType::TYPE_NONE));
            $projects[] = [$project, None::class];
        }
        return $projects;
    }


    /**
     * @return ISummator[]
     */
    private function getSummators(): array
    {
        return [
            new Average(),
            new Maximum(),
            new Minimum(),
            new WeightedAverage(),
        ];
    }


    /**
     * @param int $id
     * @return MarkType
     * @throws InvalidArgumentException If $id is not recognised
     */
    private function getDummyMarkType(int $id): MarkType
    {
        $markType = new MarkType();
        $markType->setId($id);

        switch ($id) {
            case MarkType::TYPE_PERCENT:
                return $markType
                    ->setName('Percentage (%)')
                    ->setSlug(MarkType::SLUG_PERCENT)
                    ->setComment('This should be a whole percentage between 0 and 100.')
                    ->setEnabled(true);
            case MarkType::TYPE_LETTER:
                return $markType
                    ->setName('Letter Grade')
                    ->setSlug(MarkType::SLUG_LETTER)
                    ->setComment('Se1ect a letter grade from the list.')
                    ->setEnabled(true);
            case MarkType::TYPE_TOTAL:
                return $markType
                    ->setName('Total Mark')
                    ->setSlug(MarkType::SLUG_TOTAL)
                    ->setComment('This should be a mark between 0 and {MAX_MARK}. It may be a whole number or a decimal.')
                    ->setEnabled(true);
            case MarkType::TYPE_FREE:
                return $markType
                    ->setName('Free Type (legacy support)')
                    ->setSlug(MarkType::SLUG_FREE)
                    ->setComment('Should be a letter (A), score (15/20), or percentage (88%).')
                    ->setEnabled(false);
            case MarkType::TYPE_RUBRIC:
                return $markType
                    ->setName('Rubric')
                    ->setSlug(MarkType::SLUG_RUBRIC)
                    ->setComment('To mark the Rubric you <b>must</b> select one result for each criterion. Simply click the squares to select the results.')
                    ->setEnabled(false);
            case MarkType::TYPE_NONE:
                return $markType
                    ->setName('None')
                    ->setSlug(MarkType::SLUG_NONE)
                    ->setComment('This due work is not marked.')
                    ->setEnabled(true);
            case MarkType::TYPE_COMPLETION:
                return $markType
                    ->setName('Completion')
                    ->setSlug(MarkType::SLUG_COMPLETION)
                    ->setComment("Either 'Completed' or 'Not Completed'")
                    ->setEnabled(false);
            default:
                throw new InvalidArgumentException("Unknown MarkTypeId: $id");
        }
    }


    /**
     * @return GradeGroup
     */
    private function getDummyLetterGradeGroup(): GradeGroup
    {
        $gradeGroup = new GradeGroup();
        $gradeGroup->setId(3);
        $gradeGroup->setName('Competency');
        $gradeGroup->setGradeLevels(new GenericCollection(GradeLevel::class));

        $levelDatums = [
            ['13', '0', '20', 'Needs Attention'],
            ['14', '40', '53', 'Acceptable'],
            ['15', '60', '68', 'Good'],
            ['16', '75', '88', 'Very Good'],
            ['17', '90', '95', 'Excellent'],
        ];
        foreach ($levelDatums as $levelDatum) {
            $level = new GradeLevel();
            $level->setId($levelDatum[0]);
            $level->setLowerBound($levelDatum[1]);
            $level->setRawGrade($levelDatum[2]);
            $level->setCaption($levelDatum[3]);
            $level->setGroup($gradeGroup);

            $gradeGroup->addGradeLevel($level);
        }
        return $gradeGroup;
    }


    /**
     * Generate a Quiz with attached Attempts, Feedbacks, Questions + Responses
     *
     * Quiz does not yet have a MarkType or MarkScheme attached, that is caller's responsibility
     *
     * The quiz logically has 1 section with 3 questions worth 1 mark each.
     * The quiz has 1 attempt with 2 answers, 1 of which is correct (ie norm mark = 50.0)
     */
    private function getDummyQuiz()
    {
        $question1 = [
            'question_id' => 1,
            'section_id'  => 1,
            'sequence'    => 1,
            'type_id'     => 1,
            'marks'       => 1.0,
            'question'    => '<div class=\"ff4\">What does Jerome (Vincent) place on the comb at his workstation?</div>',
            'config'      => null,
            'deleted_at'  => null,
        ];
        $question2 = [
            'question_id' => 2,
            'section_id'  => 1,
            'sequence'    => 2,
            'type_id'     => 1,
            'marks'       => 1.0,
            'question'    => '<div class=\"ff4\">They used to say that a child conceived in love has a greater chance</div>',
            'config'      => null,
            'deleted_at'  => null,
        ];

        $response1 = [
            'response_id'        => 1,
            'attempt_id'         => 1,
            'section_id'         => 1,
            'question_id'        => 1,
            'response'           => 'toothpaste',
            'comment'            => "No, that's not the correct answer",
            'mark'               => 0.0,
            'automarked_already' => 'false',
            'updated_at'         => '2012-07-04 13:42:15',
            'deleted_at'         => null,
        ];

        $response2 = [
            'response_id'        => 2,
            'attempt_id'         => 1,
            'section_id'         => 1,
            'question_id'        => 2,
            'response'           => 'Being happy',
            'comment'            => "Correct. Although the correct term is 'Happiness'",
            'mark'               => 1.0,
            'automarked_already' => 'false',
            'updated_at'         => '2012-07-04 13:42:30',
            'deleted_at'         => null,
        ];

        $quiz     = new Quiz();
        $attempt  = new QuizAttempt();
        $feedback = new QuizFeedback();

        $linker      = new ParticipantLinker();
        $plan        = $linker->createAndLinkPlan($quiz);
        $participant = $linker->createAndLinkParticipant($this->getDummyQuizAttempter(), $plan);
        $participant->getAllResponses()->add($attempt);
        $participant->getAllFeedbacks()->add($feedback);

        $quiz->getAllParticipants()->add($participant);

        // Link them all together
        $feedback->setResponse($attempt);
        $feedback->setAssessable($quiz);
        $attempt->getAllFeedback()->add($feedback);
        $attempt->setAssessable($quiz);

        // Inject data
        $responses  = [
            [
                'mark'      => $response1['mark'],
                'available' => $question1['marks'],
            ],
            [
                'mark'      => $response2['mark'],
                'available' => $question2['marks'],
            ],
        ];
        $marksGiven = $marksAvailable = 0;
        foreach ($responses as $response) {
            $marksGiven     += $response['mark'];
            $marksAvailable += $response['available'];
        }

        $attempt->setMarksAvailable($marksAvailable);

        /* Presume Total Mark Fractional mark scheme for historical data,
         * causing human readable mark to be cached in the format "$numerator / $denominator"
         */
        $attempt->setHumanReadableMark("{$marksGiven} / {$marksAvailable}")
            ->setNormalisedMark(100 * $marksGiven / $marksAvailable);

        return $quiz;
    }


    /**
     * Generate a dummy User instance
     * This person "attempted the quiz"
     *
     * @return mixed
     */
    private function getDummyQuizAttempter()
    {
        return (new User())->setId(19);
    }
}
