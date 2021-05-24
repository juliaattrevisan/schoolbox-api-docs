<?php

/**
 * Summator
 *
 * This tests the Summator strategies such that their
 * accumulation methods work correctly
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Summator\ISummator;
use Schoolbox\Learning\Assessment\Summator\Average as SummatorAverage;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage as SummatorWeightedAverage;
use Schoolbox\Learning\Assessment\Summator\Maximum as SummatorMaximum;
use Schoolbox\Learning\Assessment\Summator\Minimum as SummatorMinimum;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as DueWorkSubmission;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;
use Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizReview;

class SummatorTest extends TestCase
{
    use Defaults;

    /**
     * A sample project containing IAssessables
     *
     * @var Project
     */
    protected $project;

    /**
     * For floating point comparisons
     *
     * See assertEquals() in https://phpunit.de/manual/current/en/appendixes.assertions.html
     *
     * @var float
     */
    protected $delta = 0.00000005;

    /**
     * Test the unweighted average
     * when there is nothing in here at all
     */
    public function testUnweightedAverage0()
    {
        $project = $this->spawnProject(new SummatorAverage());
        $project->setAssessables(new GenericCollection(IAssessable::class));

        $plan        = $this->getLinker()->createAndLinkPlan($project);
        $participant = $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);


        /* Most Recent Published Average is null
         */
        $this->assertEquals(null, $project->getNormalisedMark($participant, true));

        /* Most Recent Average is null
         */
        $this->assertEquals(null, $project->getNormalisedMark($participant, false));
    }


    /**
     * Test the unweighted average
     * when there is no feedback
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \DomainException
     */
    public function testUnweightedAverage1()
    {
        $project = $this->spawnProject(new SummatorAverage());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        foreach ($project->getAssessables() as $currentAssessable) {
            $currentAssessable->getParticipant($this->hodes)->setAllFeedbacks(
                $currentAssessable->getParticipant($this->hodes)->getAllFeedbacks()->slice(0, 0)
            );

            /** @var \Schoolbox\Learning\Assessment\IResponse $currentResponse */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllResponses() as $currentResponse) {
                $currentResponse->setAllFeedback($currentResponse->getAllFeedback()->slice(0, 0));
            }
        }

        /* Most Recent Published Average is null (nothing to calculate)
         */
        $this->assertEquals(null, $project->getNormalisedMark($project->getParticipant($this->hodes), true));

        // Quiz auto-marked to 60.0 (this constitutes a draft mark)
        $this->assertEquals(60.0, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * Test the unweighted average
     * when there is nothing published
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testUnweightedAverage2()
    {
        $project = $this->spawnProject(new SummatorAverage());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        foreach ($project->getAssessables() as $currentAssessable) {

            /** @var \Schoolbox\Learning\Assessment\IFeedback $currentFeedback */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllFeedbacks() as $currentFeedback) {
                if ($currentAssessable instanceof Task) {
                    $currentFeedback->getResponse()->setCreatedAt(null);
                } else {
                    $currentFeedback->setPublishedAt(null);
                }
            }

            /** @var \Schoolbox\Learning\Assessment\IResponse $currentResponse */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllResponses() as $currentResponse) {
                foreach ($currentResponse->getAllFeedback() as $currentFeedback) {
                    if ($currentAssessable instanceof Task) {
                        $currentFeedback->getResponse()->setCreatedAt(null);
                    } else {
                        $currentFeedback->setPublishedAt(null);
                    }
                }
            }
        }

        /* Most Recent Published Average is null (nothing to calculate)
         */
        $this->assertEquals(null, $project->getNormalisedMark($project->getParticipant($this->hodes), true));

        /* Most Recent Average is
         *      = [98 + null + 77 + 100 + (100 * 12/20)] / 4
         *      = [98 + 77 + 100 + 60] / 4
         *      = 335/4
         *      = 83.75
         */
        $this->assertEquals(83.75, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * Test the unweighted average
     * when there's one of each type with published and unpublished feedback
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testUnweightedAverage3()
    {
        /* There are three assessables for Hodes
         *
         * Due Work (100 marks)
         * Task (null)
         * Quiz (12 marks)
         */
        $project = $this->spawnProject(new SummatorAverage());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnTask())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        /* Most Recent Published Average is
         *      = [100 + (100 * 12/20)] / 2
         *      = [100 + 60] / 2
         *      = 80
         */
        $this->assertEquals(80, $project->getNormalisedMark($project->getParticipant($this->hodes), true));

        /* Most Recent Average is
         *      = [100 + (100 * 12/20)] / 2
         *      = [100 + 60] / 2
         *      = 80 (most recent feedback also happens to be most recent published feedback)
         */
        $this->assertEquals(80, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * Test the unweighted average
     * when there's more than one of a particular type with published and unpublished feedback
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testUnweightedAverage4()
    {
        /* There are five assessables for Hodes
         *
         * Due Work 3 (98 marks)
         * Task (null)
         * Due Work 2 (77 marks)
         * Due Work (100 marks)
         * Quiz (12 marks)
         */
        $project = $this->spawnProject(new SummatorAverage());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        /* Most Recent Published Average is
         *      = [null + 77 + 100 + (100 * 12/20)] / 3
         *      = [177 + 60] / 3
         *      = 79
         */
        $this->assertEquals(79, $project->getNormalisedMark($project->getParticipant($this->hodes), true));

        /* Most Recent Average is
         *      = [null + 77 + 100 + (100 * 5/20)] / 3
         *      = [177 + 60] / 3
         *      = 79 (most recent feedback also happens to be most recent published feedback)
         */
        $this->assertEquals(79, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * Test the unweighted average
     * when there's more than one of a particular type with published and unpublished feedback
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testUnweightedAverage5()
    {
        /* There are five assessables for Hodes
         *
         * Due Work 3 (98 marks)
         * Task (null marks)
         * Due Work 2 (77 marks)
         * Due Work (100 marks)
         * Quiz (12 marks)
         */
        $project = $this->spawnProject(new SummatorAverage());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        /* Most Recent Published Average is
         *      = [98 + 77 + 100 + (100 * 12/20)] / 4
         *      = [275 + 60] / 4
         *      = 83.75
         */
        $this->assertEquals(83.75, $project->getNormalisedMark($project->getParticipant($this->hodes), true));

        /* Most Recent Average is
         *      = [98 + 77 + 100 + (100 * 12/20)] / 4
         *      = [275 + 60] / 4
         *      = 83.75
         */
        $this->assertEquals(83.75, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * Test the weighted average
     * when there is no feedback
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \DomainException
     */
    public function testWeightedAverage1()
    {
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        foreach ($project->getAssessables() as $currentAssessable) {
            $currentAssessable->getParticipant($this->hodes)->setAllFeedbacks(
                $currentAssessable->getParticipant($this->hodes)->getAllFeedbacks()->slice(0, 0)
            );

            /** @var \Schoolbox\Learning\Assessment\IResponse $currentResponse */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllResponses() as $currentResponse) {
                $currentResponse->setAllFeedback($currentResponse->getAllFeedback()->slice(0, 0));
            }
        }

        /* Most Recent Published Weighted Average is null (nothing to calculate)
         */
        $this->assertEquals(null, $project->getNormalisedMark($project->getParticipant($this->hodes), true));

        // Quiz is auto-marked to 60.0 (this constitutes a draft-mark)
        $this->assertEquals(60.0, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * Test the weighted average
     * when there is nothing published
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testWeightedAverage2()
    {
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        foreach ($project->getAssessables() as $currentAssessable) {

            /** @var \Schoolbox\Learning\Assessment\IFeedback $currentFeedback */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllFeedbacks() as $currentFeedback) {
                if ($currentAssessable instanceof Task) {
                    $currentFeedback->getResponse()->setCreatedAt(null);
                } else {
                    $currentFeedback->setPublishedAt(null);
                }
            }

            /** @var \Schoolbox\Learning\Assessment\IResponse $currentResponse */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllResponses() as $currentResponse) {
                foreach ($currentResponse->getAllFeedback() as $currentFeedback) {
                    if ($currentAssessable instanceof Task) {
                        $currentFeedback->getResponse()->setCreatedAt(null);
                    } else {
                        $currentFeedback->setPublishedAt(null);
                    }
                }
            }
        }

        /* Most Recent Published Weighted Average is null (nothing to calculate)
         */
        $this->assertEquals(null, $project->getNormalisedMark($project->getParticipant($this->hodes), true));

        /* Most Recent Weighted Average is
         *      = [(98*6) + (null*0) + (77*10) + (100*5) + ((100 * 12/20)*8)] / 29
         *      = [588 + 0 + 770 + 500 + 480] / 29
         *      = 2338 / 29
         *      = 80.620689655
         */
        $value = $project->getNormalisedMark($project->getParticipant($this->hodes), false);
        $this->assertEqualsWithDelta(80.620689655, $value, $this->delta);
    }


    /**
     * Test the weighted average
     * when there's one of each type with published and unpublished feedback
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testWeightedAverage3()
    {
        /* There are three assessables for Hodes
         *
         * Due Work (100 marks)
         * Task (null marks)
         * Quiz (12 marks)
         */
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnTask())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        /* Most Recent Published Weighted Average is
         *      = [(100*5) + ((100 * 12/20)*8)] / 13
         *      = [500 + 480] / 13
         *      = 980 / 13
         *      = 75.38461538462
         */
        $value = $project->getNormalisedMark($project->getParticipant($this->hodes), true);
        $this->assertEqualsWithDelta(75.38461538462, $value, $this->delta);

        /* Most Recent Weighted Average is
         *      = [(100*5) + ((100 * 12/20)*8)] / 13
         *      = [500 + 480] / 13
         *      = 980 / 13
         *      = 75.38461538462
         */
        $value = $project->getNormalisedMark($project->getParticipant($this->hodes), false);
        $this->assertEqualsWithDelta(75.38461538462, $value, $this->delta);
    }


    /**
     * Test the weighted average
     * when there's more than one of a particular type with published and unpublished feedback
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testWeightedAverage4()
    {
        /* There are five assessables for Hodes
         *
         * Due Work 3 (98 marks)
         * Task (null marks)
         * Due Work 2 (77 marks)
         * Due Work (100 marks)
         * Quiz (12 marks)
         */
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        /* Most Recent Published Weighted Average is
         *      = [(77*10) + (100*5) + ((100 * 12/20)*8)] / 23
         *      = [770 + 500 + 480] / 23
         *      = 1750 / 23
         *      = 76.08695652174
         */
        $this->assertEquals(76.08695652174, $project->getNormalisedMark($project->getParticipant($this->hodes), true));

        /* Most Recent Weighted Average is
         *      = [(77*10) + (100*5) + ((100 * 12/20)*8)] / 23
         *      = [770 + 500 + 480] / 23
         *      = 1750 / 23
         *      = 76.08695652174
         */
        $this->assertEquals(76.08695652174, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * Test the weighted average
     * when there's more than one of a particular type with published and unpublished feedback
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testWeightedAverage5()
    {
        /* There are five assessables for Hodes
         *
         * Due Work 3 (98 marks)
         * Task (null marks)
         * Due Work 2 (77 marks)
         * Due Work (100 marks)
         * Quiz (12 marks)
         */
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        /* Most Recent Published Weighted Average is
         *      = [(98*6) + (77*10) + (100*5) + ((100 * 12/20)*8)] / 29
         *      = [588 + 770 + 500 + 480] / 29
         *      = 2338 / 29
         *      = 80.62068965517
         */
        $this->assertEquals(80.62068965517, $project->getNormalisedMark($project->getParticipant($this->hodes), true));

        /* Most Recent Weighted Average is
         *      = [(98*6) + (77*10) + (100*5) + ((100 * 12/20)*8)] / 29
         *      = [588 + 770 + 500 + 480] / 29
         *      = 2338 / 29
         *      = 80.62068965517
        */
        $this->assertEquals(80.62068965517, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }

    /** Really go to town */

    /**
     * A real belting
     *
     * @throws \UnexpectedValueException
     */
    public function testWeightedAverage10()
    {
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables($this->massProduce());

       /* Due Work 1: Weight 20, NormalisedMark: P,18 UP,18
        * Due Work 2: Weight 11, NormalisedMark: P,91 UP,91
        * Due Work 3: Weight  1, NormalisedMark: P,89 UP,89
        * Due Work 4: Weight  4, NormalisedMark: P,null UP,null
        * Due Work 5: Weight  4, NormalisedMark: P,null UP,76
        * Due Work 6: Weight  4, NormalisedMark: P,null UP,null
        * Due Work 7: Weight 22, NormalisedMark: P,96 UP,96
        *
        * Total Weight Published: 54
        * Total Weight UnPublished: 58
        *
        * Most Recent Published Weighted Average is:
        *       = [(18*20) + (91*11) + (89*1) + (96*22)] / 54
        *       = [360 + 1001 + 89 + 2112] / 54
        *       = 3562/54
        *       = 65.962962963
        *
        * Most Recent Weighted Average is:
        *       = [(18*20) + (91*11) + (89*1) + (76*4) + (96*22)] / 58
        *       = [360 + 1001 + 89 + 304 + 2112] / 58
        *       = 3866/58
        *       = 66.655172414
        */
        $value = $project->getNormalisedMark($project->getParticipant($this->hodes), true);
        $this->assertEqualsWithDelta(65.962962963, $value, $this->delta);
        $value = $project->getNormalisedMark($project->getParticipant($this->hodes), false);
        $this->assertEqualsWithDelta(66.655172414, $value, $this->delta);
    }


    /**
     * A real belting
     *
     * @throws \UnexpectedValueException
     */
    public function testWeightedAverage11()
    {
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables($this->massProduce());

        /* Reset all the weights to zero
         */
        foreach ($project->getAssessables() as $currentAssessable) {
            $currentAssessable->setWeight(0);
        }

        /* Due Work 1: Weight  0, NormalisedMark: P,18 UP,18
         * Due Work 2: Weight  0, NormalisedMark: P,91 UP,91
         * Due Work 3: Weight  0, NormalisedMark: P,89 UP,89
         * Due Work 4: Weight  0, NormalisedMark: P,null UP,null
         * Due Work 5: Weight  0, NormalisedMark: P,null UP,76
         * Due Work 6: Weight  0, NormalisedMark: P,null UP,null
         * Due Work 7: Weight  0, NormalisedMark: P,96 UP,96
         *
         * Total Weight: 66
         *
         * Most Recent Published Weighted Average is: 0
         * Most Recent Weighted Average is: 0
         */
        $this->assertEquals(0.00000000000, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(0.00000000000, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * A real belting
     *
     * @throws \UnexpectedValueException
     */
    public function testWeightedAverage12()
    {
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables($this->massProduce());

        /* Reset all but one of the weights
         */
        foreach ($project->getAssessables() as $currentAssessable) {
            if ($currentAssessable->getId() != 10005) {
                $currentAssessable->setWeight(0);
            }
        }

        /* Due Work 1: Weight  0, NormalisedMark: P,18 UP,18
         * Due Work 2: Weight  0, NormalisedMark: P,91 UP,91
         * Due Work 3: Weight  0, NormalisedMark: P,89 UP,89
         * Due Work 4: Weight  0, NormalisedMark: P,null UP,null
         * Due Work 5: Weight  4, NormalisedMark: P,null UP,76
         * Due Work 6: Weight  0, NormalisedMark: P,null UP,null
         * Due Work 7: Weight  0, NormalisedMark: P,96 UP,96
         *
         * Total Weight: 66
         *
         * Most Recent Published Weighted Average is:
         *      = [(18*0/66) + (91*0/66) + (89*0/66) + (96*0/66)]
         *      = [0 + 0 + 0 + 0]
         *      = 0
         *
         * Most Recent Weighted Average is:
         *      = 76*4/4
         *      = 76
         */
        $this->assertEquals(0, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(76.00000000000, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * A real belting
     *
     * @throws \UnexpectedValueException
     */
    public function testWeightedAverage13()
    {
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables($this->massProduce());

        /* Reset all but one of the weights
         */
        foreach ($project->getAssessables() as $currentAssessable) {
            $currentAssessable->setWeight(null);
        }

        /* Due Work 1: Weight  null, NormalisedMark: P,null UP,18
         * Due Work 2: Weight  null, NormalisedMark: P,null UP,91
         * Due Work 3: Weight  null, NormalisedMark: P,null UP,89
         * Due Work 4: Weight  null, NormalisedMark: P,null UP,null
         * Due Work 5: Weight  null, NormalisedMark: P,null UP,76
         * Due Work 6: Weight  null, NormalisedMark: P,null UP,null
         * Due Work 7: Weight  null, NormalisedMark: P,null UP,96
         *
         * Total Weight: 66
         *
         * Most Recent Published Weighted Average is: null
         *
         * Most Recent Weighted Average is:
         *      = 76*4/4
         *      = 76
         */
        $this->assertEquals(null, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(null, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * A real belting
     *
     * @throws \UnexpectedValueException
     */
    public function testWeightedAverage14()
    {
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables($this->massProduce());

        /* Reset all but one of the weights
         */
        foreach ($project->getAssessables() as $currentAssessable) {
            if ($currentAssessable->getId() != 10005) {
                $currentAssessable->setWeight(null);
            }
        }

        /* Due Work 1: Weight  null, NormalisedMark: P,null UP,18
         * Due Work 2: Weight  null, NormalisedMark: P,null UP,91
         * Due Work 3: Weight  null, NormalisedMark: P,null UP,89
         * Due Work 4: Weight  null, NormalisedMark: P,null UP,null
         * Due Work 5: Weight  4, NormalisedMark: P,null UP,76
         * Due Work 6: Weight  null, NormalisedMark: P,null UP,null
         * Due Work 7: Weight  null, NormalisedMark: P,null UP,96
         *
         * Total Weight: 66
         *
         * Most Recent Published Weighted Average is: 0
         *
         * Most Recent Weighted Average is:
         *      = 76*4/4
         *      = 76
         */
        $this->assertEquals(0, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(76.00000000000, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * Test the maximum
     * when there is no feedback
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \DomainException
     */
    public function testMaximum1()
    {
        $project = $this->spawnProject(new SummatorMaximum());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        foreach ($project->getAssessables() as $currentAssessable) {
            $currentAssessable->getParticipant($this->hodes)->setAllFeedbacks(
                $currentAssessable->getParticipant($this->hodes)->getAllFeedbacks()->slice(0, 0)
            );

            /** @var \Schoolbox\Learning\Assessment\IResponse $currentResponse */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllResponses() as $currentResponse) {
                $currentResponse->setAllFeedback($currentResponse->getAllFeedback()->slice(0, 0));
            }
        }

        /* Most Recent Published Maximum is null (nothing to calculate)
         */
        $this->assertEquals(null, $project->getNormalisedMark($project->getParticipant($this->hodes), true));

        // Quiz is auto-marked to 60.0 (this constitutes a draft-mark)
        $this->assertEquals(60.0, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * Test the maximum
     * when there is nothing published
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testMaximum2()
    {
        $project = $this->spawnProject(new SummatorMaximum());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        foreach ($project->getAssessables() as $currentAssessable) {

            /** @var \Schoolbox\Learning\Assessment\IFeedback $currentFeedback */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllFeedbacks() as $currentFeedback) {
                if ($currentAssessable instanceof Task) {
                    $currentFeedback->getResponse()->setCreatedAt(null);
                } else {
                    $currentFeedback->setPublishedAt(null);
                }
            }

            /** @var \Schoolbox\Learning\Assessment\IResponse $currentResponse */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllResponses() as $currentResponse) {
                foreach ($currentResponse->getAllFeedback() as $currentFeedback) {
                    if ($currentAssessable instanceof Task) {
                        $currentFeedback->getResponse()->setCreatedAt(null);
                    } else {
                        $currentFeedback->setPublishedAt(null);
                    }
                }
            }
        }

        /* Most Recent Published Maximum
         */
        $this->assertEquals(null, $project->getNormalisedMark($project->getParticipant($this->hodes), true));

        /* Most Recent Maximum
         */
        $this->assertEqualsWithDelta(100, $project->getNormalisedMark($project->getParticipant($this->hodes), false), $this->delta);
    }


    /**
     * Test the maximum
     * when there's one of each type with published and unpublished feedback
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testMaximum3()
    {
        /* There are three assessables for Hodes
         *
         * Due Work 2 (77 marks)
         * Task (null marks)
         * Quiz (12 marks)
         */
        $project = $this->spawnProject(new SummatorMaximum());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnQuiz()));

        /* Most Recent Published Maximum
         */
        $this->assertEqualsWithDelta(77, $project->getNormalisedMark($project->getParticipant($this->hodes), true), $this->delta);

        /* Most Recent Maximum
         */
        $this->assertEqualsWithDelta(77, $project->getNormalisedMark($project->getParticipant($this->hodes), false), $this->delta);
    }


    /**
     * Test the maximum
     * when there's more than one of a particular type with published and unpublished feedback
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testMaximum4()
    {
        /* There are five assessables for Hodes
         *
         * Due Work 3 (98 marks)
         * Task (null marks)
         * Due Work 2 (77 marks)
         * Due Work (100 marks)
         * Quiz (12 marks)
         */
        $project = $this->spawnProject(new SummatorMaximum());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnTask())
            ->add($this->spawnDueWork3())
            ->add($this->spawnDueWork2())
            ->add($this->spawnQuiz()));

        /* Most Recent Published Maximum
         */
        $this->assertEquals(98, $project->getNormalisedMark($project->getParticipant($this->hodes), true));

        /* Most Recent Maximum
         */
        $this->assertEquals(98, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * Test the maximum
     * when there's more than one of a particular type with published and unpublished feedback
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testMaximum5()
    {
        /* There are five assessables for Hodes
         *
         * Due Work 3 (98 marks)
         * Task (null marks)
         * Due Work 2 (77 marks)
         * Due Work (100 marks)
         * Quiz (12 marks)
         */
        $project = $this->spawnProject(new SummatorMaximum());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        /* Most Recent Published Maximum
         */
        $this->assertEquals(100, $project->getNormalisedMark($project->getParticipant($this->hodes), true));

        /* Most Recent Maximum
        */
        $this->assertEquals(100, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }

    /** Really go to town */

    /**
     * A real belting
     *
     * @throws \UnexpectedValueException
     */
    public function testMaximum6()
    {
        $project = $this->spawnProject(new SummatorMaximum());
        $project->setAssessables($this->massProduce());

        /* Due Work 1: Weight 20, NormalisedMark: P,18 UP,18
         * Due Work 2: Weight 11, NormalisedMark: P,91 UP,91
         * Due Work 3: Weight  1, NormalisedMark: P,89 UP,89
         * Due Work 4: Weight  4, NormalisedMark: P,null UP,null
         * Due Work 5: Weight  4, NormalisedMark: P,null UP,76
         * Due Work 6: Weight  4, NormalisedMark: P,null UP,null
         * Due Work 7: Weight 22, NormalisedMark: P,96 UP,96
         */

        $this->assertEquals(96, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(96, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * A real belting
     *
     * @throws \UnexpectedValueException
     */
    public function testMaximum7()
    {
        $project = $this->spawnProject(new SummatorMaximum());
        $project->setAssessables($this->massProduce());

        /* Reset all the weights to zero
         */
        foreach ($project->getAssessables() as $currentAssessable) {
            $currentAssessable->setWeight(0);
        }

        /* Due Work 1: Weight  0, NormalisedMark: P,18 UP,18
         * Due Work 2: Weight  0, NormalisedMark: P,91 UP,91
         * Due Work 3: Weight  0, NormalisedMark: P,89 UP,89
         * Due Work 4: Weight  0, NormalisedMark: P,null UP,null
         * Due Work 5: Weight  0, NormalisedMark: P,null UP,76
         * Due Work 6: Weight  0, NormalisedMark: P,null UP,null
         * Due Work 7: Weight  0, NormalisedMark: P,96 UP,96
         */
        $this->assertEquals(96, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(96, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * A real belting
     *
     * @throws \UnexpectedValueException
     */
    public function testMaximum8()
    {
        $project = $this->spawnProject(new SummatorMaximum());
        $project->setAssessables($this->massProduce());

        /* Reset all but one of the weights
         */
        foreach ($project->getAssessables() as $currentAssessable) {
            if ($currentAssessable->getId() != 10005) {
                $currentAssessable->setWeight(0);
            }
        }

        /* Due Work 1: Weight  0, NormalisedMark: P,18 UP,18
         * Due Work 2: Weight  0, NormalisedMark: P,91 UP,91
         * Due Work 3: Weight  0, NormalisedMark: P,89 UP,89
         * Due Work 4: Weight  0, NormalisedMark: P,null UP,null
         * Due Work 5: Weight  4, NormalisedMark: P,null UP,76
         * Due Work 6: Weight  0, NormalisedMark: P,null UP,null
         * Due Work 7: Weight  0, NormalisedMark: P,96 UP,96
         */
        $this->assertEquals(96, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(96, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * A real belting
     *
     * @throws \UnexpectedValueException
     */
    public function testMaximum9()
    {
        $project = $this->spawnProject(new SummatorMaximum());
        $project->setAssessables($this->massProduce());

        foreach ($project->getAssessables() as $currentAssessable) {

            /** @var \Schoolbox\Learning\Assessment\IFeedback $currentFeedback */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllFeedbacks() as $currentFeedback) {
                $currentFeedback->setPublishedAt(null);
            }

            /** @var \Schoolbox\Learning\Assessment\IResponse $currentResponse */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllResponses() as $currentResponse) {
                foreach ($currentResponse->getAllFeedback() as $currentFeedback) {
                    $currentFeedback->setPublishedAt(null);
                }
            }

            /* Reset all the weights
             */
            $currentAssessable->setWeight(null);
        }

        /* Due Work 1: Weight  null, NormalisedMark: P,null UP,18
         * Due Work 2: Weight  null, NormalisedMark: P,null UP,91
         * Due Work 3: Weight  null, NormalisedMark: P,null UP,89
         * Due Work 4: Weight  null, NormalisedMark: P,null UP,null
         * Due Work 5: Weight  null, NormalisedMark: P,null UP,76
         * Due Work 6: Weight  null, NormalisedMark: P,null UP,null
         * Due Work 7: Weight  null, NormalisedMark: P,null UP,96
         */
        $this->assertEquals(null, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(96, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * A real belting
     *
     * @throws \UnexpectedValueException
     */
    public function testMaximum10()
    {
        $project = $this->spawnProject(new SummatorMaximum());
        $project->setAssessables($this->massProduce());

        /* Reset all but one of the weights
         */
        foreach ($project->getAssessables() as $currentAssessable) {
            if ($currentAssessable->getId() != 10005) {
                $currentAssessable->setWeight(null);
            }
        }

        /* Due Work 1: Weight  null, NormalisedMark: P,null UP,18
         * Due Work 2: Weight  null, NormalisedMark: P,null UP,91
         * Due Work 3: Weight  null, NormalisedMark: P,null UP,89
         * Due Work 4: Weight  null, NormalisedMark: P,null UP,null
         * Due Work 5: Weight  4, NormalisedMark: P,null UP,76
         * Due Work 6: Weight  null, NormalisedMark: P,null UP,null
         * Due Work 7: Weight  null, NormalisedMark: P,null UP,96
         */
        $this->assertEquals(96, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(96, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * A more intricate mixture
     *
     * @throws \UnexpectedValueException
     */
    public function testMaximum11()
    {
        /**
         * A Due Work with multiple floating feedback
         */
        $id = 10001;
        $title = 'First Due Work';
        $description = 'The first due work to do';
        $typeName = 'Homework';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(2)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setWeight(20)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90011)
            ->setAssessable($dueWork)
            ->setNormalisedMark(44)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-13 13:31:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90011)
            ->setAssessable($dueWork)
            ->setNormalisedMark(55)
            ->setComment('A revision to the previous feedback, unpublished')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-22 14:14:14', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-22 14:14:14', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $project = $this->spawnProject(new SummatorMaximum());
        $project->setAssessables((new GenericCollection(IAssessable::class))->add($dueWork));

        $this->assertEquals(44, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(55, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * A more intricate mixture
     *
     * @throws \UnexpectedValueException
     */
    public function testMaximum12()
    {
        /**
         * A Due Work with multiple floating feedback
         */
        $id = 10001;
        $title = 'First Due Work';
        $description = 'The first due work to do';
        $typeName = 'Homework';

        $dueWork1 = new DueWork();
        $dueWork1
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(2)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setWeight(20)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork1);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90011)
            ->setAssessable($dueWork1)
            ->setNormalisedMark(44)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-13 13:31:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork1->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90011)
            ->setAssessable($dueWork1)
            ->setNormalisedMark(55)
            ->setComment('A revision to the previous feedback, unpublished')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-22 14:14:14', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-22 14:14:14', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork1->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);


        /**
         * A Due Work with multiple floating feedback
         */
        $id = 10002;
        $title = 'Second Due Work';
        $description = 'The second due work to do';
        $typeName = 'Homework';

        $dueWork2 = new DueWork();
        $dueWork2
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(2)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setWeight(20)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork2);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90121)
            ->setAssessable($dueWork2)
            ->setNormalisedMark(41)
            ->setComment('Initial feedback, published')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-13 13:31:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork2->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90122)
            ->setAssessable($dueWork2)
            ->setNormalisedMark(55)
            ->setComment('A revision to the previous feedback, unpublished')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-22 14:14:14', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-22 14:14:14', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork2->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $submission = new DueWorkSubmission();
        $submission
            ->setId(70121)
            ->setAssessable($dueWork2)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-23 23:23:23', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-23 23:23:23', \Alaress_Date_Format::DATABASE_FORMAT));

        $linkedFeedback = (new DueWorkFeedback())
            ->setId(90123)
            ->setAssessable($dueWork2)
            ->setNormalisedMark(71)
            ->setComment('Some additional feedback, recently updated but not published')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-24 01:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-24 16:16:16', \Alaress_Date_Format::DATABASE_FORMAT));

        $submission->getAllFeedback()->add($linkedFeedback);
        $linkedFeedback->setResponse($submission);
        $dueWork2->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90124)
            ->setAssessable($dueWork2)
            ->setNormalisedMark(73)
            ->setComment('Thought about it some more and decided to recompose my entire Feedback')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-09-01 01:01:01', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-09-01 01:01:01', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork2->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $project = $this->spawnProject(new SummatorMaximum());
        $project->setAssessables((new GenericCollection(IAssessable::class))->add($dueWork1)->add($dueWork2));

        $this->assertEquals(44, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(73, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * Test the minimum
     * when there is no feedback
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \DomainException
     */
    public function testMinimum1()
    {
        $project = $this->spawnProject(new SummatorMinimum());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        foreach ($project->getAssessables() as $currentAssessable) {
            $currentAssessable->getParticipant($this->hodes)->setAllFeedbacks(
                $currentAssessable->getParticipant($this->hodes)->getAllFeedbacks()->slice(0, 0)
            );

            /** @var \Schoolbox\Learning\Assessment\IResponse $currentResponse */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllResponses() as $currentResponse) {
                $currentResponse->setAllFeedback($currentResponse->getAllFeedback()->slice(0, 0));
            }
        }

        /* Most Recent Published Minimum is null (nothing to calculate)
         */
        $this->assertEquals(null, $project->getNormalisedMark($project->getParticipant($this->hodes), true));

        // Quiz is auto-marked to 60.0 (this constitutes a draft-mark)
        $this->assertEquals(60.0, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * Test the minimum
     * when there is nothing published
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testMinimum2()
    {
        $project = $this->spawnProject(new SummatorMinimum());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        foreach ($project->getAssessables() as $currentAssessable) {

            /** @var \Schoolbox\Learning\Assessment\IFeedback $currentFeedback */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllFeedbacks() as $currentFeedback) {
                if ($currentAssessable instanceof Task) {
                    $currentFeedback->getResponse()->setCreatedAt(null);
                } else {
                    $currentFeedback->setPublishedAt(null);
                }
            }

            /** @var \Schoolbox\Learning\Assessment\IResponse $currentResponse */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllResponses() as $currentResponse) {
                foreach ($currentResponse->getAllFeedback() as $currentFeedback) {
                    if ($currentAssessable instanceof Task) {
                        $currentFeedback->getResponse()->setCreatedAt(null);
                    } else {
                        $currentFeedback->setPublishedAt(null);
                    }
                }
            }
        }

        /* Most Recent Published Minimum
         */
        $this->assertEquals(null, $project->getNormalisedMark($project->getParticipant($this->hodes), true));

        /* Most Recent Minimum
         * Minimum of (98, null, 77, 100, 60)
         */
        $this->assertEqualsWithDelta(60, $project->getNormalisedMark($project->getParticipant($this->hodes), false), $this->delta);
    }


    /**
     * Test the minimum
     * when there's one of each type with published and unpublished feedback
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testMinimum3()
    {
        /* There are three assessables for Hodes
         */
        $project = $this->spawnProject(new SummatorMinimum());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnQuiz()));

        /* Most Recent Published Minimum
         *      12/20 = 60 (Quiz 99762)
         *      77 (Due Work 7889)
         */
        $this->assertEqualsWithDelta(60, $project->getNormalisedMark($project->getParticipant($this->hodes), true), $this->delta);

        /* Most Recent Minimum
         *      12/20 = 60 (Quiz 99762)
         *      77 (Due Work 7889)
         */
        $this->assertEqualsWithDelta(60, $project->getNormalisedMark($project->getParticipant($this->hodes), false), $this->delta);
    }


    /**
     * Test the minimum
     * when there's more than one of a particular type with published and unpublished feedback
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testMinimum4()
    {
        $project = $this->spawnProject(new SummatorMinimum());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnTask())
            ->add($this->spawnDueWork3())
            ->add($this->spawnDueWork2()));

        /* Most Recent Published Minimum
         *      77 (Due Work 7889)
         *      98 (Due Work 9912)
         */
        $this->assertEquals(77, $project->getNormalisedMark($project->getParticipant($this->hodes), true));

        /* Most Recent Minimum
         */
        $this->assertEquals(77, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * Test the minimum
     * when there's more than one of a particular type with published and unpublished feedback
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testMinimum5()
    {
        $project = $this->spawnProject(new SummatorMinimum());
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork()));

        /* Most Recent Published Minimum
         *      77 (Due Work 7889)
         *      98 (Due Work 9912)
         *      100 (Due Work 4236)
         */
        $this->assertEquals(77, $project->getNormalisedMark($project->getParticipant($this->hodes), true));

        /* Most Recent Minimum
        */
        $this->assertEquals(77, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }

    /** Really go to town */

    /**
     * A real belting
     *
     * @throws \UnexpectedValueException
     */
    public function testMinimum6()
    {
        $project = $this->spawnProject(new SummatorMinimum());
        $project->setAssessables($this->massProduce());

        /* Due Work 1: Weight 20, NormalisedMark: P,18 UP,18
         * Due Work 2: Weight 11, NormalisedMark: P,91 UP,91
         * Due Work 3: Weight  1, NormalisedMark: P,89 UP,89
         * Due Work 4: Weight  4, NormalisedMark: P,null UP,null
         * Due Work 5: Weight  4, NormalisedMark: P,null UP,76
         * Due Work 6: Weight  4, NormalisedMark: P,null UP,null
         * Due Work 7: Weight 22, NormalisedMark: P,96 UP,96
         */
        $this->assertEquals(18, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(18, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * A real belting
     *
     * @throws \UnexpectedValueException
     */
    public function testMinimum7()
    {
        $project = $this->spawnProject(new SummatorMinimum());
        $project->setAssessables($this->massProduce());

        /* Reset all the weights to zero
         */
        foreach ($project->getAssessables() as $currentAssessable) {
            $currentAssessable->setWeight(0);
        }

        /* Due Work 1: Weight  0, NormalisedMark: P,18 UP,18
         * Due Work 2: Weight  0, NormalisedMark: P,91 UP,91
         * Due Work 3: Weight  0, NormalisedMark: P,89 UP,89
         * Due Work 4: Weight  0, NormalisedMark: P,null UP,null
         * Due Work 5: Weight  0, NormalisedMark: P,null UP,76
         * Due Work 6: Weight  0, NormalisedMark: P,null UP,null
         * Due Work 7: Weight  0, NormalisedMark: P,96 UP,96
         */
        $this->assertEquals(18, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(18, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * A real belting
     *
     * @throws \UnexpectedValueException
     */
    public function testMinimum8()
    {
        $project = $this->spawnProject(new SummatorMinimum());
        $project->setAssessables($this->massProduce());

        /* Reset all but one of the weights
         */
        foreach ($project->getAssessables() as $currentAssessable) {
            if ($currentAssessable->getId() != 10005) {
                $currentAssessable->setWeight(0);
            }
        }

        /* Due Work 1: Weight  0, NormalisedMark: P,18 UP,18
         * Due Work 2: Weight  0, NormalisedMark: P,91 UP,91
         * Due Work 3: Weight  0, NormalisedMark: P,89 UP,89
         * Due Work 4: Weight  0, NormalisedMark: P,null UP,null
         * Due Work 5: Weight  4, NormalisedMark: P,null UP,76
         * Due Work 6: Weight  0, NormalisedMark: P,null UP,null
         * Due Work 7: Weight  0, NormalisedMark: P,96 UP,96
         */
        $this->assertEquals(18, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(18, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * A real belting
     *
     * @throws \UnexpectedValueException
     */
    public function testMinimum9()
    {
        $project = $this->spawnProject(new SummatorMinimum());
        $project->setAssessables($this->massProduce());

        foreach ($project->getAssessables() as $currentAssessable) {

            /** @var \Schoolbox\Learning\Assessment\IFeedback $currentFeedback */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllFeedbacks() as $currentFeedback) {
                $currentFeedback->setPublishedAt(null);
            }
            /** @var \Schoolbox\Learning\Assessment\IResponse $currentResponse */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllResponses() as $currentResponse) {
                foreach ($currentResponse->getAllFeedback() as $currentFeedback) {
                    $currentFeedback->setPublishedAt(null);
                }
            }

            /* Reset all the weights
             */
            $currentAssessable->setWeight(null);
        }

        /* Due Work 1: Weight  null, NormalisedMark: P,null UP,18
         * Due Work 2: Weight  null, NormalisedMark: P,null UP,91
         * Due Work 3: Weight  null, NormalisedMark: P,null UP,89
         * Due Work 4: Weight  null, NormalisedMark: P,null UP,null
         * Due Work 5: Weight  null, NormalisedMark: P,null UP,76
         * Due Work 6: Weight  null, NormalisedMark: P,null UP,null
         * Due Work 7: Weight  null, NormalisedMark: P,null UP,96
         */
        $this->assertEquals(null, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(18, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * A real belting
     *
     * @throws \UnexpectedValueException
     */
    public function testMinimum10()
    {
        $project = $this->spawnProject(new SummatorMinimum());
        $project->setAssessables($this->massProduce());

        foreach ($project->getAssessables() as $currentAssessable) {

            /** @var \Schoolbox\Learning\Assessment\IFeedback $currentFeedback */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllFeedbacks() as $currentFeedback) {
                $currentFeedback->setPublishedAt(null);
            }

            /** @var \Schoolbox\Learning\Assessment\IResponse $currentResponse */
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllResponses() as $currentResponse) {
                foreach ($currentResponse->getAllFeedback() as $currentFeedback) {
                    $currentFeedback->setPublishedAt(null);
                }
            }

            /* Reset all but one of the weights
             */
            if ($currentAssessable->getId() != 10005) {
                $currentAssessable->setWeight(null);
            }
        }

        /* Due Work 1: Weight  null, NormalisedMark: P,null UP,18
         * Due Work 2: Weight  null, NormalisedMark: P,null UP,91
         * Due Work 3: Weight  null, NormalisedMark: P,null UP,89
         * Due Work 4: Weight  null, NormalisedMark: P,null UP,null
         * Due Work 5: Weight  4, NormalisedMark: P,null UP,76
         * Due Work 6: Weight  null, NormalisedMark: P,null UP,null
         * Due Work 7: Weight  null, NormalisedMark: P,null UP,96
         */
        $this->assertEquals(null, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(18, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * A more intricate mixture
     *
     * @throws \UnexpectedValueException
     */
    public function testMinimum11()
    {
        /**
         * A Due Work with multiple floating feedback
         */
        $id = 10001;
        $title = 'First Due Work';
        $description = 'The first due work to do';
        $typeName = 'Homework';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(2)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setWeight(20)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90011)
            ->setAssessable($dueWork)
            ->setNormalisedMark(44)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-13 13:31:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90011)
            ->setAssessable($dueWork)
            ->setNormalisedMark(55)
            ->setComment('A revision to the previous feedback, unpublished')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-22 14:14:14', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-22 14:14:14', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $project = $this->spawnProject(new SummatorMinimum());
        $project->setAssessables((new GenericCollection(IAssessable::class))->add($dueWork));

        $this->assertEquals(44, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(55, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * A more intricate mixture
     *
     * @throws \UnexpectedValueException
     */
    public function testMinimum12()
    {
        /**
         * A Due Work with multiple floating feedback
         */
        $id = 10001;
        $title = 'First Due Work';
        $description = 'The first due work to do';
        $typeName = 'Homework';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(2)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setWeight(20)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90011)
            ->setAssessable($dueWork)
            ->setNormalisedMark(44)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-13 13:31:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90011)
            ->setAssessable($dueWork)
            ->setNormalisedMark(55)
            ->setComment('A revision to the previous feedback, unpublished')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-22 14:14:14', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-22 14:14:14', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);


        /**
         * A Due Work with multiple floating feedback
         */
        $id = 10002;
        $title = 'Second Due Work';
        $description = 'The second due work to do';
        $typeName = 'Homework';

        $dueWork2 = new DueWork();
        $dueWork2
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(2)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setWeight(20)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork2);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90121)
            ->setAssessable($dueWork2)
            ->setNormalisedMark(41)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-13 13:31:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork2->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90122)
            ->setAssessable($dueWork2)
            ->setNormalisedMark(55)
            ->setComment('A revision to the previous feedback, unpublished')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-22 14:14:14', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-22 14:14:14', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork2->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $submission = new DueWorkSubmission();
        $submission
            ->setId(70121)
            ->setAssessable($dueWork2)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-23 23:23:23', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-23 23:23:23', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new DueWorkFeedback())
            ->setId(90123)
            ->setAssessable($dueWork2)
            ->setNormalisedMark(91)
            ->setComment('Some additional feedback, recently updated but not published')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-24 01:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-24 16:16:16', \Alaress_Date_Format::DATABASE_FORMAT));

        $submission->getAllFeedback()->add($feedback);
        $dueWork2->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90124)
            ->setAssessable($dueWork2)
            ->setNormalisedMark(73)
            ->setComment('Thought about it some more and decided to recompose my entire Feedback')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-09-01 01:01:01', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-09-01 01:01:01', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork2->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $project = $this->spawnProject(new SummatorMinimum());
        $project->setAssessables((new GenericCollection(IAssessable::class))->add($dueWork)->add($dueWork2));

        $this->assertEquals(41, $project->getNormalisedMark($project->getParticipant($this->hodes), true));
        $this->assertEquals(55, $project->getNormalisedMark($project->getParticipant($this->hodes), false));
    }


    /**
     * Mass produce a giant Due Work set
     *
     * @return GenericCollection
     * @throws \UnexpectedValueException
     */
    public function massProduce()
    {
        $collection = new GenericCollection(IAssessable::class);

        /**
         * A Due Work with one floating feedback
         */
        $id = 10001;
        $title = 'First Due Work';
        $description = 'The first due work to do';
        $typeName = 'Homework';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(2)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setWeight(20)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90011)
            ->setAssessable($dueWork)
            ->setNormalisedMark(18)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-13 13:31:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);
        $collection->add($dueWork);

        /**
         * A Due Work with one submission with one associated feedback
         * and multiple floating feedback
         */
        $id = 10002;
        $title = 'Second Due Work';
        $description = 'The second due work to do';
        $typeName = 'Homework';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(2)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setWeight(11)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        $submission = new DueWorkSubmission();
        $submission
            ->setId(70021)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new DueWorkFeedback())
            ->setId(90021)
            ->setAssessable($dueWork)
            ->setNormalisedMark(14)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:14:15', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:14:12', \Alaress_Date_Format::DATABASE_FORMAT));

        $submission->getAllFeedback()->add($feedback);
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90022)
            ->setAssessable($dueWork)
            ->setNormalisedMark(54)
            ->setComment('An outdated feedback')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-12 12:21:12', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90023)
            ->setAssessable($dueWork)
            ->setNormalisedMark(91)
            ->setComment('This is the most recent published feedback; also the most recent feedback')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-13 13:31:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);
        $collection->add($dueWork);

        /**
         * A Due Work with one submission with one associated feedback
         * and one floating feedback
         */
        $id = 10003;
        $title = 'Third Due Work';
        $description = 'The third due work to do';
        $typeName = 'Oral Presentation';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(3)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setWeight(1)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        $submission = new DueWorkSubmission();
        $submission
            ->setId(70031)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new DueWorkFeedback())
            ->setId(90031)
            ->setAssessable($dueWork)
            ->setNormalisedMark(45)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:14:15', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:14:12', \Alaress_Date_Format::DATABASE_FORMAT));

        $submission->getAllFeedback()->add($feedback);
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90002)
            ->setAssessable($dueWork)
            ->setNormalisedMark(89)
            ->setComment('This is the most recent published feedback; also the most recent feedback')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-13 13:31:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);
        $collection->add($dueWork);


        /**
         * A Due Work with one submission
         */
        $id = 10004;
        $title = 'Fourth Due Work';
        $description = 'The fourth due work to do';
        $typeName = 'Oral Presentation';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(3)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setWeight(4)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        $submission = new DueWorkSubmission();
        $submission
            ->setId(70041)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $collection->add($dueWork);

        /**
         * A Due Work with no submissions nor feedbacks
         */
        $id = 10005;
        $title = 'Fifth Due Work';
        $description = 'The fifth due work to do';
        $typeName = 'Class Presentation';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(5)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setWeight(4)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        $submission = new DueWorkSubmission();
        $submission
            ->setId(70051)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new DueWorkFeedback())
            ->setId(90051)
            ->setAssessable($dueWork)
            ->setNormalisedMark(76)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 10:10:10', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 10:10:10', \Alaress_Date_Format::DATABASE_FORMAT));

        $submission->getAllFeedback()->add($feedback);
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        $collection->add($dueWork);

        /**
         * A Due Work with no submissions and one unpublished feedback
         */
        $id = 10006;
        $title = 'Sixth Due Work';
        $description = 'The Sixth due work to do';
        $typeName = 'Class Presentation';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(5)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setWeight(4)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        $collection->add($dueWork);


        /**
         * A Due Work with multiple submissions and associated feedbacks
         * and multiple floating feedbacks
         */
        $id = 10007;
        $title = 'Seventh Due Work';
        $description = 'The seventh due work to do';
        $typeName = 'Homework';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(1)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setWeight(22)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90071)
            ->setAssessable($dueWork)
            ->setNormalisedMark(41)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 01:01:01', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 01:01:01', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90072)
            ->setAssessable($dueWork)
            ->setNormalisedMark(34)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-02 02:02:02', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 02:02:02', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90073)
            ->setAssessable($dueWork)
            ->setNormalisedMark(67)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-03 03:03:03', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-03 03:03:03', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $submission = new DueWorkSubmission();
        $submission
            ->setId(70071)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-04 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-04 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new DueWorkFeedback())
            ->setId(90074)
            ->setAssessable($dueWork)
            ->setNormalisedMark(99)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-04 04:04:04', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-04 04:04:04', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-04 04:04:04', \Alaress_Date_Format::DATABASE_FORMAT));

        $submission->getAllFeedback()->add($feedback);
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        $feedback = (new DueWorkFeedback())
            ->setId(90075)
            ->setAssessable($dueWork)
            ->setNormalisedMark(97)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-05 05:05:05', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-05 05:05:05', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-05 05:05:05', \Alaress_Date_Format::DATABASE_FORMAT));

        $submission->getAllFeedback()->add($feedback);
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        $feedback = (new DueWorkFeedback())
            ->setId(90076)
            ->setAssessable($dueWork)
            ->setNormalisedMark(96)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 06:06:06', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 06:06:06', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 06:06:06', \Alaress_Date_Format::DATABASE_FORMAT));

        $submission->getAllFeedback()->add($feedback);
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        $collection->add($dueWork);

        /* Finally
         */
        return $collection;
    }

    /**
     * Spawn a Project with stuff in it
     *
     * @param ISummator $summatorStrategy
     * @return Project
     */
    protected function spawnProject(ISummator $summatorStrategy)
    {
        $id = 5567;
        $title = 'Find Diablo';
        $description = 'The things you need to do in order to slay the prime evil';

        $project = new Project($summatorStrategy);
        $project
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->somodio)
            ->setPublishAt($this->tomorrow)
            ->setOpenAt($this->tomorrow)
            ->setDueAt($this->fiftyDaysFromNow)
            ->setCloseAt($this->fiftyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($project);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        return $project;
    }


    /**
     * Spawn another Due Work
     *
     * @return DueWork
     * @throws \UnexpectedValueException
     */
    protected function spawnDueWork3()
    {
        $id = 13;
        $title = 'Stay Awhile And Listen';
        $description = 'I have a story to tell...';
        $typeName = 'Homework';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(2)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setWeight(6)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(9912)
            ->setAssessable($dueWork)
            ->setNormalisedMark(98)
            ->setComment('The greatest story ever told!')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-13 13:31:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        return $dueWork;
    }


    /**
     * Spawn another Due Work
     *
     * @return DueWork
     * @throws \UnexpectedValueException
     */
    protected function spawnDueWork2()
    {
        $id = 7;
        $title = 'Collect Horadric Gems';
        $description = 'How many gems did you find?';
        $typeName = 'Class Presentation';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(2)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setWeight(10)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(7889)
            ->setAssessable($dueWork)
            ->setNormalisedMark(77)
            ->setComment('cool collection of gems')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-11 11:12:23', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        return $dueWork;
    }


    /**
     * Spawn a Due Work
     *
     * @return DueWork
     * @throws \UnexpectedValueException
     */
    protected function spawnDueWork()
    {
        $id = 2;
        $title = 'Enter the Realm of Terroarrrr';
        $description = 'Diablo was found in the land of terroarrrrr! Find him, and whack him!';
        $typeName = 'Essay';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(1)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setWeight(5)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        $submission = new DueWorkSubmission();
        $submission
            ->setId(553)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new DueWorkFeedback())
            ->setId(3385)
            ->setAssessable($dueWork)
            ->setNormalisedMark(-3)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:14:15', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:14:12', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllResponses()->add($submission);
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        $submission->getAllFeedback()->add($feedback);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4223)
            ->setAssessable($dueWork)
            ->setNormalisedMark(37)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 22:22:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4236)
            ->setAssessable($dueWork)
            ->setNormalisedMark(100)
            ->setComment('meow - this is the most recently published feedback, despite the ordering in which this appears')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 23:43:43', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 23:41:41', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 23:41:41', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4225)
            ->setAssessable($dueWork)
            ->setNormalisedMark(10)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 23:23:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 23:23:23', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 23:23:21', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        return $dueWork;
    }


    /**
     * Spawn a Task
     *
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    protected function spawnTask()
    {
        $id = 1234;
        $title = 'Read the Horadric Scrolls';
        $description = 'There is a book by Deckard Cain which you must read before searching for Diablo.';

        $task = new Task();
        $task
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->celia)
            ->setWeight(4)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->thirteenDaysFromNow)
            ->setDueAt($this->fourteenDaysFromNow)
            ->setCloseAt($this->fourteenDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($task);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        $response = new TaskResponse();
        $response
            ->setId(883)
            ->setAssessable($task)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new TaskFeedback())
            ->setResponse($response);

        $response->getAllFeedback()->add($feedback);

        $task->getParticipant($this->hodes)->getAllResponses()->add($response);
        $task->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        return $task;
    }


    /**
     * Spawn a Quiz.
     * * Quiz has 5 questions each of 5 marks, for total of 25.0
     * * Quiz has 1 attempt of 4 questions worth 20.0 marks. David Rhodes was given 12.0 marks total. ie 60.0
     *
     * For now, since we are not updating the Quiz DB schema, a QuizAttempt has 0:1 QuizFeedback
     *
     * @return Quiz
     * @throws \UnexpectedValueException
     */
    protected function spawnQuiz()
    {
        $id = 3;
        $title = 'How did you find The Prime Evil?';
        $description = 'A quick pop quiz about how you died your way to Diablo.';

        $responses = [
            [
                'mark' => 1, // Question 1
                'available' => 5,
            ],
            [
                'mark' => 1, // Question 2
                'available' => 5,
            ],
            [
                'mark' => 5, // Question 3
                'available' => 5,
            ],
            [
                'mark' => 5, // Question 4
                'available' => 5,
            ],
        ];
        $marksGiven = $marksAvailable = 0;
        foreach ($responses as $response) {
            $marksGiven += $response['mark'];
            $marksAvailable += $response['available'];
        }

        $quiz = new Quiz();
        $quiz
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->somodio)
            ->setWeight(8)
            ->setPublishAt($this->twentyDaysFromNow)
            ->setOpenAt($this->twentyDaysFromNow)
            ->setDueAt($this->thirtyDaysFromNow)
            ->setCloseAt($this->fortyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($quiz);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        $attempt = new QuizAttempt();
        $attempt
            ->setId(8655)
            ->setAssessable($quiz)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setSubmittedAt($this->today)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today)
            ->setMarksAvailable($marksAvailable)
            ->setMarksGiven($marksGiven);
        $quiz->getParticipant($this->hodes)->getAllResponses()->add($attempt);

        $feedback = new QuizReview();
        $feedback
            ->setId(99759)
            ->setAssessable($quiz)
            ->setNormalisedMark(IMarkScheme::NORMALISED_MARK_MAX * $marksGiven / $marksAvailable)
            ->setReviewer($this->celia)
            ->setResponse($attempt)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-15 03:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-08 21:03:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-08 21:03:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
        $quiz->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        return $quiz;
    }
}
