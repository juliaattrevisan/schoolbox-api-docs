<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Learning\Assessment\Dehydrator;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\User;

/**
 * Class DehydratorTest
 */
class DehydratorTest extends TestCase
{
    /**
     * @var Dehydrator
     */
    private $dehydrator;

    /**
     * @var ParticipantLinker
     */
    private $linker;

    /**
     * These fields are common to dehydrations of all assessables.
     */
    private $commonFields = [
        'id'             => true,
        'type'           => true,
        'title'          => true,
        'weight'         => true,
        'markVisibility' => true,
        'workType'       => true,
        'participants'   => true,
    ];

    /**
     * Setup stuff
     */
    public function setUp()
    {
        $this->dehydrator = new Dehydrator();
        $this->linker     = new ParticipantLinker();
    }

    /**
     * Test dehydration of common fields
     */
    public function testDehydrateAssessmentCommonFields(): void
    {
        // Create everything
        $dueWork = (new DueWork())
            ->setId(3)
            ->setWeight(66)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setTitle('Test Due Work')
            ->setMarkVisibility(DueWork::MARK_VISIBILITY_PARENT_STUDENT)
            // TODO move/copy this const to DueWork
            ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_NONE);

        // I'm only interested in the common fields for all assessment types,
        // as defined in $this->commonFields.
        $expected = [
            'id'             => $dueWork->getId(),
            'type'           => $dueWork->getFamilyName(),
            'title'          => $dueWork->getTitle(),
            'weight'         => $dueWork->getWeight(),
            'markVisibility' => $dueWork->getMarkVisibility(),
            'workType'       => null,
            'participants'   => [],
        ];
        $actual = $this->dehydrator->dehydrateAssessment($dueWork);
        $this->assertEquals($expected, array_intersect_key($actual, $this->commonFields));
    }

    /**
     * Test dehydration of due-work-specific fields
     */
    public function testDehydrateAssessmentDueWorkFields(): void
    {
        // Create everything
        $dueWork = (new DueWork())
            ->setId(3)
            ->setMarkVisibility(DueWork::MARK_VISIBILITY_PARENT_STUDENT)
            // TODO move/copy this const to DueWork
            ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_NONE);

        // I'm only interested in the specific fields for this assessment type.
        $expected = [
            'acceptOnline'      => $dueWork->getAcceptOnline(),
            'selfReviewability' => $dueWork->getSelfReviewability(),
        ];
        $actual = $this->dehydrator->dehydrateAssessment($dueWork);
        $this->assertEquals($expected, array_diff_key($actual, $this->commonFields));
    }

    /**
     * Test dehydration of quiz-specific fields
     */
    public function testDehydrateAssessmentQuizFields(): void
    {
        // Create everything
        $quiz = (new Quiz())
            ->setId(3)
            ->setWeight(66)
            ->setTitle('Test Quiz')
            ->setMarkVisibility(DueWork::MARK_VISIBILITY_PARENT_STUDENT)
            ->setDuration(120)
            ->setMaxAttempts(5);

        // I'm only interested in the specific fields for this assessment type.
        $expected = [
            'duration'    => $quiz->getDuration(),
            'maxAttempts' => $quiz->getMaxAttempts(),
        ];
        $actual = $this->dehydrator->dehydrateAssessment($quiz);
        $this->assertEquals($expected, array_diff_key($actual, $this->commonFields));
    }

    /**
     * Test dehydration of task-specific fields
     */
    public function testDehydrateAssessmentTaskFields(): void
    {
        // Create everything
        $task = (new Task())
            ->setId(3)
            ->setTitle('Test Task')
            ->setIsSelfAssessable(true)
        ;

        // Don't bother testing the default fields, as they all match
        // I'm more interested in testing participants
        $expected = [
            'selfAssessable' => $task->getIsSelfAssessable(),
        ];

        $actual = $this->dehydrator->dehydrateAssessment($task);
        $this->assertEquals($expected, array_diff_key($actual, $this->commonFields));
    }

    /**
     * Test dehydration of project-specific fields
     */
    public function testDehydrateAssessmentProjectFields(): void
    {
        // Create everything
        $project = (new Project())
            ->setId(1)
            ->setTitle('Project')
            ->setDescription('Test Project description')
            ->setPublishAt(new \Alaress_Date_Format('2018-01-01 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new \Alaress_Date_Format('2018-01-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new \Alaress_Date_Format('2018-01-03 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new \Alaress_Date_Format('2018-01-04 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));
        $task    = (new Task())
            ->setId(11)
            ->setTitle('Task')
            ->setDescription('Test Task description')
            ->setPublishAt(new \Alaress_Date_Format('2018-02-01 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new \Alaress_Date_Format('2018-02-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new \Alaress_Date_Format('2018-02-03 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new \Alaress_Date_Format('2018-02-04 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));
        $quiz    = (new Quiz())
            ->setId(12)
            ->setTitle('Quiz')
            ->setDescription('Test Quiz description')
            ->setPublishAt(new \Alaress_Date_Format('2018-03-01 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new \Alaress_Date_Format('2018-03-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new \Alaress_Date_Format('2018-03-03 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new \Alaress_Date_Format('2018-03-04 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));
        $dueWork = (new DueWork())
            ->setId(13)
            ->setTitle('Due Work')
            ->setDescription('Test Due Work description')
            ->setPublishAt(new \Alaress_Date_Format('2018-04-01 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new \Alaress_Date_Format('2018-04-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new \Alaress_Date_Format('2018-04-03 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new \Alaress_Date_Format('2018-04-04 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        // Set up participation?
        $userA = (new User())
            ->setId(1)
            ->setFirstname('Aaron')
            ->setLastname('Aaronson');
        $userB = (new User())
            ->setId(2)
            ->setFirstname('Barry')
            ->setLastname('Barryson');
        $userC = (new User())
            ->setId(3)
            ->setFirstname('Carl')
            ->setLastname('Carlson');

        $taskPlan = $this->linker->createAndLinkPlan($task, true);
        $this->linker->createAndLinkParticipant($userA, $taskPlan);
        $this->linker->createAndLinkParticipant($userB, $taskPlan);

        $dueWorkPlan = $this->linker->createAndLinkPlan($dueWork, true);
        $this->linker->createAndLinkParticipant($userA, $dueWorkPlan);
        $this->linker->createAndLinkParticipant($userC, $dueWorkPlan);

        $quizPlan = $this->linker->createAndLinkPlan($quiz, true);
        $this->linker->createAndLinkParticipant($userB, $quizPlan);
        $this->linker->createAndLinkParticipant($userC, $quizPlan);

        $project->getAssessables()
            ->add($task)
            ->add($dueWork)
            ->add($quiz);

        $actual = $this->dehydrator->dehydrateAssessment($project);

        // Test activity titles
        $this->assertEquals($task->getTitle(), $actual['activities'][$task->getId()]['title']);
        $this->assertEquals($dueWork->getTitle(), $actual['activities'][$dueWork->getId()]['title']);
        $this->assertEquals($quiz->getTitle(), $actual['activities'][$quiz->getId()]['title']);

        // Test activity participation
        $this->assertArrayHasKey($userA->getId(), $actual['activities'][$task->getId()]['participants']);
        $this->assertArrayHasKey($userB->getId(), $actual['activities'][$task->getId()]['participants']);
        $this->assertArrayNotHasKey($userC->getId(), $actual['activities'][$task->getId()]['participants']);

        $this->assertArrayHasKey($userA->getId(), $actual['activities'][$dueWork->getId()]['participants']);
        $this->assertArrayNotHasKey($userB->getId(), $actual['activities'][$dueWork->getId()]['participants']);
        $this->assertArrayHasKey($userC->getId(), $actual['activities'][$dueWork->getId()]['participants']);

        $this->assertArrayNotHasKey($userA->getId(), $actual['activities'][$quiz->getId()]['participants']);
        $this->assertArrayHasKey($userB->getId(), $actual['activities'][$quiz->getId()]['participants']);
        $this->assertArrayHasKey($userC->getId(), $actual['activities'][$quiz->getId()]['participants']);

    }

    /**
     * Test dehydration of participant-specific fields
     */
    public function testDehydrateAssessmentParticipantFields(): void
    {
        $dueWork = (new DueWork())
            ->setDescription('This is a description of a test due work')
            ->setPublishAt(new \Alaress_Date_Format('2018-01-01 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new \Alaress_Date_Format('2018-01-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new \Alaress_Date_Format('2018-01-03 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new \Alaress_Date_Format('2018-01-04 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $dynamicPlan = $this->linker->createAndLinkPlan($dueWork, true)
                                    ->setName('Dynamic Plan');
        $participantA = $this->linker->createAndLinkParticipant(
            (new User())
                ->setId(1)
                ->setFirstname('Aaron')
                ->setLastname('Aaronson'),
            $dynamicPlan
        );

        // Boot out Barry
        $participantB = $this->linker->createAndLinkParticipant(
            (new User())
                ->setId(2)
                ->setFirstname('Barry')
                ->setLastname('Barryson'),
            $dynamicPlan
        );
        $participantB->setExcludedAt(\Alaress_Date_Format::now());

        $customPlan = $this->linker->createAndLinkPlan($dueWork, false)
                                   ->setName('Custom Plan')
                                   ->setDescription('This is a custom description')
                                   ->setPublishDate(new \Alaress_Date_Format('2018-02-01 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
                                   ->setOpenDate(new \Alaress_Date_Format('2018-02-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
                                   ->setDueDate(new \Alaress_Date_Format('2018-02-03 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
                                   ->setCloseDate(new \Alaress_Date_Format('2018-02-04 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));
        $participantC = $this->linker->createAndLinkParticipant(
            (new User())
                ->setId(3)
                ->setFirstname('Custom')
                ->setLastname('Customson'),
            $customPlan
        );

        // Only care about participants here
        $expected = [
            'participants' => [
                $participantA->getUser()->getId() => [
                    'description' => $participantA->getDescription(),
                    'publishDate' => $participantA->getPublishDate()->getDatabase(),
                    'openDate'    => $participantA->getOpenDate()->getDatabase(),
                    'dueDate'     => $participantA->getDueDate()->getDatabase(),
                    'closeDate'   => $participantA->getCloseDate()->getDatabase(),
                    'excluded'    => $participantA->isExcluded(),
                    'plan'        => [
                        'id'      => $dynamicPlan->getId(),
                        'name'    => $dynamicPlan->getName(),
                        'dynamic' => $dynamicPlan->getIsDynamic(),
                    ],
                    'user'          => [
                        'id'        => $participantA->getUser()->getId(),
                        'title'     => $participantA->getUser()->getTitle(),
                        'firstname' => $participantA->getUser()->getFirstname(),
                        'lastname'  => $participantA->getUser()->getLastname(),
                    ]
                ],
                $participantB->getUser()->getId() => [
                    'description' => $participantB->getDescription(),
                    'publishDate' => $participantB->getPublishDate()->getDatabase(),
                    'openDate'    => $participantB->getOpenDate()->getDatabase(),
                    'dueDate'     => $participantB->getDueDate()->getDatabase(),
                    'closeDate'   => $participantB->getCloseDate()->getDatabase(),
                    'excluded'    => $participantB->isExcluded(),
                    'plan'        => [
                        'id'      => $dynamicPlan->getId(),
                        'name'    => $dynamicPlan->getName(),
                        'dynamic' => $dynamicPlan->getIsDynamic(),
                    ],
                    'user'          => [
                        'id'        => $participantB->getUser()->getId(),
                        'title'     => $participantB->getUser()->getTitle(),
                        'firstname' => $participantB->getUser()->getFirstname(),
                        'lastname'  => $participantB->getUser()->getLastname(),
                    ]
                ],
                $participantC->getUser()->getId() => [
                    'description' => $participantC->getDescription(),
                    'publishDate' => $participantC->getPublishDate()->getDatabase(),
                    'openDate'    => $participantC->getOpenDate()->getDatabase(),
                    'dueDate'     => $participantC->getDueDate()->getDatabase(),
                    'closeDate'   => $participantC->getCloseDate()->getDatabase(),
                    'excluded'    => $participantC->isExcluded(),
                    'plan'        => [
                        'id'      => $customPlan->getId(),
                        'name'    => $customPlan->getName(),
                        'dynamic' => $customPlan->getIsDynamic(),
                    ],
                    'user'          => [
                        'id'        => $participantC->getUser()->getId(),
                        'title'     => $participantC->getUser()->getTitle(),
                        'firstname' => $participantC->getUser()->getFirstname(),
                        'lastname'  => $participantC->getUser()->getLastname(),
                    ]
                ],
            ],
        ];

        $actual = $this->dehydrator->dehydrateAssessment($dueWork);
        $this->assertEquals(
            $expected['participants'][$participantA->getUser()->getId()],
            $actual['participants'][$participantA->getUser()->getId()]
        );
        $this->assertEquals(
            $expected['participants'][$participantB->getUser()->getId()],
            $actual['participants'][$participantB->getUser()->getId()]
        );
        $this->assertEquals(
            $expected['participants'][$participantC->getUser()->getId()],
            $actual['participants'][$participantC->getUser()->getId()]
        );
    }


    /**
     * Test dehydration of plan
     */
    public function testDehydratePlan(): void
    {
        $dueWork = (new DueWork())
            ->setDescription('This is a description of a test due work')
            ->setPublishAt(new \Alaress_Date_Format('2018-01-01 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new \Alaress_Date_Format('2018-01-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new \Alaress_Date_Format('2018-01-03 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new \Alaress_Date_Format('2018-01-04 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $plan = $this->linker->createAndLinkPlan($dueWork, true)
                             ->setName('Plan');
        $participantA = $this->linker->createAndLinkParticipant(
            (new User())
                ->setId(1)
                ->setFirstname('Aaron')
                ->setLastname('Aaronson'),
            $plan
        );

        // Boot out Barry
        $participantB = $this->linker->createAndLinkParticipant(
            (new User())
                ->setId(2)
                ->setFirstname('Barry')
                ->setLastname('Barryson'),
            $plan
        );
        $participantB->setExcludedAt(\Alaress_Date_Format::now());

        $expected = [
            'id'           => $plan->getId(),
            'name'         => $plan->getName(),
            'dynamic'      => $plan->getIsDynamic(),
            'participants' => [
                $participantA->getUser()->getId() => [
                    'description' => $participantA->getDescription(),
                    'publishDate' => $participantA->getPublishDate()->getDatabase(),
                    'openDate'    => $participantA->getOpenDate()->getDatabase(),
                    'dueDate'     => $participantA->getDueDate()->getDatabase(),
                    'closeDate'   => $participantA->getCloseDate()->getDatabase(),
                    'excluded'    => $participantA->isExcluded(),
                    'user'          => [
                        'id'        => $participantA->getUser()->getId(),
                        'title'     => $participantA->getUser()->getTitle(),
                        'firstname' => $participantA->getUser()->getFirstname(),
                        'lastname'  => $participantA->getUser()->getLastname(),
                    ]
                ],
                $participantB->getUser()->getId() => [
                    'description' => $participantB->getDescription(),
                    'publishDate' => $participantB->getPublishDate()->getDatabase(),
                    'openDate'    => $participantB->getOpenDate()->getDatabase(),
                    'dueDate'     => $participantB->getDueDate()->getDatabase(),
                    'closeDate'   => $participantB->getCloseDate()->getDatabase(),
                    'excluded'    => $participantB->isExcluded(),
                    'user'          => [
                        'id'        => $participantB->getUser()->getId(),
                        'title'     => $participantB->getUser()->getTitle(),
                        'firstname' => $participantB->getUser()->getFirstname(),
                        'lastname'  => $participantB->getUser()->getLastname(),
                    ]
                ],
            ],
            'assessment' => [
                'id'                => $dueWork->getId(),
                'type'              => $dueWork->getFamilyName(),
                'title'             => $dueWork->getTitle(),
                'weight'            => $dueWork->getWeight(),
                'markVisibility'    => $dueWork->getMarkVisibility(),
                'workType'          => null,
                'acceptOnline'      => $dueWork->getAcceptOnline(),
                'selfReviewability' => $dueWork->getSelfReviewability(),
            ],
        ];

        $this->assertEquals($expected, $this->dehydrator->dehydratePlan($plan));
    }


    /**
     * Test dehydration of an assessment into plans
     */
    public function testDehydrateAssessmentIntoPlans(): void
    {
        $dueWork = (new DueWork())
            ->setDescription('This is a description of a test due work')
            ->setPublishAt(new \Alaress_Date_Format('2018-01-01 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new \Alaress_Date_Format('2018-01-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new \Alaress_Date_Format('2018-01-03 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new \Alaress_Date_Format('2018-01-04 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $dynamicPlan = $this->linker->createAndLinkPlan($dueWork, true)
                                    ->setId(1)
                                    ->setName('Dynamic Plan');
        $participantA = $this->linker->createAndLinkParticipant(
            (new User())
                ->setId(1)
                ->setFirstname('Aaron')
                ->setLastname('Aaronson'),
            $dynamicPlan
        );

        // Boot out Barry
        $participantB = $this->linker->createAndLinkParticipant(
            (new User())
                ->setId(2)
                ->setFirstname('Barry')
                ->setLastname('Barryson'),
            $dynamicPlan
        );
        $participantB->setExcludedAt(\Alaress_Date_Format::now());

        $customPlan = $this->linker->createAndLinkPlan($dueWork, false)
                                   ->setId(2)
                                   ->setName('Custom Plan')
                                   ->setDescription('This is a custom description')
                                   ->setPublishDate(new \Alaress_Date_Format('2018-02-01 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
                                   ->setOpenDate(new \Alaress_Date_Format('2018-02-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
                                   ->setDueDate(new \Alaress_Date_Format('2018-02-03 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
                                   ->setCloseDate(new \Alaress_Date_Format('2018-02-04 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));
        $participantC = $this->linker->createAndLinkParticipant(
            (new User())
                ->setId(3)
                ->setFirstname('Custom')
                ->setLastname('Customson'),
            $customPlan
        );

        // Only care about plans, but verify all participants are in all plans
        $participantList = [
            $participantA->getUser()->getId() => [
                'description' => $participantA->getDescription(),
                'publishDate' => $participantA->getPublishDate()->getDatabase(),
                'openDate'    => $participantA->getOpenDate()->getDatabase(),
                'dueDate'     => $participantA->getDueDate()->getDatabase(),
                'closeDate'   => $participantA->getCloseDate()->getDatabase(),
                'excluded'    => $participantA->isExcluded(),
                'user'          => [
                    'id'        => $participantA->getUser()->getId(),
                    'title'     => $participantA->getUser()->getTitle(),
                    'firstname' => $participantA->getUser()->getFirstname(),
                    'lastname'  => $participantA->getUser()->getLastname(),
                ],
            ],
            $participantB->getUser()->getId() => [
                'description' => $participantB->getDescription(),
                'publishDate' => $participantB->getPublishDate()->getDatabase(),
                'openDate'    => $participantB->getOpenDate()->getDatabase(),
                'dueDate'     => $participantB->getDueDate()->getDatabase(),
                'closeDate'   => $participantB->getCloseDate()->getDatabase(),
                'excluded'    => $participantB->isExcluded(),
                'user'          => [
                    'id'        => $participantB->getUser()->getId(),
                    'title'     => $participantB->getUser()->getTitle(),
                    'firstname' => $participantB->getUser()->getFirstname(),
                    'lastname'  => $participantB->getUser()->getLastname(),
                ],
            ],
            $participantC->getUser()->getId() => [
                'description' => $participantC->getDescription(),
                'publishDate' => $participantC->getPublishDate()->getDatabase(),
                'openDate'    => $participantC->getOpenDate()->getDatabase(),
                'dueDate'     => $participantC->getDueDate()->getDatabase(),
                'closeDate'   => $participantC->getCloseDate()->getDatabase(),
                'excluded'    => $participantC->isExcluded(),
                'user'          => [
                    'id'        => $participantC->getUser()->getId(),
                    'title'     => $participantC->getUser()->getTitle(),
                    'firstname' => $participantC->getUser()->getFirstname(),
                    'lastname'  => $participantC->getUser()->getLastname(),
                ],
            ],
        ];
        $expected = [
            1 => [
                'id'           => $dynamicPlan->getId(),
                'name'         => $dynamicPlan->getName(),
                'dynamic'      => $dynamicPlan->getIsDynamic(),
                'participants' => $participantList,
                'assessment'   => [
                    'id'                => $dueWork->getId(),
                    'type'              => $dueWork->getFamilyName(),
                    'title'             => $dueWork->getTitle(),
                    'weight'            => $dueWork->getWeight(),
                    'markVisibility'    => $dueWork->getMarkVisibility(),
                    'workType'          => null,
                    'acceptOnline'      => $dueWork->getAcceptOnline(),
                    'selfReviewability' => $dueWork->getSelfReviewability(),
                ],
            ],
            2 => [
                'id'           => $customPlan->getId(),
                'name'         => $customPlan->getName(),
                'dynamic'      => $customPlan->getIsDynamic(),
                'participants' => $participantList,
                'assessment'   => [
                    'id'                => $dueWork->getId(),
                    'type'              => $dueWork->getFamilyName(),
                    'title'             => $dueWork->getTitle(),
                    'weight'            => $dueWork->getWeight(),
                    'markVisibility'    => $dueWork->getMarkVisibility(),
                    'workType'          => null,
                    'acceptOnline'      => $dueWork->getAcceptOnline(),
                    'selfReviewability' => $dueWork->getSelfReviewability(),
                ],
            ]
        ];

        $this->assertEquals($expected, $this->dehydrator->dehydrateAssessmentIntoPlans($dueWork));
    }
}
